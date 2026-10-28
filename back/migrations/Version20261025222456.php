<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261025222456 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE categorie (id INT AUTO_INCREMENT NOT NULL, uuid CHAR(36) NOT NULL, titre VARCHAR(100) NOT NULL, date_creation DATETIME NOT NULL, date_modification DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_497DD634D17F50A6 (uuid), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE menu (id INT AUTO_INCREMENT NOT NULL, uuid CHAR(36) NOT NULL, titre VARCHAR(150) NOT NULL, description LONGTEXT NOT NULL, prix NUMERIC(6, 2) NOT NULL, date_creation DATETIME NOT NULL, date_modification DATETIME DEFAULT NULL, id_restaurant INT NOT NULL, UNIQUE INDEX UNIQ_7D053A93D17F50A6 (uuid), INDEX IDX_7D053A934E1F92E8 (id_restaurant), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE menu_categorie (id INT AUTO_INCREMENT NOT NULL, id_menu INT NOT NULL, id_categorie INT NOT NULL, UNIQUE INDEX UNIQ_MENU_CATEGORIE_PAIR (id_menu, id_categorie), INDEX IDX_503DB100F6252691 (id_menu), INDEX IDX_503DB100C9486A13 (id_categorie), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE photo (id INT AUTO_INCREMENT NOT NULL, uuid CHAR(36) NOT NULL, titre VARCHAR(150) NOT NULL, url_image VARCHAR(255) NOT NULL, date_creation DATETIME NOT NULL, date_modification DATETIME DEFAULT NULL, id_restaurant INT NOT NULL, UNIQUE INDEX UNIQ_14B78418D17F50A6 (uuid), INDEX IDX_14B784184E1F92E8 (id_restaurant), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE plat (id INT AUTO_INCREMENT NOT NULL, uuid CHAR(36) NOT NULL, titre VARCHAR(150) NOT NULL, description LONGTEXT NOT NULL, prix NUMERIC(6, 2) NOT NULL, date_creation DATETIME NOT NULL, date_modification DATETIME DEFAULT NULL, id_categorie INT NOT NULL, UNIQUE INDEX UNIQ_2038A207D17F50A6 (uuid), INDEX IDX_2038A207C9486A13 (id_categorie), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reservation (id INT AUTO_INCREMENT NOT NULL, uuid CHAR(36) NOT NULL, date_reservation DATE NOT NULL, heure_prevue TIME NOT NULL, nb_convives SMALLINT UNSIGNED NOT NULL, allergies LONGTEXT DEFAULT NULL, statut VARCHAR(20) NOT NULL, date_creation DATETIME NOT NULL, date_modification DATETIME DEFAULT NULL, id_restaurant INT NOT NULL, id_utilisateur INT NOT NULL, UNIQUE INDEX UNIQ_42C84955D17F50A6 (uuid), INDEX IDX_42C849554E1F92E8 (id_restaurant), INDEX IDX_42C8495550EAE44 (id_utilisateur), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE utilisateur (id INT AUTO_INCREMENT NOT NULL, uuid CHAR(36) NOT NULL, roles JSON NOT NULL, prenom VARCHAR(80) NOT NULL, nom VARCHAR(80) NOT NULL, email VARCHAR(255) NOT NULL, mot_de_passe_hash VARCHAR(255) NOT NULL, nb_convives_defaut SMALLINT UNSIGNED DEFAULT NULL, allergies LONGTEXT DEFAULT NULL, date_creation DATETIME NOT NULL, date_modification DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_1D1C63B3D17F50A6 (uuid), UNIQUE INDEX UNIQ_1D1C63B3E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE menu ADD CONSTRAINT FK_7D053A934E1F92E8 FOREIGN KEY (id_restaurant) REFERENCES restaurant (id)');
        $this->addSql('ALTER TABLE menu_categorie ADD CONSTRAINT FK_503DB100F6252691 FOREIGN KEY (id_menu) REFERENCES menu (id)');
        $this->addSql('ALTER TABLE menu_categorie ADD CONSTRAINT FK_503DB100C9486A13 FOREIGN KEY (id_categorie) REFERENCES categorie (id)');
        $this->addSql('ALTER TABLE photo ADD CONSTRAINT FK_14B784184E1F92E8 FOREIGN KEY (id_restaurant) REFERENCES restaurant (id)');
        $this->addSql('ALTER TABLE plat ADD CONSTRAINT FK_2038A207C9486A13 FOREIGN KEY (id_categorie) REFERENCES categorie (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C849554E1F92E8 FOREIGN KEY (id_restaurant) REFERENCES restaurant (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C8495550EAE44 FOREIGN KEY (id_utilisateur) REFERENCES utilisateur (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE menu DROP FOREIGN KEY FK_7D053A934E1F92E8');
        $this->addSql('ALTER TABLE menu_categorie DROP FOREIGN KEY FK_503DB100F6252691');
        $this->addSql('ALTER TABLE menu_categorie DROP FOREIGN KEY FK_503DB100C9486A13');
        $this->addSql('ALTER TABLE photo DROP FOREIGN KEY FK_14B784184E1F92E8');
        $this->addSql('ALTER TABLE plat DROP FOREIGN KEY FK_2038A207C9486A13');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C849554E1F92E8');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C8495550EAE44');
        $this->addSql('DROP TABLE categorie');
        $this->addSql('DROP TABLE menu');
        $this->addSql('DROP TABLE menu_categorie');
        $this->addSql('DROP TABLE photo');
        $this->addSql('DROP TABLE plat');
        $this->addSql('DROP TABLE reservation');
        $this->addSql('DROP TABLE utilisateur');
    }
}
