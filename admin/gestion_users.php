<?php
session_start();
require '../config.php';
require '../includes/auth.php';

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$success = null;
$error   = null;

/* =========================
   CRÉATION UTILISATEUR
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['creer_user'])) {

    $username    = trim($_POST['username']);
    $nom_complet = trim($_POST['nom_complet']);
    $passwordRaw = $_POST['password'];
    $role        = $_POST['role'];

    if (strlen($passwordRaw) < 6) {
        $error = "Le mot de passe doit contenir au moins 6 caractères";
    } else {
        // Vérifie unicité username
        $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $check->execute([$username]);

        if ($check->fetch()) {
            $error = "Ce nom d'utilisateur existe déjà";
        } else {
            $password = password_hash($passwordRaw, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO users (username, nom_complet, password, role)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$username, $nom_complet, $password, $role]);

            $success = "Utilisateur créé avec succès";
        }
    }
}

/* =========================
   CHANGEMENT DE RÔLE
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['changer_role'])) {

    $user_id = (int)$_POST['user_id'];
    $role    = $_POST['nouveau_role'];

    if ($user_id === (int)$_SESSION['user_id']) {
        $error = "Vous ne pouvez pas changer votre propre rôle";
    } else {
        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->execute([$role, $user_id]);
        $success = "Rôle mis à jour avec succès";
    }
}

/* =========================
   CHANGEMENT MOT DE PASSE
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['changer_mdp'])) {

    $user_id     = (int)$_POST['user_id'];
    $newPassword = $_POST['new_password'];

    if (strlen($newPassword) < 6) {
        $error = "Mot de passe trop court (6 caractères minimum)";
    } else {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hash, $user_id]);

        $success = "Mot de passe mis à jour avec succès";
    }
}

/* =========================
   LISTE UTILISATEURS
========================= */
$stmt = $pdo->query("SELECT * FROM users ORDER BY role DESC, nom_complet");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des utilisateurs – CNSS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="with-sidebar">

<?php include '../includes/sidebar.php'; ?>

<main class="main-content">

    <h2 class="h3 mb-4 text-primary fw-bold">Gestion des utilisateurs</h2>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- =========================
         CRÉATION UTILISATEUR
    ========================== -->
    <div class="card shadow-sm mb-5">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Créer un utilisateur</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Nom complet</label>
                        <input type="text" name="nom_complet" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Nom d'utilisateur</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Mot de passe</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Rôle</label>
                        <select name="role" class="form-select" required>
                            <option value="controleur">Contrôleur</option>
                            <option value="superviseur">Superviseur</option>
                            <option value="admin">Administrateur</option>
                        </select>
                    </div>
                </div>

                <div class="mt-3 text-end">
                    <button type="submit" name="creer_user" class="btn btn-primary">
                        <i class="bi bi-person-plus"></i> Créer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- =========================
         LISTE UTILISATEURS
    ========================== -->
    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h5 class="mb-0">Utilisateurs existants</h5>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nom</th>
                        <th>Username</th>
                        <th>Rôle</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= htmlspecialchars($u['nom_complet'] ?? '-') ?></td>
                        <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                        <td>
                            <span class="badge bg-<?= $u['role'] === 'admin' ? 'danger' : ($u['role'] === 'superviseur' ? 'warning' : 'primary') ?>">
                                <?= ucfirst($u['role']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($u['id'] !== $_SESSION['user_id']): ?>

                                <!-- Changer rôle -->
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <select name="nouveau_role" class="form-select form-select-sm d-inline w-auto">
                                        <option value="controleur" <?= $u['role'] === 'controleur' ? 'selected' : '' ?>>Contrôleur</option>
                                        <option value="superviseur" <?= $u['role'] === 'superviseur' ? 'selected' : '' ?>>Superviseur</option>
                                        <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                    </select>
                                    <button type="submit" name="changer_role"
                                            class="btn btn-sm btn-outline-primary ms-1">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </button>
                                </form>

                                <!-- Changer mot de passe -->
                                <button class="btn btn-sm btn-outline-warning ms-2"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#mdp<?= $u['id'] ?>">
                                    <i class="bi bi-key"></i>
                                </button>

                                <div class="collapse mt-2" id="mdp<?= $u['id'] ?>">
                                    <div class="card card-body">
                                        <form method="POST">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <input type="password"
                                                   name="new_password"
                                                   class="form-control form-control-sm mb-2"
                                                   placeholder="Nouveau mot de passe"
                                                   required>
                                            <button type="submit"
                                                    name="changer_mdp"
                                                    class="btn btn-sm btn-warning w-100">
                                                Mettre à jour
                                            </button>
                                        </form>
                                    </div>
                                </div>

                            <?php else: ?>
                                <span class="text-muted">Vous-même</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
