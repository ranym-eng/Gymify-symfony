<?php

namespace App\Controller;

use App\Entity\Activité;
use App\Enum\ActivityType;
use App\Repository\ActivityRepository;
use App\Form\ActivityFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use App\Entity\Cours;

class ActivityController extends AbstractController
{
    #[Route('/activity/new', name: 'app_activity_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $activity = new Activité();
        $form = $this->createForm(ActivityFormType::class, $activity);
        $form->handleRequest($request);
    
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $imageFile */
            $imageFile = $form->get('imageFile')->getData();
            
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();
                
                $uploadDir = $this->getParameter('kernel.project_dir').'/public/uploads/activities';
                
                try {
                    $imageFile->move($uploadDir, $newFilename);
                    $activity->setUrl('/uploads/activities/'.$newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Error uploading image: '.$e->getMessage());
                    return $this->render('activity/new.html.twig', [
                        'page_title' => 'Add New Activity',
                        'form' => $form->createView()
                    ]);
                }
            }
  
            $em->persist($activity);
            $em->flush();
  
            $this->addFlash('success', 'Activity created successfully!');
            return $this->redirectToRoute('app_activity_index');
        }
  
        return $this->render('activity/new.html.twig', [
            'page_title' => 'Add New Activity',
            'form' => $form->createView()
        ]);
    }
  
    #[Route('/activity/add', name: 'app_activity_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        $activity = new Activité();
        $form = $this->createForm(ActivityFormType::class, $activity);
        $form->handleRequest($request);
  
        if (!$form->isSubmitted()) {
            $this->addFlash('error', 'Form not submitted');
            return $this->redirectToRoute('app_activity_new');
        }
  
        if (!$form->isValid()) {
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
            return $this->redirectToRoute('app_activity_new');
        }
  
        /** @var UploadedFile $imageFile */
        $imageFile = $form->get('imageFile')->getData();
        
        if ($imageFile) {
            $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();
            
            $uploadDir = $this->getParameter('kernel.project_dir').'/public/uploads/activities';
            
            try {
                $imageFile->move($uploadDir, $newFilename);
                $activity->setUrl('/uploads/activities/'.$newFilename);
            } catch (FileException $e) {
                $this->addFlash('error', 'Error uploading image: '.$e->getMessage());
                return $this->redirectToRoute('app_activity_new');
            }
        }
  
        $entityManager->persist($activity);
        $entityManager->flush();
  
        $this->addFlash('success', 'Activity created successfully!');
        return $this->redirectToRoute('app_activity_index');
    }

    #[Route('/activity/edit/{id}', name: 'app_activity_edit', methods: ['GET', 'POST'])]
    public function edit(
        int $id,
        Request $request,
        ActivityRepository $repository,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        $activity = $repository->find($id);
        
        if (!$activity) {
            $this->addFlash('error', 'Activity not found');
            return $this->redirectToRoute('app_activity_index');
        }

        $form = $this->createForm(ActivityFormType::class, $activity);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $imageFile */
            $imageFile = $form->get('imageFile')->getData();
            
            // Handle image removal
            if ($request->request->get('remove_image') === '1' && $activity->getUrl()) {
                $imagePath = $this->getParameter('kernel.project_dir').'/public'.$activity->getUrl();
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
                $activity->setUrl(null);
            }
            
            // Handle new image upload
            if ($imageFile) {
                // Remove old image if exists
                if ($activity->getUrl()) {
                    $imagePath = $this->getParameter('kernel.project_dir').'/public'.$activity->getUrl();
                    if (file_exists($imagePath)) {
                        unlink($imagePath);
                    }
                }
                
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();
                
                $uploadDir = $this->getParameter('kernel.project_dir').'/public/uploads/activities';
                
                try {
                    $imageFile->move($uploadDir, $newFilename);
                    $activity->setUrl('/uploads/activities/'.$newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Error uploading image: '.$e->getMessage());
                    return $this->render('activity/edit.html.twig', [
                        'page_title' => 'Edit Activity',
                        'activity' => $activity,
                        'form' => $form->createView(),
                        'activity_types' => ActivityType::cases()
                    ]);
                }
            }

            $entityManager->persist($activity);
            $entityManager->flush();
            
            $this->addFlash('success', 'Activity updated successfully');
            return $this->redirectToRoute('app_activity_index');
        }

        return $this->render('activity/edit.html.twig', [
            'page_title' => 'Edit Activity',
            'activity' => $activity,
            'form' => $form->createView(),
            'activity_types' => ActivityType::cases()
        ]);
    }
    
    #[Route('/admin/activity/{id}/delete', name: 'app_activity_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        EntityManagerInterface $entityManager,
        int $id
    ): Response {
        $activity = $entityManager->getRepository(Activité::class)->find($id);
        
        if (!$activity) {
            $this->addFlash('error', 'Activity not found');
            return $this->redirectToRoute('app_activity_index');
        }
        $cours = $entityManager->getRepository(Cours::class)->findBy(['activité' => $activity]);
         foreach ($cours as $cour) {
          $entityManager->remove($cour);
          }

        if (!$this->isCsrfTokenValid('delete'.$activity->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token');
            return $this->redirectToRoute('app_activity_index');
        }

        // Remove associated image
        if ($activity->getUrl()) {
            $imagePath = $this->getParameter('kernel.project_dir').'/public'.$activity->getUrl();
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }

        $entityManager->remove($activity);
        $entityManager->flush();

        $this->addFlash('success', 'Activity deleted successfully!');
        return $this->redirectToRoute('app_activity_index');
    }

    #[Route('/activity', name: 'app_activity_index')]
    public function activity(ActivityRepository $activityRepository): Response
    {
        return $this->render('activity/index.html.twig', [
            'page_title' => 'Activity Dashboard',
            'activities' => $activityRepository->findAll(),
            'stats' => [
                'visitors' => 1294,
                'subscribers' => 1303,
                'sales' => 1345,
                'orders' => 576
            ]
        ]);
    }
    #[Route('/activitySportif', name:'activity_sportif')]
    public function activitySportif(ActivityRepository $activityRepository): Response
    {
        return $this->render('sportif/activity.html.twig', [
            'activities' => $activityRepository->findAll(),
            'activity_types' => ActivityType::cases()
            
        ]);
    }

}