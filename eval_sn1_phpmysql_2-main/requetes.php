<?php
// =========================================================
// Toutes les requêtes SQL du jeu, rangées par fonctionnalité.
// Chaque fonction reçoit $dbh (la connexion de config.php) en premier paramètre.
// Utilisation : require 'requetes.php'; puis par ex. $niveaux = listerNiveaux($dbh);
// =========================================================


// ---------------------------------------------------------
// 1. COMPTES JOUEURS
// ---------------------------------------------------------

// Inscription : le nom d'utilisateur ou l'email est-il déjà pris ?
function utilisateurExiste($dbh, $nom, $email)
{
    $sth = $dbh->prepare('SELECT COUNT(*) FROM utilisateurs
                          WHERE nom_utilisateur = :nom OR email = :email');
    $sth->execute(['nom' => $nom, 'email' => $email]);
    return $sth->fetchColumn() > 0;
}

// Inscription : crée le compte (mot de passe hashé) et renvoie son id
function creerUtilisateur($dbh, $nom, $email, $motDePasse)
{
    $sth = $dbh->prepare('INSERT INTO utilisateurs (nom_utilisateur, email, mot_de_passe)
                          VALUES (:nom, :email, :mdp)');
    $sth->execute([
        'nom'   => $nom,
        'email' => $email,
        'mdp'   => password_hash($motDePasse, PASSWORD_DEFAULT),
    ]);
    return (int) $dbh->lastInsertId();
}

// Connexion : cherche le joueur par email OU par nom d'utilisateur.
// Ensuite, en PHP : password_verify($motDePasse, $joueur['mot_de_passe'])
function trouverUtilisateurPourConnexion($dbh, $identifiant)
{
    $sth = $dbh->prepare('SELECT id, nom_utilisateur, email, mot_de_passe, role
                          FROM utilisateurs
                          WHERE email = :login1 OR nom_utilisateur = :login2');
    $sth->execute(['login1' => $identifiant, 'login2' => $identifiant]);
    return $sth->fetch();   // false si aucun joueur
}


// ---------------------------------------------------------
// 2. NIVEAUX
// ---------------------------------------------------------

// Page « Choix du niveau » : tous les niveaux, du plus facile au plus dur
function listerNiveaux($dbh)
{
    return $dbh->query('SELECT id, nom, nombre_min, nombre_max, essais_max, couleur
                        FROM niveaux
                        ORDER BY nombre_max ASC, essais_max DESC')->fetchAll();
}

// Un niveau précis (celui choisi par le joueur)
function trouverNiveau($dbh, $niveauId)
{
    $sth = $dbh->prepare('SELECT id, nom, nombre_min, nombre_max, essais_max, couleur
                          FROM niveaux WHERE id = :id');
    $sth->execute(['id' => $niveauId]);
    return $sth->fetch();   // false si le niveau n'existe pas
}


// ---------------------------------------------------------
// 3. LE JEU
// ---------------------------------------------------------

// Termine (perdu) les parties laissées en cours par ce joueur,
// à appeler avant d'en commencer une nouvelle
function abandonnerPartiesEnCours($dbh, $utilisateurId)
{
    $sth = $dbh->prepare("UPDATE parties
                          SET resultat = 'perdu', date_fin = NOW()
                          WHERE utilisateur_id = :uid AND resultat = 'en_cours'");
    $sth->execute(['uid' => $utilisateurId]);
}

// Nouvelle partie : tire le nombre secret et enregistre la partie « en cours ».
// $niveau = le tableau renvoyé par trouverNiveau(). Renvoie l'id de la partie.
function creerPartie($dbh, $utilisateurId, $niveau)
{
    $secret = random_int((int) $niveau['nombre_min'], (int) $niveau['nombre_max']);

    $sth = $dbh->prepare('INSERT INTO parties (utilisateur_id, niveau_id, nombre_secret)
                          VALUES (:uid, :niveau, :secret)');
    $sth->execute([
        'uid'    => $utilisateurId,
        'niveau' => $niveau['id'],
        'secret' => $secret,
    ]);
    return (int) $dbh->lastInsertId();
}

// La partie en cours du joueur (avec les infos de son niveau), ou false
function partieEnCours($dbh, $utilisateurId)
{
    $sth = $dbh->prepare("SELECT p.id, p.nombre_secret, p.nombre_essais, p.date_debut,
                                 n.id AS niveau_id, n.nom AS niveau, n.nombre_min, n.nombre_max, n.essais_max, n.couleur
                          FROM parties p
                          INNER JOIN niveaux n ON n.id = p.niveau_id
                          WHERE p.utilisateur_id = :uid AND p.resultat = 'en_cours'
                          ORDER BY p.date_debut DESC
                          LIMIT 1");
    $sth->execute(['uid' => $utilisateurId]);
    return $sth->fetch();
}

// Les propositions déjà faites dans une partie (pour les afficher pendant le jeu)
function listerEssais($dbh, $partieId)
{
    $sth = $dbh->prepare('SELECT proposition, indice, date_essai
                          FROM essais
                          WHERE partie_id = :partie
                          ORDER BY id ASC');
    $sth->execute(['partie' => $partieId]);
    return $sth->fetchAll();
}

// Vérifie la saisie AVANT jouerEssai() : une erreur ne doit pas coûter d'essai.
// Renvoie le nombre (int) s'il est valide, ou null sinon.
function propositionValide($partie, $saisie)
{
    $nombre = filter_var($saisie, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => (int) $partie['nombre_min'], 'max_range' => (int) $partie['nombre_max']],
    ]);
    return $nombre === false ? null : $nombre;
}

// Un essai du joueur : compare au nombre secret, enregistre l'essai,
// et termine la partie si c'est gagné ou si les essais sont épuisés.
// $partie = le tableau renvoyé par partieEnCours().
// Renvoie ['indice' => plus|moins|trouve, 'resultat' => en_cours|gagne|perdu, 'essais_restants' => n]
function jouerEssai($dbh, $partie, $proposition)
{
    if ($proposition == $partie['nombre_secret']) {
        $indice = 'trouve';
    } elseif ($proposition < $partie['nombre_secret']) {
        $indice = 'plus';    // le nombre secret est plus grand
    } else {
        $indice = 'moins';   // le nombre secret est plus petit
    }

    $nbEssais = $partie['nombre_essais'] + 1;
    if ($indice === 'trouve') {
        $resultat = 'gagne';
    } elseif ($nbEssais >= $partie['essais_max']) {
        $resultat = 'perdu';
    } else {
        $resultat = 'en_cours';
    }

    // Les deux requêtes passent ensemble ou pas du tout
    $dbh->beginTransaction();

    $sth = $dbh->prepare('INSERT INTO essais (partie_id, proposition, indice)
                          VALUES (:partie, :proposition, :indice)');
    $sth->execute(['partie' => $partie['id'], 'proposition' => $proposition, 'indice' => $indice]);

    $sth = $dbh->prepare("UPDATE parties
                          SET nombre_essais = nombre_essais + 1,
                              resultat = :resultat,
                              date_fin = IF(:resultat2 = 'en_cours', NULL, NOW())
                          WHERE id = :partie AND resultat = 'en_cours'");
    $sth->execute(['resultat' => $resultat, 'resultat2' => $resultat, 'partie' => $partie['id']]);

    $dbh->commit();

    return [
        'indice'          => $indice,
        'resultat'        => $resultat,
        'essais_restants' => $partie['essais_max'] - $nbEssais,
    ];
}


// ---------------------------------------------------------
// 4. MES STATISTIQUES
// ---------------------------------------------------------

// Résumé du joueur : parties, victoires, défaites, % de victoires, moyennes
function statistiquesGlobales($dbh, $utilisateurId)
{
    $sth = $dbh->prepare("SELECT COUNT(*) AS nb_parties,
                                 COALESCE(SUM(resultat = 'gagne'), 0) AS victoires,
                                 COALESCE(SUM(resultat = 'perdu'), 0) AS defaites,
                                 ROUND(100 * SUM(resultat = 'gagne') / COUNT(*), 1) AS taux_victoire,
                                 ROUND(AVG(CASE WHEN resultat = 'gagne' THEN nombre_essais END), 1) AS moyenne_essais,
                                 ROUND(AVG(CASE WHEN resultat = 'gagne' THEN TIMESTAMPDIFF(SECOND, date_debut, date_fin) END)) AS duree_moyenne
                          FROM parties
                          WHERE utilisateur_id = :uid AND resultat <> 'en_cours'");
    $sth->execute(['uid' => $utilisateurId]);
    return $sth->fetch();
}

// Une ligne par niveau (même ceux jamais joués) : parties, victoires, moyenne, meilleur score
function statistiquesParNiveau($dbh, $utilisateurId)
{
    $sth = $dbh->prepare("SELECT n.nom AS niveau, n.couleur,
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
                          ORDER BY n.nombre_max ASC");
    $sth->execute(['uid' => $utilisateurId]);
    return $sth->fetchAll();
}

// Historique : les dernières parties terminées du joueur
function historiqueParties($dbh, $utilisateurId, $limite = 20)
{
    $limite = (int) $limite;   // nombre forcé : on peut l'écrire directement dans le SQL
    $sth = $dbh->prepare("SELECT p.id, n.id AS niveau_id, n.nom AS niveau, n.couleur, p.resultat,
                                 p.nombre_essais, n.essais_max, p.nombre_secret,
                                 TIMESTAMPDIFF(SECOND, p.date_debut, p.date_fin) AS duree_secondes,
                                 p.date_fin
                          FROM parties p
                          INNER JOIN niveaux n ON n.id = p.niveau_id
                          WHERE p.utilisateur_id = :uid AND p.resultat <> 'en_cours'
                          ORDER BY p.date_fin DESC, p.id DESC
                          LIMIT $limite");
    $sth->execute(['uid' => $utilisateurId]);
    return $sth->fetchAll();
}


// ---------------------------------------------------------
// 5. CLASSEMENT
// ---------------------------------------------------------

// Les meilleures parties d'un niveau : gagnées, moins d'essais d'abord,
// puis la plus rapide, puis la plus ancienne en cas d'égalité
function meilleuresParties($dbh, $niveauId, $limite = 10)
{
    $limite = (int) $limite;
    $sth = $dbh->prepare("SELECT u.nom_utilisateur, p.nombre_essais,
                                 TIMESTAMPDIFF(SECOND, p.date_debut, p.date_fin) AS duree_secondes,
                                 p.date_fin
                          FROM parties p
                          INNER JOIN utilisateurs u ON u.id = p.utilisateur_id
                          WHERE p.niveau_id = :niveau AND p.resultat = 'gagne'
                          ORDER BY p.nombre_essais ASC, duree_secondes ASC, p.date_fin ASC
                          LIMIT $limite");
    $sth->execute(['niveau' => $niveauId]);
    return $sth->fetchAll();
}

// Variante : un seul résultat par joueur (son meilleur) pour un niveau
function meilleursJoueurs($dbh, $niveauId, $limite = 10)
{
    $limite = (int) $limite;
    $sth = $dbh->prepare("SELECT u.nom_utilisateur,
                                 MIN(p.nombre_essais) AS meilleur,
                                 COUNT(*) AS victoires,
                                 ROUND(AVG(p.nombre_essais), 1) AS moyenne_essais
                          FROM parties p
                          INNER JOIN utilisateurs u ON u.id = p.utilisateur_id
                          WHERE p.niveau_id = :niveau AND p.resultat = 'gagne'
                          GROUP BY u.id, u.nom_utilisateur
                          ORDER BY meilleur ASC, victoires DESC, moyenne_essais ASC
                          LIMIT $limite");
    $sth->execute(['niveau' => $niveauId]);
    return $sth->fetchAll();
}
