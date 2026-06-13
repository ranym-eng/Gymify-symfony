<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/orders')]
class OrderController extends AbstractController
{
    #[Route('/orders', name: 'admin_orders_index')]
    public function index(CommandeRepository $commandeRepository): Response
    {
        $commandes = $commandeRepository->findBy([], ['dateC' => 'DESC']);
        
        return $this->render('order/index.html.twig', [
            'commandes' => $commandes
        ]);
    }

    #[Route('/order/{id}', name: 'admin_order_show', methods: ['GET'])]
    public function show(Commande $commande): Response
    {
        return $this->render('order/show.html.twig', [
            'commande' => $commande
        ]);
    }

    #[Route('/{id}/update-status', name: 'admin_order_update_status', methods: ['POST'])]
    public function updateStatus(
        Request $request,
        Commande $commande, 
        EntityManagerInterface $entityManager
    ): Response
    {
        $newStatus = $request->request->get('status');
        if (!in_array($newStatus, ['En cours', 'Validée', 'Annulée'])) {
            $this->addFlash('error', 'Statut invalide.');
            return $this->redirectToRoute('admin_orders_index');
        }

        $commande->setStatutC($newStatus);
        $entityManager->flush();

        $this->addFlash('success', 'Le statut de la commande a été mis à jour.');
        return $this->redirectToRoute('admin_orders_index');
    }

    #[Route('/{id}/delete', name: 'admin_order_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Commande $commande,
        EntityManagerInterface $entityManager
    ): Response
    {
        $entityManager->remove($commande);
        $entityManager->flush();

        $this->addFlash('success', 'La commande a été supprimée.');
        return $this->redirectToRoute('admin_orders_index');
    }

    #[Route('/admin/orders/{id}/update', name: 'admin_order_update', methods: ['POST'])]
    public function update(Request $request, Commande $commande, EntityManagerInterface $entityManager): Response
    {
        $status = $request->request->get('status');
        
        if ($status) {
            $commande->setStatus($status);
            $entityManager->flush();
            
            $this->addFlash('success', 'Statut de la commande mis à jour avec succès');
        }

        return $this->redirectToRoute('admin_order_show', ['id' => $commande->getIdC()]);
    }

    #[Route('/{id}/export-pdf', name: 'admin_order_export_pdf', methods: ['GET'])]
    public function exportPdf(Commande $commande): Response
    {
        // Create new PDF document
        $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // Set document information
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('Your Company');
        $pdf->SetTitle('Facture - Commande #' . $commande->getIdC());

        // Set default header data
        $pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE, PDF_HEADER_STRING);

        // Set header and footer fonts
        $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
        $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

        // Set default monospaced font
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

        // Set margins
        $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

        // Set auto page breaks
        $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

        // Add a page
        $pdf->AddPage();

        // Set font
        $pdf->SetFont('helvetica', '', 12);

        // Company information
        $html = '<h1 style="text-align: center;">Votre Entreprise</h1>';
        $html .= '<p style="text-align: center;">123 Rue Example, Ville</p>';
        $html .= '<p style="text-align: center;">Tél: +216 XX XXX XXX</p>';
        $html .= '<p style="text-align: center;">Email: contact@example.com</p>';
        
        // Invoice title
        $html .= '<h2 style="text-align: center; margin: 20px 0;">FACTURE</h2>';
        
        // Order details
        $html .= '<table border="1" cellpadding="5">
            <tr>
                <th width="40%">Numéro de Commande:</th>
                <td width="60%">' . $commande->getIdC() . '</td>
            </tr>
            <tr>
                <th>Date:</th>
                <td>' . $commande->getDateC()->format('d/m/Y H:i:s') . '</td>
            </tr>
            <tr>
                <th>Statut:</th>
                <td>' . strtoupper($commande->getStatutC()) . '</td>
            </tr>
            <tr>
                <th>Total:</th>
                <td>' . number_format($commande->getTotalC(), 2, '.', ' ') . ' DT</td>
            </tr>
        </table>';
        
        // Footer
        $html .= '<div style="text-align: center; margin-top: 30px;">
            <strong>Merci de votre confiance!</strong><br>
            <small>Conditions: Cette facture est valable pendant 30 jours.<br>
            Pour toute question, veuillez nous contacter.</small>
        </div>';

        // Output the HTML content
        $pdf->writeHTML($html, true, false, true, false, '');

        // Close and output PDF document
        $filename = sprintf('facture_commande_%d.pdf', $commande->getIdC());
        return new Response(
            $pdf->Output($filename, 'S'),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]
        );
    }
} 