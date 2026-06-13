<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250416030520 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE abonnement (id_abonnement INT AUTO_INCREMENT NOT NULL, activite_id INT DEFAULT NULL, date_debut DATE NOT NULL, date_fin DATE NOT NULL, type_abonnement VARCHAR(255) NOT NULL, tarif DOUBLE PRECISION NOT NULL, INDEX IDX_351268BB9B0F88B1 (activite_id), PRIMARY KEY(id_abonnement)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE activité (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(50) NOT NULL, description VARCHAR(300) NOT NULL, url VARCHAR(200) NOT NULL, type ENUM(\'PERSONAL_TRAINING\', \'GROUP_ACTIVITY\', \'FITNESS_CONSULTATION\'), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE comment (id INT AUTO_INCREMENT NOT NULL, content VARCHAR(255) NOT NULL, createdAt DATETIME DEFAULT NULL, postId INT DEFAULT NULL, id_User INT DEFAULT NULL, INDEX IDX_9474526CE094D20D (postId), INDEX IDX_9474526CA6816575 (id_User), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE cours (id INT AUTO_INCREMENT NOT NULL, activité_id INT DEFAULT NULL, planning_id INT DEFAULT NULL, entaineur_id INT DEFAULT NULL, salle_id INT DEFAULT NULL, title VARCHAR(255) NOT NULL, description VARCHAR(255) NOT NULL, objectif ENUM(\'PERTE_PROIDS\',\'PRISE_DE_MASSE\',\'ENDURANCE\',\'RELAXATION\'), date_debut DATE NOT NULL, heur_debut TIME NOT NULL, heur_fin TIME NOT NULL, INDEX IDX_FDCA8C9CED02027C (activité_id), INDEX IDX_FDCA8C9C3D865311 (planning_id), INDEX IDX_FDCA8C9C582CD907 (entaineur_id), INDEX IDX_FDCA8C9CDC304035 (salle_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE equipe (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, image_url VARCHAR(255) NOT NULL, niveau VARCHAR(255) NOT NULL, nombre_membres INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE equipe_event (id INT AUTO_INCREMENT NOT NULL, equipe_id INT DEFAULT NULL, event_id INT DEFAULT NULL, INDEX IDX_61A29C2D6D861B89 (equipe_id), INDEX IDX_61A29C2D71F7E88B (event_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE events (id INT AUTO_INCREMENT NOT NULL, salle_id INT DEFAULT NULL, nom VARCHAR(255) NOT NULL, date DATE NOT NULL, heure_debut TIME NOT NULL, heure_fin TIME NOT NULL, description VARCHAR(255) NOT NULL, image_url VARCHAR(500) NOT NULL, type VARCHAR(255) NOT NULL, reward VARCHAR(255) NOT NULL, latitude DOUBLE PRECISION NOT NULL, longitude DOUBLE PRECISION NOT NULL, INDEX IDX_5387574ADC304035 (salle_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE infosportif (id INT AUTO_INCREMENT NOT NULL, poids DOUBLE PRECISION NOT NULL, taille DOUBLE PRECISION NOT NULL, age INT NOT NULL, sexe VARCHAR(300) NOT NULL, objectif VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE likes (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE paiement (id_paiement INT AUTO_INCREMENT NOT NULL, amount DOUBLE PRECISION NOT NULL, status VARCHAR(255) NOT NULL, payment_intent_id VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', currency VARCHAR(255) NOT NULL, PRIMARY KEY(id_paiement)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE planning (id INT AUTO_INCREMENT NOT NULL, entaineur_id INT DEFAULT NULL, date_debut DATE NOT NULL, description VARCHAR(300) NOT NULL, title VARCHAR(255) NOT NULL, date_fin DATE NOT NULL, INDEX IDX_D499BFF6582CD907 (entaineur_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE post (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, content VARCHAR(1000) NOT NULL, image_url VARCHAR(255) DEFAULT NULL, created_at DATETIME DEFAULT NULL, id_User INT DEFAULT NULL, INDEX IDX_5A8A6C8DA6816575 (id_User), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE reactions (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(10) NOT NULL, id_User INT DEFAULT NULL, postId INT DEFAULT NULL, INDEX IDX_38737FB3A6816575 (id_User), INDEX IDX_38737FB3E094D20D (postId), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE reclamation (id INT AUTO_INCREMENT NOT NULL, sujet VARCHAR(255) NOT NULL, description VARCHAR(255) NOT NULL, statut VARCHAR(255) NOT NULL, date_creation DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE reponse (id INT AUTO_INCREMENT NOT NULL, message VARCHAR(255) NOT NULL, date_reponse DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE salle (id INT AUTO_INCREMENT NOT NULL, responsable_id INT DEFAULT NULL, nom VARCHAR(200) NOT NULL, adresse VARCHAR(200) NOT NULL, details VARCHAR(500) NOT NULL, num_tel VARCHAR(50) NOT NULL, email VARCHAR(100) NOT NULL, url_photo VARCHAR(500) NOT NULL, UNIQUE INDEX UNIQ_4E977E5C53C59D72 (responsable_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(50) NOT NULL, prenom VARCHAR(50) NOT NULL, email VARCHAR(100) NOT NULL, password VARCHAR(255) NOT NULL, image_url VARCHAR(100) DEFAULT NULL, date_naissance DATE NOT NULL, role VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE abonnement ADD CONSTRAINT FK_351268BB9B0F88B1 FOREIGN KEY (activite_id) REFERENCES activité (id)');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526CE094D20D FOREIGN KEY (postId) REFERENCES post (id)');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526CA6816575 FOREIGN KEY (id_User) REFERENCES user (id)');
        $this->addSql('ALTER TABLE cours ADD CONSTRAINT FK_FDCA8C9CED02027C FOREIGN KEY (activité_id) REFERENCES activité (id)');
        $this->addSql('ALTER TABLE cours ADD CONSTRAINT FK_FDCA8C9C3D865311 FOREIGN KEY (planning_id) REFERENCES planning (id)');
        $this->addSql('ALTER TABLE cours ADD CONSTRAINT FK_FDCA8C9C582CD907 FOREIGN KEY (entaineur_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE cours ADD CONSTRAINT FK_FDCA8C9CDC304035 FOREIGN KEY (salle_id) REFERENCES salle (id)');
        $this->addSql('ALTER TABLE equipe_event ADD CONSTRAINT FK_61A29C2D6D861B89 FOREIGN KEY (equipe_id) REFERENCES equipe (id)');
        $this->addSql('ALTER TABLE equipe_event ADD CONSTRAINT FK_61A29C2D71F7E88B FOREIGN KEY (event_id) REFERENCES events (id)');
        $this->addSql('ALTER TABLE events ADD CONSTRAINT FK_5387574ADC304035 FOREIGN KEY (salle_id) REFERENCES salle (id)');
        $this->addSql('ALTER TABLE planning ADD CONSTRAINT FK_D499BFF6582CD907 FOREIGN KEY (entaineur_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE post ADD CONSTRAINT FK_5A8A6C8DA6816575 FOREIGN KEY (id_User) REFERENCES user (id)');
        $this->addSql('ALTER TABLE reactions ADD CONSTRAINT FK_38737FB3A6816575 FOREIGN KEY (id_User) REFERENCES user (id)');
        $this->addSql('ALTER TABLE reactions ADD CONSTRAINT FK_38737FB3E094D20D FOREIGN KEY (postId) REFERENCES post (id)');
        $this->addSql('ALTER TABLE salle ADD CONSTRAINT FK_4E977E5C53C59D72 FOREIGN KEY (responsable_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE abonnement DROP FOREIGN KEY FK_351268BB9B0F88B1');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526CE094D20D');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526CA6816575');
        $this->addSql('ALTER TABLE cours DROP FOREIGN KEY FK_FDCA8C9CED02027C');
        $this->addSql('ALTER TABLE cours DROP FOREIGN KEY FK_FDCA8C9C3D865311');
        $this->addSql('ALTER TABLE cours DROP FOREIGN KEY FK_FDCA8C9C582CD907');
        $this->addSql('ALTER TABLE cours DROP FOREIGN KEY FK_FDCA8C9CDC304035');
        $this->addSql('ALTER TABLE equipe_event DROP FOREIGN KEY FK_61A29C2D6D861B89');
        $this->addSql('ALTER TABLE equipe_event DROP FOREIGN KEY FK_61A29C2D71F7E88B');
        $this->addSql('ALTER TABLE events DROP FOREIGN KEY FK_5387574ADC304035');
        $this->addSql('ALTER TABLE planning DROP FOREIGN KEY FK_D499BFF6582CD907');
        $this->addSql('ALTER TABLE post DROP FOREIGN KEY FK_5A8A6C8DA6816575');
        $this->addSql('ALTER TABLE reactions DROP FOREIGN KEY FK_38737FB3A6816575');
        $this->addSql('ALTER TABLE reactions DROP FOREIGN KEY FK_38737FB3E094D20D');
        $this->addSql('ALTER TABLE salle DROP FOREIGN KEY FK_4E977E5C53C59D72');
        $this->addSql('DROP TABLE abonnement');
        $this->addSql('DROP TABLE activité');
        $this->addSql('DROP TABLE comment');
        $this->addSql('DROP TABLE cours');
        $this->addSql('DROP TABLE equipe');
        $this->addSql('DROP TABLE equipe_event');
        $this->addSql('DROP TABLE events');
        $this->addSql('DROP TABLE infosportif');
        $this->addSql('DROP TABLE likes');
        $this->addSql('DROP TABLE paiement');
        $this->addSql('DROP TABLE planning');
        $this->addSql('DROP TABLE post');
        $this->addSql('DROP TABLE reactions');
        $this->addSql('DROP TABLE reclamation');
        $this->addSql('DROP TABLE reponse');
        $this->addSql('DROP TABLE salle');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
