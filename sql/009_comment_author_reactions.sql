-- 009: Kommentar-Autor und Reaktionen
-- Kommentare bekommen ein author_id-Feld (nullable, bestehende Kommentare bleiben erhalten)
-- Neue Tabelle comment_reactions: ein Reaktionstyp pro User pro Kommentar, toggle-fähig

-- 1. author_id zu comments hinzufügen
ALTER TABLE comments ADD COLUMN author_id INT NULL AFTER recording_id;

ALTER TABLE comments ADD CONSTRAINT FK_COMMENT_AUTHOR
    FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE SET NULL;

-- 2. Reaktionstabelle anlegen
CREATE TABLE comment_reactions (
    id         INT          NOT NULL AUTO_INCREMENT,
    comment_id INT          NOT NULL,
    user_id    INT          NOT NULL,
    type       VARCHAR(20)  NOT NULL COMMENT 'thumbs_up | heart | thumbs_down',
    PRIMARY KEY (id),
    UNIQUE KEY uniq_comment_user (comment_id, user_id),
    CONSTRAINT FK_REACTION_COMMENT FOREIGN KEY (comment_id) REFERENCES comments (id) ON DELETE CASCADE,
    CONSTRAINT FK_REACTION_USER    FOREIGN KEY (user_id)    REFERENCES users (id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
