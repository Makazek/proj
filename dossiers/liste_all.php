<?php
session_start();
require '../config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['superviseur', 'admin'])) {
    header('Location: ../index.php');
    exit;
}

// Récupère tous les dossiers
$stmt = $pdo->query("
    SELECT d.*, tp.nom as type_prestation_nom, u.nom_complet as controleur_nom
    FROM dossiers d 
    LEFT JOIN types_prestation tp ON d.type_prestation_id = tp.id 
    LEFT JOIN users u ON d.controleur_id = u.id 
    ORDER BY d.date_reception DESC
");
$dossiers = $stmt->fetchAll();

// Récupère la liste des contrôleurs pour le changement
$stmt = $pdo->query("SELECT id, nom_complet, username FROM users WHERE role = 'controleur' ORDER BY nom_complet");
$controleurs = $stmt->fetchAll();

// Traitement changement de contrôleur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['changer_controleur'])) {
    $dossier_id = $_POST['dossier_id'];
    $nouveau_controleur = $_POST['nouveau_controleur'] ?: null;

    $stmt = $pdo->prepare("UPDATE dossiers SET controleur_id = ? WHERE id = ?");
    $stmt->execute([$nouveau_controleur, $dossier_id]);

    // Log historique
    $action = $nouveau_controleur ? "Réaffecté à un nouveau contrôleur" : "Contrôleur retiré";
    $stmt = $pdo->prepare("INSERT INTO historique (dossier_id, user_id, action, commentaire) VALUES (?, ?, ?, ?)");
    $stmt->execute([$dossier_id, $_SESSION['user_id'], $action, "Changement par le superviseur"]);

    header('Location: liste_all.php?success=1');
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tous les dossiers - CNSS (Superviseur)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="with-sidebar">
    <?php include '../includes/sidebar.php'; ?>

    <header class="position-fixed top-0 end-0 p-3 bg-white shadow-sm" style="z-index: 1030;">
        <div class="d-flex align-items-center">
            <div class="text-end me-3">
                <div class="fw-bold"><?= htmlspecialchars($_SESSION['nom_complet'] ?? $_SESSION['username']) ?></div>
                <small class="text-muted text-capitalize"><?= $_SESSION['role'] ?></small>
            </div>
            <a href="../logout.php" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </header>

    <main class="main-content">
        <h2 class="h3 mb-4 text-primary fw-bold">Tous les dossiers du service</h2>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                Contrôleur mis à jour avec succès.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-primary">
                            <tr>
                                <th>Numéro</th>
                                <th>Type prestation</th>
                                <th>Date réception</th>
                                <th>Contrôleur actuel</th>
                                <th>Statut</th>
                                <th>Délai restant</th>
                                <th>Changer contrôleur</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($dossiers)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">Aucun dossier</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($dossiers as $d): 
                                    $jours = (new DateTime())->diff(new DateTime($d['date_reception']))->days;
                                    $delai = 10 - $jours;
                                    $badge = $delai > 2 ? 'bg-success' : ($delai > 0 ? 'bg-warning' : 'bg-danger');
                                    $delai_text = $delai > 0 ? "$delai jours" : "Retard " . abs($delai) . " jours";
                                ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($d['numero_dossier']) ?></strong></td>
                                        <td><?= htmlspecialchars($d['type_prestation_nom']) ?></td>
                                        <td><?= date('d/m/Y', strtotime($d['date_reception'])) ?></td>
                                        <td><?= htmlspecialchars($d['controleur_nom'] ?? 'Non assigné') ?></td>
                                        <td>
                                            <span class="badge <?= $d['statut'] === 'valide' ? 'bg-success' : ($d['statut'] === 'rejete' ? 'bg-danger' : 'bg-warning') ?>">
                                                <?= ucfirst(str_replace('_', ' ', $d['statut'])) ?>
                                            </span>
                                        </td>
                                        <td><span class="badge <?= $badge ?>"><?= $delai_text ?></span></td>
                                        <td>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="dossier_id" value="<?= $d['id'] ?>">
                                                <select name="nouveau_controleur" class="form-select form-select-sm d-inline w-auto">
                                                    <option value="">Non assigné</option>
                                                    <?php foreach ($controleurs as $c): ?>
                                                        <option value="<?= $c['id'] ?>" <?= $d['controleur_id'] == $c['id'] ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($c['nom_complet'] ?? $c['username']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" name="changer_controleur" class="btn btn-sm btn-outline-primary ms-2">
                                                    <i class="bi bi-arrow-repeat"></i>
                                                </button>
                                            </form>
                                        </td>
                                        <td>
                                            <a href="detail.php?id=<?= $d['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>