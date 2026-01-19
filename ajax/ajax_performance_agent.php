<?php
session_start();
require __DIR__ . '/../config.php';

/* =========================
   SÉCURITÉ
========================= */
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['superviseur','admin'])) {
    echo '<div class="alert alert-danger">Accès refusé</div>';
    exit;
}

/* =========================
   PARAMÈTRES
========================= */
$controleur_id = intval($_GET['controleur_id'] ?? 0);

$debut = $_GET['debut'] ?? null;
$fin   = $_GET['fin'] ?? null;

if ($controleur_id <= 0) {
    echo '<div class="alert alert-danger">Contrôleur invalide</div>';
    exit;
}

/* =========================
   RÉCUP INFO CONTRÔLEUR
========================= */
$stmt = $pdo->prepare("SELECT nom_complet FROM users WHERE id = ? AND role = 'controleur'");
$stmt->execute([$controleur_id]);
$agent = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$agent) {
    echo '<div class="alert alert-danger">Contrôleur introuvable</div>';
    exit;
}

/* =========================
   CONSTRUCTION FILTRE DATE
========================= */
$whereDate = '';
$params = [$controleur_id];

if (!empty($debut) && !empty($fin)) {
    $whereDate = "AND d.date_reception BETWEEN ? AND ?";
    $params[] = $debut;
    $params[] = $fin;
}

/* =========================
   KPI PRINCIPAUX
========================= */
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(d.statut = 'valide') AS valides,
        SUM(d.statut = 'rejete') AS rejetes,
        SUM(d.statut = 'soumis_chef') AS soumis,
        SUM(DATEDIFF(CURDATE(), d.date_reception) > 10) AS en_retard,
        ROUND(AVG(
            CASE 
                WHEN d.statut IN ('valide','rejete') 
                THEN DATEDIFF(COALESCE(d.date_validation, CURDATE()), d.date_reception)
            END
        ),1) AS delai_moyen
    FROM dossiers d
    WHERE d.controleur_id = ?
    $whereDate
");
$stmt->execute($params);
$kpi = $stmt->fetch(PDO::FETCH_ASSOC);

/* =========================
   KPI CALCULÉS
========================= */
$total_traités = $kpi['valides'] + $kpi['rejetes'];

$taux_productivite = $kpi['total'] > 0
    ? round(($total_traités / $kpi['total']) * 100, 1)
    : 0;

$taux_conformite = $total_traités > 0
    ? round(($kpi['valides'] / $total_traités) * 100, 1)
    : 0;

$taux_rejet = $total_traités > 0
    ? round(($kpi['rejetes'] / $total_traités) * 100, 1)
    : 0;

$taux_delai = $total_traités > 0
    ? round((($total_traités - $kpi['en_retard']) / $total_traités) * 100, 1)
    : 0;

/* =========================
   RÉPARTITION PAR TYPE
========================= */
$stmt = $pdo->prepare("
    SELECT t.nom,
           COUNT(d.id) AS total,
           SUM(d.statut = 'valide') AS valides
    FROM dossiers d
    JOIN types_prestation t ON t.id = d.type_prestation_id
    WHERE d.controleur_id = ?
    $whereDate
    GROUP BY t.id
");
$stmt->execute($params);
$types = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- =========================
     UI PERFORMANCE AGENT
========================= -->
<div class="container-fluid">

    <!-- HEADER -->
    <div class="mb-4">
        <h4 class="fw-bold mb-1"><?= htmlspecialchars($agent['nom_complet']) ?></h4>
        <small class="text-muted">
            Performance individuelle
            <?php if ($debut && $fin): ?>
                (<?= date('d/m/Y', strtotime($debut)) ?> → <?= date('d/m/Y', strtotime($fin)) ?>)
            <?php else: ?>
                (toutes périodes)
            <?php endif; ?>
        </small>
    </div>

    <!-- KPI -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card text-center shadow-sm">
                <div class="card-body">
                    <div class="fs-3 fw-bold text-primary"><?= $kpi['total'] ?></div>
                    <div class="text-muted">Dossiers reçus</div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card text-center shadow-sm">
                <div class="card-body">
                    <div class="fs-3 fw-bold text-success"><?= $kpi['valides'] ?></div>
                    <div class="text-muted">Validés</div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card text-center shadow-sm">
                <div class="card-body">
                    <div class="fs-3 fw-bold text-danger"><?= $kpi['rejetes'] ?></div>
                    <div class="text-muted">Rejetés</div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card text-center shadow-sm">
                <div class="card-body">
                    <div class="fs-3 fw-bold text-warning"><?= $kpi['en_retard'] ?></div>
                    <div class="text-muted">En retard</div>
                </div>
            </div>
        </div>
    </div>

    <!-- TAUX -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="alert alert-primary text-center">
                <strong><?= $taux_productivite ?>%</strong><br>
                Productivité
            </div>
        </div>
        <div class="col-md-3">
            <div class="alert alert-success text-center">
                <strong><?= $taux_conformite ?>%</strong><br>
                Conformité qualité
            </div>
        </div>
        <div class="col-md-3">
            <div class="alert alert-danger text-center">
                <strong><?= $taux_rejet ?>%</strong><br>
                Taux de rejet
            </div>
        </div>
        <div class="col-md-3">
            <div class="alert alert-info text-center">
                <strong><?= $taux_delai ?>%</strong><br>
                Respect délais
            </div>
        </div>
    </div>

    <!-- TYPES -->
    <h6 class="fw-semibold mb-3">Répartition par type de prestation</h6>
    <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th>Type</th>
                    <th>Total</th>
                    <th>Validés</th>
                    <th>Taux</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($types as $t): 
                    $tx = $t['total'] > 0 ? round(($t['valides'] / $t['total']) * 100) : 0;
                ?>
                <tr>
                    <td><?= htmlspecialchars($t['nom']) ?></td>
                    <td><?= $t['total'] ?></td>
                    <td><?= $t['valides'] ?></td>
                    <td>
                        <span class="badge bg-<?= $tx >= 70 ? 'success' : ($tx >= 40 ? 'warning' : 'danger') ?>">
                            <?= $tx ?>%
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>
<div class="d-flex justify-content-end mt-4 gap-2">
    <a href="../ajax/export_performance_agent.php
        ?controleur_id=<?= $controleur_id ?>
        &debut=<?= urlencode($debut) ?>
        &fin=<?= urlencode($fin) ?>"
       class="btn btn-outline-primary">
        <i class="bi bi-file-earmark-pdf me-2"></i>
        Télécharger le rapport PDF
    </a>
</div>