<?php
require_once 'config.php';

if (estConnecte()) {
    header('Location: index.php');
    exit;
}

$erreurs = [];
$pseudo = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pseudo = trim($_POST['pseudo'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mdp = $_POST['mot_de_passe'] ?? '';
    $mdp2 = $_POST['mot_de_passe_confirmation'] ?? '';

    // Vérifications en PHP (le required du HTML ne suffit pas)
    if (strlen($pseudo) < 3 || strlen($pseudo) > 20) {
        $erreurs[] = 'Le pseudo doit faire entre 3 et 20 caractères.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreurs[] = "L'email n'est pas valide.";
    }
    if (strlen($mdp) < 8) {
        $erreurs[] = 'Le mot de passe doit faire au moins 8 caractères.';
    }
    if ($mdp !== $mdp2) {
        $erreurs[] = 'Les deux mots de passe ne sont pas identiques.';
    }
    if (empty($erreurs) && utilisateurExiste($dbh, $pseudo, $email)) {
        $erreurs[] = 'Ce pseudo ou cet email est déjà utilisé.';
    }

    // Tout est bon : on crée le compte et on connecte directement le joueur
    if (empty($erreurs)) {
        $id = creerUtilisateur($dbh, $pseudo, $email, $mdp);
        session_regenerate_id(true);
        $_SESSION['user_id'] = $id;
        $_SESSION['pseudo'] = $pseudo;
        header('Location: index.php');
        exit;
    }
}

$titre = 'Inscription';
require 'header.php';
?>
<main class="inscription">
    <h2>Créer un compte</h2>

    <?php foreach ($erreurs as $erreur) { ?>
        <p class="erreur"><?= htmlspecialchars($erreur) ?></p>
    <?php } ?>

    <form method="post" action="inscription.php">
        <div class="champ">
            <label for="pseudo">Pseudo</label>
            <input type="text" id="pseudo" name="pseudo" placeholder="Votre pseudo"
                   minlength="3" maxlength="20" value="<?= htmlspecialchars($pseudo) ?>" required>
        </div>

        <div class="champ">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" placeholder="Votre email"
                   value="<?= htmlspecialchars($email) ?>" required>
        </div>

        <div class="champ">
            <label for="mdp">Mot de passe</label>
            <input type="password" id="mdp" name="mot_de_passe" placeholder="8 caractères minimum" minlength="8" required>
        </div>

        <div class="champ">
            <label for="mdp2">Confirmer le mot de passe</label>
            <input type="password" id="mdp2" name="mot_de_passe_confirmation" placeholder="Répétez le mot de passe" minlength="8" required>
        </div>

        <button type="submit" class="btn-inscription">Créer le compte</button>

        <p class="lien-connexion">Déjà un compte ? <a href="connexion.php">Se connecter</a></p>
    </form>
</main>
<?php require 'footer.php'; ?>
