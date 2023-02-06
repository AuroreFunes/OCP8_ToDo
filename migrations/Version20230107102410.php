<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230107102410 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE task ADD actor_id INT DEFAULT NULL, ADD progress INT DEFAULT NULL, ADD dead_line DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB2510DAF24A FOREIGN KEY (actor_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_527EDB2510DAF24A ON task (actor_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY FK_527EDB2510DAF24A');
        $this->addSql('DROP INDEX IDX_527EDB2510DAF24A ON task');
        $this->addSql('ALTER TABLE task DROP actor_id, DROP progress, DROP dead_line');
    }
}
