-- =========================================================
-- Requêtes SQL du jeu de devinette
-- Les :mots (ex. :uid) sont les paramètres remplis par PHP avec execute().
-- Les mêmes requêtes sont prêtes à l'emploi dans requetes.php.
-- =========================================================


-- ---------------------------------------------------------
-- 1. COMPTES JOUEURS
-- ---------------------------------------------------------

-- Inscription : nom d'utilisateur ou email déjà pris ?
SELECT COUNT(*) FROM utilisateurs
WHERE nom_utilisateur = :nom OR email = :email;

-- Inscription : création du compte (mot de passe hashé avec password_hash() en PHP)
INSERT INTO utilisateurs (nom_utilisateur, email, mot_de_passe)
VALUES (:nom, :email, :mdp);

-- Connexion : on récupère le joueur, puis password_verify() vérifie le mot de passe en PHP
SELECT id, nom_utilisateur, email, mot_de_passe, role
FROM utilisateurs
WHERE email = :login1 OR nom_utilisateur = :login2;


-- ---------------------------------------------------------
-- 2. NIVEAUX (lus en base : ajouter une ligne crée un nouveau niveau)
-- ---------------------------------------------------------

-- Choix du niveau : tous les niveaux, du plus facile au plus dur
SELECT id, nom, nombre_min, nombre_max, essais_max, couleur
FROM niveaux
ORDER BY nombre_max ASC, essais_max DESC;

-- Le niveau choisi
SELECT id, nom, nombre_min, nombre_max, essais_max, couleur
FROM niveaux WHERE id = :id;

-- Exemple : ajouter un niveau suffit à le faire apparaître dans le jeu
-- INSERT INTO niveaux (nom, nombre_min, nombre_max, essais_max, couleur) VALUES ('Légende', 1, 5000, 12, '#6A3FA0');


-- ---------------------------------------------------------
-- 3. LE JEU
-- ---------------------------------------------------------

-- Avant une nouvelle partie : les parties laissées en cours comptent comme perdues
UPDATE parties
SET resultat = 'perdu', date_fin = NOW()
WHERE utilisateur_id = :uid AND resultat = 'en_cours';

-- Nouvelle partie (le nombre secret est tiré en PHP avec random_int(nombre_min, nombre_max))
INSERT INTO parties (utilisateur_id, niveau_id, nombre_secret)
VALUES (:uid, :niveau, :secret);

-- La partie en cours du joueur, avec les règles de son niveau
SELECT p.id, p.nombre_secret, p.nombre_essais, p.date_debut,
       n.id AS niveau_id, n.nom AS niveau, n.nombre_min, n.nombre_max, n.essais_max, n.couleur
FROM parties p
INNER JOIN niveaux n ON n.id = p.niveau_id
WHERE p.utilisateur_id = :uid AND p.resultat = 'en_cours'
ORDER BY p.date_debut DESC
LIMIT 1;

-- Les propositions déjà faites dans la partie
SELECT proposition, indice, date_essai
FROM essais
WHERE partie_id = :partie
ORDER BY id ASC;

-- Un essai : on l'enregistre...
INSERT INTO essais (partie_id, proposition, indice)
VALUES (:partie, :proposition, :indice);

-- ... puis on met à jour la partie (gagne, perdu, ou toujours en_cours)
UPDATE parties
SET nombre_essais = nombre_essais + 1,
    resultat = :resultat,
    date_fin = IF(:resultat2 = 'en_cours', NULL, NOW())
WHERE id = :partie AND resultat = 'en_cours';


-- ---------------------------------------------------------
-- 4. MES STATISTIQUES
-- ---------------------------------------------------------

-- Résumé : parties, victoires, défaites, % de victoires, essais et durée moyens
SELECT COUNT(*) AS nb_parties,
       COALESCE(SUM(resultat = 'gagne'), 0) AS victoires,
       COALESCE(SUM(resultat = 'perdu'), 0) AS defaites,
       ROUND(100 * SUM(resultat = 'gagne') / COUNT(*), 1) AS taux_victoire,
       ROUND(AVG(CASE WHEN resultat = 'gagne' THEN nombre_essais END), 1) AS moyenne_essais,
       ROUND(AVG(CASE WHEN resultat = 'gagne' THEN TIMESTAMPDIFF(SECOND, date_debut, date_fin) END)) AS duree_moyenne
FROM parties
WHERE utilisateur_id = :uid AND resultat <> 'en_cours';

-- Par niveau (LEFT JOIN : les niveaux jamais joués apparaissent avec 0)
SELECT n.nom AS niveau, n.couleur,
       COUNT(p.id) AS nb_parties,
       COALESCE(SUM(p.resultat = 'gagne'), 0) AS victoires,
       ROUND(AVG(CASE WHEN p.resultat = 'gagne' THEN p.nombre_essais END), 1) AS moyenne_essais,
       MIN(CASE WHEN p.resultat = 'gagne' THEN p.nombre_essais END) AS meilleur
FROM niveaux n
LEFT JOIN parties p
       ON p.niveau_id = n.id
      AND p.utilisateur_id = :uid
      AND p.resultat <> 'en_cours'
GROUP BY n.id, n.nom, n.couleur, n.nombre_max
ORDER BY n.nombre_max ASC;

-- Historique : les 20 dernières parties terminées
SELECT p.id, n.id AS niveau_id, n.nom AS niveau, n.couleur, p.resultat,
       p.nombre_essais, n.essais_max, p.nombre_secret,
       TIMESTAMPDIFF(SECOND, p.date_debut, p.date_fin) AS duree_secondes,
       p.date_fin
FROM parties p
INNER JOIN niveaux n ON n.id = p.niveau_id
WHERE p.utilisateur_id = :uid AND p.resultat <> 'en_cours'
ORDER BY p.date_fin DESC, p.id DESC
LIMIT 20;


-- ---------------------------------------------------------
-- 5. CLASSEMENT
-- ---------------------------------------------------------

-- Meilleures parties d'un niveau : moins d'essais, puis la plus rapide, puis la plus ancienne
SELECT u.nom_utilisateur, p.nombre_essais,
       TIMESTAMPDIFF(SECOND, p.date_debut, p.date_fin) AS duree_secondes,
       p.date_fin
FROM parties p
INNER JOIN utilisateurs u ON u.id = p.utilisateur_id
WHERE p.niveau_id = :niveau AND p.resultat = 'gagne'
ORDER BY p.nombre_essais ASC, duree_secondes ASC, p.date_fin ASC
LIMIT 10;

-- Variante : le meilleur résultat de chaque joueur sur un niveau
SELECT u.nom_utilisateur,
       MIN(p.nombre_essais) AS meilleur,
       COUNT(*) AS victoires,
       ROUND(AVG(p.nombre_essais), 1) AS moyenne_essais
FROM parties p
INNER JOIN utilisateurs u ON u.id = p.utilisateur_id
WHERE p.niveau_id = :niveau AND p.resultat = 'gagne'
GROUP BY u.id, u.nom_utilisateur
ORDER BY meilleur ASC, victoires DESC, moyenne_essais ASC
LIMIT 10;
