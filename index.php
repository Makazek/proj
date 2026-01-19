<?php
session_start();
require 'config.php';

/*
|--------------------------------------------------------------------------
| Si l'utilisateur est déjà connecté → redirection selon le rôle
|--------------------------------------------------------------------------
*/
if (isset($_SESSION['user_id'], $_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'controleur':
            header('Location: agent/dashboard_agent.php');
            break;

        case 'superviseur':
            header('Location: chef/dashboard_chef.php');
            break;

        case 'admin':
            header('Location: admin/dashboard_admin.php');
            break;

        default:
            session_destroy();
            header('Location: index.php');
    }
    exit;
}

$error = '';

/*
|--------------------------------------------------------------------------
| Traitement du formulaire de connexion
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = "Veuillez remplir tous les champs";
    } else {

        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {

            // Création session
            $_SESSION['user_id']     = $user['id'];
            $_SESSION['username']    = $user['username'];
            $_SESSION['role']        = $user['role'];
            $_SESSION['nom_complet'] = $user['nom_complet'];

            // Historique connexion
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
            $stmt = $pdo->prepare("
                INSERT INTO historique (user_id, action, commentaire, ip_address)
                VALUES (?, 'Connexion réussie', 'Connexion à l’application', ?)
            ");
            $stmt->execute([$user['id'], $ip]);

            // Redirection SELON LE RÔLE
            switch ($user['role']) {
                case 'controleur':
                    header('Location: agent/dashboard_agent.php');
                    break;

                case 'superviseur':
                    header('Location: chef/dashboard_chef.php');
                    break;

                case 'admin':
                    header('Location: admin/dashboard_admin.php');
                    break;

                default:
                    session_destroy();
                    header('Location: index.php');
            }
            exit;

        } else {
            $error = "Identifiants incorrects";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion – CNSS Djibouti</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <!-- Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="login-page">

    <!-- Spotlight -->
    <div id="spotlight"></div>

    <!-- Login Card -->
    <div class="login-card">
        <div class="login-header">
            <img src="assets/images/logo_cnss.png" alt="CNSS Djibouti">
            <h3 class="mb-0">Caisse Nationale de Sécurité Sociale</h3>
            <p class="mb-0 opacity-75">Application de Suivi des Dossiers</p>
        </div>

        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <div class="mb-4">
                    <label class="form-label">Nom d'utilisateur</label>
                    <input type="text" name="username" class="form-control" required autofocus>
                </div>

                <div class="mb-4">
                    <label class="form-label">Mot de passe</label>
                    <input type="password" name="password" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-login w-100">
                    <i class="bi bi-box-arrow-in-right me-2"></i> Se connecter
                </button>
            </form>

            <div class="text-center mt-4">
                <small class="text-muted">
                    © 2025 CNSS Djibouti – Tous droits réservés
                </small>
            </div>
        </div>
    </div>

    <!-- JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        const spotlight = document.getElementById('spotlight');
        document.addEventListener('mousemove', (e) => {
            spotlight.style.left = e.clientX + 'px';
            spotlight.style.top  = e.clientY + 'px';
        });
    </script>

</body>
</html>
