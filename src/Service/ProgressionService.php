<?php

namespace App\Service;

use App\Entity\AbonnementData;

class ProgressionService
{
    public function getProgression(AbonnementData $abonnementData): array
    {
        $now = new \DateTime();
        $dateDebut = $abonnementData->getDateDebut();
        $dateFin = $abonnementData->getDateFin();

        if ($dateFin <= $dateDebut) {
            return [
                'progressionPercentage' => 0.0,
                'participationsCount' => 0,
                'progressionSeuil' => 10,
                'hasUnlockedAdvantage' => false,
            ];
        }

        $totalDuration = $dateFin->getTimestamp() - $dateDebut->getTimestamp();
        $elapsedDuration = $now->getTimestamp() - $dateDebut->getTimestamp();

        if ($elapsedDuration < 0 || $totalDuration <= 0) {
            return [
                'progressionPercentage' => 0.0,
                'participationsCount' => 0,
                'progressionSeuil' => 10,
                'hasUnlockedAdvantage' => false,
            ];
        }

        $progressionPercentage = min(100.0, ($elapsedDuration / $totalDuration) * 100);
        $participationsCount = 0; // Replace with actual logic to count participations
        $progressionSeuil = 10; // Example threshold
        $hasUnlockedAdvantage = $progressionPercentage >= 80 && $participationsCount >= $progressionSeuil;

        return [
            'progressionPercentage' => round($progressionPercentage, 2),
            'participationsCount' => $participationsCount,
            'progressionSeuil' => $progressionSeuil,
            'hasUnlockedAdvantage' => $hasUnlockedAdvantage,
        ];
    }
}