<?php
require_once 'config.php';

if (estConnecte()) {
    header('Location: index.php');
    exit;
}

$erreur = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $mdp = $_POST['mot_de_passe'] ?? '';

    // On cherche le joueur par email (ou pseudo), puis on vérifie le mot de passe hashé
    $joueur = trouverUtilisateurPourConnexion($dbh, $email);

    if ($joueur && password_verify($mdp, $joueur['mot_de_passe'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $joueur['id'];
        $_SESSION['pseudo'] = $joueur['nom_utilisateur'];
        header('Location: index.php');
        exit;
    }

    $erreur = 'Email ou mot de passe incorrect.';
}

$titre = 'Connexion';
require 'header.php';
?>
<main class="inscription">
    <h2>Se connecter</h2>

    <?php if ($erreur !== '') { ?>
        <p class="erreur"><?= htmlspecialchars($erreur) ?></p>
    <?php } ?>

    <form method="post" action="connexion.php">
        <div class="champ">
            <label for="email">Email ou pseudo</label>
            <input type="text" id="email" name="email" placeholder="Votre email"
                   value="<?= htmlspecialchars($email) ?>" required>
        </div>

        <div class="champ">
            <label for="mdp">Mot de passe</label>
            <input type="password" id="mdp" name="mot_de_passe" placeholder="Votre mot de passe" required>
        </div>

        <button type="submit" class="btn-inscription">Se connecter</button>

        <p class="lien-connexion">Pas encore de compte ? <a href="inscription.php">Créer un compte</a></p>
    </form>
</main>
<?php require 'footer.php'; ?>
