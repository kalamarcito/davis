<?php

namespace App\Controller\Admin;

use App\Entity\AddressBook;
use App\Entity\AddressBookInstance;
use App\Entity\Principal;
use App\Entity\User;
use App\Form\AddressBookType;
use App\Services\BirthdayService;
use Doctrine\Persistence\ManagerRegistry;
use Sabre\DAV\Sharing\Plugin as SharingPlugin;
use Sabre\DAV\UUIDUtil;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/addressbooks', name: 'addressbook_')]
class AddressBookController extends AbstractController
{
    #[Route('/{userId}', name: 'index')]
    public function addressBooks(ManagerRegistry $doctrine, int $userId): Response
    {
        $user = $doctrine->getRepository(User::class)->findOneById($userId);
        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }

        $principalUri = Principal::PREFIX.$user->getUsername();

        $principal = $doctrine->getRepository(Principal::class)->findOneByUri($principalUri);
        $allInstances = $doctrine->getRepository(AddressBookInstance::class)->findByPrincipalUri($principalUri);

        $owned = [];
        $shared = [];
        foreach ($allInstances as $instance) {
            if ($instance->isShared()) {
                $shared[] = $instance;
            } else {
                $owned[] = $instance;
            }
        }

        $allPrincipals = $doctrine->getRepository(Principal::class)->findAllExceptPrincipal($principalUri);

        return $this->render('addressbooks/index.html.twig', [
            'addressbook_instances' => $owned,
            'shared_instances' => $shared,
            'principal' => $principal,
            'userId' => $userId,
            'allPrincipals' => $allPrincipals,
        ]);
    }

    #[Route('/{userId}/new', name: 'create')]
    #[Route('/{userId}/edit/{id}', name: 'edit', requirements: ['id' => "\d+"])]
    public function addressbookCreate(ManagerRegistry $doctrine, Request $request, int $userId, ?int $id, TranslatorInterface $trans, BirthdayService $birthdayService): Response
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
            $addressbookInstance = $doctrine->getRepository(AddressBookInstance::class)->findOneById($id);
            if (!$addressbookInstance) {
                throw $this->createNotFoundException('Address book not found');
            }
            $addressbook = $addressbookInstance->getAddressBook();
        } else {
            $addressbook = new AddressBook();
            $addressbookInstance = new AddressBookInstance();
            $addressbookInstance->setAddressBook($addressbook);
            $addressbookInstance->setPrincipalUri(Principal::PREFIX.$username);
            $addressbookInstance->setAccess(SharingPlugin::ACCESS_SHAREDOWNER);
            $addressbook->addInstance($addressbookInstance);
        }

        $isBirthdayCalendarEnabled = $this->getParameter('caldav_enabled') && $this->getParameter('carddav_enabled');

        $form = $this->createForm(AddressBookType::class, $addressbookInstance, ['new' => !$id, 'birthday_calendar_enabled' => $isBirthdayCalendarEnabled]);

        if ($isBirthdayCalendarEnabled) {
            $form->get('includedInBirthdayCalendar')->setData($addressbook->isIncludedInBirthdayCalendar());
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $doctrine->getManager();

            // If this is a new address book, generate a URI if not provided
            if (!$id && !$addressbookInstance->getUri()) {
                $addressbookInstance->setUri(UUIDUtil::getUUID());
            }

            $entityManager->persist($addressbook);
            $entityManager->persist($addressbookInstance);
            $entityManager->flush();

            $this->addFlash('success', $trans->trans('addressbooks.saved'));

            if ($isBirthdayCalendarEnabled && true === $form->get('includedInBirthdayCalendar')->getData()) {
                $addressbook->setIncludedInBirthdayCalendar(true);
            } else {
                $addressbook->setIncludedInBirthdayCalendar(false);
            }

            if ($isBirthdayCalendarEnabled) {
                // Let's sync the user birthday calendar if needed
                $birthdayService->syncUser($username);
            }

            $entityManager->flush();

            return $this->redirectToRoute('addressbook_index', ['userId' => $userId]);
        }

        return $this->render('addressbooks/edit.html.twig', [
            'form' => $form->createView(),
            'principal' => $principal,
            'userId' => $userId,
            'addressbook_instance' => $addressbookInstance,
            'addressbook' => $addressbook,
            'is_shared' => $addressbookInstance->isShared(),
            'access_level' => $addressbookInstance->getAccess(),
        ]);
    }

    #[Route('/{userId}/delete/{id}', name: 'delete', requirements: ['id' => "\d+"])]
    public function addressbookDelete(ManagerRegistry $doctrine, int $userId, string $id, TranslatorInterface $trans, BirthdayService $birthdayService): Response
    {
        $user = $doctrine->getRepository(User::class)->findOneById($userId);
        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }

        $addressbookInstance = $doctrine->getRepository(AddressBookInstance::class)->findOneById($id);
        if (!$addressbookInstance) {
            throw $this->createNotFoundException('Address Book not found');
        }

        // Only the owner can delete the address book
        if ($addressbookInstance->isShared()) {
            throw $this->createAccessDeniedException('Only the owner can delete this address book.');
        }

        $addressbook = $addressbookInstance->getAddressBook();
        $entityManager = $doctrine->getManager();

        // Remove the instance
        $entityManager->remove($addressbookInstance);

        // If this was the last instance, remove the address book and all its data
        $remainingInstances = $doctrine->getRepository(AddressBookInstance::class)->findBy(['addressBook' => $addressbook]);
        if (count($remainingInstances) <= 1) { // <= 1 because we're about to remove one
            foreach ($addressbook->getCards() ?? [] as $card) {
                $entityManager->remove($card);
            }
            foreach ($addressbook->getChanges() ?? [] as $change) {
                $entityManager->remove($change);
            }
            $entityManager->remove($addressbook);
        }

        $entityManager->flush();
        $this->addFlash('success', $trans->trans('addressbooks.deleted'));

        $isBirthdayCalendarEnabled = $this->getParameter('caldav_enabled') && $this->getParameter('carddav_enabled');
        if ($isBirthdayCalendarEnabled) {
            // Let's sync the user birthday calendar if needed
            $birthdayService->syncUser($user->getUsername());
        }

        return $this->redirectToRoute('addressbook_index', ['userId' => $userId]);
    }

    #[Route('/{userId}/shares/{addressbookid}', name: 'shares', requirements: ['addressbookid' => "\d+"])]
    public function addressBookShares(ManagerRegistry $doctrine, int $userId, string $addressbookid, TranslatorInterface $trans): Response
    {
        $instances = $doctrine->getRepository(AddressBookInstance::class)->findBy(['addressBook' => $addressbookid]);

        $response = [];
        foreach ($instances as $instance) {
            // Skip owner instances
            if (in_array($instance->getAccess(), AddressBookInstance::getOwnerAccesses())) {
                continue;
            }
            $principal = $doctrine->getRepository(Principal::class)->findOneByUri($instance->getPrincipalUri());
            $response[] = [
                'principalId' => $principal ? $principal->getId() : null,
                'principalUri' => $instance->getPrincipalUri(),
                'displayName' => $principal ? $principal->getDisplayName() : $instance->getPrincipalUri(),
                'email' => $principal ? $principal->getEmail() : '',
                'accessText' => $trans->trans('addressbook.share_access.'.$instance->getAccess()),
                'isWriteAccess' => SharingPlugin::ACCESS_READWRITE === $instance->getAccess(),
                'canWrite' => $instance->canWrite(),
                'canCreate' => $instance->canCreate(),
                'canDelete' => $instance->canDelete(),
                'revokeUrl' => $this->generateUrl('addressbook_revoke', ['userId' => $userId, 'id' => $instance->getId()]),
            ];
        }

        return new JsonResponse($response);
    }

    #[Route('/{userId}/share/{instanceid}', name: 'share_add', requirements: ['instanceid' => "\d+"])]
    public function addressBookShareAdd(ManagerRegistry $doctrine, Request $request, int $userId, string $instanceid, TranslatorInterface $trans): Response
    {
        $instance = $doctrine->getRepository(AddressBookInstance::class)->findOneById($instanceid);
        if (!$instance) {
            throw $this->createNotFoundException('Address Book not found');
        }

        // Only the owner can manage sharing
        if ($instance->isShared()) {
            throw $this->createAccessDeniedException('Only the owner can share this address book.');
        }

        if (!is_numeric($request->get('principalId'))) {
            throw new BadRequestHttpException();
        }

        $newShareeToAdd = $doctrine->getRepository(Principal::class)->findOneById($request->get('principalId'));
        if (!$newShareeToAdd) {
            throw $this->createNotFoundException('Member not found');
        }

        $existingSharedInstance = $doctrine->getRepository(AddressBookInstance::class)->findOneBy([
            'addressBook' => $instance->getAddressBook(),
            'principalUri' => $newShareeToAdd->getUri(),
        ]);

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
            $sharedInstance = new AddressBookInstance();
            $sharedInstance->setAddressBook($instance->getAddressBook())
                     ->setShareHref('mailto:'.$newShareeToAdd->getEmail())
                     ->setDescription($instance->getDescription())
                     ->setDisplayName($instance->getDisplayName())
                     ->setUri(UUIDUtil::getUUID())
                     ->setPrincipalUri($newShareeToAdd->getUri())
                     ->setAccess($access)
                     ->setPermissions($permissions);
            $entityManager->persist($sharedInstance);
        }

        $entityManager->flush();
        $this->addFlash('success', $trans->trans('addressbook.shared'));

        return $this->redirectToRoute('addressbook_index', ['userId' => $userId]);
    }

    #[Route('/{userId}/revoke/{id}', name: 'revoke', requirements: ['id' => "\d+"])]
    public function addressBookRevoke(ManagerRegistry $doctrine, int $userId, string $id, TranslatorInterface $trans): Response
    {
        $user = $doctrine->getRepository(User::class)->findOneById($userId);
        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }

        $instance = $doctrine->getRepository(AddressBookInstance::class)->findOneById($id);
        if (!$instance) {
            throw $this->createNotFoundException('Address Book not found');
        }

        $principalUri = Principal::PREFIX.$user->getUsername();

        // Only shared instances can be revoked; owners remove their own address
        // books through "delete", not "revoke".
        if (!$instance->isShared()) {
            throw $this->createAccessDeniedException('Only shared instances can be revoked.');
        }

        // A share may be revoked either by the sharee themselves (leaving an
        // address book shared with them) or by an owner of the underlying
        // address book (removing someone they shared with, from the modal).
        $isSharee = $instance->getPrincipalUri() === $principalUri;
        $isOwner = $doctrine->getRepository(AddressBookInstance::class)->count([
            'addressBook' => $instance->getAddressBook(),
            'principalUri' => $principalUri,
            'access' => AddressBookInstance::getOwnerAccesses(),
        ]) > 0;

        if (!$isSharee && !$isOwner) {
            throw $this->createAccessDeniedException('You can only revoke your own shared access or shares of address books you own.');
        }

        $entityManager = $doctrine->getManager();
        $entityManager->remove($instance);
        $entityManager->flush();

        $this->addFlash('success', $trans->trans('addressbook.revoked'));

        return $this->redirectToRoute('addressbook_index', ['userId' => $userId]);
    }
}