<?php
session_start();
require '../config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['superviseur','admin'])) {
    die('Accès refusé');
}

/* =========================
   PÉRIODE
========================= */
$mois = $_GET['mois'] ?? date('Y-m');
$debut = $mois . '-01';
$fin   = date('Y-m-t', strtotime($debut));

/* =========================
   KPI GLOBAUX SERVICE
========================= */
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(statut = 'valide') AS valides,
        SUM(statut = 'rejete') AS rejetes,
        SUM(statut = 'soumis_chef') AS attente,
        ROUND(AVG(
            CASE 
                WHEN date_validation IS NOT NULL 
                THEN DATEDIFF(date_validation, date_reception)
            END
        ),1) AS delai_moyen
    FROM dossiers
    WHERE date_reception BETWEEN ? AND ?
");
$stmt->execute([$debut, $fin]);
$kpi = $stmt->fetch(PDO::FETCH_ASSOC);

/* =========================
   PERFORMANCE PAR CONTRÔLEUR
========================= */
$stmt = $pdo->prepare("
    SELECT 
        u.id AS controleur_id,
        u.nom_complet,
        COUNT(d.id) AS total,
        SUM(d.statut = 'valide') AS valides,
        SUM(d.statut = 'rejete') AS rejetes,
        ROUND(AVG(
            CASE 
                WHEN d.date_validation IS NOT NULL 
                THEN DATEDIFF(d.date_validation, d.date_reception)
            END
        ),1) AS delai_moyen
    FROM users u
    LEFT JOIN dossiers d 
        ON d.controleur_id = u.id
        AND d.date_reception BETWEEN ? AND ?
    WHERE u.role = 'controleur'
    GROUP BY u.id
    ORDER BY valides DESC
");
$stmt->execute([$debut, $fin]);
$agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Rapport Service</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="with-sidebar">
<?php include '../includes/sidebar.php'; ?>

<main class="main-content">

<h2 class="fw-bold text-primary mb-4">
    Rapport du service – <?= date('F Y', strtotime($debut)) ?>
</h2>

<!-- KPI GLOBAUX -->
<div class="row g-4 mb-5">
    <div class="col-md-3">
        <div class="kpi-card">
            <i class="bi bi-folder kpi-icon"></i>
            <div class="kpi-number"><?= $kpi['total'] ?></div>
            <div class="kpi-label">Dossiers reçus</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card">
            <i class="bi bi-check-circle kpi-icon text-success"></i>
            <div class="kpi-number"><?= $kpi['valides'] ?></div>
            <div class="kpi-label">Validés</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card">
            <i class="bi bi-x-circle kpi-icon text-danger"></i>
            <div class="kpi-number"><?= $kpi['rejetes'] ?></div>
            <div class="kpi-label">Rejetés</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card">
            <i class="bi bi-stopwatch kpi-icon"></i>
            <div class="kpi-number"><?= $kpi['delai_moyen'] ?? '—' ?> j</div>
            <div class="kpi-label">Délai moyen</div>
        </div>
    </div>
</div>

<!-- TABLE PERFORMANCE -->
<div class="card shadow-sm">
<div class="card-header bg-white">
    <h5 class="mb-0">Performance par contrôleur</h5>
</div>

<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-hover align-middle mb-0">
<thead class="table-light">
<tr>
    <th>#</th>
    <th>Contrôleur</th>
    <th>Dossiers</th>
    <th>Validés</th>
    <th>Rejetés</th>
    <th>Délai moyen</th>
    <th>Actions</th>
</tr>
</thead>
<tbody>
<?php foreach ($agents as $i => $a): ?>
<tr>
    <td><strong>#<?= $i+1 ?></strong></td>
    <td><?= htmlspecialchars($a['nom_complet']) ?></td>
    <td><?= $a['total'] ?></td>
    <td><span class="badge bg-success"><?= $a['valides'] ?></span></td>
    <td><span class="badge bg-danger"><?= $a['rejetes'] ?></span></td>
    <td><?= $a['delai_moyen'] ?? '-' ?> j</td>
    <td>
        <button class="btn btn-sm btn-outline-primary"
                onclick="ouvrirPerformance(<?= $a['controleur_id'] ?>)">
            <i class="bi bi-bar-chart"></i>
        </button>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
</div>

</main>

<!-- MODAL AJAX -->
<div class="modal fade" id="modalPerf" tabindex="-1">
<div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title fw-bold">Performance détaillée</h5>
<button class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body" id="modalPerfContent">
<div class="text-center text-muted py-5">Chargement…</div>
</div>
</div>
</div>
</div>

<script>
function ouvrirPerformance(controleurId) {
    fetch('../rapports/ajax/ajax_performance_agent.php?controleur_id=' + controleurId)
        .then(r => r.text())
        .then(html => {
            document.getElementById('modalPerfContent').innerHTML = html;
            new bootstrap.Modal(document.getElementById('modalPerf')).show();
        });
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
