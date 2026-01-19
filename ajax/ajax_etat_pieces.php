<?php
session_start();
require '../config.php';

$dossier_id = intval($_GET['id'] ?? 0);
if ($dossier_id <= 0) {
    echo '<div class="alert alert-danger">ID dossier invalide</div>';
    exit;
}

// Récup dossier
$stmt = $pdo->prepare("
    SELECT d.*, t.nom AS type_prestation
    FROM dossiers d
    JOIN types_prestation t ON t.id = d.type_prestation_id
    WHERE d.id = ?
");
$stmt->execute([$dossier_id]);
$dossier = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$dossier) {
    echo '<div class="alert alert-danger">Dossier introuvable</div>';
    exit;
}

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

// Historique rejets
$stmt = $pdo->prepare("
    SELECT dr.motif, dr.date_rejet, u.nom_complet AS user_nom
    FROM dossier_rejets dr
    LEFT JOIN users u ON u.id = dr.user_id
    WHERE dr.dossier_id = ?
    ORDER BY dr.date_rejet DESC
");
$stmt->execute([$dossier_id]);
$rejets = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
    <h5 class="fw-bold mb-3">Dossier : <?= htmlspecialchars($dossier['numero_dossier']) ?></h5>
<p class="mb-1"><strong>Assuré :</strong> <?= htmlspecialchars($dossier['nom_assure'] ?: 'Non renseigné') ?></p>
<p class="mb-1"><strong>Régime :</strong> 
    <span class="badge bg-secondary"><?= $dossier['regime'] === 'RP' ? 'Régime Privé' : 'Régime Général' ?></span>
</p>
<p class="mb-3"><strong>Type :</strong> <?= htmlspecialchars($dossier['type_prestation']) ?></p>

    <?php if ($dossier['date_debut_traitement']): ?>
        <div class="alert alert-info small mb-3">
            <i class="bi bi-calendar-check me-2"></i>
            Début de traitement : <?= date('d/m/Y', strtotime($dossier['date_debut_traitement'])) ?>
        </div>
    <?php endif; ?>

    <h6 class="fw-semibold mb-3">Checklist des pièces jointes</h6>
    <div class="row g-3 mb-4">
        <?php foreach ($pieces as $p): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body">
                        <h6 class="card-title fw-medium mb-3 text-break"><?= htmlspecialchars($p['nom_piece']) ?></h6>
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

    <?php if (!empty($rejets)): ?>
        <h6 class="fw-semibold mb-3 text-danger">Historique des rejets</h6>
        <div class="table-responsive">
            <table class="table table-sm table-bordered">
                <thead class="table-danger">
                    <tr>
                        <th>Date</th>
                        <th>Par</th>
                        <th>Motif</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rejets as $r): ?>
                        <tr>
                            <td><?= date('d/m/Y à H:i', strtotime($r['date_rejet'])) ?></td>
                            <td><?= htmlspecialchars($r['user_nom'] ?? 'Système') ?></td>
                            <td><?= nl2br(htmlspecialchars($r['motif'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>