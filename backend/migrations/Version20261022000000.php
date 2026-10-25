<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261022000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Schema iniziale PostPickr';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE users (
            id INT AUTO_INCREMENT NOT NULL,
            email VARCHAR(180) NOT NULL,
            password VARCHAR(255) NOT NULL,
            timezone VARCHAR(64) NOT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE api_tokens (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            token_hash VARCHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            UNIQUE INDEX UNIQ_api_tokens_hash (token_hash),
            INDEX IDX_api_tokens_user (user_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_api_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE social_accounts (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            platform VARCHAR(16) NOT NULL,
            external_account_id VARCHAR(255) NOT NULL,
            access_token LONGTEXT NOT NULL,
            token_expires_at DATETIME DEFAULT NULL,
            status VARCHAR(16) NOT NULL,
            INDEX IDX_social_accounts_user (user_id),
            UNIQUE INDEX uniq_user_platform (user_id, platform),
            PRIMARY KEY(id),
            CONSTRAINT FK_social_accounts_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE media_assets (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            storage_key VARCHAR(255) NOT NULL,
            type VARCHAR(64) NOT NULL,
            size BIGINT NOT NULL,
            public_url VARCHAR(1024) NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX IDX_media_assets_user (user_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_media_assets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE generation_requests (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            params JSON NOT NULL,
            model VARCHAR(100) NOT NULL,
            status VARCHAR(16) NOT NULL,
            result_text LONGTEXT DEFAULT NULL,
            error VARCHAR(500) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            INDEX IDX_generation_requests_user (user_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_generation_requests_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE post_targets (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            social_account_id INT NOT NULL,
            generation_request_id INT DEFAULT NULL,
            media_asset_id INT DEFAULT NULL,
            text LONGTEXT NOT NULL,
            status VARCHAR(16) NOT NULL,
            scheduled_at DATETIME DEFAULT NULL,
            external_id VARCHAR(255) DEFAULT NULL,
            container_id VARCHAR(255) DEFAULT NULL,
            last_error VARCHAR(500) DEFAULT NULL,
            attempts SMALLINT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX IDX_post_targets_user (user_id),
            INDEX IDX_post_targets_account (social_account_id),
            INDEX IDX_post_targets_generation (generation_request_id),
            INDEX IDX_post_targets_media (media_asset_id),
            INDEX idx_status_scheduled (status, scheduled_at),
            INDEX idx_user_status_scheduled (user_id, status, scheduled_at),
            PRIMARY KEY(id),
            CONSTRAINT FK_post_targets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
            CONSTRAINT FK_post_targets_account FOREIGN KEY (social_account_id) REFERENCES social_accounts (id) ON DELETE CASCADE,
            CONSTRAINT FK_post_targets_generation FOREIGN KEY (generation_request_id) REFERENCES generation_requests (id) ON DELETE SET NULL,
            CONSTRAINT FK_post_targets_media FOREIGN KEY (media_asset_id) REFERENCES media_assets (id) ON DELETE SET NULL
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE post_targets');
        $this->addSql('DROP TABLE generation_requests');
        $this->addSql('DROP TABLE media_assets');
        $this->addSql('DROP TABLE social_accounts');
        $this->addSql('DROP TABLE api_tokens');
        $this->addSql('DROP TABLE users');
    }
}
