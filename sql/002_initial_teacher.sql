-- Tongsheng — erster Lehrer-Account
-- In phpMyAdmin ausführen nach 001_initial_schema.sql

INSERT INTO `users` (`email`, `family_name`, `role`, `password`)
VALUES (
    'ysong@song-kraus.com',
    'Yuxuan Song',
    'teacher',
    '$2y$13$pm7dQpSNIzT2i47YqFYdYOMb4/xrk2ffGRCDiM98jEITQJ2iP9nYe'
);
