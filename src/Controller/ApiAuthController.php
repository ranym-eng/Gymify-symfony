<?php
namespace App\Controller;

use App\Entity\User;
use App\Enum\Role;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\FormLoginAuthenticator;

class ApiAuthController extends AbstractController
{#[Route('/api/auth/google', name: 'api_auth_google', methods: ['POST'])]
    public function googleSignup(
        Request $request,
        ClientRegistry $clientRegistry,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $accessToken = $data['access_token'] ?? null;
    
        if (!$accessToken) {
            return new JsonResponse(['error' => 'Access token missing'], Response::HTTP_BAD_REQUEST);
        }
    
        $client = $clientRegistry->getClient('google');
        try {
            // Use the access token to fetch user info
            $googleUser = $client->fetchUserFromToken(['access_token' => $accessToken]);
            $email = $googleUser->getEmail();
            $name = $googleUser->getName();
            $googleId = $googleUser->getId();
    
            // Check if user exists
            $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
            if (!$user) {
                // Create new user
                $user = new Sportif();
                $user->setEmail($email);
                $user->setPrenom(explode(' ', $name)[0] ?? 'Utilisateur');
                $user->setNom(explode(' ', $name)[1] ?? 'Google');
                $user->setGoogleId($googleId);
                $user->setRoles([Role::SPORTIF->value]);
                $user->setDateNaissance(new \DateTime('1990-01-01'));
    
                $randomPassword = bin2hex(random_bytes(16));
                $user->setPassword($passwordHasher->hashPassword($user, $randomPassword));
    
                $em->persist($user);
                $em->flush();
            } else {
                // Update Google ID if not set
                if (!$user->getGoogleId()) {
                    $user->setGoogleId($googleId);
                    $em->flush();
                }
            }
    
            return new JsonResponse([
                'message' => 'User authenticated successfully',
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'role' => $user->getRole()->value,
                ],
            ], Response::HTTP_OK);
        } catch (IdentityProviderException $e) {
            return new JsonResponse(['error' => 'Google authentication failed: ' . $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'An error occurred: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }}