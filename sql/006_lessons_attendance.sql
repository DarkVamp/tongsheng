-- 006: Schüler-Flag + Unterrichtsstunden + Anwesenheit

ALTER TABLE users ADD COLUMN is_student TINYINT(1) NOT NULL DEFAULT 0 AFTER family_group_id;

CREATE TABLE IF NOT EXISTS lessons (
    id          INT          NOT NULL AUTO_INCREMENT,
    date        DATE         NOT NULL,
    title       VARCHAR(255) NULL,
    created_by  INT          NOT NULL,
    created_at  DATETIME     NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    PRIMARY KEY (id),
    KEY IDX_LESSON_DATE (date),
    CONSTRAINT FK_LESSON_TEACHER
        FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attendance (
    id          INT      NOT NULL AUTO_INCREMENT,
    lesson_id   INT      NOT NULL,
    student_id  INT      NOT NULL,
    present     TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY UNIQ_LESSON_STUDENT (lesson_id, student_id),
    CONSTRAINT FK_ATTENDANCE_LESSON
        FOREIGN KEY (lesson_id) REFERENCES lessons (id) ON DELETE CASCADE,
    CONSTRAINT FK_ATTENDANCE_STUDENT
        FOREIGN KEY (student_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
