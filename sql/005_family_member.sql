-- 005: Familienmitglieder-Rolle + Einladungssystem
-- In phpMyAdmin ausführen

-- family_group_id verknüpft alle Mitglieder einer Familie
-- Für primäre Family-Accounts gilt: family_group_id = id
-- Für Family-Member: family_group_id = id des primären Family-Accounts
ALTER TABLE users ADD COLUMN family_group_id INT NULL AFTER role;
ALTER TABLE users ADD KEY idx_family_group (family_group_id);

-- Bestehende Family-Accounts bekommen ihren eigenen ID als Gruppen-ID
UPDATE users SET family_group_id = id WHERE role = 'family';

-- Einladungstabelle für tokenbasierte Registrierung
CREATE TABLE IF NOT EXISTS invitations (
    id              INT          NOT NULL AUTO_INCREMENT,
    email           VARCHAR(180) NOT NULL,
    role            VARCHAR(20)  NOT NULL DEFAULT 'family',
    family_group_id INT          NULL,
    token           VARCHAR(64)  NOT NULL,
    invited_by      INT          NOT NULL,
    created_at      DATETIME     NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    expires_at      DATETIME     NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    PRIMARY KEY (id),
    UNIQUE KEY UNIQ_TOKEN (token),
    KEY IDX_INVITED_BY (invited_by),
    CONSTRAINT FK_INVITATION_USER
        FOREIGN KEY (invited_by) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
