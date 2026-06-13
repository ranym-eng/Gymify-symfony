<?php
namespace App\Controller;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use App\Entity\Abonnement;
use App\Entity\Paiement;
use App\Form\AbonnementType;
use App\Repository\AbonnementRepository;
use App\Repository\ActivityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Routing\Attribute\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Knp\Component\Pager\PaginatorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/responsable/abonnement')]
#[IsGranted('ROLE_RESPONSABLE')]
class AbonnementController extends AbstractController
{
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
 
    }

    #[Route('/', name: 'responsable_abonnement_index', methods: ['GET'])]
    public function index(
        AbonnementRepository $abonnementRepository,
        Request $request,
        ActivityRepository $activityRepository,
        PaginatorInterface $paginator
    ): Response {
        $salle = $this->getUser()->getSalle();
    
        $typeFilter = $request->query->get('type');
        $activityFilter = $request->query->get('activity');
    
        $typeFilterValue = match($typeFilter) {
            'Mensuel' => 'mois',
            'Trimestriel' => 'trimestre',
            'Annuel' => 'année',
            default => $typeFilter
        };
    
        $query = $abonnementRepository->createQueryByFilters(
            $salle,
            $typeFilterValue,
            $activityFilter
        );
    
        $pagination = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            5
        );
    
        $activities = $activityRepository->findAll();
    
        return $this->render('abonnement/index.html.twig', [
            'abonnements' => $pagination,
            'activities' => $activities,
            'current_type_filter' => $typeFilter,
            'current_activity_filter' => $activityFilter,
        ]);
    }

    #[Route('/new', name: 'responsable_abonnement_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        ActivityRepository $activityRepository
    ): Response {
        $abonnement = new Abonnement();
        $salle = $this->getUser()->getSalle();
        $abonnement->setSalle($salle);
        
        $form = $this->createForm(AbonnementType::class, $abonnement, [
            'activites' => $activityRepository->findAll()
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($abonnement);
            $em->flush();
            $this->addFlash('success', 'Abonnement créé avec succès');
            return $this->redirectToRoute('responsable_abonnement_index');
        }

        return $this->render('abonnement/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'responsable_abonnement_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Abonnement $abonnement,
        EntityManagerInterface $em,
        ActivityRepository $activityRepository
    ): Response {
        $salle = $this->getUser()->getSalle();
        if ($abonnement->getSalle() !== $salle) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(AbonnementType::class, $abonnement, [
            'activites' => $activityRepository->findAll()
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Abonnement mis à jour avec succès');
            return $this->redirectToRoute('responsable_abonnement_index');
        }

        return $this->render('abonnement/edit.html.twig', [
            'form' => $form->createView(),
            'abonnement' => $abonnement,
        ]);
    }

    #[Route('/{id}/delete', name: 'responsable_abonnement_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Abonnement $abonnement,
        EntityManagerInterface $em
    ): Response {
        $salle = $this->getUser()->getSalle();
        if ($abonnement->getSalle() !== $salle) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete'.$abonnement->getId(), $request->request->get('_token'))) {
            $em->remove($abonnement);
            $em->flush();
            $this->addFlash('success', 'Abonnement supprimé avec succès');
        }

        return $this->redirectToRoute('responsable_abonnement_index');
    }

    #[Route('/export-pdf', name: 'responsable_abonnement_export_pdf', methods: ['GET'])]
    public function exportPdf(
        Request $request,
        AbonnementRepository $abonnementRepository,
        ActivityRepository $activityRepository
    ): Response {
        $user = $this->getUser();
        $salle = $user->getSalle();
        
        if (!$salle) {
            $this->addFlash('error', 'Aucune salle associée à votre compte');
            return $this->redirectToRoute('responsable_abonnement_index');
        }
    
        $typeFilter = $request->query->get('type');
        $activityFilter = $request->query->get('activity');
    
        $typeFilterValue = match($typeFilter) {
            'Mensuel' => 'mois',
            'Trimestriel' => 'trimestre',
            'Annuel' => 'année',
            default => $typeFilter
        };
    
        $query = $abonnementRepository->createQueryByFilters(
            $salle,
            $typeFilterValue,
            $activityFilter
        );
        $abonnements = $query->getQuery()->getResult();
    
        $activityName = null;
        if ($activityFilter) {
            $activity = $activityRepository->find($activityFilter);
            $activityName = $activity ? $activity->getNom() : null;
        }
    
        $pdfOptions = new Options();
        $pdfOptions->set([
            'defaultFont' => 'Arial',
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => true,
            'tempDir' => sys_get_temp_dir()
        ]);
    
        $dompdf = new Dompdf($pdfOptions);
    
        $html = $this->renderView('abonnement/export_pdf.html.twig', [
            'abonnements' => $abonnements,
            'salle' => $salle,
            'filters' => [
                'type' => $typeFilter,
                'activity' => $activityName,
            ],
            'export_date' => new \DateTime(),
            'user' => $user
        ]);
    
        try {
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
    
            $filename = sprintf('abonnements_%s_%s.pdf', 
                $salle->getNom(),
                (new \DateTime())->format('Y-m-d')
            );
    
            return new Response($dompdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => sprintf('attachment; filename="%s"', $filename)
            ]);
        } catch (\Exception $e) {
            $this->logger->error('PDF export failed: ' . $e->getMessage());
            $this->addFlash('error', 'Une erreur est survenue lors de la génération du PDF');
            return $this->redirectToRoute('responsable_abonnement_index');
        }
    }

    #[Route('/export-excel', name: 'responsable_abonnement_export_excel', methods: ['GET'])]
    public function exportExcel(
        Request $request,
        AbonnementRepository $abonnementRepository,
        ActivityRepository $activityRepository
    ): Response {
        $user = $this->getUser();
        $salle = $user->getSalle();
        
        if (!$salle) {
            $this->addFlash('error', 'Aucune salle associée à votre compte');
            return $this->redirectToRoute('responsable_abonnement_index');
        }

        $typeFilter = $request->query->get('type');
        $activityFilter = $request->query->get('activity');

        $typeFilterValue = match($typeFilter) {
            'Mensuel' => 'mois',
            'Trimestriel' => 'trimestre',
            'Annuel' => 'année',
            default => $typeFilter
        };

        $query = $abonnementRepository->createQueryByFilters(
            $salle,
            $typeFilterValue,
            $activityFilter
        );
        $abonnements = $query->getQuery()->getResult();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Abonnements');

        $sheet->setCellValue('A1', 'Type');
        $sheet->setCellValue('B1', 'Prix (DT)');
        $sheet->setCellValue('C1', 'Activité');
        $sheet->setCellValue('D1', 'Salle');

        $sheet->getStyle('A1:D1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '3F51B5']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT],
        ]);

        $row = 2;
        foreach ($abonnements as $abonnement) {
            $sheet->setCellValue('A' . $row, $abonnement->getType()->getLabel());
            $sheet->setCellValue('B' . $row, $abonnement->getTarif());
            $sheet->setCellValue('C' . $row, $abonnement->getActivite()->getNom());
            $sheet->setCellValue('D' . $row, $abonnement->getSalle()->getNom());
            $row++;
        }

        foreach (range('A', 'D') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $sheet->setCellValue('A' . ($row + 1), 'Généré par ' . $user->getPrenom() . ' ' . $user->getNom() . ' le ' . (new \DateTime())->format('d/m/Y H:i'));

        $writer = new Xlsx($spreadsheet);
        
        $filename = sprintf('abonnements_%s_%s.xlsx', 
            $salle->getNom(),
            (new \DateTime())->format('Y-m-d')
        );

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
            'Cache-Control' => 'max-age=0',
        ]);
    }

    #[Route('/abonnement/{id}/confirmation/qr', name: 'abonnement_confirmation_qr', methods: ['GET'])]
    #[IsGranted('ROLE_SPORTIF')]
    public function generateQrCode(Abonnement $abonnement, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        
        if (!$user) {
            return new Response('User not authenticated', 401);
        }
    
        $paiement = $em->getRepository(Paiement::class)->findOneBy([
            'abonnement' => $abonnement,
            'user' => $user,
            'status' => 'succeeded'
        ], ['createdAt' => 'DESC']);
        
        if (!$paiement) {
            return new Response('No valid payment found', 403);
        }
        
        try {
            $pdfUrl = $this->generateUrl('abonnement_confirmation_pdf', [
                'id' => $abonnement->getId()
            ], UrlGeneratorInterface::ABSOLUTE_URL);
            
            // Création du QR Code avec bacon/bacon-qr-code
            $qrCode = new QrCode($pdfUrl);
            $qrCode->setSize(150);
            $qrCode->setMargin(10);
            
            $writer = new PngWriter();
            $result = $writer->write($qrCode);
            
            return new Response($result->getString(), 200, [
                'Content-Type' => 'image/png',
                'Content-Disposition' => 'inline; filename="confirmation-qr.png"',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
        } catch (\Exception $e) {
            return new Response('Error generating QR code: ' . $e->getMessage(), 500);
        }
    }
    #[Route('/abonnement/{id}/confirmation/pdf', name: 'abonnement_confirmation_pdf', methods: ['GET'])]
    #[IsGranted('ROLE_SPORTIF')]
    public function downloadConfirmationPdf(Abonnement $abonnement, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $this->logger->info('Accessing PDF confirmation for abonnement', [
            'abonnement_id' => $abonnement->getId(),
            'user_id' => $user ? $user->getId() : 'anonymous',
            'roles' => $user ? $user->getRoles() : 'no user'
        ]);
    
        $paiement = $em->getRepository(Paiement::class)->findOneBy([
            'abonnement' => $abonnement,
            'user' => $user,
            'status' => 'succeeded'
        ], ['created_at' => 'DESC']);
    
        if (!$paiement) {
            $this->logger->error('No succeeded Paiement record found for abonnement', [
                'abonnement_id' => $abonnement->getId(),
                'user_id' => $user ? $user->getId() : 'anonymous'
            ]);
            throw $this->createAccessDeniedException('No valid payment found for this subscription.');
        }
    
        $this->logger->info('Paiement record found for PDF generation', [
            'paiement_id' => $paiement->getId(),
            'status' => $paiement->getStatus(),
            'paymentIntentId' => $paiement->getPaymentIntentId()
        ]);
    
        // Rest of the method remains unchanged
    
    
        $pdfOptions = new Options();
        $pdfOptions->set([
            'defaultFont' => 'Arial',
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => true,
            'tempDir' => sys_get_temp_dir()
        ]);
    
        $dompdf = new Dompdf($pdfOptions);
        try {
            $html = $this->renderView('abonnement/confirmation_pdf.html.twig', [
                'abonnement' => $abonnement,
                'paiement' => $paiement,
                'user' => $user,
                'export_date' => new \DateTime(),
                'confirmation_code' => $paiement->getPaymentIntentId() ?: 'ABON-' . $abonnement->getId() . '-' . time()
            ]);
    
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
    
            $filename = sprintf('confirmation_abonnement_%s_%s.pdf',
                $abonnement->getType()->getLabel(),
                (new \DateTime())->format('Y-m-d')
            );
    
            return new Response($dompdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"' // Force le téléchargement
            ]);
        } catch (\Exception $e) {
            $this->logger->error('PDF generation failed for abonnement', [
                'abonnement_id' => $abonnement->getId(),
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            throw new \RuntimeException('Failed to generate PDF: ' . $e->getMessage());
        }
    }

    #[Route('/debug/paiement/{id}', name: 'debug_paiement')]
    public function debugPaiement(int $id, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        $paiement = $em->getRepository(Paiement::class)->findOneBy([
            'abonnement' => $id,
            'user' => $user,
            'status' => 'succeeded'
        ], ['createdAt' => 'DESC']);
        return new JsonResponse([
            'user_id' => $user ? $user->getId() : 'anonymous',
            'paiement' => $paiement ? [
                'id' => $paiement->getId(),
                'status' => $paiement->getStatus(),
                'createdAt' => $paiement->getCreatedAt()->format('Y-m-d H:i:s'),
                'paymentIntentId' => $paiement->getPaymentIntentId()
            ] : null
        ]);
    }
    #[Route('/test-secret', name: 'test_secret', methods: ['GET'])]
public function testSecret(): JsonResponse
{
    return new JsonResponse([
        'APP_URL_SIGNER_SECRET' => $this->getParameter('env(APP_URL_SIGNER_SECRET)')
    ]);
}
}