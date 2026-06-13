<?php

namespace App\Enum;

enum TypeAbonnement: string
{
    case MOIS = 'mois';
    case SEMESTRE = 'trimestre';
    case ANNEE = 'année';

    public function getLabel(): string
    {
        return match($this) {
            self::MOIS => 'Mensuel',
            self::SEMESTRE => 'Trimestriel',
            self::ANNEE => 'Annuel',
        };
    }
}