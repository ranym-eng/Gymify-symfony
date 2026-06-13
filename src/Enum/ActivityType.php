<?php

namespace App\Enum;

enum ActivityType: string
{
    case PERSONAL_TRAINING = 'PERSONAL_TRAINING';
    case GROUP_ACTIVITY = 'GROUP_ACTIVITY';
    case FITNESS_CONSULTATION = 'FITNESS_CONSULTATION';

    public function label(): string
    {
        return match($this) {
            self::PERSONAL_TRAINING => 'Personal Training',
            self::GROUP_ACTIVITY => 'Group Activity',
            self::FITNESS_CONSULTATION => 'Fitness Consultation',
        };
    }
    public function icon(): string
    {
        return match($this) {
            self::PERSONAL_TRAINING => 'fas fa-dumbbell', 
            self::GROUP_ACTIVITY => 'fas fa-users', 
            self::FITNESS_CONSULTATION => 'fas fa-clipboard-list',
        };
    }

    public function description(): string
    {
        return match($this) {
            self::PERSONAL_TRAINING => 'Entraînement personnalisé avec un coach.',
            self::GROUP_ACTIVITY => 'Activités collectives pour tous.',
            self::FITNESS_CONSULTATION => 'Consultation fitness pour optimiser vos performances.',
        };
    }

  
    
}