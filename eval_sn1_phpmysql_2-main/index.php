<?php
require_once 'config.php';

// Les niveaux sont lus dans la base : ajouter une ligne suffit à en créer un
$niveaux = listerNiveaux($dbh);

$titre = 'Choisir un niveau';
require 'header.php';
?>
<main class="choix">
    <h1>Choisissez votre niveau</h1>

    <div class="cards">
        <?php foreach ($niveaux as $niveau) { ?>
            <article class="card" style="--couleur: <?= htmlspecialchars($niveau['couleur']) ?>;">
                <h2><span class="dot"></span><?= htmlspecialchars($niveau['nom']) ?></h2>
                <p>Entre <?= (int) $niveau['nombre_min'] ?> et <?= (int) $niveau['nombre_max'] ?><br><?= (int) $niveau['essais_max'] ?> essais</p>
                <!-- Le bouton envoie vers play.php?niveau=... (un visiteur non connecté part vers la connexion) -->
                <form method="get" action="play.php">
                    <input type="hidden" name="niveau" value="<?= (int) $niveau['id'] ?>">
                    <button type="submit" class="btn-jouer">Jouer</button>
                </form>
            </article>
        <?php } ?>
    </div>
</main>
<?php require 'footer.php'; ?>
