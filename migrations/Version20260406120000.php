<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260406120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add saint is_group and sex (demographics for PT-BR titles and LLM backfill).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE saint ADD is_group BOOLEAN DEFAULT false NOT NULL');
        $this->addSql("ALTER TABLE saint ADD sex VARCHAR(16) DEFAULT 'unknown' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE saint DROP sex');
        $this->addSql('ALTER TABLE saint DROP is_group');
    }
}
