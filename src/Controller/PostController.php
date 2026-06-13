<?php

namespace App\Controller;
use App\Entity\User;

use App\Entity\Post;
use App\Form\PostType;
use App\Entity\Reactions;           // ← Ajoutez cet import
use App\Repository\PostRepository;
use App\Service\AblyService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use App\Form\PostFilterType;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/post')]
final class PostController extends AbstractController
{
    private AblyService $ablyService;
    private TranslatorInterface $translator;
    
    public function __construct(AblyService $ablyService, TranslatorInterface $translator)
    {
        $this->ablyService = $ablyService;
        $this->translator = $translator;
    }
    
    #[Route('/', name: 'app_post_index', methods: ['GET'])]
    public function index(Request $request, PostRepository $postRepository, PaginatorInterface $paginator): Response
    {
        // Create the filter form
        $form = $this->createForm(PostFilterType::class);
        $form->handleRequest($request);
        
        // Apply filters
        $filters = $form->isSubmitted() && $form->isValid() ? $form->getData() : [];
        
        // Get filtered query
        $query = $postRepository->findByFilters($filters);
        
        // Determine items per page
        $itemsPerPage = isset($filters['itemsPerPage']) ? (int)$filters['itemsPerPage'] : 4;
        
        // Paginate the results
        $posts = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            $itemsPerPage
        );
        
        return $this->render('post/index.html.twig', [
            'posts' => $posts,
            'reactionTypes' => Reactions::TYPES,
            'filter_form' => $form->createView(),
        ]);
    }

    #[Route('/new', name: 'app_post_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $post = new Post();
        $form = $this->createForm(PostType::class, $post);
        $form->handleRequest($request);
    
        $imageFile = $form->get('imageFile')->getData();
        $webImage  = $form->get('webImage')->getData();
    
        if ($imageFile && $webImage) {
            $this->addFlash('error', $this->translator->trans('blog.error.image_both'));
            return $this->render('post/new.html.twig', [
                'form' => $form->createView(),
            ]);
        }
    
        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser() ?: $entityManager->getRepository(User::class)->find(1);
            $post->setUser($user)->setCreatedAt(new \DateTime());
    
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = preg_replace('/[^a-z0-9_]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $originalFilename)));
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();
    
                try {
                    // Déplacement du fichier vers le dossier absolute défini
                $targetDir    = $this->getParameter('uploads_directory');
                $imageFile->move($targetDir, $newFilename);

                // Construction du chemin absolu à stocker
                $absolutePath = $targetDir . DIRECTORY_SEPARATOR . $newFilename;
                $post->setImageUrl($absolutePath);  // Chemin web
                } catch (FileException $e) {
                    $this->addFlash('error', $this->translator->trans('blog.error.image_upload'));
                    return $this->render('post/new.html.twig', ['form' => $form]);
                }
            } elseif ($webImage) {
                $post->setImageUrl($webImage);
            }
    
            $entityManager->persist($post);
            $entityManager->flush();
            
            // Send real-time notification via Ably
            $this->ablyService->publishNewPost([
                'id' => $post->getId(),
                'title' => $post->getTitle(),
                'content' => substr(strip_tags($post->getContent()), 0, 100) . '...',
                'author' => $user->getNom() ?? 'Anonymous',
                'createdAt' => $post->getCreatedAt()->format('d/m/Y H:i'),
                'imageUrl' => $post->getImageUrl()
            ]);
    
            $this->addFlash('success', $this->translator->trans('blog.success.created'));
            return $this->redirectToRoute('app_post_index');
        }
    
        return $this->render('post/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'app_post_show', methods: ['GET'])]
    public function show(Request $request, Post $post, PaginatorInterface $paginator): Response
    {
        // Get comments for the post
        $comments = $post->getComments();
        
        // Create a paginator from the collection
        $pagination = $paginator->paginate(
            $comments,
            $request->query->getInt('page', 1), // Current page, default is 1
            5 // Items per page
        );
        
        return $this->render('post/show.html.twig', [
            'post' => $post,
            'comments' => $pagination,
            'reactionTypes' => Reactions::TYPES,
        ]);
    }

    #[Route('/post/{id}/edit', name: 'app_post_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Post $post, EntityManagerInterface $entityManager): Response
    {
        // ✅ Vérifier que l'utilisateur est bien le créateur du post
        $user = $this->getUser();
        if ($user !== $post->getUser()) {
            throw new AccessDeniedHttpException('Vous n\'êtes pas autorisé à modifier ce post.');
        }

        // 1) Création du form et pré-remplissage du champ URL si nécessaire
        $form = $this->createForm(PostType::class, $post);
        $form->get('webImage')->setData(
            filter_var($post->getImageUrl(), FILTER_VALIDATE_URL)
                ? $post->getImageUrl()
                : ''
        );

        $form->handleRequest($request);

        // 2) Récupération des uploads
        $imageFile = $form->get('imageFile')->getData();
        $webImage  = $form->get('webImage')->getData();

        // 3) Soumission du form
        if ($form->isSubmitted() && $form->isValid()) {
            // 3.a) Check exclusivité fichier vs URL
            if ($imageFile && $webImage) {
                $this->addFlash('error', 'Veuillez choisir soit une image locale, soit une URL, pas les deux.');
                return $this->render('post/edit.html.twig', [
                    'form' => $form->createView(),
                    'post' => $post,
                    'existingImage' => $post->getImageUrl(),
                ]);
            } else {
                $imageUpdated = false;
                
                // 3.b) Traitement du fichier uploadé
                if ($imageFile) {
                    $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = preg_replace('/[^a-z0-9_]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $originalFilename)));
                    $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                    try {
                        $targetDir = $this->getParameter('uploads_directory');
                        $imageFile->move($targetDir, $newFilename);
                        $post->setImageUrl('/uploads/'.$newFilename);
                        $imageUpdated = true;
                    } catch (FileException $e) {
                        $this->addFlash('error', "Erreur lors de l'upload de l'image.");
                        return $this->render('post/edit.html.twig', [
                            'form' => $form->createView(),
                            'post' => $post,
                            'existingImage' => $post->getImageUrl(),
                        ]);
                    }
                }
                
                // 3.c) Traitement de l'URL web
                if ($webImage) {
                    $post->setImageUrl($webImage);
                    $imageUpdated = true;
                } 
                // 3.d) Si aucune image n'est fournie mais qu'il y avait une image précédente
                // et que l'utilisateur a explicitement vidé les deux champs, on supprime l'image
                else if (!$imageFile && !$webImage && $post->getImageUrl() && 
                         $request->request->has('post') && 
                         array_key_exists('webImage', $request->request->get('post'))) {
                    $post->setImageUrl(null);
                    $imageUpdated = true;
                }

                // 3.e) Sauvegarde
                $entityManager->flush();
                $this->addFlash('success', 'Post modifié avec succès !');
                return $this->redirectToRoute('app_post_index');
            }
        }

        // 4) Affichage du template
        return $this->render('post/edit.html.twig', [
            'form' => $form->createView(),
            'post' => $post,
            'existingImage' => $post->getImageUrl(),
        ]);
    }

    #[Route('/{id}', name: 'app_post_delete', methods: ['POST'])]
    public function delete(Request $request, Post $post, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$post->getId(), $request->request->get('_token'))) {
            $entityManager->remove($post);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_post_index', [], Response::HTTP_SEE_OTHER);
    }
}  