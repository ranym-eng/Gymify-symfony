<?php
namespace App\Enum;

enum Role: string
{
    case SPORTIF = 'sportif';
    case ADMIN = 'admin';
    case RESPONSABLE_SALLE = 'responsable_salle';
    case ENTRAINEUR = 'entraineur';

    public function label(): string
    {
        return match ($this) {
            self::SPORTIF => 'Sportif',
            self::ADMIN => 'Admin',
            self::RESPONSABLE_SALLE => 'Responsable Salle',
            self::ENTRAINEUR => 'Entraineur',
        };
    }
}

