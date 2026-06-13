<?php
namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;
class AppCustomAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';
    private HttpClientInterface $client;
    public function __construct(
        private UserRepository $userRepository,
        private UrlGeneratorInterface $urlGenerator,
        HttpClientInterface $client 
        // Ajoute cette dépendance
        ) {
            $this->client = $client; // <- C'EST ÇA QUI MANQUAIT
        }

        public function authenticate(Request $request): Passport
        {
            $email = $request->request->get('email', '');
            $password = $request->request->get('password', '');
            $csrfToken = $request->request->get('_csrf_token', '');
            $recaptchaToken = $request->request->get('g-recaptcha-response');
        
            $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $email);
        
            // Vérifier le token reCAPTCHA
            if (!$this->isRecaptchaValid($recaptchaToken)) {
                throw new AuthenticationException('Échec de la vérification reCAPTCHA. Veuillez réessayer.');
            }
        
            return new Passport(
                new UserBadge($email, function ($identifier) {
                    $user = $this->userRepository->findOneBy(['email' => $identifier]);
                    if (!$user) {
                        throw new AuthenticationException('Utilisateur non trouvé.');
                    }
                    if ($user->isBlocked()) {
                        throw new AuthenticationException('Votre compte est bloqué. Veuillez contacter un administrateur.');
                    }
                    return $user;
                }),
                new PasswordCredentials($password),
                [
                    new CsrfTokenBadge('authenticate', $csrfToken),
                    new RememberMeBadge(),
                ]
            );
        }
        private function isRecaptchaValid(?string $recaptchaToken): bool
{
    if (empty($recaptchaToken)) {
        return false;
    }

    $response = $this->client->request('POST', 'https://www.google.com/recaptcha/api/siteverify', [
        'body' => [
            'secret' => '6LdsuycrAAAAAHUiaKbRgurxfp55Gw_GqhzEiQV2', // METS ICI TA CLE SECRETE
            'response' => $recaptchaToken
        ],
    ]);

    $data = $response->toArray(false); // false pour ignorer les erreurs HTTP 400

    return $data['success'] ?? false;
}


    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Get target path if the user was redirected
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        // Get user roles
        $roles = $token->getUser()->getRoles();

        // Redirect based on role
        if (in_array('ROLE_ADMIN', $roles, true)) {
            return new RedirectResponse($this->urlGenerator->generate('app_admin'));
        }
        if (in_array('ROLE_SPORTIF', $roles, true)) {
            return new RedirectResponse($this->urlGenerator->generate('home'));
        }
        if (in_array('ROLE_ENTRAINEUR', $roles, true)) {
            return new RedirectResponse($this->urlGenerator->generate('app_entraineur'));
        }
        if (in_array('ROLE_RESPONSABLE_SALLE', $roles, true)) {
            return new RedirectResponse($this->urlGenerator->generate('dashboard_responsable_salle'));
        }

        // Default redirect for users with no specific role
        return new RedirectResponse($this->urlGenerator->generate('app_home'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}