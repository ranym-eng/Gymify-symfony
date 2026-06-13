<?php

namespace App\Command;

use App\Service\NotificationService;
use App\Repository\PaiementRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:check-subscription-reminders',
    description: 'Vérifie les abonnements proches de l\'expiration et envoie des rappels'
)]
class CheckSubscriptionRemindersCommand extends Command
{
    private $paiementRepository;
    private $notificationService;

    public function __construct(PaiementRepository $paiementRepository, NotificationService $notificationService)
    {
        parent::__construct();
        $this->paiementRepository = $paiementRepository;
        $this->notificationService = $notificationService;
    }

    protected function configure(): void
    {
        // Description is set in AsCommand attribute, but kept here for compatibility
        $this->setDescription('Vérifie les abonnements proches de l\'expiration et envoie des rappels');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
    
        $dateLimit = (new \DateTimeImmutable())->modify('+7 days');
        $paiements = $this->paiementRepository->findExpiringPaiements($dateLimit);
    
        if (empty($paiements)) {
            $io->note('Aucun abonnement ne expire dans 7 jours.');
        }
    
        foreach ($paiements as $paiement) {
            $user = $paiement->getUser();
            $abonnement = $paiement->getAbonnement();
            if ($user && in_array('ROLE_SPORTIF', $user->getRoles())) {
                $message = sprintf(
                    'Votre abonnement %s (%s) expire le %s. Pensez à renouveler !',
                    $abonnement->getType()->value,
                    $abonnement->getActivite()->getNom(),
                    $paiement->getDateFin()->format('d/m/Y')
                );
                // Attempt WebSocket notification
                $sent = $this->notificationService->sendNotification($user->getId(), $message);
                if (!$sent) {
                    // Queue notification in file
                    $this->notificationService->queueNotification($user->getId(), $message);
                    $io->note("Notification mise en file d'attente pour l'utilisateur {$user->getId()}");
                } else {
                    $io->note("Notification envoyée via WebSocket à l'utilisateur {$user->getId()}");
                }
                $io->note("Rappel envoyé à l'utilisateur {$user->getId()} pour le paiement {$paiement->getId()}");
            }
        }
    
        $io->success('Vérification des rappels terminée.');
        return Command::SUCCESS;
    }
}