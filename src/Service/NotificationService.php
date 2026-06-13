<?php

namespace App\Service;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use SplObjectStorage;

class NotificationService implements MessageComponentInterface
{
    protected $clients;
    private $queueFile = 'var/notifications_queue.json';

    public function __construct()
    {
        $this->clients = new SplObjectStorage();
        echo "NotificationService initialisé\n";
        if (!file_exists(dirname($this->queueFile))) {
            mkdir(dirname($this->queueFile), 0755, true);
        }
        if (!file_exists($this->queueFile)) {
            file_put_contents($this->queueFile, json_encode([]));
        }
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        echo "Nouvelle connexion (ID: {$conn->resourceId})\n";
        echo "Nombre total de clients connectés: {$this->clients->count()}\n";
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        echo "Message reçu de {$from->resourceId}: $msg\n";
        $data = json_decode($msg, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "Erreur JSON: " . json_last_error_msg() . "\n";
            return;
        }
        if (isset($data['userId'])) {
            $from->userId = $data['userId'];
            echo "Utilisateur {$data['userId']} associé à la connexion {$from->resourceId}\n";
            echo "Clients après association: {$this->clients->count()}\n";
            $this->sendQueuedNotifications($data['userId']);
        } else {
            echo "Aucun userId fourni dans le message\n";
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
        echo "Connexion fermée (ID: {$conn->resourceId})\n";
        echo "Nombre total de clients restants: {$this->clients->count()}\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "Erreur sur connexion {$conn->resourceId}: {$e->getMessage()}\n";
        $conn->close();
    }

    public function sendNotification($userId, $message)
    {
        echo "Tentative d'envoi à l'utilisateur $userId: $message\n";
        echo "Nombre total de clients connectés: {$this->clients->count()}\n";
        $found = false;
        foreach ($this->clients as $client) {
            $clientUserId = isset($client->userId) ? $client->userId : 'non défini';
            echo "Vérification client ID {$client->resourceId}, userId: $clientUserId\n";
            if (isset($client->userId) && $client->userId == $userId) {
                $client->send(json_encode([
                    'type' => 'reminder',
                    'message' => $message,
                    'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
                ]));
                echo "Notification envoyée à $userId via connexion {$client->resourceId}\n";
                $found = true;
            }
        }
        if (!$found) {
            echo "Aucun client connecté pour l'utilisateur $userId\n";
        }
        return $found;
    }

    public function queueNotification($userId, $message)
    {
        echo "Mise en file d'attente de la notification pour l'utilisateur $userId: $message\n";
        $queue = json_decode(file_get_contents($this->queueFile), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "Erreur lecture file JSON: " . json_last_error_msg() . "\n";
            $queue = [];
        }
        $queue[] = [
            'userId' => $userId,
            'message' => $message,
            'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
        ];
        $writeResult = file_put_contents($this->queueFile, json_encode($queue, JSON_PRETTY_PRINT));
        if ($writeResult === false) {
            echo "Erreur écriture file JSON: impossible d'écrire dans {$this->queueFile}\n";
        } else {
            echo "Notification ajoutée à la file (taille: $writeResult octets)\n";
        }
    }

    public function sendQueuedNotifications($userId)
    {
        echo "Début sendQueuedNotifications pour l'utilisateur $userId\n";
        $queue = json_decode(file_get_contents($this->queueFile), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "Erreur lecture file JSON: " . json_last_error_msg() . "\n";
            return;
        }
        echo "File JSON lue, notifications trouvées: " . count($queue) . "\n";
        $remainingQueue = [];
        $sent = false;
        foreach ($queue as $index => $notification) {
            echo "Traitement notification $index: userId={$notification['userId']}, message={$notification['message']}\n";
            if ($notification['userId'] == $userId) {
                $sent = $this->sendNotification($userId, $notification['message']) || $sent;
                if ($sent) {
                    echo "Notification en file envoyée à l'utilisateur $userId: {$notification['message']}\n";
                    continue;
                }
                echo "Échec envoi notification, reste en file: {$notification['message']}\n";
            }
            $remainingQueue[] = $notification;
        }
        echo "Notifications envoyées: " . ($sent ? 'Oui' : 'Non') . "\n";
        $writeResult = file_put_contents($this->queueFile, json_encode($remainingQueue, JSON_PRETTY_PRINT));
        if ($writeResult === false) {
            echo "Erreur écriture file JSON: impossible d'écrire dans {$this->queueFile}\n";
        } else {
            echo "File mise à jour, notifications restantes: " . count($remainingQueue) . "\n";
        }
    }
}