<?php
require_once 'config.php';    // connexion $dbh (+ session_start)
// requetes.php est déjà chargé par config.php

// 1. Tous les niveaux, pour les onglets (lus dans la base)
$niveaux = listerNiveaux($dbh);

// 2. Le niveau demandé dans l'URL : classement.php?niveau=2
//    Si rien n'est donné ou si le niveau n'existe pas, on prend le premier
$niveauChoisi = $niveaux[0];
foreach ($niveaux as $niveau) {
    if (isset($_GET['niveau']) && (int) $_GET['niveau'] === (int) $niveau['id']) {
        $niveauChoisi = $niveau;
    }
}

// 3. Les 10 meilleures parties gagnées de ce niveau
//    (le moins d'essais d'abord, puis la plus rapide)
$parties = meilleuresParties($dbh, $niveauChoisi['id'], 10);

// Affiche une durée en secondes sous la forme 0:42
function formatDuree($secondes)
{
    $secondes = (int) $secondes;
    return intdiv($secondes, 60) . ':' . sprintf('%02d', $secondes % 60);
}

$titre = 'Classement';
require 'header.php';
?>

<main class="classement">
    <h1>Classement</h1>

    <!-- Un onglet par niveau -->
    <nav class="onglets">
        <?php foreach ($niveaux as $niveau) { ?>
            <a href="classement.php?niveau=<?= (int) $niveau['id'] ?>"
               class="onglet <?= $niveau['id'] == $niveauChoisi['id'] ? 'actif' : '' ?>"
               style="--couleur: <?= htmlspecialchars($niveau['couleur']) ?>;">
                <?= htmlspecialchars($niveau['nom']) ?>
            </a>
        <?php } ?>
    </nav>

    <p class="sous-titre">
        Niveau <?= htmlspecialchars($niveauChoisi['nom']) ?> :
        entre <?= (int) $niveauChoisi['nombre_min'] ?> et <?= (int) $niveauChoisi['nombre_max'] ?>,
        <?= (int) $niveauChoisi['essais_max'] ?> essais
    </p>

    <?php if (count($parties) === 0) { ?>

        <p class="vide">Aucune partie gagnée pour ce niveau pour le moment.</p>

    <?php } else { ?>

        <table class="tableau-classement">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Joueur</th>
                    <th>Essais</th>
                    <th>Durée</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($parties as $rang => $partie) { ?>
                    <tr>
                        <td><?= $rang + 1 ?></td>
                        <td><?= htmlspecialchars($partie['nom_utilisateur']) ?></td>
                        <td><?= (int) $partie['nombre_essais'] ?></td>
                        <td><?= formatDuree($partie['duree_secondes']) ?></td>
                        <td><?= date('d/m', strtotime($partie['date_fin'])) ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>

    <?php } ?>
</main>

<?php require 'footer.php'; ?>
