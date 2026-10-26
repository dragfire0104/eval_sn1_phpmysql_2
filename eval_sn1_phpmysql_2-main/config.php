<?php
session_start();

try {
    $dbh = new PDO('mysql:host=localhost;dbname=juste_prix;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('Erreur de connexion : ' . $e->getMessage());
}

require_once __DIR__ . '/requetes.php';

// Le joueur est-il connecté ?
function estConnecte()
{
    return isset($_SESSION['user_id']);
}

// Page réservée aux joueurs connectés : sinon, direction la connexion
function exigerConnexion()
{
    if (!estConnecte()) {
        header('Location: connexion.php');
        exit;
    }
}
