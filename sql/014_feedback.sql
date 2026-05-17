-- 014: Feedback-Nachrichten (Lehrerin → Schüler pro Unterrichtsstunde)

CREATE TABLE feedback_messages (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    lesson_id   INT         NOT NULL,
    student_id  INT         NOT NULL,
    author_id   INT         NOT NULL,
    text        TEXT        NULL,
    created_at  DATETIME    NOT NULL,
    CONSTRAINT fk_fb_msg_lesson   FOREIGN KEY (lesson_id)  REFERENCES lessons(id) ON DELETE CASCADE,
    CONSTRAINT fk_fb_msg_student  FOREIGN KEY (student_id) REFERENCES users(id)   ON DELETE CASCADE,
    CONSTRAINT fk_fb_msg_author   FOREIGN KEY (author_id)  REFERENCES users(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE feedback_attachments (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    message_id  INT          NOT NULL,
    type        VARCHAR(10)  NOT NULL COMMENT 'audio | image',
    filename    VARCHAR(255) NOT NULL,
    mime_type   VARCHAR(50)  NOT NULL,
    file_size   INT          NOT NULL,
    created_at  DATETIME     NOT NULL,
    CONSTRAINT fk_fb_att_message FOREIGN KEY (message_id) REFERENCES feedback_messages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
