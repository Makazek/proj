<?php
session_start();
require '../config.php';

if (!in_array($_SESSION['role'], ['superviseur', 'admin'])) {
    echo '<div class="alert alert-danger">Accès refusé</div>';
    exit;
}

$stmt = $pdo->prepare("
    SELECT d.id, d.numero_dossier, d.nom_assure, d.regime, tp.nom AS type_prestation, 
           u.nom_complet AS agent_nom, d.date_soumission_chef
    FROM dossiers d
    JOIN types_prestation tp ON tp.id = d.type_prestation_id
    LEFT JOIN users u ON u.id = d.controleur_id
    WHERE d.statut = 'soumis_chef'
    ORDER BY d.date_soumission_chef ASC
");
$stmt->execute();
$dossiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php if (empty($dossiers)): ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-check2-circle fs-1 mb-3 text-success"></i>
        <h5>Aucun dossier en attente</h5>
    </div>
<?php else: ?>
    <div class="list-group list-group-flush">
        <?php foreach ($dossiers as $d): ?>
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="fw-bold mb-1"><?= htmlspecialchars($d['numero_dossier']) ?></h6>
                    <small class="text-muted">
                        <?= htmlspecialchars($d['nom_assure']) ?> (<?= $d['regime'] ?>) - <?= htmlspecialchars($d['type_prestation']) ?><br>
                        Par <?= htmlspecialchars($d['agent_nom']) ?> • Soumis le <?= date('d/m/Y', strtotime($d['date_soumission_chef'])) ?>
                    </small>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-success btn-sm" onclick="validerDossier(<?= $d['id'] ?>)">
                        <i class="bi bi-check-lg"></i>
                    </button>
                    <button class="btn btn-danger btn-sm" onclick="rejeterDossier(<?= $d['id'] ?>)">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>