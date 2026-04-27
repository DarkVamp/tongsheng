-- 008: families als eigene Tabelle
-- Rolle 'family' fällt weg — nur noch 'teacher' und 'family_member'
-- Alle Familienmitglieder (inkl. ehem. 'family'-User) haben family_id → families.id
-- ON DELETE CASCADE: Familienzeile löschen → alle zugehörigen User + Aufnahmen werden gelöscht

-- 1. Neue Tabelle
CREATE TABLE families (
    id         INT          NOT NULL AUTO_INCREMENT,
    name       VARCHAR(255) NOT NULL,
    created_at DATETIME     NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Bestehende Familien migrieren (eine Zeile pro role='family' User)
INSERT INTO families (name, created_at)
SELECT family_name, NOW() FROM users WHERE role = 'family' ORDER BY id;

-- 3. family_id zu users hinzufügen
ALTER TABLE users ADD COLUMN family_id INT NULL AFTER is_student;

-- 4. Primäre Familie-User verknüpfen
UPDATE users u
JOIN families f ON f.name = u.family_name
SET u.family_id = f.id
WHERE u.role = 'family';

-- 5. Familienmitglieder verknüpfen (über family_group_id → primary user → family)
UPDATE users um
JOIN users uf ON uf.id = um.family_group_id
SET um.family_id = uf.family_id
WHERE um.role = 'family_member';

-- 6. invitations: family_id ergänzen und migrieren
ALTER TABLE invitations ADD COLUMN family_id INT NULL AFTER family_group_id;
UPDATE invitations i
JOIN users u ON u.id = i.family_group_id
SET i.family_id = u.family_id
WHERE i.family_group_id IS NOT NULL;

-- 7. Rolle 'family' auf 'family_member' umstellen
UPDATE users SET role = 'family_member' WHERE role = 'family';

-- 8. Alte Spalten entfernen
ALTER TABLE invitations DROP COLUMN family_group_id;
ALTER TABLE users DROP INDEX idx_family_group;
ALTER TABLE users DROP COLUMN family_group_id;

-- 9. FK Constraints
ALTER TABLE users ADD CONSTRAINT FK_USER_FAMILY
    FOREIGN KEY (family_id) REFERENCES families (id) ON DELETE CASCADE;

ALTER TABLE invitations ADD CONSTRAINT FK_INVITATION_FAMILY
    FOREIGN KEY (family_id) REFERENCES families (id) ON DELETE CASCADE;
