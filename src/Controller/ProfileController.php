<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ProfileEditType;
use App\Form\ChangePasswordType;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'profile_show', methods: ['GET'])]
    public function show(): Response
    {
        $user = $this->getUserOrDeny();

        return $this->render('profile/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/profile/edit', name: 'profile_edit', methods: ['GET','POST'])]
    public function edit(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUserOrDeny();

        $form = $this->createForm(ProfileEditType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Profil mis à jour ✅');
            return $this->redirectToRoute('profile_show');
        }

        return $this->render('profile/edit.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/profile/password', name: 'profile_password', methods: ['GET','POST'])]
    public function changePassword(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em
    ): Response {
        $user = $this->getUserOrDeny();

        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $current = (string) $form->get('currentPassword')->getData();
            $new     = (string) $form->get('newPassword')->getData();

            if (!$hasher->isPasswordValid($user, $current)) {
                $this->addFlash('error', 'Mot de passe actuel incorrect.');
                return $this->redirectToRoute('profile_password');
            }

            $user->setPassword($hasher->hashPassword($user, $new));
            $em->flush();

            $this->addFlash('success', 'Mot de passe changé ✅');
            return $this->redirectToRoute('profile_show');
        }

        return $this->render('profile/password.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/profile/reservations', name: 'profile_reservations', methods: ['GET'])]
    public function reservations(Request $request, ReservationRepository $repo): Response
    {
        $user = $this->getUserOrDeny();

        $status = (string) $request->query->get('status', 'ALL');

        $criteria = ['user' => $user];
        if ($status !== 'ALL') {
            $criteria['status'] = $status;
        }

        $reservations = $repo->findBy($criteria, ['createdAt' => 'DESC']);

        return $this->render('profile/reservations.html.twig', [
            'reservations' => $reservations,
            'status' => $status,
        ]);
    }

    private function getUserOrDeny(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        return $user;
    }
}
