<?php

namespace App\Doctrine\DBAL\Types;

use App\Enum\ActivityType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

class ActivityTypeType extends Type
{
    public const NAME = 'activity_type'; // Nom unique pour votre type

    /**
     * Définit comment le type est stocké en base de données
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        // Utilisez VARCHAR car nous stockons la valeur string de l'enum
        return 'VARCHAR(255)';
    }

    /**
     * Convertit la valeur de la base de données en objet PHP (enum)
     */
    public function convertToPHPValue($value, AbstractPlatform $platform): ?ActivityType
    {
        if ($value === null) {
            return null;
        }

        // Convertit la string stockée en DB en instance de votre enum
        return ActivityType::from($value);
    }

    /**
     * Convertit l'objet PHP (enum) en valeur pour la base de données
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        // Nous stockons la valeur string de l'enum
        return $value->value;
    }

    /**
     * Nom unique du type pour Doctrine
     */
    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * Indique si Doctrine a besoin de convertir les valeurs avant les requêtes SQL
     */
    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}