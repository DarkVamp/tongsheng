-- 013: homework_type auf Bildern, neue homework_audio-Tabelle
ALTER TABLE homework_images
    ADD COLUMN homework_type VARCHAR(50) NOT NULL DEFAULT 'schreiben' AFTER family_id;

CREATE TABLE homework_audio (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    lesson_id  INT NOT NULL,
    family_id  INT NOT NULL,
    homework_type VARCHAR(50) NOT NULL,
    filename   VARCHAR(255) NOT NULL,
    mime_type  VARCHAR(50) NOT NULL,
    file_size  INT NOT NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_hwa_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id)  ON DELETE CASCADE,
    CONSTRAINT fk_hwa_family FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE
);
