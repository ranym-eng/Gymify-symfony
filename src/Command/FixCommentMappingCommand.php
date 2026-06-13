<?php

namespace App\Command;

use App\Entity\Comment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:fix-comment-mapping',
    description: 'Fix comment mapping and check database records',
)]
class FixCommentMappingCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        // 1. Clear Doctrine's metadata cache
        $io->section('Clearing Doctrine metadata cache');
        $this->entityManager->clear();
        
        // 2. Fetch comments using raw SQL
        $io->section('Fetching comments with raw SQL');
        $conn = $this->entityManager->getConnection();
        $sql = 'SELECT * FROM comment';
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();
        $comments = $result->fetchAllAssociative();
        
        $io->success(sprintf('Found %d comments in the database using raw SQL', count($comments)));
        
        if (count($comments) > 0) {
            $io->table(
                array_keys($comments[0]), 
                array_map(fn($comment) => array_map(fn($val) => $val ?? 'NULL', $comment), $comments)
            );
        }
        
        // 3. Fetch comments using Doctrine
        $io->section('Fetching comments with Doctrine');
        try {
            $comments = $this->entityManager->getRepository(Comment::class)->findAll();
            $io->success(sprintf('Found %d comments in the database using Doctrine', count($comments)));
            
            if (count($comments) > 0) {
                $tableData = [];
                foreach ($comments as $comment) {
                    $tableData[] = [
                        $comment->getId(),
                        $comment->getContent(),
                        $comment->getCreatedAt() ? $comment->getCreatedAt()->format('Y-m-d H:i:s') : 'NULL',
                        $comment->getPost() ? $comment->getPost()->getId() : 'NULL',
                        $comment->getUser() ? $comment->getUser()->getId() : 'NULL',
                    ];
                }
                
                $io->table(['ID', 'Content', 'Created At', 'Post ID', 'User ID'], $tableData);
            }
        } catch (\Exception $e) {
            $io->error('Failed to fetch comments with Doctrine: ' . $e->getMessage());
        }
        
        $io->success('Command completed');
        
        return Command::SUCCESS;
    }
} 