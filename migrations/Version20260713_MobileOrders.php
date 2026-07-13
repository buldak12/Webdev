<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260713_MobileOrders extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create mobile_orders table for mobile app orders';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE mobile_orders (
            id INT AUTO_INCREMENT NOT NULL,
            customer_email VARCHAR(255) NOT NULL,
            customer_name VARCHAR(255) DEFAULT NULL,
            customer_phone VARCHAR(50) DEFAULT NULL,
            items_json LONGTEXT NOT NULL,
            status VARCHAR(20) NOT NULL,
            fulfillment_type VARCHAR(20) DEFAULT NULL,
            payment_method VARCHAR(50) DEFAULT NULL,
            delivery_address VARCHAR(255) DEFAULT NULL,
            total NUMERIC(10, 2) NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_customer_email (customer_email),
            INDEX idx_created_at (created_at),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE mobile_orders');
    }
}
