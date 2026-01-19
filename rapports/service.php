<?php
session_start();
require '../config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['superviseur','admin'])) {
    header('Location: ../index.php');
    exit;
}

/* =========================
   FILTRE PERIODE
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
                WHEN statut = 'valide' 
                THEN DATEDIFF(date_validation, date_reception)
            END
        ),1) AS delai_moyen
    FROM dossiers
    WHERE date_reception BETWEEN ? AND ?
");
$stmt->execute([$debut, $fin]);
$kpi = $stmt->fetch();

/* =========================
   PERFORMANCE PAR AGENT
========================= */
$stmt = $pdo->prepare("
    SELECT u.id,
           u.nom_complet,
           COUNT(d.id) AS total,
           SUM(d.statut = 'valide') AS valides,
           SUM(d.statut = 'rejete') AS rejetes,
           ROUND(AVG(
             CASE WHEN d.statut='valide'
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
$agents = $stmt->fetchAll();

/* =========================
   PERFORMANCE PAR PRESTATION
========================= */
$stmt = $pdo->prepare("
    SELECT t.nom,
           COUNT(d.id) AS total,
           SUM(d.statut='valide') AS valides,
           SUM(d.statut='rejete') AS rejetes,
           ROUND(AVG(
             CASE WHEN d.statut='valide'
             THEN DATEDIFF(d.date_validation, d.date_reception)
             END
           ),1) AS delai_moyen
    FROM types_prestation t
    LEFT JOIN dossiers d 
        ON d.type_prestation_id = t.id
        AND d.date_reception BETWEEN ? AND ?
    GROUP BY t.id
");
$stmt->execute([$debut, $fin]);
$prestations = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Rapport service</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="with-sidebar">
<?php include '../includes/sidebar.php'; ?>

<main class="main-content">

<h2 class="fw-bold text-primary mb-4">Rapport du service – <?= date('F Y', strtotime($debut)) ?></h2>

<!-- KPI -->
<div class="row g-4 mb-5">
<?php
$cards = [
    ['Total dossiers', $kpi['total'], 'folder'],
    ['Validés', $kpi['valides'], 'check-circle'],
    ['Rejetés', $kpi['rejetes'], 'x-circle'],
    ['En attente chef', $kpi['attente'], 'hourglass-split'],
];
foreach ($cards as $c):
?>
<div class="col-md-3">
    <div class="card shadow-sm text-center">
        <div class="card-body">
            <i class="bi bi-<?= $c[2] ?> fs-1 text-primary"></i>
            <h3 class="mt-2"><?= $c[1] ?></h3>
            <div class="text-muted"><?= $c[0] ?></div>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>

<!-- PERFORMANCE AGENTS -->
<h4 class="mb-3">Performance par contrôleur</h4>
<div class="table-responsive mb-5">
<table class="table table-hover align-middle">
<thead class="table-light">
<tr>
    <th>#</th>
    <th>Contrôleur</th>
    <th>Dossiers</th>
    <th>Validés</th>
    <th>Rejetés</th>
    <th>Délai moyen</th>
    <th></th>
</tr>
</thead>
<tbody>
<?php foreach ($agents as $i=>$a): ?>
<tr>
<td><?= $i+1 ?></td>
<td><?= htmlspecialchars($a['nom_complet']) ?></td>
<td><?= $a['total'] ?></td>
<td><span class="badge bg-success"><?= $a['valides'] ?></span></td>
<td><span class="badge bg-danger"><?= $a['rejetes'] ?></span></td>
<td><?= $a['delai_moyen'] ?? '-' ?> j</td>
<td>
<button class="btn btn-outline-primary btn-sm"
        onclick="ouvrirPerformance(<?= $a['id'] ?>)">
    <i class="bi bi-graph-up"></i> Détails
</button>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<!-- PERFORMANCE PRESTATION -->
<h4 class="mb-3">Performance par type de prestation</h4>
<div class="table-responsive">
<table class="table table-bordered">
<thead class="table-light">
<tr>
    <th>Prestation</th>
    <th>Total</th>
    <th>Validés</th>
    <th>Rejetés</th>
    <th>Délai moyen</th>
</tr>
</thead>
<tbody>
<?php foreach ($prestations as $p): ?>
<tr>
<td><?= htmlspecialchars($p['nom']) ?></td>
<td><?= $p['total'] ?></td>
<td><?= $p['valides'] ?></td>
<td><?= $p['rejetes'] ?></td>
<td><?= $p['delai_moyen'] ?? '-' ?> j</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

</main>

<!-- MODAL PERFORMANCE AGENT -->
<div class="modal fade" id="modalPerformance" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">

      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">
          <i class="bi bi-bar-chart-line me-2"></i>
          Performance individuelle du contrôleur
        </h5>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body" id="performanceContent">
        <div class="text-center py-5 text-muted">
          <div class="spinner-border text-primary mb-3"></div>
          <div>Chargement des indicateurs de performance…</div>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
function ouvrirPerformance(controleurId) {

    if (!controleurId) {
        alert("Contrôleur invalide");
        return;
    }

    const debut = document.getElementById('filtre_debut')?.value || '';
    const fin   = document.getElementById('filtre_fin')?.value || '';

    // Ouvre la modal
    const modalElement = document.getElementById('modalPerformance');
    const modal = new bootstrap.Modal(modalElement);
    modal.show();

    // Loader
    document.getElementById('performanceContent').innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary mb-3"></div>
            <div class="fw-semibold">Analyse des performances en cours…</div>
        </div>
    `;

    // AJAX
    fetch(`/cnss_suivi/ajax/ajax_performance_agent.php?controleur_id=${controleurId}&debut=${debut}&fin=${fin}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('performanceContent').innerHTML = html;
        })
        .catch(err => {
            document.getElementById('performanceContent').innerHTML = `
                <div class="alert alert-danger">
                    Erreur lors du chargement des données
                </div>
            `;
            console.error(err);
        });
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
