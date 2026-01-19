<?php
// logout.php - Déconnexion sécurisée

session_start();

// Vide toutes les variables de session
$_SESSION = [];

// Supprime le cookie de session si présent
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Détruit la session
session_destroy();

// Redirection immédiate vers la page de login
header('Location: index.php');
exit;
?>