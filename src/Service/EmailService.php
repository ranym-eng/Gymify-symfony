<?php
namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class EmailService
{
    private $mailer;
    private $twig;
    private $logger;

    public function __construct(MailerInterface $mailer, Environment $twig, LoggerInterface $logger)
    {
        $this->mailer = $mailer;
        $this->twig = $twig;
        $this->logger = $logger;
    }

    public function sendEmail(string $to, string $subject, string $template, array $context): void
    {
        try {
            $email = (new Email())
                ->from('ghabijihen9@gmail.com') // Remplacez par votre adresse e-mail
                ->to($to)
                ->subject($subject)
                ->html($this->twig->render($template, $context));

            $this->mailer->send($email);
            $this->logger->info('E-mail envoyé avec succès', ['to' => $to, 'subject' => $subject]);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de l\'envoi de l\'e-mail', [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}