<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261025215754 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE restaurant ADD uuid CHAR(36) NOT NULL, ADD horaire_midi JSON NOT NULL, ADD horaire_soir JSON NOT NULL, ADD capacite_max SMALLINT UNSIGNED NOT NULL, ADD date_creation DATETIME NOT NULL, ADD date_modification DATETIME DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_EB95123FD17F50A6 ON restaurant (uuid)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_EB95123FD17F50A6 ON restaurant');
        $this->addSql('ALTER TABLE restaurant DROP uuid, DROP horaire_midi, DROP horaire_soir, DROP capacite_max, DROP date_creation, DROP date_modification');
    }
}
