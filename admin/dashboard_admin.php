<?php
session_start();
require '../config.php';
require '../includes/auth.php';

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

/* KPI globaux */
$stmt = $pdo->query("
    SELECT
        COUNT(*) AS total_dossiers,

        SUM(
            CASE 
                WHEN statut = 'valide' 
                THEN 1 ELSE 0 
            END
        ) AS total_valides,

        SUM(
            CASE 
                WHEN statut = 'rejete' 
                THEN 1 ELSE 0 
            END
        ) AS total_rejetes,

        SUM(
            CASE
                WHEN statut NOT IN ('valide','rejete')
                 AND DATEDIFF(CURDATE(), date_reception) > 10
                THEN 1 ELSE 0
            END
        ) AS en_retard

    FROM dossiers
");
$kpi = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<?php if ($kpi['en_retard'] > 0): ?>
<div class="alert alert-danger">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    <strong><?= $kpi['en_retard'] ?></strong> dossiers dépassent le délai réglementaire de 10 jours.
</div>
<?php endif; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Administrateur – CNSS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="with-sidebar">

<?php include '../includes/sidebar.php'; ?>


<main class="main-content">

    <h2 class="h3 mb-4 text-primary fw-bold">Dashboard Administrateur</h2>

    <!-- KPI -->
    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h6 class="text-muted">Total dossiers</h6>
                    <h3 class="fw-bold text-primary"><?= $kpi['total_dossiers'] ?></h3>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h6 class="text-muted">Validés</h6>
                    <h3 class="fw-bold text-success"><?= $kpi['total_valides'] ?></h3>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h6 class="text-muted">Rejetés</h6>
                    <h3 class="fw-bold text-danger"><?= $kpi['total_rejetes'] ?></h3>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h6 class="text-muted">En retard (>10j)</h6>
                    <h3 class="fw-bold text-warning"><?= $kpi['en_retard'] ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Accès rapides -->
    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold">
            Accès rapides
        </div>
        <div class="card-body">
            <a href="gestion_users.php" class="btn btn-primary me-2">
                <i class="bi bi-people"></i> Gestion utilisateurs
            </a>
            <a href="dossiers_admin.php" class="btn btn-outline-secondary me-2">
                <i class="bi bi-folders"></i> Tous les dossiers
            </a>
            <a href="rapports_admin.php" class="btn btn-outline-success">
                <i class="bi bi-file-earmark-text"></i> Rapports
            </a>
        </div>
    </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
