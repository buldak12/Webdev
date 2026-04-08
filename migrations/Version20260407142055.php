<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260407142055 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE address (id INT AUTO_INCREMENT NOT NULL, full_name VARCHAR(100) NOT NULL, phone VARCHAR(20) NOT NULL, street_address VARCHAR(255) NOT NULL, barangay VARCHAR(100) DEFAULT NULL, city VARCHAR(100) NOT NULL, province VARCHAR(100) NOT NULL, region VARCHAR(20) NOT NULL, postal_code VARCHAR(10) NOT NULL, country VARCHAR(2) NOT NULL, notes LONGTEXT DEFAULT NULL, is_default_shipping TINYINT NOT NULL, is_default_billing TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_D4E6F81A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE age_verification (id INT AUTO_INCREMENT NOT NULL, id_type VARCHAR(30) NOT NULL, id_number VARCHAR(50) DEFAULT NULL, id_front_image VARCHAR(255) NOT NULL, id_back_image VARCHAR(255) DEFAULT NULL, selfie_image VARCHAR(255) DEFAULT NULL, date_of_birth DATE NOT NULL, status VARCHAR(20) NOT NULL, rejection_reason LONGTEXT DEFAULT NULL, reviewed_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, reviewed_by_id INT DEFAULT NULL, INDEX IDX_A50D1DDEA76ED395 (user_id), INDEX IDX_A50D1DDEFC6B21F1 (reviewed_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE cart (id INT AUTO_INCREMENT NOT NULL, session_id VARCHAR(64) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, expires_at DATETIME DEFAULT NULL, user_id INT DEFAULT NULL, promo_code_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_BA388B7613FECDF (session_id), UNIQUE INDEX UNIQ_BA388B7A76ED395 (user_id), INDEX IDX_BA388B72FAE4625 (promo_code_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE cart_item (id INT AUTO_INCREMENT NOT NULL, quantity INT NOT NULL, unit_price NUMERIC(10, 2) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, cart_id INT NOT NULL, variant_id INT NOT NULL, INDEX IDX_F0FE25271AD5CDBF (cart_id), INDEX IDX_F0FE25273B69A9AF (variant_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, slug VARCHAR(120) NOT NULL, description LONGTEXT DEFAULT NULL, image VARCHAR(255) DEFAULT NULL, sort_order INT NOT NULL, is_active TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, parent_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_64C19C1989D9B62 (slug), INDEX IDX_64C19C1727ACA70 (parent_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE loyalty_transaction (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(20) NOT NULL, points INT NOT NULL, balance_after INT NOT NULL, description VARCHAR(255) DEFAULT NULL, reference_type VARCHAR(50) DEFAULT NULL, reference_id INT DEFAULT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_4CE4AEC1A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `order` (id INT AUTO_INCREMENT NOT NULL, order_number VARCHAR(20) NOT NULL, status VARCHAR(30) NOT NULL, subtotal NUMERIC(10, 2) NOT NULL, discount NUMERIC(10, 2) NOT NULL, tax NUMERIC(10, 2) NOT NULL, shipping_cost NUMERIC(10, 2) NOT NULL, total NUMERIC(10, 2) NOT NULL, loyalty_points_earned INT NOT NULL, loyalty_points_used INT NOT NULL, notes LONGTEXT DEFAULT NULL, internal_notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, paid_at DATETIME DEFAULT NULL, shipped_at DATETIME DEFAULT NULL, delivered_at DATETIME DEFAULT NULL, user_id INT NOT NULL, shipping_address_id INT NOT NULL, billing_address_id INT DEFAULT NULL, promo_code_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_F5299398551F0F81 (order_number), INDEX IDX_F5299398A76ED395 (user_id), INDEX IDX_F52993984D4CFF2B (shipping_address_id), INDEX IDX_F529939879D0C0E4 (billing_address_id), INDEX IDX_F52993982FAE4625 (promo_code_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE order_item (id INT AUTO_INCREMENT NOT NULL, quantity INT NOT NULL, unit_price NUMERIC(10, 2) NOT NULL, total NUMERIC(10, 2) NOT NULL, product_name VARCHAR(255) NOT NULL, variant_sku VARCHAR(100) DEFAULT NULL, variant_attributes VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, order_id INT NOT NULL, variant_id INT DEFAULT NULL, INDEX IDX_52EA1F098D9F6D38 (order_id), INDEX IDX_52EA1F093B69A9AF (variant_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE payment (id INT AUTO_INCREMENT NOT NULL, gateway VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, amount NUMERIC(10, 2) NOT NULL, currency VARCHAR(3) NOT NULL, transaction_id VARCHAR(100) DEFAULT NULL, external_reference VARCHAR(100) DEFAULT NULL, gateway_response JSON DEFAULT NULL, failure_reason LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, completed_at DATETIME DEFAULT NULL, order_id INT NOT NULL, INDEX IDX_6D28840D8D9F6D38 (order_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE product (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(280) NOT NULL, description LONGTEXT DEFAULT NULL, short_description LONGTEXT DEFAULT NULL, base_price NUMERIC(10, 2) NOT NULL, sku VARCHAR(50) NOT NULL, main_image VARCHAR(255) DEFAULT NULL, images JSON DEFAULT NULL, brand VARCHAR(50) DEFAULT NULL, is_active TINYINT NOT NULL, requires_age_verification TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, category_id INT NOT NULL, UNIQUE INDEX UNIQ_D34A04AD989D9B62 (slug), UNIQUE INDEX UNIQ_D34A04ADF9038C4 (sku), INDEX IDX_D34A04AD12469DE2 (category_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE product_variant (id INT AUTO_INCREMENT NOT NULL, sku VARCHAR(50) NOT NULL, flavor VARCHAR(100) DEFAULT NULL, nicotine_strength VARCHAR(10) DEFAULT NULL, size VARCHAR(20) DEFAULT NULL, price_modifier NUMERIC(10, 2) NOT NULL, stock INT NOT NULL, low_stock_threshold INT NOT NULL, reserved_stock INT NOT NULL, is_active TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, product_id INT NOT NULL, UNIQUE INDEX UNIQ_209AA41DF9038C4 (sku), INDEX IDX_209AA41D4584665A (product_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE promo_code (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(50) NOT NULL, description VARCHAR(255) DEFAULT NULL, type VARCHAR(20) NOT NULL, value NUMERIC(10, 2) NOT NULL, minimum_order_amount NUMERIC(10, 2) DEFAULT NULL, maximum_discount NUMERIC(10, 2) DEFAULT NULL, usage_limit INT DEFAULT NULL, usage_count INT NOT NULL, usage_limit_per_user INT DEFAULT NULL, starts_at DATETIME DEFAULT NULL, expires_at DATETIME DEFAULT NULL, is_active TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_3D8C939E77153098 (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE shipment (id INT AUTO_INCREMENT NOT NULL, courier VARCHAR(20) DEFAULT NULL, tracking_number VARCHAR(100) DEFAULT NULL, status VARCHAR(20) NOT NULL, shipping_cost NUMERIC(10, 2) NOT NULL, weight NUMERIC(8, 2) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, tracking_history JSON DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, packed_at DATETIME DEFAULT NULL, shipped_at DATETIME DEFAULT NULL, delivered_at DATETIME DEFAULT NULL, estimated_delivery DATETIME DEFAULT NULL, order_id INT NOT NULL, packed_by_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_2CB20DC8D9F6D38 (order_id), INDEX IDX_2CB20DC5CCC057B (packed_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, phone VARCHAR(20) DEFAULT NULL, birth_date DATE DEFAULT NULL, age_verification_status VARCHAR(20) NOT NULL, age_verified_at DATETIME DEFAULT NULL, loyalty_points INT NOT NULL, is_active TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE address ADD CONSTRAINT FK_D4E6F81A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE age_verification ADD CONSTRAINT FK_A50D1DDEA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE age_verification ADD CONSTRAINT FK_A50D1DDEFC6B21F1 FOREIGN KEY (reviewed_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE cart ADD CONSTRAINT FK_BA388B7A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE cart ADD CONSTRAINT FK_BA388B72FAE4625 FOREIGN KEY (promo_code_id) REFERENCES promo_code (id)');
        $this->addSql('ALTER TABLE cart_item ADD CONSTRAINT FK_F0FE25271AD5CDBF FOREIGN KEY (cart_id) REFERENCES cart (id)');
        $this->addSql('ALTER TABLE cart_item ADD CONSTRAINT FK_F0FE25273B69A9AF FOREIGN KEY (variant_id) REFERENCES product_variant (id)');
        $this->addSql('ALTER TABLE category ADD CONSTRAINT FK_64C19C1727ACA70 FOREIGN KEY (parent_id) REFERENCES category (id)');
        $this->addSql('ALTER TABLE loyalty_transaction ADD CONSTRAINT FK_4CE4AEC1A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE `order` ADD CONSTRAINT FK_F5299398A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE `order` ADD CONSTRAINT FK_F52993984D4CFF2B FOREIGN KEY (shipping_address_id) REFERENCES address (id)');
        $this->addSql('ALTER TABLE `order` ADD CONSTRAINT FK_F529939879D0C0E4 FOREIGN KEY (billing_address_id) REFERENCES address (id)');
        $this->addSql('ALTER TABLE `order` ADD CONSTRAINT FK_F52993982FAE4625 FOREIGN KEY (promo_code_id) REFERENCES promo_code (id)');
        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT FK_52EA1F098D9F6D38 FOREIGN KEY (order_id) REFERENCES `order` (id)');
        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT FK_52EA1F093B69A9AF FOREIGN KEY (variant_id) REFERENCES product_variant (id)');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D8D9F6D38 FOREIGN KEY (order_id) REFERENCES `order` (id)');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_D34A04AD12469DE2 FOREIGN KEY (category_id) REFERENCES category (id)');
        $this->addSql('ALTER TABLE product_variant ADD CONSTRAINT FK_209AA41D4584665A FOREIGN KEY (product_id) REFERENCES product (id)');
        $this->addSql('ALTER TABLE shipment ADD CONSTRAINT FK_2CB20DC8D9F6D38 FOREIGN KEY (order_id) REFERENCES `order` (id)');
        $this->addSql('ALTER TABLE shipment ADD CONSTRAINT FK_2CB20DC5CCC057B FOREIGN KEY (packed_by_id) REFERENCES `user` (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE address DROP FOREIGN KEY FK_D4E6F81A76ED395');
        $this->addSql('ALTER TABLE age_verification DROP FOREIGN KEY FK_A50D1DDEA76ED395');
        $this->addSql('ALTER TABLE age_verification DROP FOREIGN KEY FK_A50D1DDEFC6B21F1');
        $this->addSql('ALTER TABLE cart DROP FOREIGN KEY FK_BA388B7A76ED395');
        $this->addSql('ALTER TABLE cart DROP FOREIGN KEY FK_BA388B72FAE4625');
        $this->addSql('ALTER TABLE cart_item DROP FOREIGN KEY FK_F0FE25271AD5CDBF');
        $this->addSql('ALTER TABLE cart_item DROP FOREIGN KEY FK_F0FE25273B69A9AF');
        $this->addSql('ALTER TABLE category DROP FOREIGN KEY FK_64C19C1727ACA70');
        $this->addSql('ALTER TABLE loyalty_transaction DROP FOREIGN KEY FK_4CE4AEC1A76ED395');
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F5299398A76ED395');
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F52993984D4CFF2B');
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F529939879D0C0E4');
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F52993982FAE4625');
        $this->addSql('ALTER TABLE order_item DROP FOREIGN KEY FK_52EA1F098D9F6D38');
        $this->addSql('ALTER TABLE order_item DROP FOREIGN KEY FK_52EA1F093B69A9AF');
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840D8D9F6D38');
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_D34A04AD12469DE2');
        $this->addSql('ALTER TABLE product_variant DROP FOREIGN KEY FK_209AA41D4584665A');
        $this->addSql('ALTER TABLE shipment DROP FOREIGN KEY FK_2CB20DC8D9F6D38');
        $this->addSql('ALTER TABLE shipment DROP FOREIGN KEY FK_2CB20DC5CCC057B');
        $this->addSql('DROP TABLE address');
        $this->addSql('DROP TABLE age_verification');
        $this->addSql('DROP TABLE cart');
        $this->addSql('DROP TABLE cart_item');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE loyalty_transaction');
        $this->addSql('DROP TABLE `order`');
        $this->addSql('DROP TABLE order_item');
        $this->addSql('DROP TABLE payment');
        $this->addSql('DROP TABLE product');
        $this->addSql('DROP TABLE product_variant');
        $this->addSql('DROP TABLE promo_code');
        $this->addSql('DROP TABLE shipment');
        $this->addSql('DROP TABLE `user`');
    }
}
