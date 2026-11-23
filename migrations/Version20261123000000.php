<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261123000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Таблица счётчика посещений page_visits';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE page_visits (path TEXT PRIMARY KEY, total BIGINT NOT NULL)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE page_visits');
    }
}
