<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

class TranslationService
{
    private $logger;
    private $validLanguages = ['en', 'fr', 'es', 'de', 'it'];

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function translateBulk(array $texts, string $targetLang): ?array
    {
        if (!in_array($targetLang, $this->validLanguages)) {
            $this->logger->error('Langue cible non valide', ['lang' => $targetLang]);
            return null;
        }

        $translations = [];
        foreach ($texts as $index => $text) {
            if (empty($text)) {
                $translations[$index] = '';
                continue;
            }

            try {
                $url = 'https://translate.google.com/m';
                $params = [
                    'hl' => 'en',
                    'sl' => 'auto',
                    'tl' => $targetLang,
                    'q' => $text,
                ];

                $ch = curl_init($url . '?' . http_build_query($params));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);

                if ($error || $httpCode !== 200) {
                    $this->logger->error('Erreur lors de la traduction avec Google Translate', [
                        'error' => $error,
                        'http_code' => $httpCode,
                        'text_index' => $index,
                        'response' => substr($response, 0, 500),
                    ]);
                    return null;
                }

                if (preg_match('#<div class="result-container">(.+?)</div>#s', $response, $matches)) {
                    $translations[$index] = trim($matches[1]);
                } else {
                    $this->logger->error('Impossible d\'extraire le texte traduit', [
                        'text_index' => $index,
                        'response' => substr($response, 0, 500),
                    ]);
                    return null;
                }
            } catch (\Exception $e) {
                $this->logger->error('Exception dans translateBulk', [
                    'text_index' => $index,
                    'exception' => $e->getMessage(),
                ]);
                return null;
            }
        }

        return $translations;
    }

    public function translate(string $text, string $targetLang): ?string
    {
        $translations = $this->translateBulk([$text], $targetLang);
        return $translations[0] ?? null;
    }
}