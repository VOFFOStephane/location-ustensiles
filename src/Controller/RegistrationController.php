<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

final class RegistrationController extends AbstractController
{
    public function __construct(private readonly EmailVerifier $emailVerifier)
    {
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em
    ): Response {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = (string) $form->get('plainPassword')->getData();

            // mot de passe hashé
            $user->setPassword($hasher->hashPassword($user, $plainPassword));

            // rôles : par défaut roles = [], getRoles() ajoutera ROLE_USER.
            // Optionnel : tu peux le forcer explicitement :
            $user->setRoles([]); // ou ['ROLE_USER'] si tu préfères

            // isVerified false par défaut (dans ton entité)
            $em->persist($user);
            $em->flush();

            // email de confirmation
            $this->emailVerifier->sendEmailConfirmation(
                'app_verify_email',
                $user,
                (new TemplatedEmail())
                    ->from(new Address('no-reply@myapp.local', 'Location Ustensiles'))
                    ->to((string) $user->getEmail())
                    ->subject('Confirmez votre adresse email')
                    ->htmlTemplate('registration/confirmation_email.html.twig')
            );

            $this->addFlash('success', 'Compte créé ✅ Vérifie tes emails pour activer ton compte.');

            // ✅ IMPORTANT : on NE log PAS l’utilisateur tant que l’email n’est pas vérifié
            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    #[Route('/verify/email', name: 'app_verify_email', methods: ['GET'])]
    public function verifyUserEmail(Request $request, TranslatorInterface $translator, Security $security): Response
    {
        // On peut vérifier sans être loggué, grâce au lien signé
        // MAIS le bundle utilise souvent l'utilisateur connecté.
        // Donc on récupère l'ID depuis l'URL via handleEmailConfirmation (il gère).
        try {
            /** @var User $user */
            $user = $this->getUser();
            if (!$user instanceof User) {
                // si pas loggué, on redirige sur login et on demande de se connecter
                $this->addFlash('info', 'Connecte-toi d’abord, puis clique à nouveau sur le lien de vérification.');
                return $this->redirectToRoute('app_login');
            }

            $this->emailVerifier->handleEmailConfirmation($request, $user);

        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('verify_email_error', $translator->trans($exception->getReason(), [], 'VerifyEmailBundle'));
            return $this->redirectToRoute('app_register');
        }

        $this->addFlash('success', 'Email vérifié ✅ Ton compte est activé.');

        // après validation email, on renvoie vers le catalogue
        return $this->redirectToRoute('product_index');
    }
}
