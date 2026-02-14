<?php

namespace App\Controller\Admin;

use App\Entity\AddressBook;
use App\Entity\AddressBookInstance;
use App\Entity\Principal;
use App\Form\AddressBookType;
use App\Services\BirthdayService;
use Doctrine\Persistence\ManagerRegistry;
use Sabre\DAV\Sharing\Plugin as SharingPlugin;
use Sabre\DAV\UUIDUtil;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/addressbooks', name: 'addressbook_')]
class AddressBookController extends AbstractController
{
    #[Route('/{username}', name: 'index')]
    public function addressBooks(ManagerRegistry $doctrine, string $username): Response
    {
        $principal = $doctrine->getRepository(Principal::class)->findOneByUri(Principal::PREFIX.$username);
        $addressbookInstances = $doctrine->getRepository(AddressBookInstance::class)->findByPrincipalUri(Principal::PREFIX.$username);

        return $this->render('addressbooks/index.html.twig', [
            'addressbook_instances' => $addressbookInstances,
            'principal' => $principal,
            'username' => $username,
        ]);
    }

    #[Route('/{username}/new', name: 'create')]
    #[Route('/{username}/edit/{id}', name: 'edit', requirements: ['id' => "\d+"])]
    public function addressbookCreate(ManagerRegistry $doctrine, Request $request, string $username, ?int $id, TranslatorInterface $trans, BirthdayService $birthdayService): Response
    {
        $principal = $doctrine->getRepository(Principal::class)->findOneByUri(Principal::PREFIX.$username);

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

            return $this->redirectToRoute('addressbook_index', ['username' => $username]);
        }

        return $this->render('addressbooks/edit.html.twig', [
            'form' => $form->createView(),
            'principal' => $principal,
            'username' => $username,
            'addressbook_instance' => $addressbookInstance,
            'addressbook' => $addressbook,
            'is_shared' => $addressbookInstance->isShared(),
            'access_level' => $addressbookInstance->getAccess(),
        ]);
    }

    #[Route('/{username}/delete/{id}', name: 'delete', requirements: ['id' => "\d+"])]
    public function addressbookDelete(ManagerRegistry $doctrine, string $username, string $id, TranslatorInterface $trans, BirthdayService $birthdayService): Response
    {
        $addressbookInstance = $doctrine->getRepository(AddressBookInstance::class)->findOneById($id);
        if (!$addressbookInstance) {
            throw $this->createNotFoundException('Address Book not found');
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
            $birthdayService->syncUser($username);
        }

        return $this->redirectToRoute('addressbook_index', ['username' => $username]);
    }
}