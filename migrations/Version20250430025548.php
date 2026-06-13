<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250430025548 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE abonnementdata DROP FOREIGN KEY abonnementdata_ibfk_1
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE abonnementdata DROP FOREIGN KEY abonnementdata_ibfk_2
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE commande_produit DROP FOREIGN KEY FK_DF1E9E87F347EFB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE commande_produit DROP FOREIGN KEY FK_DF1E9E8782EA2E54
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE ligne_commande DROP FOREIGN KEY ligne_commande_ibfk_1
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE ligne_commande DROP FOREIGN KEY ligne_commande_ibfk_2
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE abonnementdata
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE commande
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE commande_produit
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE ligne_commande
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE produit
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE abonnement DROP FOREIGN KEY AZERTYUI
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE abonnement CHANGE id_Salle id_salle INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX azertyui ON abonnement
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_351268BBA0123F6C ON abonnement (id_salle)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE abonnement ADD CONSTRAINT AZERTYUI FOREIGN KEY (id_Salle) REFERENCES salle (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE equipe CHANGE image_url image_url VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE equipe_event CHANGE equipe_id equipe_id INT NOT NULL, CHANGE event_id event_id INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE events CHANGE heure_debut heure_debut DATETIME NOT NULL, CHANGE heure_fin heure_fin DATETIME NOT NULL, CHANGE description description LONGTEXT NOT NULL, CHANGE image_url image_url VARCHAR(255) DEFAULT NULL, CHANGE latitude latitude DOUBLE PRECISION DEFAULT NULL, CHANGE longitude longitude DOUBLE PRECISION DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE infosportif CHANGE sportif_id sportif_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE infosportif ADD CONSTRAINT FK_E662FC58FFB7083B FOREIGN KEY (sportif_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_E662FC58FFB7083B ON infosportif (sportif_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE paiement DROP FOREIGN KEY paiement_ibfk_1
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE paiement DROP FOREIGN KEY paiement_ibfk_1
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE paiement DROP FOREIGN KEY paiement_ibfk_2
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE paiement ADD qr_token VARCHAR(32) DEFAULT NULL, DROP qrToken, CHANGE amount amount DOUBLE PRECISION NOT NULL, CHANGE status status VARCHAR(255) NOT NULL, CHANGE created_at created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE currency currency VARCHAR(255) NOT NULL, CHANGE date_debut date_debut DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE date_fin date_fin DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE paiement ADD CONSTRAINT FK_B1DC7A1E6B3CA4B FOREIGN KEY (id_user) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX id_user ON paiement
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_B1DC7A1E6B3CA4B ON paiement (id_user)
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX id_abonnement ON paiement
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_B1DC7A1E9098E86C ON paiement (id_abonnement)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE paiement ADD CONSTRAINT paiement_ibfk_1 FOREIGN KEY (id_user) REFERENCES user (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE paiement ADD CONSTRAINT paiement_ibfk_2 FOREIGN KEY (id_abonnement) REFERENCES abonnement (id_Abonnement)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE post CHANGE content content LONGTEXT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reclamation DROP FOREIGN KEY fghjkl
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reclamation DROP FOREIGN KEY fghjkl
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reclamation CHANGE description description LONGTEXT NOT NULL, CHANGE statut statut VARCHAR(111) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reclamation ADD CONSTRAINT FK_CE606404A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX fghjkl ON reclamation
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_CE606404A76ED395 ON reclamation (user_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reclamation ADD CONSTRAINT fghjkl FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE CASCADE ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reponse DROP FOREIGN KEY SDFGHJ
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reponse DROP FOREIGN KEY DFGHJKL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reponse CHANGE message message LONGTEXT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX sdfghj ON reponse
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_5FB6DEC7642B8210 ON reponse (admin_id)
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX dfghjkl ON reponse
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_5FB6DEC72D6BA2D9 ON reponse (reclamation_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reponse ADD CONSTRAINT SDFGHJ FOREIGN KEY (admin_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reponse ADD CONSTRAINT DFGHJKL FOREIGN KEY (reclamation_id) REFERENCES reclamation (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE salle DROP INDEX fk1, ADD UNIQUE INDEX UNIQ_4E977E5C53C59D72 (responsable_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON user (email)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE abonnementdata (DateDebut DATE NOT NULL, DateFin DATE NOT NULL, id_Abonnement INT NOT NULL, id_Sportif INT NOT NULL, INDEX abonnementdata_ibfk_2 (id_Sportif), PRIMARY KEY(id_Abonnement)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = '' 
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE commande (id_c INT AUTO_INCREMENT NOT NULL, total_c DOUBLE PRECISION NOT NULL, date_c DATETIME NOT NULL, statut_c VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, user_id INT DEFAULT NULL, PRIMARY KEY(id_c)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = '' 
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE commande_produit (commande_id INT NOT NULL, produit_id INT NOT NULL, INDEX produit_id (produit_id), INDEX IDX_DF1E9E8782EA2E54 (commande_id), PRIMARY KEY(commande_id, produit_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = '' 
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE ligne_commande (id INT AUTO_INCREMENT NOT NULL, commande_id INT NOT NULL, produit_id INT NOT NULL, quantite_lc INT NOT NULL, prix_lc NUMERIC(10, 2) NOT NULL, INDEX commande_id (commande_id), INDEX produit_id (produit_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = '' 
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE produit (id_p INT AUTO_INCREMENT NOT NULL, nom_p VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, prix_p DOUBLE PRECISION NOT NULL, stock_p INT NOT NULL, categorie_p VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, image_path VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, updated_at DATETIME DEFAULT NULL, PRIMARY KEY(id_p)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = '' 
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE abonnementdata ADD CONSTRAINT abonnementdata_ibfk_1 FOREIGN KEY (id_Abonnement) REFERENCES abonnement (id_Abonnement) ON UPDATE CASCADE ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE abonnementdata ADD CONSTRAINT abonnementdata_ibfk_2 FOREIGN KEY (id_Sportif) REFERENCES user (id) ON UPDATE CASCADE ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE commande_produit ADD CONSTRAINT FK_DF1E9E87F347EFB FOREIGN KEY (produit_id) REFERENCES produit (id_p)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE commande_produit ADD CONSTRAINT FK_DF1E9E8782EA2E54 FOREIGN KEY (commande_id) REFERENCES commande (id_c)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE ligne_commande ADD CONSTRAINT ligne_commande_ibfk_1 FOREIGN KEY (commande_id) REFERENCES commande (id_c)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE ligne_commande ADD CONSTRAINT ligne_commande_ibfk_2 FOREIGN KEY (produit_id) REFERENCES produit (id_p)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE abonnement DROP FOREIGN KEY FK_351268BBA0123F6C
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE abonnement CHANGE id_salle id_Salle INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_351268bba0123f6c ON abonnement
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX AZERTYUI ON abonnement (id_Salle)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE abonnement ADD CONSTRAINT FK_351268BBA0123F6C FOREIGN KEY (id_salle) REFERENCES salle (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE equipe CHANGE image_url image_url VARCHAR(255) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE equipe_event CHANGE equipe_id equipe_id INT DEFAULT NULL, CHANGE event_id event_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE events CHANGE description description VARCHAR(255) NOT NULL, CHANGE heure_debut heure_debut TIME NOT NULL, CHANGE heure_fin heure_fin TIME NOT NULL, CHANGE image_url image_url VARCHAR(500) NOT NULL, CHANGE latitude latitude DOUBLE PRECISION NOT NULL, CHANGE longitude longitude DOUBLE PRECISION NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE infosportif DROP FOREIGN KEY FK_E662FC58FFB7083B
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_E662FC58FFB7083B ON infosportif
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE infosportif CHANGE sportif_id sportif_id INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE paiement DROP FOREIGN KEY FK_B1DC7A1E6B3CA4B
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE paiement DROP FOREIGN KEY FK_B1DC7A1E6B3CA4B
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE paiement DROP FOREIGN KEY FK_B1DC7A1E9098E86C
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE paiement ADD qrToken VARCHAR(255) NOT NULL, DROP qr_token, CHANGE amount amount DOUBLE PRECISION DEFAULT NULL, CHANGE status status VARCHAR(255) DEFAULT NULL, CHANGE created_at created_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE updated_at updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE currency currency VARCHAR(255) DEFAULT NULL, CHANGE date_debut date_debut DATETIME DEFAULT NULL, CHANGE date_fin date_fin DATETIME DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE paiement ADD CONSTRAINT paiement_ibfk_1 FOREIGN KEY (id_user) REFERENCES user (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_b1dc7a1e6b3ca4b ON paiement
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX id_user ON paiement (id_user)
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_b1dc7a1e9098e86c ON paiement
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX id_abonnement ON paiement (id_abonnement)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE paiement ADD CONSTRAINT FK_B1DC7A1E6B3CA4B FOREIGN KEY (id_user) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE paiement ADD CONSTRAINT FK_B1DC7A1E9098E86C FOREIGN KEY (id_abonnement) REFERENCES abonnement (id_Abonnement)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE post CHANGE content content VARCHAR(1000) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reclamation DROP FOREIGN KEY FK_CE606404A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reclamation DROP FOREIGN KEY FK_CE606404A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reclamation CHANGE description description VARCHAR(255) NOT NULL, CHANGE statut statut VARCHAR(255) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reclamation ADD CONSTRAINT fghjkl FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE CASCADE ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_ce606404a76ed395 ON reclamation
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX fghjkl ON reclamation (user_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reclamation ADD CONSTRAINT FK_CE606404A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reponse DROP FOREIGN KEY FK_5FB6DEC7642B8210
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reponse DROP FOREIGN KEY FK_5FB6DEC72D6BA2D9
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reponse CHANGE message message VARCHAR(255) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_5fb6dec72d6ba2d9 ON reponse
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX DFGHJKL ON reponse (reclamation_id)
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_5fb6dec7642b8210 ON reponse
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX SDFGHJ ON reponse (admin_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reponse ADD CONSTRAINT FK_5FB6DEC7642B8210 FOREIGN KEY (admin_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reponse ADD CONSTRAINT FK_5FB6DEC72D6BA2D9 FOREIGN KEY (reclamation_id) REFERENCES reclamation (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE salle DROP INDEX UNIQ_4E977E5C53C59D72, ADD INDEX fk1 (responsable_id)
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX UNIQ_8D93D649E7927C74 ON user
        SQL);
    }
}
