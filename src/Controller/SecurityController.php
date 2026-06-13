<?php

namespace App\Controller;


use App\Entity\User;
use App\Enum\Role;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use App\Service\EmailService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
 // N'oublie pas d'importer ton EmailService
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils, ParameterBagInterface $params): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute($this->getDashboardRouteByRole());
        }

        $recaptchaKey = $params->get('RECAPTCHA_SITE_KEY');

        return $this->render('security/login.html.twig', [
            'recaptcha_site_key' => $recaptchaKey,
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'last_username' => $authenticationUtils->getLastUsername(),
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        // Cette méthode est interceptée par Symfony pour gérer le logout
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    private function getDashboardRouteByRole(): string
    {
        $user = $this->getUser();

        if (!$user) {
            return 'app_home';
        }

        $role = $user->getRole();

        return match ($role) {
            Role::ADMIN->value => 'app_admin',
            Role::ENTRAINEUR->value => 'app_entraineur',
            Role::RESPONSABLE_SALLE->value => 'dashboard_responsable_salle',
            Role::SPORTIF->value => 'home',
            default => 'app_home',
        };
    }
    #[Route('/app_forget_password', name: 'app_forgot_password')]
    public function forgotPassword(Request $request, EntityManagerInterface $em, EmailService $emailService): Response
    {
        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
    
            if ($user) {
                // Générer un token de réinitialisation
                $token = bin2hex(random_bytes(32));
                $user->setResetToken($token);
                $user->setResetTokenExpiration(new \DateTime('+1 hour'));
                $em->flush();
    
                // Générer le lien de réinitialisation
                $resetLink = $this->generateUrl('app_reset_password', [
                    'token' => $token
                ], UrlGeneratorInterface::ABSOLUTE_URL);
    
                // Envoyer l'email
                $emailService->sendEmail(
                    $user->getEmail(),
                    'Réinitialisation de votre mot de passe',
                    'emails/reset_password.html.twig',
                    ['resetLink' => $resetLink]
                );
                
    
                $this->addFlash('success', 'Un email de réinitialisation a été envoyé.');
            } else {
                $this->addFlash('error', 'Aucun compte associé à cet email.');
            }
        }
    
        return $this->redirectToRoute('app_login');
    }
    




    #[Route('/reset-password/{token}', name: 'app_reset_password')]
    public function resetPassword(
        string $token,
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $user = $em->getRepository(User::class)->findOneBy(['resetToken' => $token]);
    
        if (!$user || $user->getResetTokenExpiration() < new \DateTime()) {
            $this->addFlash('error', 'Le lien est invalide ou a expiré.');
            return $this->redirectToRoute('app_forgot_password');
        }
    
        if ($request->isMethod('POST')) {
            $plainPassword = $request->request->get('plainPassword');
            $confirmPassword = $request->request->get('confirmPassword');
    
            if ($plainPassword !== $confirmPassword) {
                $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
            } elseif (strlen($plainPassword) < 6) {
                $this->addFlash('error', 'Le mot de passe doit contenir au moins 6 caractères.');
            } else {
                $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
                $user->setResetToken(null);
                $user->setResetTokenExpiration(null);
    
                $em->persist($user);
                $em->flush();
    
                $this->addFlash('success', 'Votre mot de passe a été réinitialisé avec succès.');
    
                return $this->redirectToRoute('app_login');
            }
        }
    
        return $this->render('security/reset_password.html.twig', [
            'token' => $token,
        ]);
    }
   



    #[Route('/connect/google', name: 'connect_google')]
    public function connectGoogle(ClientRegistry $clientRegistry): Response
    {
        return $clientRegistry->getClient('google')->redirect(['email', 'profile'], []);
    }

    #[Route('/connect/google/callback', name: 'connect_google_check')]
    public function connectGoogleCheck(): Response
    {
        // The GoogleAuthenticator handles user creation and authentication
        return $this->redirectToRoute('home');
    }
}    