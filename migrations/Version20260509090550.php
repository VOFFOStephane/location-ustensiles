<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260509090550 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reservation_date_change_request ADD CONSTRAINT FK_5FA5A484B83297E7 FOREIGN KEY (reservation_id) REFERENCES reservation (id)');
        $this->addSql('ALTER TABLE reservation_date_change_request ADD CONSTRAINT FK_5FA5A484A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE user ADD is_verified TINYINT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reservation_date_change_request DROP FOREIGN KEY FK_5FA5A484B83297E7');
        $this->addSql('ALTER TABLE reservation_date_change_request DROP FOREIGN KEY FK_5FA5A484A76ED395');
        $this->addSql('ALTER TABLE user DROP is_verified');
    }
}
