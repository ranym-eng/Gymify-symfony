<?php
namespace App\Security;

use League\OAuth2\Client\Token\AccessToken;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CredentialsInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\BadgeInterface;

class OAuth2Credentials implements CredentialsInterface, BadgeInterface
{
    private $accessToken;

    public function __construct(AccessToken $accessToken)
    {
        $this->accessToken = $accessToken;
    }

    public function getToken(): string
    {
        return $this->accessToken->getToken();
    }

    // Implémenter la méthode isResolved de BadgeInterface
    public function isResolved(): bool
    {
        // On considère que le token est résolu dès qu'il est présent
        return true;
    }
}
