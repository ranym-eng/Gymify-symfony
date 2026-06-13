<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;
use App\Repository\PostRepository;
use App\Repository\CommentRepository;
use App\Repository\ReactionsRepository;

class StatisticsController extends AbstractController
{
    #[Route('/statistics', name: 'app_statistics')]
    public function index(
        EntityManagerInterface $entityManager,
        ChartBuilderInterface $chartBuilder,
        PostRepository $postRepository,
        CommentRepository $commentRepository,
        ReactionsRepository $reactionsRepository
    ): Response
    {
        // Get stats data
        $postStats = $this->getPostsChartData($postRepository);
        $commentStats = $this->getCommentsChartData($commentRepository);
        $reactionStats = $this->getReactionsChartData($reactionsRepository);

        // Create charts
        $postsChart = $this->createLineChart($chartBuilder, 'Posts par jour', $postStats['labels'], $postStats['data']);
        $commentsChart = $this->createLineChart($chartBuilder, 'Commentaires par jour', $commentStats['labels'], $commentStats['data']);
        $reactionsChart = $this->createPieChart($chartBuilder, 'Réactions', $reactionStats['labels'], $reactionStats['data']);
        
        return $this->render('statistics/index.html.twig', [
            'postsChart' => $postsChart,
            'commentsChart' => $commentsChart,
            'reactionsChart' => $reactionsChart,
        ]);
    }

    #[Route('/my-statistics', name: 'app_my_statistics')]
    public function myStatistics(
        EntityManagerInterface $entityManager,
        ChartBuilderInterface $chartBuilder,
        PostRepository $postRepository,
        CommentRepository $commentRepository,
        ReactionsRepository $reactionsRepository
    ): Response
    {
        $user = $this->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        
        // Get user stats data
        $postStats = $this->getPostsChartData($postRepository, $user);
        $commentStats = $this->getCommentsOnUserPostsChartData($commentRepository, $user);
        $reactionStats = $this->getReactionsOnUserPostsChartData($reactionsRepository, $user);
        
        // Create charts
        $postsChart = $this->createLineChart($chartBuilder, 'Mes posts par jour', $postStats['labels'], $postStats['data']);
        $commentsChart = $this->createLineChart($chartBuilder, 'Commentaires sur mes posts', $commentStats['labels'], $commentStats['data']);
        $reactionsChart = $this->createPieChart($chartBuilder, 'Réactions sur mes posts', $reactionStats['labels'], $reactionStats['data']);
        
        return $this->render('statistics/my_statistics.html.twig', [
            'postsChart' => $postsChart,
            'commentsChart' => $commentsChart,
            'reactionsChart' => $reactionsChart,
        ]);
    }
    
    private function getPostsChartData(PostRepository $repository, $user = null): array
    {
        // Date range (last 30 days)
        $endDate = new \DateTime();
        $startDate = (new \DateTime())->modify('-30 days');
        
        // Get posts grouped by date
        $query = $repository->createQueryBuilder('p')
            ->select('COUNT(p.id) as count, SUBSTRING(p.createdAt, 1, 10) as date')
            ->where('p.createdAt BETWEEN :start AND :end')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->groupBy('date')
            ->orderBy('date', 'ASC');
            
        if ($user) {
            $query->andWhere('p.user = :user')
                  ->setParameter('user', $user);
        }
        
        $results = $query->getQuery()->getResult();
        
        // Prepare data for chart
        $dates = [];
        $counts = [];
        
        // Fill in all dates in the range
        $period = new \DatePeriod(
            $startDate,
            new \DateInterval('P1D'),
            $endDate
        );
        
        $dataByDate = [];
        foreach ($results as $result) {
            $dataByDate[$result['date']] = $result['count'];
        }
        
        foreach ($period as $date) {
            $dateString = $date->format('Y-m-d');
            $dates[] = $date->format('d/m');
            $counts[] = $dataByDate[$dateString] ?? 0;
        }
        
        return [
            'labels' => $dates,
            'data' => $counts
        ];
    }
    
    private function getCommentsChartData(CommentRepository $repository, $user = null): array
    {
        // Date range (last 30 days)
        $endDate = new \DateTime();
        $startDate = (new \DateTime())->modify('-30 days');
        
        // Get comments grouped by date
        $query = $repository->createQueryBuilder('c')
            ->select('COUNT(c.id) as count, SUBSTRING(c.createdAt, 1, 10) as date')
            ->where('c.createdAt BETWEEN :start AND :end')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->groupBy('date')
            ->orderBy('date', 'ASC');
            
        if ($user) {
            $query->andWhere('c.user = :user')
                  ->setParameter('user', $user);
        }
        
        $results = $query->getQuery()->getResult();
        
        // Prepare data for chart
        $dates = [];
        $counts = [];
        
        // Fill in all dates in the range
        $period = new \DatePeriod(
            $startDate,
            new \DateInterval('P1D'),
            $endDate
        );
        
        $dataByDate = [];
        foreach ($results as $result) {
            $dataByDate[$result['date']] = $result['count'];
        }
        
        foreach ($period as $date) {
            $dateString = $date->format('Y-m-d');
            $dates[] = $date->format('d/m');
            $counts[] = $dataByDate[$dateString] ?? 0;
        }
        
        return [
            'labels' => $dates,
            'data' => $counts
        ];
    }
    
    private function getCommentsOnUserPostsChartData(CommentRepository $repository, $user): array
    {
        // Date range (last 30 days)
        $endDate = new \DateTime();
        $startDate = (new \DateTime())->modify('-30 days');
        
        // Get comments on user's posts grouped by date
        $query = $repository->createQueryBuilder('c')
            ->select('COUNT(c.id) as count, SUBSTRING(c.createdAt, 1, 10) as date')
            ->join('c.post', 'p')
            ->where('c.createdAt BETWEEN :start AND :end')
            ->andWhere('p.user = :user')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->setParameter('user', $user)
            ->groupBy('date')
            ->orderBy('date', 'ASC');
        
        $results = $query->getQuery()->getResult();
        
        // Prepare data for chart
        $dates = [];
        $counts = [];
        
        // Fill in all dates in the range
        $period = new \DatePeriod(
            $startDate,
            new \DateInterval('P1D'),
            $endDate
        );
        
        $dataByDate = [];
        foreach ($results as $result) {
            $dataByDate[$result['date']] = $result['count'];
        }
        
        foreach ($period as $date) {
            $dateString = $date->format('Y-m-d');
            $dates[] = $date->format('d/m');
            $counts[] = $dataByDate[$dateString] ?? 0;
        }
        
        return [
            'labels' => $dates,
            'data' => $counts
        ];
    }
    
    private function getReactionsChartData(ReactionsRepository $repository, $user = null): array
    {
        // Get reactions grouped by type
        $query = $repository->createQueryBuilder('r')
            ->select('r.type, COUNT(r.id) as count')
            ->groupBy('r.type');
            
        if ($user) {
            $query->join('r.post', 'p')
                  ->andWhere('r.user = :user')
                  ->setParameter('user', $user);
        }
        
        $results = $query->getQuery()->getResult();
        
        // Prepare data for chart
        $types = [];
        $counts = [];
        
        foreach ($results as $result) {
            $types[] = $result['type'];
            $counts[] = $result['count'];
        }
        
        return [
            'labels' => $types,
            'data' => $counts
        ];
    }
    
    private function getReactionsOnUserPostsChartData(ReactionsRepository $repository, $user): array
    {
        // Get reactions on user's posts grouped by type
        $query = $repository->createQueryBuilder('r')
            ->select('r.type, COUNT(r.id) as count')
            ->join('r.post', 'p')
            ->where('p.user = :user')
            ->setParameter('user', $user)
            ->groupBy('r.type');
        
        $results = $query->getQuery()->getResult();
        
        // Prepare data for chart
        $types = [];
        $counts = [];
        
        foreach ($results as $result) {
            $types[] = $result['type'];
            $counts[] = $result['count'];
        }
        
        return [
            'labels' => $types,
            'data' => $counts
        ];
    }
    
    private function createLineChart(ChartBuilderInterface $chartBuilder, string $label, array $labels, array $data): Chart
    {
        $chart = $chartBuilder->createChart(Chart::TYPE_LINE);
        
        $chart->setData([
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => $label,
                    'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                    'borderColor' => 'rgb(54, 162, 235)',
                    'data' => $data,
                    'tension' => 0.4,
                ],
            ],
        ]);
        
        $chart->setOptions([
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0
                    ]
                ],
            ],
            'responsive' => true,
            'plugins' => [
                'title' => [
                    'display' => true,
                    'text' => $label
                ]
            ]
        ]);
        
        return $chart;
    }
    
    private function createPieChart(ChartBuilderInterface $chartBuilder, string $label, array $labels, array $data): Chart
    {
        $chart = $chartBuilder->createChart(Chart::TYPE_PIE);
        
        // Define colors for reactions
        $backgroundColors = [
            'like' => 'rgba(54, 162, 235, 0.7)',
            'love' => 'rgba(255, 99, 132, 0.7)',
            'haha' => 'rgba(255, 206, 86, 0.7)',
            'wow' => 'rgba(75, 192, 192, 0.7)',
            'sad' => 'rgba(153, 102, 255, 0.7)',
            'angry' => 'rgba(255, 159, 64, 0.7)',
        ];
        
        $colors = [];
        foreach ($labels as $type) {
            $colors[] = $backgroundColors[$type] ?? 'rgba(201, 203, 207, 0.7)';
        }
        
        $chart->setData([
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => $label,
                    'backgroundColor' => $colors,
                    'data' => $data,
                ],
            ],
        ]);
        
        $chart->setOptions([
            'responsive' => true,
            'plugins' => [
                'legend' => [
                    'position' => 'top',
                ],
                'title' => [
                    'display' => true,
                    'text' => $label
                ]
            ]
        ]);
        
        return $chart;
    }
} 