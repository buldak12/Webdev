<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Make order_item.variant_id SET NULL on delete so that editing/deleting a
 * ProductVariant no longer throws a foreign-key constraint violation.
 */
final class Version20260714FixVariantFk extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'order_item.variant_id: drop old FK, add new one with ON DELETE SET NULL';
    }

    public function up(Schema $schema): void
    {
        // 1. Drop the existing FK (name may vary — look it up first via IS)
        $this->addSql("
            SET @fk = (
                SELECT CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME   = 'order_item'
                  AND COLUMN_NAME  = 'variant_id'
                  AND REFERENCED_TABLE_NAME = 'product_variant'
                LIMIT 1
            )
        ");

        $this->addSql("
            SET @sql = IF(
                @fk IS NOT NULL,
                CONCAT('ALTER TABLE order_item DROP FOREIGN KEY `', @fk, '`'),
                'SELECT 1'
            )
        ");
        $this->addSql('PREPARE stmt FROM @sql');
        $this->addSql('EXECUTE stmt');
        $this->addSql('DEALLOCATE PREPARE stmt');

        // 2. Make sure the column is nullable
        $this->addSql('ALTER TABLE order_item MODIFY variant_id INT DEFAULT NULL');

        // 3. Add the new FK with ON DELETE SET NULL
        $this->addSql('
            ALTER TABLE order_item
            ADD CONSTRAINT fk_order_item_variant
            FOREIGN KEY (variant_id) REFERENCES product_variant(id) ON DELETE SET NULL
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE order_item DROP FOREIGN KEY fk_order_item_variant');
        $this->addSql('
            ALTER TABLE order_item
            ADD CONSTRAINT fk_order_item_variant_orig
            FOREIGN KEY (variant_id) REFERENCES product_variant(id)
        ');
    }
}
