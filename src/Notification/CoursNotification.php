<?php
namespace App\Notification;

use App\Entity\Cours;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\Recipient;

class CoursNotification extends Notification
{
    private $cours;
    private $action;

    public function __construct(Cours $cours, string $action)
    {
        $this->cours = $cours;
        $this->action = $action;
        parent::__construct($this->getSubject(), ['email']);
    }

    public function getSubject(): string
    {
        return match ($this->action) {
            'created' => 'Nouveau cours ajouté : ' . $this->cours->getTitle(),
            'updated' => 'Cours modifié : ' . $this->cours->getTitle(),
            'deleted' => 'Cours supprimé : ' . $this->cours->getTitle(),
            default => 'Notification de cours',
        };
    }

    public function asEmailMessage(Recipient $recipient, string $transport = null): ?NotificationEmail
    {
        $email = (new NotificationEmail())
            ->subject($this->getSubject())
            ->from('%app.admin_email%')
            ->to($recipient->getEmail())
            ->htmlTemplate('<h1>Cours {{ course.title }}</h1>
    <p>Le cours "{{ course.title }}" a été {{ action }} par {{ trainerName }}.</p>
    <p>Date: {{ date }}</p>')
            ->context([
                'cours' => $this->cours,
                'action' => $this->action,
            ]);

        return $email;
    }
}
