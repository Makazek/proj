<?php
session_start();
require '../config.php';
require '../includes/auth.php';

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$stmt = $pdo->query("
    SELECT 
        d.id,
        d.numero_dossier,
        d.date_reception,
        d.statut,
        u.nom_complet AS controleur,
        t.nom AS type_prestation,
        DATEDIFF(CURDATE(), d.date_reception) AS jours_ecoules
    FROM dossiers d
    LEFT JOIN users u ON d.controleur_id = u.id
    JOIN types_prestation t ON d.type_prestation_id = t.id
    ORDER BY d.date_reception DESC
");
$dossiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Tous les dossiers – Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="with-sidebar">

<?php include '../includes/sidebar.php'; ?>

<main class="main-content">

    <h2 class="h3 mb-4 text-primary fw-bold">Tous les dossiers</h2>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>N° Dossier</th>
                        <th>Type</th>
                        <th>Contrôleur</th>
                        <th>Date réception</th>
                        <th>Statut</th>
                        <th>Retard</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dossiers as $d): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($d['numero_dossier']) ?></strong></td>
                            <td><?= htmlspecialchars($d['type_prestation']) ?></td>
                            <td><?= htmlspecialchars($d['controleur'] ?? '—') ?></td>
                            <td><?= date('d/m/Y', strtotime($d['date_reception'])) ?></td>

                            <td>
                                <?php
                                $badge = match($d['statut']) {
                                    'recu' => 'secondary',
                                    'en_cours' => 'info',
                                    'termine' => 'primary',
                                    'valide' => 'success',
                                    'rejete' => 'danger',
                                };
                                ?>
                                <span class="badge bg-<?= $badge ?>">
                                    <?= ucfirst($d['statut']) ?>
                                </span>
                            </td>

                            <td>
                                <?php if ($d['statut'] !== 'valide' && $d['statut'] !== 'rejete' && $d['jours_ecoules'] > 10): ?>
                                    <span class="badge bg-danger">
                                        <?= $d['jours_ecoules'] ?> j
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($dossiers)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                Aucun dossier trouvé
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
