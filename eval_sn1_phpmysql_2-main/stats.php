<?php
require_once 'config.php';
exigerConnexion();

$uid = $_SESSION['user_id'];

$global     = statistiquesGlobales($dbh, $uid);     // totaux de toutes les parties
$parNiveau  = statistiquesParNiveau($dbh, $uid);    // une ligne par niveau (même à 0)
$historique = historiqueParties($dbh, $uid, 10);    // les 10 dernières parties

$titre = 'Mes stats';
require 'header.php';
?>
<link rel="stylesheet" href="./css/style.css">
<link rel="stylesheet" href="./css/complement.css"> 
<main class="stats">
    <h1>Statistiques de <?= htmlspecialchars($_SESSION['pseudo']) ?></h1>

    <section class="stats-resume">
        <article class="stat">
            <h2>Parties jouées</h2>
            <p><?= (int) $global['nb_parties'] ?></p>
        </article>
        <article class="stat">
            <h2>Victoires</h2>
            <p><?= (int) $global['victoires'] ?></p>
        </article>
        <article class="stat">
            <h2>Taux de victoire</h2>
            <p><?= $global['taux_victoire'] !== null ? $global['taux_victoire'] . ' %' : '-' ?></p>
        </article>
        <article class="stat">
            <h2>Moyenne d'essais</h2>
            <p><?= $global['moyenne_essais'] ?? '-' ?></p>
        </article>
    </section>

    <section class="stats-niveaux">
        <h2>Par niveau</h2>
        <table>
            <thead>
                <tr>
                    <th>Niveau</th>
                    <th>Parties</th>
                    <th>Victoires</th>
                    <th>Moyenne d'essais</th>
                    <th>Meilleur score</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($parNiveau as $ligne) { ?>
                    <tr>
                        <td><?= htmlspecialchars($ligne['niveau']) ?></td>
                        <td><?= (int) $ligne['nb_parties'] ?></td>
                        <td><?= (int) $ligne['victoires'] ?></td>
                        <td><?= $ligne['moyenne_essais'] ?? '-' ?></td>
                        <td><?= $ligne['meilleur'] !== null ? (int) $ligne['meilleur'] . ' essais' : '-' ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </section>

    <section class="stats-niveaux">
        <h2>Dernières parties</h2>
        <?php if (count($historique) === 0) { ?>
            <p>Aucune partie jouée pour le moment. <a href="index.php">Lancer une partie</a></p>
        <?php } else { ?>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Niveau</th>
                        <th>Résultat</th>
                        <th>Essais</th>
                        <th>Nombre secret</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historique as $p) { ?>
                        <tr>
                            <td><?= date('d/m H:i', strtotime($p['date_fin'])) ?></td>
                            <td><?= htmlspecialchars($p['niveau']) ?></td>
                            <td><?= $p['resultat'] === 'gagne' ? 'Gagné' : 'Perdu' ?></td>
                            <td><?= (int) $p['nombre_essais'] ?> / <?= (int) $p['essais_max'] ?></td>
                            <td><?= (int) $p['nombre_secret'] ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        <?php } ?>
    </section>
</main>
<?php require 'footer.php'; ?>
