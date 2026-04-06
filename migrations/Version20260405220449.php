<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260405220449 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE saint DROP file');
        $this->addSql('ALTER TABLE saint DROP saint_phrase');
        $this->addSql('ALTER TABLE saint DROP abstract');
        $this->addSql('ALTER TABLE saint DROP biography');
        $this->addSql('ALTER TABLE saint_translation ADD biography TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE saint_translation ADD abstract TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE saint ADD file VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE saint ADD saint_phrase TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE saint ADD abstract TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE saint ADD biography TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE saint_translation DROP biography');
        $this->addSql('ALTER TABLE saint_translation DROP abstract');
    }
}
