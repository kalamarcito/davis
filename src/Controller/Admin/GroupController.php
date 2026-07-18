<?php

namespace App\Controller\Admin;

use App\Entity\AddressBookInstance;
use App\Entity\CalendarInstance;
use App\Entity\Principal;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/groups', name: 'group_')]
class GroupController extends AbstractController
{
    #[Route('', name: 'index')]
    public function index(ManagerRegistry $doctrine): Response
    {
        $groups = $doctrine->getRepository(Principal::class)->findGroups();

        return $this->render('groups/index.html.twig', [
            'groups' => $groups,
        ]);
    }

    #[Route('/new', name: 'create')]
    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'])]
    public function edit(ManagerRegistry $doctrine, Request $request, TranslatorInterface $trans, ?int $id = null): Response
    {
        $entityManager = $doctrine->getManager();
        $isNew = null === $id;

        if ($isNew) {
            $group = new Principal();
            $group->setIsMain(false)
                ->setIsAdmin(false)
                ->setIsGroup(true)
                ->setGroupSource(Principal::GROUP_SOURCE_MANUAL);
        } else {
            $group = $doctrine->getRepository(Principal::class)->findOneById($id);
            if (!$group || !$group->isGroup()) {
                throw $this->createNotFoundException('Group not found');
            }
        }

        $error = null;
        if ($request->isMethod('POST')) {
            $displayName = trim((string) $request->request->get('displayName', ''));
            $slug = strtolower(trim((string) $request->request->get('slug', '')));
            $slug = preg_replace('/[^a-z0-9\-]+/', '-', $slug) ?? '';
            $slug = trim($slug, '-');

            if ('' === $displayName) {
                $error = $trans->trans('groups.error.name_required');
            } elseif ($isNew && '' === $slug) {
                $error = $trans->trans('groups.error.slug_required');
            } elseif ($isNew && !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
                $error = $trans->trans('groups.error.slug_invalid');
            } else {
                $group->setDisplayName($displayName);

                if ($isNew) {
                    $uri = Principal::groupUriFromSlug($slug);
                    $existing = $doctrine->getRepository(Principal::class)->findOneByUri($uri);
                    if ($existing) {
                        $error = $trans->trans('groups.error.slug_taken');
                    } else {
                        $group->setUri($uri);
                        $entityManager->persist($group);
                        $entityManager->flush();
                        $this->addFlash('success', $trans->trans('groups.created'));

                        return $this->redirectToRoute('group_members', ['id' => $group->getId()]);
                    }
                } else {
                    $entityManager->flush();
                    $this->addFlash('success', $trans->trans('groups.saved'));

                    return $this->redirectToRoute('group_index');
                }
            }
        }

        $slugHint = '';
        if (!$isNew && str_starts_with((string) $group->getUri(), Principal::GROUP_URI_PREFIX)) {
            $slugHint = substr((string) $group->getUri(), strlen(Principal::GROUP_URI_PREFIX));
        }

        return $this->render('groups/edit.html.twig', [
            'group' => $group,
            'isNew' => $isNew,
            'slugHint' => $slugHint,
            'error' => $error,
        ]);
    }

    #[Route('/{id}/members', name: 'members', requirements: ['id' => '\d+'])]
    public function members(ManagerRegistry $doctrine, int $id): Response
    {
        $group = $doctrine->getRepository(Principal::class)->findOneById($id);
        if (!$group || !$group->isGroup()) {
            throw $this->createNotFoundException('Group not found');
        }

        $memberUris = array_map(static fn (Principal $p) => $p->getUri(), $group->getDelegees()->toArray());
        $candidates = $doctrine->getRepository(Principal::class)->findMainPrincipalsExcept(...$memberUris);

        return $this->render('groups/members.html.twig', [
            'group' => $group,
            'candidates' => $candidates,
        ]);
    }

    #[Route('/{id}/members/add', name: 'member_add', requirements: ['id' => '\d+'], methods: ['POST', 'GET'])]
    public function memberAdd(ManagerRegistry $doctrine, Request $request, int $id, TranslatorInterface $trans): Response
    {
        $group = $doctrine->getRepository(Principal::class)->findOneById($id);
        if (!$group || !$group->isGroup()) {
            throw $this->createNotFoundException('Group not found');
        }

        if (!is_numeric($request->get('principalId'))) {
            throw new BadRequestHttpException();
        }

        $member = $doctrine->getRepository(Principal::class)->findOneById($request->get('principalId'));
        if (!$member || !$member->getIsMain()) {
            throw $this->createNotFoundException('Member not found');
        }

        $group->addDelegee($member);
        $doctrine->getManager()->flush();
        $this->addFlash('success', $trans->trans('groups.member_added'));

        return $this->redirectToRoute('group_members', ['id' => $id]);
    }

    #[Route('/{id}/members/{memberId}/remove', name: 'member_remove', requirements: ['id' => '\d+', 'memberId' => '\d+'])]
    public function memberRemove(ManagerRegistry $doctrine, int $id, int $memberId, TranslatorInterface $trans): Response
    {
        $group = $doctrine->getRepository(Principal::class)->findOneById($id);
        if (!$group || !$group->isGroup()) {
            throw $this->createNotFoundException('Group not found');
        }

        $member = $doctrine->getRepository(Principal::class)->findOneById($memberId);
        if (!$member) {
            throw $this->createNotFoundException('Member not found');
        }

        $group->removeDelegee($member);
        $doctrine->getManager()->flush();
        $this->addFlash('success', $trans->trans('groups.member_removed'));

        return $this->redirectToRoute('group_members', ['id' => $id]);
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'])]
    public function delete(ManagerRegistry $doctrine, int $id, TranslatorInterface $trans): Response
    {
        $group = $doctrine->getRepository(Principal::class)->findOneById($id);
        if (!$group || !$group->isGroup()) {
            throw $this->createNotFoundException('Group not found');
        }

        $entityManager = $doctrine->getManager();

        // Revoke all resource shares that target this group principal.
        foreach ($doctrine->getRepository(CalendarInstance::class)->findBy(['principalUri' => $group->getUri()]) as $instance) {
            $entityManager->remove($instance);
        }
        foreach ($doctrine->getRepository(AddressBookInstance::class)->findBy(['principalUri' => $group->getUri()]) as $instance) {
            $entityManager->remove($instance);
        }

        $group->removeAllDelegees();
        $entityManager->remove($group);
        $entityManager->flush();

        $this->addFlash('success', $trans->trans('groups.deleted'));

        return $this->redirectToRoute('group_index');
    }
}
