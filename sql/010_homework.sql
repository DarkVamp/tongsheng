-- 010: Hausaufgaben-Feature
-- Lehrerin kann eine Stunde als "Hausaufgaben aufgegeben" markieren.
-- Familien können Bilder als Hausaufgaben hochladen.

ALTER TABLE lessons
    ADD COLUMN homework_assigned TINYINT(1) NOT NULL DEFAULT 0;

CREATE TABLE homework_images (
    id             INT          NOT NULL AUTO_INCREMENT,
    lesson_id      INT          NOT NULL,
    family_id      INT          NOT NULL,
    file_path      VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    mime_type      VARCHAR(50)  NOT NULL,
    uploaded_at    DATETIME     NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_homework_lesson (lesson_id),
    INDEX idx_homework_family (family_id),
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE
);
