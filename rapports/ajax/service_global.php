<?php
require '../../config.php';

$kpi = $pdo->query("
    SELECT
        COUNT(*) AS recus,
        SUM(statut IN ('termine','valide','rejete')) AS traites,
        SUM(statut = 'valide') AS valides,
        SUM(statut = 'rejete') AS rejetes,
        SUM(DATEDIFF(CURDATE(), date_reception) <= 10) AS dans_delai
    FROM dossiers
")->fetch(PDO::FETCH_ASSOC);

$taux_productivite = $kpi['recus'] > 0 ? round(($kpi['traites'] / $kpi['recus']) * 100, 1) : 0;
$taux_conformite = $kpi['traites'] > 0 ? round(($kpi['valides'] / $kpi['traites']) * 100, 1) : 0;
$taux_rejet = $kpi['traites'] > 0 ? round(($kpi['rejetes'] / $kpi['traites']) * 100, 1) : 0;
$taux_delai = $kpi['traites'] > 0 ? round(($kpi['dans_delai'] / $kpi['traites']) * 100, 1) : 0;
?>

<div class="row g-4">
    <div class="col-md-3">
        <div class="card text-center shadow-sm">
            <div class="card-body">
                <div class="fs-1 fw-bold text-primary"><?= $taux_productivite ?>%</div>
                <small class="text-muted">Productivité service</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center shadow-sm">
            <div class="card-body">
                <div class="fs-1 fw-bold text-success"><?= $taux_conformite ?>%</div>
                <small class="text-muted">Conformité qualité</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center shadow-sm">
            <div class="card-body">
                <div class="fs-1 fw-bold text-danger"><?= $taux_rejet ?>%</div>
                <small class="text-muted">Taux de rejet</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center shadow-sm">
            <div class="card-body">
                <div class="fs-1 fw-bold text-info"><?= $taux_delai ?>%</div>
                <small class="text-muted">Respect des délais</small>
            </div>
        </div>
    </div>
</div>