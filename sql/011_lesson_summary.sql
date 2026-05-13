-- 011: Zusammenfassung-Feld für Unterrichtsstunden
ALTER TABLE lessons ADD COLUMN summary TEXT NULL AFTER homework_assigned;
