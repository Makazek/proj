<?php
session_start();
require '../config.php';

$dossier_id = intval($_GET['id'] ?? 0);
if ($dossier_id <= 0) {
    echo '<div class="alert alert-danger">ID invalide</div>';
    exit;
}

// Vérifie que le dossier est soumis_chef
$stmt = $pdo->prepare("SELECT statut FROM dossiers WHERE id = ?");
$stmt->execute([$dossier_id]);
if ($stmt->fetchColumn() !== 'soumis_chef') {
    echo '<div class="alert alert-danger">Dossier non soumis</div>';
    exit;
}

// Infos dossier
$stmt = $pdo->prepare("
    SELECT d.numero_dossier, d.nom_assure, d.regime, tp.nom AS type_prestation, u.nom_complet AS agent_nom
    FROM dossiers d
    JOIN types_prestation tp ON tp.id = d.type_prestation_id
    LEFT JOIN users u ON u.id = d.controleur_id
    WHERE d.id = ?
");
$stmt->execute([$dossier_id]);
$d = $stmt->fetch(PDO::FETCH_ASSOC);

// Checklist
$stmt = $pdo->prepare("
    SELECT pf.original_fourni, pf.copie_fourni, pf.valide, pr.nom_piece
    FROM pieces_fournies pf
    JOIN pieces_requises pr ON pr.id = pf.piece_requise_id
    WHERE pf.dossier_id = ?
    ORDER BY pr.ordre
");
$stmt->execute([$dossier_id]);
$pieces = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
    <h5 class="fw-bold mb-3">Dossier : <?= htmlspecialchars($d['numero_dossier']) ?></h5>
    <p class="mb-1"><strong>Assuré :</strong> <?= htmlspecialchars($d['nom_assure']) ?></p>
    <p class="mb-1"><strong>Régime :</strong> <?= $d['regime'] ?></p>
    <p class="mb-1"><strong>Type :</strong> <?= htmlspecialchars($d['type_prestation']) ?></p>
    <p class="mb-3"><strong>Contrôleur :</strong> <?= htmlspecialchars($d['agent_nom']) ?></p>

    <h6 class="fw-semibold mb-3">Checklist des pièces jointes</h6>
    <div class="row g-3">
        <?php foreach ($pieces as $p): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <h6 class="card-title fw-medium mb-3"><?= htmlspecialchars($p['nom_piece']) ?></h6>
                        <div class="d-flex flex-column gap-2 small">
                            <span class="<?= $p['original_fourni'] ? 'text-success' : 'text-muted' ?>">
                                <i class="bi bi-<?= $p['original_fourni'] ? 'check-circle-fill' : 'circle' ?> me-2"></i> Original
                            </span>
                            <span class="<?= $p['copie_fourni'] ? 'text-success' : 'text-muted' ?>">
                                <i class="bi bi-<?= $p['copie_fourni'] ? 'check-circle-fill' : 'circle' ?> me-2"></i> Copie
                            </span>
                            <span class="<?= $p['valide'] ? 'text-success fw-bold' : 'text-danger fw-bold' ?>">
                                <i class="bi bi-<?= $p['valide'] ? 'check-circle-fill' : 'x-circle-fill' ?> me-2"></i> Conforme
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="text-center mt-5">
        <button class="btn btn-success btn-lg px-5" onclick="confirmerValidation(<?= $dossier_id ?>)">
            <i class="bi bi-check2-all me-2"></i> Confirmer la validation définitive
        </button>
    </div>
</div>