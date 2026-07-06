<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260416010100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create activity_log table for admin panel action history';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE activity_log (id INT AUTO_INCREMENT NOT NULL, actor_id INT DEFAULT NULL, route_name VARCHAR(120) DEFAULT NULL, method VARCHAR(10) NOT NULL, action VARCHAR(255) NOT NULL, ip_address VARCHAR(45) DEFAULT NULL, user_agent VARCHAR(255) DEFAULT NULL, context JSON DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_B30AF4B4F675F31B (actor_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE activity_log ADD CONSTRAINT FK_B30AF4B4F675F31B FOREIGN KEY (actor_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE activity_log');
    }
}
