<?php
namespace App\Security;

use App\Entity\Sportif;
use App\Enum\Role;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class GoogleAuthenticator extends AbstractAuthenticator
{
    private $userRepository;
    private $clientRegistry;
    private $entityManager;
    private $logger;

    public function __construct(
        UserRepository $userRepository,
        ClientRegistry $clientRegistry,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ) {
        $this->userRepository = $userRepository;
        $this->clientRegistry = $clientRegistry;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'connect_google_check';
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('google');
        try {
            $this->logger->debug('Google OAuth callback params', $request->query->all());
            $googleUser = $client->fetchUser();
            $googleId = $googleUser->getId();
            $email = $googleUser->getEmail();

            $user = $this->userRepository->findOneBy(['googleId' => $googleId]) ??
                    $this->userRepository->findOneBy(['email' => $email]);

            if (!$user) {
                $user = new Sportif();
                $user->setGoogleId($googleId);
                $user->setEmail($email);

                $fullName = $googleUser->getName();
                $nameParts = explode(' ', $fullName, 2);
                $user->setPrenom($nameParts[0]);
                $user->setNom($nameParts[1] ?? '');
                $user->setPassword('google-auth-' . bin2hex(random_bytes(16)));
                $user->setDateNaissance(new \DateTime('1990-01-01'));

                $this->entityManager->persist($user);
                $this->entityManager->flush();
                $this->logger->info('New Sportif user created via Google', ['email' => $email]);
            }

            $userBadge = new UserBadge($user->getUserIdentifier(), function () use ($user) {
                return $user;
            });

            return new SelfValidatingPassport($userBadge);
        } catch (IdentityProviderException $e) {
            $this->logger->error('Google OAuth error', ['exception' => $e->getMessage()]);
            throw new AuthenticationException('Google authentication failed: ' . $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error('Unexpected Google auth error', ['exception' => $e->getMessage()]);
            throw new AuthenticationException('Google authentication failed: ' . $e->getMessage());
        }
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $this->logger->error('Google auth failure', ['message' => $exception->getMessage()]);
        return new Response('Google authentication failed: ' . $exception->getMessage(), 403);
    }
}