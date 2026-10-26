-- =========================================================
-- Base de données : jeu de devinette (Juste Prix)
-- Le joueur doit trouver un nombre secret entre 1 et N,
-- N et le nombre d'essais dépendent du niveau choisi.
-- =========================================================
DROP DATABASE IF EXISTS juste_prix;
CREATE DATABASE juste_prix
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE juste_prix;

-- =========================================================
-- Joueurs
-- =========================================================
CREATE TABLE utilisateurs (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom_utilisateur  VARCHAR(50)  NOT NULL UNIQUE,
    email            VARCHAR(255) NOT NULL UNIQUE,
    mot_de_passe     VARCHAR(255) NOT NULL,          -- hash password_hash(), jamais en clair
    role             ENUM('joueur', 'admin') NOT NULL DEFAULT 'joueur',  -- pour l'extension « Administration »
    date_inscription DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =========================================================
-- Niveaux de difficulté
-- Ajouter une ligne ici suffit à créer un nouveau niveau dans le jeu.
-- =========================================================
CREATE TABLE niveaux (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom         VARCHAR(30)  NOT NULL UNIQUE,
    nombre_min  INT UNSIGNED NOT NULL DEFAULT 1,      -- plage du nombre secret : de nombre_min...
    nombre_max  INT UNSIGNED NOT NULL,                -- ... à nombre_max
    essais_max  INT UNSIGNED NOT NULL,                -- nombre d'essais autorisés
    couleur     CHAR(7)      NOT NULL DEFAULT '#888888',  -- couleur du badge (ex. #3DAE4A)
    CHECK (nombre_max > nombre_min),
    CHECK (essais_max >= 1)
) ENGINE=InnoDB;

-- =========================================================
-- Parties
-- Une partie est créée au début (en_cours), puis terminée (gagne / perdu).
-- Le nombre secret reste côté serveur, en base : le joueur ne peut pas le lire.
-- =========================================================
CREATE TABLE parties (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT UNSIGNED NOT NULL,
    niveau_id      INT UNSIGNED NOT NULL,
    nombre_secret  INT UNSIGNED NOT NULL,
    nombre_essais  INT UNSIGNED NOT NULL DEFAULT 0,
    resultat       ENUM('en_cours', 'gagne', 'perdu') NOT NULL DEFAULT 'en_cours',
    date_debut     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_fin       DATETIME NULL,                     -- NULL tant que la partie est en cours

    CONSTRAINT fk_parties_utilisateur
        FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    CONSTRAINT fk_parties_niveau
        FOREIGN KEY (niveau_id) REFERENCES niveaux(id)
) ENGINE=InnoDB;

-- =========================================================
-- Essais : chaque proposition du joueur pendant une partie
-- =========================================================
CREATE TABLE essais (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    partie_id   INT UNSIGNED NOT NULL,
    proposition INT UNSIGNED NOT NULL,
    indice      ENUM('plus', 'moins', 'trouve') NOT NULL,   -- plus = le secret est plus grand
    date_essai  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_essais_partie
        FOREIGN KEY (partie_id) REFERENCES parties(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Index pour accélérer les statistiques et le classement
CREATE INDEX idx_parties_utilisateur ON parties (utilisateur_id, resultat);
CREATE INDEX idx_parties_classement  ON parties (niveau_id, resultat, nombre_essais);
CREATE INDEX idx_essais_partie       ON essais (partie_id);

-- =========================================================
-- Les 4 niveaux du sujet
-- =========================================================
INSERT INTO niveaux (nom, nombre_min, nombre_max, essais_max, couleur) VALUES
('Facile',    1, 50,   10, '#3DAE4A'),
('Normal',    1, 100,  8,  '#1E9BD7'),
('Difficile', 1, 500,  10, '#E8873A'),
('Expert',    1, 1000, 10, '#D7263D');

-- =========================================================
-- Données de test
-- Comptes : alice@test.fr / test123 (admin)   et   bob@test.fr / azerty1
-- =========================================================
INSERT INTO utilisateurs (id, nom_utilisateur, email, mot_de_passe, role, date_inscription) VALUES
(1, 'alice', 'alice@test.fr', '$2y$10$4v7W4Ahd88sbKCyRJDmQ8.XIwhMiBxm7EnwybW7v86slgk/mym2Mq', 'admin',  '2026-10-19 09:00:00'),
(2, 'bob',   'bob@test.fr',   '$2y$10$shNBZqnavc1i9r9aKYtJiurY6hpyVwN3uJHJ8l672lCf.FyJHgiei', 'joueur', '2026-10-19 09:30:00');

INSERT INTO parties (id, utilisateur_id, niveau_id, nombre_secret, nombre_essais, resultat, date_debut, date_fin) VALUES
(1, 1, 2, 68,  4,  'gagne',    '2026-10-20 10:00:00', '2026-10-20 10:01:30'),
(2, 1, 1, 23,  3,  'gagne',    '2026-10-20 10:05:00', '2026-10-20 10:05:40'),
(3, 1, 3, 377, 10, 'perdu',    '2026-10-20 10:10:00', '2026-10-20 10:14:00'),
(4, 2, 2, 41,  6,  'gagne',    '2026-10-21 11:00:00', '2026-10-21 11:02:10'),
(5, 2, 1, 7,   5,  'gagne',    '2026-10-21 11:10:00', '2026-10-21 11:10:50'),
(6, 2, 2, 90,  8,  'perdu',    '2026-10-21 11:20:00', '2026-10-21 11:23:00'),
(7, 1, 2, 15,  0,  'en_cours', '2026-10-22 09:00:00', NULL);

INSERT INTO essais (partie_id, proposition, indice, date_essai) VALUES
-- partie 1 : alice, Normal, secret 68, gagnée en 4 (l'exemple du sujet)
(1, 50, 'plus', '2026-10-20 10:00:20'), (1, 75, 'moins', '2026-10-20 10:00:45'),
(1, 62, 'plus', '2026-10-20 10:01:05'), (1, 68, 'trouve', '2026-10-20 10:01:30'),
-- partie 2 : alice, Facile, secret 23, gagnée en 3
(2, 25, 'moins', '2026-10-20 10:05:10'), (2, 12, 'plus', '2026-10-20 10:05:25'),
(2, 23, 'trouve', '2026-10-20 10:05:40'),
-- partie 3 : alice, Difficile, secret 377, perdue (10 essais)
(3, 250, 'plus', '2026-10-20 10:10:20'), (3, 375, 'plus', '2026-10-20 10:10:45'),
(3, 437, 'moins', '2026-10-20 10:11:10'), (3, 406, 'moins', '2026-10-20 10:11:35'),
(3, 390, 'moins', '2026-10-20 10:12:00'), (3, 382, 'moins', '2026-10-20 10:12:25'),
(3, 379, 'moins', '2026-10-20 10:12:50'), (3, 374, 'plus', '2026-10-20 10:13:15'),
(3, 376, 'plus', '2026-10-20 10:13:40'), (3, 378, 'moins', '2026-10-20 10:14:00'),
-- partie 4 : bob, Normal, secret 41, gagnée en 6
(4, 50, 'moins', '2026-10-21 11:00:20'), (4, 25, 'plus', '2026-10-21 11:00:45'),
(4, 37, 'plus', '2026-10-21 11:01:10'), (4, 43, 'moins', '2026-10-21 11:01:35'),
(4, 40, 'plus', '2026-10-21 11:01:55'), (4, 41, 'trouve', '2026-10-21 11:02:10'),
-- partie 5 : bob, Facile, secret 7, gagnée en 5
(5, 25, 'moins', '2026-10-21 11:10:10'), (5, 12, 'moins', '2026-10-21 11:10:20'),
(5, 6, 'plus', '2026-10-21 11:10:30'), (5, 9, 'moins', '2026-10-21 11:10:40'),
(5, 7, 'trouve', '2026-10-21 11:10:50'),
-- partie 6 : bob, Normal, secret 90, perdue (8 essais)
(6, 50, 'plus', '2026-10-21 11:20:20'), (6, 75, 'plus', '2026-10-21 11:20:40'),
(6, 87, 'plus', '2026-10-21 11:21:00'), (6, 93, 'moins', '2026-10-21 11:21:20'),
(6, 88, 'plus', '2026-10-21 11:21:40'), (6, 91, 'moins', '2026-10-21 11:22:00'),
(6, 89, 'plus', '2026-10-21 11:22:30'), (6, 92, 'moins', '2026-10-21 11:23:00');
