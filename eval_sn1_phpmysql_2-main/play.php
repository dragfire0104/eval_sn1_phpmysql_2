<?php
require_once 'config.php';
exigerConnexion();

$uid = $_SESSION['user_id'];

// 1. Nouvelle partie : play.php?niveau=2 (depuis l'accueil ou "Rejouer")
if (isset($_GET['niveau'])) {
    $niveau = trouverNiveau($dbh, (int) $_GET['niveau']);
    if (!$niveau) {
        header('Location: index.php');
        exit;
    }
    abandonnerPartiesEnCours($dbh, $uid);     // une seule partie en cours à la fois
    creerPartie($dbh, $uid, $niveau);         // le nombre secret est tiré et gardé en base
    header('Location: play.php');
    exit;
}

$partie = partieEnCours($dbh, $uid);

// 2. Le joueur envoie une proposition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $partie) {
    $proposition = propositionValide($partie, $_POST['nombre'] ?? '');

    if ($proposition === null) {
        // Saisie invalide : on prévient, mais l'essai n'est pas compté
        $_SESSION['message'] = 'Entrez un nombre entier entre ' . $partie['nombre_min'] . ' et ' . $partie['nombre_max'] . '.';
        header('Location: play.php');
        exit;
    }

    $reponse = jouerEssai($dbh, $partie, $proposition);

    if ($reponse['resultat'] !== 'en_cours') {
        header('Location: play.php?fin=1');
        exit;
    }

    $_SESSION['message'] = $reponse['indice'] === 'plus'
        ? $proposition . ' : c\'est plus !'
        : $proposition . ' : c\'est moins !';
    header('Location: play.php');   // PRG : un F5 ne renvoie pas l'essai
    exit;
}

// Message à afficher une seule fois
$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

// 3. Partie terminée : on affiche la dernière partie finie
$derniere = null;
if (!$partie && isset($_GET['fin'])) {
    $historique = historiqueParties($dbh, $uid, 1);
    $derniere = $historique[0] ?? null;
}

// Pas de partie en cours et rien à afficher : retour au choix du niveau
if (!$partie && !$derniere) {
    header('Location: index.php');
    exit;
}

$essais = $partie ? listerEssais($dbh, $partie['id']) : [];

$titre = 'Partie en cours';
require 'header.php';
?>
<main class="jeu">

<?php if ($partie) { ?>

    <section class="carte-jeu" style="--couleur: <?= htmlspecialchars($partie['couleur']) ?>;">
        <h1><span class="dot"></span>Niveau <?= htmlspecialchars($partie['niveau']) ?></h1>
        <p>Trouvez le nombre entre <b><?= (int) $partie['nombre_min'] ?></b> et <b><?= (int) $partie['nombre_max'] ?></b></p>
        <p class="essais-restants">
            Essais restants : <b><?= $partie['essais_max'] - $partie['nombre_essais'] ?></b> / <?= (int) $partie['essais_max'] ?>
        </p>

        <?php if ($message !== '') { ?>
            <p class="indice"><?= htmlspecialchars($message) ?></p>
        <?php } ?>

        <form method="post" action="play.php" class="form-jeu">
            <input type="number" name="nombre" min="<?= (int) $partie['nombre_min'] ?>" max="<?= (int) $partie['nombre_max'] ?>"
                   placeholder="Votre nombre" required autofocus>
            <button type="submit" class="btn-jouer">Valider</button>
        </form>

        <?php if (count($essais) > 0) { ?>
            <h2>Vos essais</h2>
            <ol class="liste-essais">
                <?php foreach ($essais as $essai) { ?>
                    <li><?= (int) $essai['proposition'] ?> → <?= $essai['indice'] === 'plus' ? 'plus' : 'moins' ?></li>
                <?php } ?>
            </ol>
        <?php } ?>
    </section>

<?php } else { ?>

    <section class="carte-jeu fin <?= $derniere['resultat'] ?>" style="--couleur: <?= htmlspecialchars($derniere['couleur']) ?>;">
        <?php if ($derniere['resultat'] === 'gagne') { ?>
            <h1>Gagné !</h1>
            <p>Le nombre était bien <b><?= (int) $derniere['nombre_secret'] ?></b>.</p>
            <p>Trouvé en <b><?= (int) $derniere['nombre_essais'] ?></b> essai(s) sur <?= (int) $derniere['essais_max'] ?>,
               en <?= (int) $derniere['duree_secondes'] ?> secondes.</p>
        <?php } else { ?>
            <h1>Perdu…</h1>
            <p>Le nombre secret était <b><?= (int) $derniere['nombre_secret'] ?></b>.</p>
            <p>Vous avez utilisé vos <?= (int) $derniere['essais_max'] ?> essais.</p>
        <?php } ?>

        <div class="actions">
            <form method="get" action="play.php">
                <input type="hidden" name="niveau" value="<?= (int) $derniere['niveau_id'] ?>">
                <button type="submit" class="btn-jouer">Rejouer (<?= htmlspecialchars($derniere['niveau']) ?>)</button>
            </form>
            <a href="index.php">Changer de niveau</a>
            <a href="classement.php">Voir le classement</a>
        </div>
    </section>

<?php } ?>

</main>
<?php require 'footer.php'; ?>
