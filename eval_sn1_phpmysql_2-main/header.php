<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titre ?? 'Plus ou Moins') ?></title>
    <link rel="stylesheet" href="./css/style.css">
    <link rel="stylesheet" href="./css/complement.css">
</head>
<body>
<header>
    <nav class="nav_barre">
        <div class="nav_title"> &plusmn; <b>Juste prix</b></div>
        <ul class="nav_link">
            <li><a href="./index.php">jouer</a></li>
            <li><a href="./classement.php">classement</a></li>
            <?php if (estConnecte()) { ?>
                <li><a href="./stats.php">mes stats</a></li>
                <li><a href="./stats.php"><?= htmlspecialchars($_SESSION['pseudo']) ?></a></li>
                <li><a href="./deconnexion.php">se déconnecter</a></li>
            <?php } else { ?>
                <li><a href="./connexion.php">connexion</a></li>
                <li><a href="./inscription.php">inscription</a></li>
            <?php } ?>
        </ul>
    </nav>
</header>
