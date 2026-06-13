<?php
namespace App\Security;

class ClientRegistry
{
    private $clients = [];

    public function addClient($name, $client)
    {
        $this->clients[$name] = $client;
    }

    public function getClient($name)
    {
        return $this->clients[$name] ?? null;
    }
}
