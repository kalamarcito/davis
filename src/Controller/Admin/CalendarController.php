<?php

namespace App\Controller\Admin;

use App\Entity\Calendar;
use App\Entity\CalendarInstance;
use App\Entity\CalendarSubscription;
use App\Entity\Principal;
use App\Entity\User;
use App\Form\CalendarInstanceType;
use Doctrine\Persistence\ManagerRegistry;
use Sabre\DAV\Sharing\Plugin as SharingPlugin;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/calendars', name: 'calendar_')]
class CalendarController extends AbstractController
{
    #[Route('/{userId}', name: 'index')]
    public function calendars(ManagerRegistry $doctrine, UrlGeneratorInterface $router, int $userId): Response
    {
        $user = $doctrine->getRepository(User::class)->findOneById($userId);
        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }

        $username = $user->getUsername();
        $principalUri = Principal::PREFIX.$username;

        $principal = $doctrine->getRepository(Principal::class)->findOneByUri($principalUri);
        $allCalendars = $doctrine->getRepository(CalendarInstance::class)->findByPrincipalUri($principalUri);

        $subscriptions = $doctrine->getRepository(CalendarSubscription::class)->findByPrincipalUri($principalUri);

        // Separate shared calendars
        $calendars = [];
        $shared = [];
        $auto = [];
        foreach ($allCalendars as $calendar) {
            if ($calendar->isAutomaticallyGenerated()) {
                $auto[] = [
                    'entity' => $calendar,
                    'uri' => $router->generate('dav', ['path' => 'calendars/'.$username.'/'.$calendar->getUri()], UrlGeneratorInterface::ABSOLUTE_URL),
                ];
            } elseif (!$calendar->isShared()) {
                $calendars[] = [
                    'entity' => $calendar,
                    'uri' => $router->generate('dav', ['path' => 'calendars/'.$username.'/'.$calendar->getUri()], UrlGeneratorInterface::ABSOLUTE_URL),
                ];
            } else {
                $shared[] = [
                    'entity' => $calendar,
                    'uri' => $router->generate('dav', ['path' => 'calendars/'.$username.'/'.$calendar->getUri()], UrlGeneratorInterface::ABSOLUTE_URL),
                ];
            }
        }

        // We need all the other users so we can propose to share calendars with them
        $allPrincipalsExcept = $doctrine->getRepository(Principal::class)->findAllExceptPrincipal($principalUri);

        return $this->render('calendars/index.html.twig', [
            'calendars' => $calendars,
            'subscriptions' => $subscriptions,
            'shared' => $shared,
            'auto' => $auto,
            'principal' => $principal,
            'userId' => $userId,
            'allPrincipals' => $allPrincipalsExcept,
        ]);
    }

    #[Route('/{userId}/new', name: 'create')]
    #[Route('/{userId}/edit/{id}', name: 'edit', requirements: ['id' => "\d+"])]
    public function calendarEdit(ManagerRegistry $doctrine, Request $request, int $userId, ?int $id, TranslatorInterface $trans): Response
    {
        $user = $doctrine->getRepository(User::class)->findOneById($userId);
        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }

        $username = $user->getUsername();
        $principalUri = Principal::PREFIX.$username;

        $principal = $doctrine->getRepository(Principal::class)->findOneByUri($principalUri);

        if (!$principal) {
            throw $this->createNotFoundException('User not found');
        }

        if ($id) {
            $calendarInstance = $doctrine->getRepository(CalendarInstance::class)->findOneById($id);
            if (!$calendarInstance) {
                throw $this->createNotFoundException('Calendar not found');
            }
        } else {
            $calendarInstance = new CalendarInstance();
            $calendar = new Calendar();
            $calendarInstance->setCalendar($calendar);
        }

        $arePublicCalendarsEnabled = $this->getParameter('public_calendars_enabled');

        $form = $this->createForm(CalendarInstanceType::class, $calendarInstance, [
            'new' => !$id,
            'shared' => $calendarInstance->isShared(),
            'public_calendars_enabled' => $arePublicCalendarsEnabled,
        ]);

        $components = explode(',', $calendarInstance->getCalendar()->getComponents());

        $form->get('events')->setData(in_array(Calendar::COMPONENT_EVENTS, $components));
        $form->get('todos')->setData(in_array(Calendar::COMPONENT_TODOS, $components));
        $form->get('notes')->setData(in_array(Calendar::COMPONENT_NOTES, $components));
        $form->get('principalUri')->setData($principalUri);

        $form->handleRequest($request);

        $entityManager = $doctrine->getManager();

        if ($form->isSubmitted() && $form->isValid()) {
            // Only owners can change those
            if (!$calendarInstance->isShared()) {
                $components = [];
                if ($form->get('events')->getData()) {
                    $components[] = Calendar::COMPONENT_EVENTS;
                }
                if ($form->get('todos')->getData()) {
                    $components[] = Calendar::COMPONENT_TODOS;
                }
                if ($form->get('notes')->getData()) {
                    $components[] = Calendar::COMPONENT_NOTES;
                }
                if ($arePublicCalendarsEnabled && true === $form->get('public')->getData()) {
                    $calendarInstance->setPublic(true);
                } else {
                    $calendarInstance->setPublic(false);
                }

                $calendarInstance->getCalendar()->setComponents(implode(',', $components));
            }

            // We want to remove all shares if a calendar goes public
            if ($arePublicCalendarsEnabled && true === $form->get('public')->getData() && $id) {
                $calendarId = $calendarInstance->getCalendar()->getId();
                $instances = $doctrine->getRepository(CalendarInstance::class)->findSharedInstancesOfInstance($calendarId, false);
                foreach ($instances as $instance) {
                    $entityManager->remove($instance);
                }
            }

            $entityManager->persist($calendarInstance);
            $entityManager->flush();

            $this->addFlash('success', $trans->trans('calendar.saved'));

            return $this->redirectToRoute('calendar_index', ['userId' => $userId]);
        }

        return $this->render('calendars/edit.html.twig', [
            'form' => $form->createView(),
            'principal' => $principal,
            'userId' => $userId,
            'calendar' => $calendarInstance,
        ]);
    }

    #[Route('/{userId}/shares/{calendarid}', name: 'shares', requirements: ['calendarid' => "\d+"])]
    public function calendarShares(ManagerRegistry $doctrine, int $userId, string $calendarid, TranslatorInterface $trans): Response
    {
        $instances = $doctrine->getRepository(CalendarInstance::class)->findSharedInstancesOfInstance($calendarid, true);

        $response = [];
        foreach ($instances as $instance) {
            $permissions = $instance[0]['permissions'];
            $response[] = [
                'principalId' => $instance['principalId'],
                'principalUri' => $instance[0]['principalUri'],
                'displayName' => $instance['displayName'],
                'email' => $instance['email'],
                'accessText' => $trans->trans('calendar.share_access.'.$instance[0]['access']),
                'isWriteAccess' => SharingPlugin::ACCESS_READWRITE === $instance[0]['access'],
                'canWrite' => (bool) ($permissions & 1),
                'canCreate' => (bool) ($permissions & 2),
                'canDelete' => (bool) ($permissions & 4),
                'revokeUrl' => $this->generateUrl('calendar_revoke', ['userId' => $userId, 'id' => $instance[0]['id']]),
            ];
        }

        return new JsonResponse($response);
    }

    #[Route('/{userId}/share/{instanceid}', name: 'share_add', requirements: ['instanceid' => "\d+"])]
    public function calendarShareAdd(ManagerRegistry $doctrine, Request $request, int $userId, string $instanceid, TranslatorInterface $trans): Response
    {
        $instance = $doctrine->getRepository(CalendarInstance::class)->findOneById($instanceid);
        if (!$instance) {
            throw $this->createNotFoundException('Calendar not found');
        }

        // Only the owner can manage sharing
        if ($instance->isShared()) {
            throw $this->createAccessDeniedException('Only the owner can share this calendar.');
        }

        if (!is_numeric($request->get('principalId'))) {
            throw new BadRequestHttpException();
        }

        $newShareeToAdd = $doctrine->getRepository(Principal::class)->findOneById($request->get('principalId'));
        if (!$newShareeToAdd) {
            throw $this->createNotFoundException('Member not found');
        }

        // Let's check that there wasn't another instance
        // already existing first, so we can update it:
        $existingSharedInstance = $doctrine->getRepository(CalendarInstance::class)->findSharedInstanceOfInstanceFor($instance->getCalendar()->getId(), $newShareeToAdd->getUri());

        // Calculate permissions bitmask from request
        $permissions = 0;
        if ('true' === $request->get('canWrite')) {
            $permissions |= 1;
        }
        if ('true' === $request->get('canCreate')) {
            $permissions |= 2;
        }
        if ('true' === $request->get('canDelete')) {
            $permissions |= 4;
        }

        // Legacy: if 'write' param is sent (old UI), treat as full permissions
        if ('true' === $request->get('write') && 0 === $permissions) {
            $permissions = 7;
        }

        $access = ($permissions > 0) ? SharingPlugin::ACCESS_READWRITE : SharingPlugin::ACCESS_READ;

        $entityManager = $doctrine->getManager();

        if ($existingSharedInstance) {
            $existingSharedInstance->setAccess($access);
            $existingSharedInstance->setPermissions($permissions);
        } else {
            $sharedInstance = new CalendarInstance();
            $sharedInstance->setTransparent(1)
                     ->setCalendar($instance->getCalendar())
                     ->setShareHref('mailto:'.$newShareeToAdd->getEmail())
                     ->setDescription($instance->getDescription())
                     ->setDisplayName($instance->getDisplayName())
                     ->setCalendarColor($instance->getCalendarColor())
                     ->setUri(\Sabre\DAV\UUIDUtil::getUUID())
                     ->setPrincipalUri($newShareeToAdd->getUri())
                     ->setAccess($access)
                     ->setPermissions($permissions);
            $entityManager->persist($sharedInstance);
        }

        $entityManager->flush();
        $this->addFlash('success', $trans->trans('calendar.shared'));

        return $this->redirectToRoute('calendar_index', ['userId' => $userId]);
    }

    #[Route('/{userId}/delete/{id}', name: 'delete', requirements: ['id' => "\d+"])]
    public function calendarDelete(ManagerRegistry $doctrine, int $userId, string $id, TranslatorInterface $trans): Response
    {
        $user = $doctrine->getRepository(User::class)->findOneById($userId);
        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }
        $principalUri = Principal::PREFIX.$user->getUsername();

        $instance = $doctrine->getRepository(CalendarInstance::class)->findOneById($id);
        if (!$instance) {
            throw $this->createNotFoundException('Calendar not found');
        }

        // Only the owner can delete the calendar
        if ($instance->isShared()) {
            throw $this->createAccessDeniedException('Only the owner can delete this calendar.');
        }

        $entityManager = $doctrine->getManager();

        // Scheduling objects attached to the calendar objects of the calendar
        $schedulingObjectsOfCalendarObjects = $doctrine->getRepository(CalendarInstance::class)->findAllSchedulingObjectsForCalendar($instance->getId(), $principalUri);
        foreach ($schedulingObjectsOfCalendarObjects ?? [] as $object) {
            $entityManager->remove($object);
        }
        foreach ($instance->getCalendar()->getObjects() ?? [] as $object) {
            $entityManager->remove($object);
        }
        foreach ($instance->getCalendar()->getChanges() ?? [] as $change) {
            $entityManager->remove($change);
        }

        // Remove the original calendar instance
        $entityManager->remove($instance);

        // Remove shared instances
        $sharedInstances = $doctrine->getRepository(CalendarInstance::class)->findSharedInstancesOfInstance($instance->getCalendar()->getId(), false);
        foreach ($sharedInstances as $sharedInstance) {
            $entityManager->remove($sharedInstance);
        }

        // Finally remove the calendar itself
        $entityManager->remove($instance->getCalendar());

        $entityManager->flush();
        $this->addFlash('success', $trans->trans('calendar.deleted'));

        return $this->redirectToRoute('calendar_index', ['userId' => $userId]);
    }

    #[Route('/{userId}/revoke/{id}', name: 'revoke', requirements: ['id' => "\d+"])]
    public function calendarRevoke(ManagerRegistry $doctrine, int $userId, string $id, TranslatorInterface $trans): Response
    {
        $user = $doctrine->getRepository(User::class)->findOneById($userId);
        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }

        $instance = $doctrine->getRepository(CalendarInstance::class)->findOneById($id);
        if (!$instance) {
            throw $this->createNotFoundException('Calendar not found');
        }

        $principalUri = Principal::PREFIX.$user->getUsername();

        // Only shared instances can be revoked; owners remove their own
        // calendars through "delete", not "revoke".
        if (!$instance->isShared()) {
            throw $this->createAccessDeniedException('Only shared instances can be revoked.');
        }

        // A share may be revoked either by the sharee themselves (leaving a
        // calendar shared with them) or by an owner of the underlying calendar
        // (removing someone they shared with, from the sharing modal).
        $isSharee = $instance->getPrincipalUri() === $principalUri;
        $isOwner = $doctrine->getRepository(CalendarInstance::class)->count([
            'calendar' => $instance->getCalendar(),
            'principalUri' => $principalUri,
            'access' => CalendarInstance::getOwnerAccesses(),
        ]) > 0;

        if (!$isSharee && !$isOwner) {
            throw $this->createAccessDeniedException('You can only revoke your own shared access or shares of calendars you own.');
        }

        $entityManager = $doctrine->getManager();
        $entityManager->remove($instance);

        $entityManager->flush();
        $this->addFlash('success', $trans->trans('calendar.revoked'));

        return $this->redirectToRoute('calendar_index', ['userId' => $userId]);
    }
}
