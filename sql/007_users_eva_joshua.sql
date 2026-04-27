-- 007: Eva Kraus (Familienmitglied) + Joshua Kraus (Schüler)
-- Temporäres Passwort für beide: Tongsheng1

INSERT INTO `users`
    (`email`, `family_name`, `role`, `password`, `family_group_id`, `is_student`)
VALUES (
    'eva@song-kraus.com',
    'Eva Kraus',
    'family_member',
    '$2y$13$tcaxtmlJgY8fcs/nU4j0NuPNGLDhFiefUqSInxYXk409ZWvJOz4Ii',
    (SELECT `family_group_id` FROM `users` WHERE `email` = 'rkraus@song-kraus.com'),
    0
),
(
    'joshua@song-kraus.com',
    'Joshua Kraus',
    'family_member',
    '$2y$13$t9exCdiUwIvUYrt7a3BZcecdhJz/vr9gxWtswyo9tsAX7m.RNey7W',
    (SELECT `family_group_id` FROM `users` WHERE `email` = 'rkraus@song-kraus.com'),
    1
);
