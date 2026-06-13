<?php
namespace App\Security;

interface TokenInterface
{
    public function getToken();
    public function setToken(string $token);
}
