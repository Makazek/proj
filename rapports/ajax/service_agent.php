<?php
require '../../config.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    echo '<div class="alert alert-danger">ID agent invalide</div>';
    exit;
}

// Infos agent
$stmt = $pdo->prepare("SELECT nom_complet FROM users WHERE id = ? AND role = 'controleur'");
$stmt->execute([$id]);
$agent = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$agent) {
    echo '<div class="alert alert-danger">Contrôleur introuvable</div>';
    exit;
}

// KPI agent
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) AS total,
        SUM(statut = 'valide') AS valides,
        SUM(statut = 'rejete') AS rejetes,
        SUM(DATEDIFF(CURDATE(), date_reception) > 10 AND statut NOT IN ('valide', 'rejete')) AS en_retard,
        ROUND(AVG(CASE WHEN statut = 'valide' THEN DATEDIFF(date_validation, date_reception) END), 1) AS delai_moyen
    FROM dossiers
    WHERE controleur_id = ?
");
$stmt->execute([$id]);
$kpi = $stmt->fetch(PDO::FETCH_ASSOC);

// Taux
$taux_validation = $kpi['total'] > 0 ? round(($kpi['valides'] / $kpi['total']) * 100, 1) : 0;

$taux_retard = $kpi['total'] > 0 ? round(($kpi['en_retard'] / $kpi['total']) * 100, 1) : 0;

// Évolution agent
$stmt = $pdo->prepare("
    SELECT DATE_FORMAT(date_validation, '%b %Y') AS mois, COUNT(*) AS nb
    FROM dossiers
    WHERE statut = 'valide' AND date_validation IS NOT NULL AND controleur_id = ?
    GROUP BY mois
    ORDER BY date_validation DESC
    LIMIT 6
");
$stmt->execute([$id]);
$evolution = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
?>

<div class="card shadow-sm">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><?= htmlspecialchars($agent['nom_complet']) ?></h5>
    </div>
    <div class="card-body">
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card h-100 text-center shadow-sm">
                    <div class="card-body">
                        <div class="fs-2 fw-bold text-primary"><?= $kpi['total'] ?? 0 ?></div>
                        <small class="text-muted">Dossiers assignés</small>
                        <p class="small mt-2 text-muted">Dont <?= $kpi['valides'] ?> validés et <?= $kpi['rejetes'] ?> rejetés</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 text-center shadow-sm">
                    <div class="card-body">
                        <div class="fs-2 fw-bold text-success"><?= $taux_validation ?>%</div>
                        <small class="text-muted">Taux validation</small>
                        <p class="small mt-2 text-muted">Performance globale du contrôleur</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 text-center shadow-sm">
                    <div class="card-body">
                        <div class="fs-2 fw-bold text-info"><?= $kpi['delai_moyen'] ?? 'N/A' ?> j</div>
                        <small class="text-muted">Délai moyen</small>
                        <p class="small mt-2 text-muted">Durée moyenne de traitement des dossiers</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 text-center shadow-sm">
                    <div class="card-body">
                        <div class="fs-2 fw-bold text-danger"><?= $kpi['en_retard'] ?? 0 ?></div>
                        <small class="text-muted">En retard</small>
                        <p class="small mt-2 text-muted">Dossiers en cours >10 jours</p>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card h-100 shadow-sm">
                    <div class="card-body p-3">
                        <canvas id="chartEvolution"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center">
            <a href="pdf/agent_pdf.php?id=<?= $id ?>" class="btn btn-success btn-lg">
                <i class="bi bi-file-earmark-pdf me-2"></i> Générer PDF agent
            </a>
        </div>
    </div>
</div>

<script>
    const ctx = document.getElementById('chartEvolution').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: [<?php foreach ($evolution as $e) echo "'".$e['mois']."',"; ?>],
            datasets: [{
                label: 'Validés',
                data: [<?php foreach ($evolution as $e) echo $e['nb'].","; ?>],
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                tension: 0.4,
                fill: true,
                pointRadius: 0
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true, display: false },
                x: { display: true }
            }
        }
    });
</script>