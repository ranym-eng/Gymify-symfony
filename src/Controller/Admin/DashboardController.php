<?php

namespace App\Controller\Admin;

use App\Repository\ProduitRepository;
use App\Repository\CommandeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{
    #[Route('/adminDashboard', name: 'admin_dashboard')]
    public function index(ProduitRepository $produitRepository, CommandeRepository $commandeRepository): Response
    {
        // Get total products
        $totalProducts = $produitRepository->count([]);
        
        // Get order statistics
        $totalOrders = $commandeRepository->count([]);
        $completedOrders = $commandeRepository->count(['statutC' => 'Validée']);
        $pendingOrders = $commandeRepository->count(['statutC' => 'En cours']);
        
        // Get recent orders (last 5)
        $recentOrders = $commandeRepository->findBy([], ['dateC' => 'DESC'], 5);
        
        // Get recent products (last 5)
        $recentProducts = $produitRepository->findBy([], ['idP' => 'DESC'], 5);

        return $this->render('dashboard/index.html.twig', [
            'total_products' => $totalProducts,
            'total_orders' => $totalOrders,
            'completed_orders' => $completedOrders,
            'pending_orders' => $pendingOrders,
            'recent_orders' => $recentOrders,
            'recent_products' => $recentProducts,
        ]);
    }
} 