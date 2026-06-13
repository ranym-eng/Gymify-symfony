<?php
// src/Controller/ApiTranslationController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class ApiTranslationController extends AbstractController
{
    #[Route('/api/translate', name: 'api_translate', methods: ['POST'])]
    public function translate(Request $request): JsonResponse
    {
        // Décoder le corps de la requête JSON
        $data = json_decode($request->getContent(), true);

        // Récupérer les données de la requête (texte à traduire et langue cible)
        $text = $data['text'] ?? '';
        $targetLanguage = $data['language'] ?? 'en'; // langue par défaut : anglais

        // Si le texte est vide, renvoyer une erreur
        if (empty($text)) {
            return new JsonResponse(['error' => 'Le texte est requis'], 400);
        }

        try {
            // Effectuer la traduction en utilisant une méthode interne
            $translatedText = $this->translateWithGoogle($text, $targetLanguage);

            // Retourner la traduction en réponse
            return new JsonResponse(['translatedText' => $translatedText]);
        } catch (\Exception $e) {
            // Si une erreur survient, renvoyer un message d'erreur
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Méthode pour traduire avec l'API de Google Translate via cURL
     */
    private function translateWithGoogle(string $text, string $targetLanguage): string
    {
        // Remplacez par votre clé API Google
        $apiKey = 'YOUR_GOOGLE_API_KEY';
        
        // URL de l'API Google Translate
        $url = "https://translation.googleapis.com/language/translate/v2?key=$apiKey";
        
        // Paramètres de la requête
        $data = [
            'q' => $text,
            'target' => $targetLanguage,
        ];

        // Effectuer la requête HTTP avec cURL
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Pour retourner la réponse sous forme de chaîne
        curl_setopt($ch, CURLOPT_POST, true); // Requête POST
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data)); // Données envoyées
        $response = curl_exec($ch);
        curl_close($ch); // Fermer la connexion cURL

        // Décoder la réponse JSON de Google Translate
        $responseData = json_decode($response, true);
        
        // Vérifier la réponse et extraire la traduction
        if (isset($responseData['data']['translations'][0]['translatedText'])) {
            return $responseData['data']['translations'][0]['translatedText'];
        } else {
            throw new \Exception('Erreur de traduction avec Google Translate');
        }
    }
}
