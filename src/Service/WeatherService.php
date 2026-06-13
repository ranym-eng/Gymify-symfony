<?php

namespace App\Service;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class WeatherService
{
    private $httpClient;
    private $apiKey;
    private $cache;

    public function __construct(HttpClientInterface $httpClient, string $apiKey)
    {
        $this->httpClient = $httpClient;
        $this->apiKey = $apiKey;
        $this->cache = new FilesystemAdapter();
    }

    public function getCurrentWeather(string $location): array
    {
        $cacheKey = 'weather_current_' . md5($location);
        $cachedItem = $this->cache->getItem($cacheKey);

        if ($cachedItem->isHit()) {
            return $cachedItem->get();
        }

        try {
            $response = $this->httpClient->request('GET', 'http://api.weatherapi.com/v1/current.json', [
                'query' => [
                    'key' => $this->apiKey,
                    'q' => $location,
                    'lang' => 'fr',
                ],
            ]);

            $data = $response->toArray();
            $cachedItem->set($data);
            $cachedItem->expiresAfter(3600); // Cache for 1 hour
            $this->cache->save($cachedItem);

            return $data;
        } catch (\Exception $e) {
            throw new \Exception('Erreur lors de la récupération des données météo actuelles : ' . $e->getMessage());
        }
    }

    public function getForecast(string $location, int $days = 7): array
    {
        $cacheKey = 'weather_forecast_' . md5($location . $days);
        $cachedItem = $this->cache->getItem($cacheKey);

        if ($cachedItem->isHit()) {
            return $cachedItem->get();
        }

        try {
            $response = $this->httpClient->request('GET', 'http://api.weatherapi.com/v1/forecast.json', [
                'query' => [
                    'key' => $this->apiKey,
                    'q' => $location,
                    'days' => $days,
                    'lang' => 'fr',
                ],
            ]);

            $data = $response->toArray();
            $cachedItem->set($data);
            $cachedItem->expiresAfter(3600); // Cache for 1 hour
            $this->cache->save($cachedItem);

            return $data;
        } catch (\Exception $e) {
            throw new \Exception('Erreur lors de la récupération des prévisions météo : ' . $e->getMessage());
        }
    }
}