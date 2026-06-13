<?php

namespace App\Controller\Shop;

use App\Entity\Commande;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/shop/orders')]
class OrderController extends AbstractController
{
    #[Route('/', name: 'shop_orders_index')]
    public function index(CommandeRepository $commandeRepository): Response
    {
        $commandes = $commandeRepository->findBy([], ['dateC' => 'DESC']);
        
        return $this->render('shop/order/index.html.twig', [
            'commandes' => $commandes
        ]);
    }

    #[Route('/{id}', name: 'shop_order_show', methods: ['GET'])]
    public function show(Commande $commande): Response
    {
        return $this->render('shop/order/show.html.twig', [
            'commande' => $commande
        ]);
    }

    #[Route('/{id}/cancel', name: 'shop_order_cancel', methods: ['POST'])]
    public function cancel(
        Commande $commande,
        EntityManagerInterface $entityManager
    ): Response
    {
        if ($commande->getStatutC() === 'En cours') {
            $commande->setStatutC('Annulée');
            $entityManager->flush();
            
            $this->addFlash('success', 'Votre commande a été annulée avec succès.');
        } else {
            $this->addFlash('error', 'Cette commande ne peut plus être annulée.');
        }

        return $this->redirectToRoute('shop_orders_index');
    }
} 