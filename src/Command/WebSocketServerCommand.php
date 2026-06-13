<?php

namespace App\Command;

use App\Service\NotificationService;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:websocket-server',
    description: 'Start the WebSocket server for notifications'
)]
class WebSocketServerCommand extends Command
{
    private $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    protected function configure(): void
    {
        // Description is set in AsCommand attribute, but kept here for compatibility
        $this->setDescription('Start the WebSocket server for notifications');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Starting WebSocket server on port 8081...');
        $server = IoServer::factory(
            new HttpServer(
                new WsServer(
                    $this->notificationService
                )
            ),
            8081
        );
        $server->run();
        return Command::SUCCESS;
    }
}