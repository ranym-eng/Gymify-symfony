<?php
// src/Controller/CaptchaController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Image;

class CaptchaController extends AbstractController
{
    /**
     * @Route("/captcha", name="generate_captcha", methods={"GET"})
     */
    public function generateCaptcha(EntityManagerInterface $entityManager)
    {
        // Sélectionner un ensemble d'images (par exemple, 9 images)
        $images = $entityManager->getRepository(Image::class)->findRandomImages(9);

        // Créer un tableau de réponses attendues
        $correctAnswers = [];
        foreach ($images as $image) {
            // Ajoutez les objets associés à l'image dans le tableau de réponses attendues
            $correctAnswers[] = $image->getObjects();
        }

        // Sauvegarder l'ensemble des réponses attendues dans la session pour validation ultérieure
        $_SESSION['captcha_answers'] = $correctAnswers;

        // Renvoie les images au frontend
        return new JsonResponse([
            'images' => array_map(function($image) {
                return $image->getImagePath();
            }, $images),
            'captcha_id' => uniqid(),
        ]);
    }
}
