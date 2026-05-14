-- 012: Hausaufgaben-Typen pro Unterrichtsstunde
ALTER TABLE lessons ADD COLUMN homework_types JSON NULL DEFAULT NULL AFTER summary;
