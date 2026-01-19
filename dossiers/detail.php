<?php
session_start();
require '../config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$dossier_id = intval($_GET['id'] ?? 0);
if ($dossier_id <= 0) {
    die('ID dossier invalide');
}

/* =========================
   RÉCUPÉRATION DU DOSSIER
========================= */
$stmt = $pdo->prepare("
    SELECT d.*, t.nom AS type_prestation, u.nom_complet AS controleur_nom
    FROM dossiers d
    JOIN types_prestation t ON t.id = d.type_prestation_id
    LEFT JOIN users u ON u.id = d.controleur_id
    WHERE d.id = ?
");
$stmt->execute([$dossier_id]);
$dossier = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$dossier) {
    die('Dossier introuvable');
}

if ($_SESSION['role'] === 'controleur' && $dossier['controleur_id'] != $_SESSION['user_id']) {
    die('Accès refusé');
}

/* =========================
   HISTORIQUE DES REJETS
========================= */
$stmt = $pdo->prepare("
    SELECT dr.*, u.nom_complet AS user_nom
    FROM dossier_rejets dr
    LEFT JOIN users u ON u.id = dr.user_id
    WHERE dr.dossier_id = ?
    ORDER BY dr.date_rejet DESC
");
$stmt->execute([$dossier_id]);
$historique_rejets = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   INITIALISATION PIÈCES
========================= */
$check = $pdo->prepare("SELECT COUNT(*) FROM pieces_fournies WHERE dossier_id = ?");
$check->execute([$dossier_id]);

if ($check->fetchColumn() == 0) {
    $piecesReq = $pdo->prepare("SELECT id FROM pieces_requises WHERE type_prestation_id = ? ORDER BY ordre");
    $piecesReq->execute([$dossier['type_prestation_id']]);
    $requises = $piecesReq->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($requises)) {
        $insert = $pdo->prepare("
            INSERT INTO pieces_fournies (dossier_id, piece_requise_id, original_fourni, copie_fourni, valide)
            VALUES (?, ?, 0, 0, 0)
        ");
        foreach ($requises as $piece_id) {
            $insert->execute([$dossier_id, $piece_id]);
        }
    }
}

/* =========================
   CHECKLIST
========================= */
$stmt = $pdo->prepare("
    SELECT pf.id, pf.original_fourni, pf.copie_fourni, pf.valide, pr.nom_piece
    FROM pieces_fournies pf
    JOIN pieces_requises pr ON pr.id = pf.piece_requise_id
    WHERE pf.dossier_id = ?
    ORDER BY pr.ordre
");
$stmt->execute([$dossier_id]);
$pieces = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   PEUT MODIFIER LA CHECKLIST ?
========================= */
$can_edit_checklist = in_array($dossier['statut'], ['recu', 'en_cours']);

/* =========================
   SAUVEGARDE CHECKLIST
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_checklist']) && $can_edit_checklist) {
    foreach ($_POST['pieces'] as $piece_id => $data) {
        $original = isset($data['original']) ? 1 : 0;
        $copie = isset($data['copie']) ? 1 : 0;
        $valide = isset($data['valide']) ? 1 : 0;

        // RÈGLE MÉTIER : Conforme IMPOSSIBLE sans Original ou Copie
        if ($valide && !$original && !$copie) {
            $valide = 0;
        }

        $update = $pdo->prepare("
            UPDATE pieces_fournies
            SET original_fourni = ?, copie_fourni = ?, valide = ?
            WHERE id = ? AND dossier_id = ?
        ");
        $update->execute([$original, $copie, $valide, $piece_id, $dossier_id]);
    }

    // Passage automatique recu → en_cours
    if ($dossier['statut'] === 'recu') {
        $pdo->prepare("UPDATE dossiers SET statut = 'en_cours', date_debut_traitement = CURDATE() WHERE id = ?")
            ->execute([$dossier_id]);

        $log = $pdo->prepare("INSERT INTO historique (dossier_id, user_id, action) VALUES (?, ?, 'Début de traitement')");
        $log->execute([$dossier_id, $_SESSION['user_id']]);
    }

    $log = $pdo->prepare("INSERT INTO historique (dossier_id, user_id, action) VALUES (?, ?, 'Mise à jour checklist')");
    $log->execute([$dossier_id, $_SESSION['user_id']]);

    header("Location: detail.php?id=$dossier_id&success=checklist");
    exit;
}

/* =========================
   ACTIONS
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // Soumettre au chef
    if ($action === 'soumettre' && $_SESSION['role'] === 'controleur') {
        $complete = true;
        foreach ($pieces as $p) {
            if (!$p['valide']) {
                $complete = false;
                break;
            }
        }

        if ($complete) {
            $pdo->prepare("UPDATE dossiers SET statut = 'soumis_chef', date_soumission_chef = CURDATE() WHERE id = ?")->execute([$dossier_id]);
            $log_action = "Dossier soumis au chef pour validation";
        } else {
            header("Location: detail.php?id=$dossier_id&error=checklist");
            exit;
        }
    }

    // Rejeter par agent
    if ($action === 'rejeter' && $_SESSION['role'] === 'controleur') {
        $motif = trim($_POST['motif_rejet'] ?? '');
        if ($motif !== '') {
            $pdo->prepare("UPDATE dossiers SET statut = 'rejete' WHERE id = ?")->execute([$dossier_id]);
            $pdo->prepare("INSERT INTO dossier_rejets (dossier_id, user_id, motif) VALUES (?, ?, ?)")
                ->execute([$dossier_id, $_SESSION['user_id'], $motif]);
            $log_action = "Dossier rejeté par l'agent";
        }
    }

    // Reprendre après rejet
    if ($action === 'reprendre' && $_SESSION['role'] === 'controleur' && $dossier['statut'] === 'rejete') {
        $pdo->prepare("UPDATE dossiers SET statut = 'en_cours' WHERE id = ?")->execute([$dossier_id]);
        $log_action = "Dossier repris pour correction après rejet";

        $log = $pdo->prepare("INSERT INTO historique (dossier_id, user_id, action) VALUES (?, ?, ?)");
        $log->execute([$dossier_id, $_SESSION['user_id'], $log_action]);

        header("Location: detail.php?id=$dossier_id&success=repris");
        exit;
    }

    // Valider par chef
    if ($action === 'valider' && in_array($_SESSION['role'], ['superviseur', 'admin']) && $dossier['statut'] === 'soumis_chef') {
        $pdo->prepare("UPDATE dossiers SET statut = 'valide', date_validation = CURDATE() WHERE id = ?")->execute([$dossier_id]);
        $log_action = "Dossier validé par le chef";
    }

    // Rejeter par chef
    if ($action === 'rejeter_chef' && in_array($_SESSION['role'], ['superviseur', 'admin'])) {
        $motif = trim($_POST['motif_rejet_chef'] ?? '');
        if ($motif !== '') {
            $pdo->prepare("UPDATE dossiers SET statut = 'rejete' WHERE id = ?")->execute([$dossier_id]);
            $pdo->prepare("INSERT INTO dossier_rejets (dossier_id, user_id, motif) VALUES (?, ?, ?)")
                ->execute([$dossier_id, $_SESSION['user_id'], $motif]);
            $log_action = "Dossier rejeté par le chef";
        }
    }

    if (isset($log_action)) {
        $log = $pdo->prepare("INSERT INTO historique (dossier_id, user_id, action, commentaire) VALUES (?, ?, ?, ?)");
        $log->execute([$dossier_id, $_SESSION['user_id'], $log_action, $motif ?? '']);
    }

    header("Location: detail.php?id=$dossier_id&success=action");
    exit;
}

/* =========================
   CHECKLIST COMPLÈTE ?
========================= */
$checklist_complete = true;
foreach ($pieces as $p) {
    if (!$p['valide']) {
        $checklist_complete = false;
        break;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dossier <?= htmlspecialchars($dossier['numero_dossier']) ?> - CNSS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .piece-card {
            min-height: 140px;
        }
        .piece-title {
            font-size: 0.95rem;
            font-weight: 600;
            line-height: 1.4;
            word-break: break-word;
        }
        .locked-checklist {
            opacity: 0.6;
            pointer-events: none;
        }
    </style>
</head>
<body class="with-sidebar">
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid py-4">
            <!-- En-tête -->
            <div class="d-flex justify-content-between align-items-start mb-4">
                <div>
                    <h2 class="fw-bold mb-1">Dossier <?= htmlspecialchars($dossier['numero_dossier']) ?></h2>
                    <p class="mb-1"><strong>Assuré :</strong> <?= htmlspecialchars($dossier['nom_assure'] ?: 'Non renseigné') ?></p>
                    <p class="mb-1"><strong>Type :</strong> <?= htmlspecialchars($dossier['type_prestation']) ?></p>
                    <p class="mb-0">
                        <strong>Contrôleur :</strong> <?= htmlspecialchars($dossier['controleur_nom'] ?? 'Non assigné') ?><br>
                        <strong>Réception :</strong> <?= date('d/m/Y', strtotime($dossier['date_reception'])) ?>
                    </p>
                </div>
                <div class="text-end">
                    <span class="badge fs-4 px-4 py-2 <?= 
                        $dossier['statut'] === 'valide' ? 'bg-success' :
                        ($dossier['statut'] === 'rejete' ? 'bg-danger' :
                        ($dossier['statut'] === 'soumis_chef' ? 'bg-info' : 'bg-warning'))
                    ?>">
                        <?= ucfirst(str_replace('_', ' ', $dossier['statut'])) ?>
                    </span>
                </div>
            </div>

            <!-- Messages -->
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?php if ($_GET['success'] === 'checklist'): ?>Checklist mise à jour avec succès
                    <?php elseif ($_GET['success'] === 'repris'): ?>Dossier repris – vous pouvez maintenant corriger la checklist
                    <?php elseif ($_GET['success'] === 'action'): ?>Action effectuée avec succès<?php endif; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error']) && $_GET['error'] === 'checklist'): ?>
                <div class="alert alert-warning">
                    Toutes les pièces doivent être "Conforme" pour soumettre au chef
                </div>
            <?php endif; ?>

            <!-- Checklist -->
            <div class="card shadow-sm mb-5">
                <div class="card-header <?= $can_edit_checklist ? 'bg-primary' : 'bg-secondary' ?> text-white">
                    <h5 class="mb-0">Checklist des pièces jointes <?= !$can_edit_checklist ? '(bloquée)' : '' ?></h5>
                </div>
                <div class="card-body <?= !$can_edit_checklist ? 'locked-checklist' : '' ?>">
                    <form method="POST">
                        <div class="row g-4">
                            <?php foreach ($pieces as $p): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="card piece-card border-0 shadow-sm">
                                        <div class="card-body">
                                            <h6 class="piece-title mb-4"><?= htmlspecialchars($p['nom_piece']) ?></h6>
                                            <div class="d-flex gap-4 align-items-center">
                                                <div class="form-check">
                                                    <input class="form-check-input original-checkbox" type="checkbox" name="pieces[<?= $p['id'] ?>][original]"
                                                           id="orig_<?= $p['id'] ?>" <?= $p['original_fourni'] ? 'checked' : '' ?> <?= !$can_edit_checklist ? 'disabled' : '' ?>>
                                                    <label class="form-check-label" for="orig_<?= $p['id'] ?>">Original</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input copie-checkbox" type="checkbox" name="pieces[<?= $p['id'] ?>][copie]"
                                                           id="copie_<?= $p['id'] ?>" <?= $p['copie_fourni'] ? 'checked' : '' ?> <?= !$can_edit_checklist ? 'disabled' : '' ?>>
                                                    <label class="form-check-label" for="copie_<?= $p['id'] ?>">Copie</label>
                                                </div>
                                                <div class="form-check ms-auto">
                                                    <input class="form-check-input valide-checkbox" type="checkbox" name="pieces[<?= $p['id'] ?>][valide]"
                                                           id="valide_<?= $p['id'] ?>" <?= $p['valide'] ? 'checked' : '' ?> disabled>
                                                    <label class="form-check-label text-success fw-bold" for="valide_<?= $p['id'] ?>">Conforme</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($can_edit_checklist): ?>
                            <div class="mt-5 text-center">
                                <button type="submit" name="save_checklist" class="btn btn-primary btn-lg px-5">
                                    <i class="bi bi-save me-2"></i> Enregistrer la checklist
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="mt-4 text-center text-muted">
                                <i class="bi bi-lock-fill fs-3"></i><br>
                                <strong>Checklist bloquée</strong><br>
                                En Attente de validation
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Bouton Reprendre après rejet -->
            <?php if ($_SESSION['role'] === 'controleur' && $dossier['statut'] === 'rejete'): ?>
                <div class="text-center mb-5">
                    <form method="POST">
                        <button type="submit" name="action" value="reprendre" class="btn btn-warning btn-lg px-5 py-4 shadow">
                            <i class="bi bi-arrow-counterclockwise fs-3 me-3"></i><br>
                            <strong>Reprendre le traitement pour correction</strong><br>
                            <small>Débloquer la checklist après rejet</small>
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Bouton Soumettre au chef -->
            <?php if ($_SESSION['role'] === 'controleur' && $checklist_complete && in_array($dossier['statut'], ['recu', 'en_cours'])): ?>
                <div class="text-center mb-5">
                    <form method="POST">
                        <button type="submit" name="action" value="soumettre" class="btn btn-success btn-lg px-5 py-4 shadow">
                            <i class="bi bi-send-check fs-3 me-3"></i><br>
                            <strong>Soumettre au chef pour validation</strong><br>
                            <small class="text-white-50">Checklist complète – prêt pour validation</small>
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Historique rejets -->
            <?php if (!empty($historique_rejets)): ?>
                <div class="card shadow-sm mb-5">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0">Historique des rejets</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Par</th>
                                        <th>Motif</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($historique_rejets as $r): ?>
                                        <tr>
                                            <td><?= date('d/m/Y à H:i', strtotime($r['date_rejet'])) ?></td>
                                            <td><?= htmlspecialchars($r['user_nom'] ?? 'Système') ?></td>
                                            <td><?= nl2br(htmlspecialchars($r['motif'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Autres actions -->
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Autres actions</h5>
                </div>
                <div class="card-body">
                    <?php if ($_SESSION['role'] === 'controleur'): ?>
                        <form method="POST" class="mt-3">
                            <div class="mb-3">
                                <label class="form-label">Motif du rejet</label>
                                <textarea name="motif_rejet" class="form-control" rows="4" placeholder="Expliquez le motif..." required></textarea>
                            </div>
                            <button type="submit" name="action" value="rejeter" class="btn btn-danger btn-lg">
                                <i class="bi bi-x-circle me-2"></i> Rejeter le dossier
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if (in_array($_SESSION['role'], ['superviseur', 'admin']) && $dossier['statut'] === 'soumis_chef'): ?>
                        <div class="row g-4 mt-4">
                            <div class="col-md-6">
                                <form method="POST">
                                    <button type="submit" name="action" value="valider" class="btn btn-success btn-lg w-100">
                                        <i class="bi bi-check2-all me-2"></i> Valider définitivement
                                    </button>
                                </form>
                            </div>
                            <div class="col-md-6">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label class="form-label">Motif du rejet</label>
                                        <textarea name="motif_rejet_chef" class="form-control" rows="4" required></textarea>
                                    </div>
                                    <button type="submit" name="action" value="rejeter_chef" class="btn btn-danger btn-lg w-100">
                                        <i class="bi bi-x-circle me-2"></i> Rejeter et retourner
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Logique Conforme bloqué tant que pas Original ou Copie
        document.querySelectorAll('.original-checkbox, .copie-checkbox').forEach(input => {
            input.addEventListener('change', function() {
                const match = this.id.match(/^(orig|copie)_(\d+)$/);
                if (!match) return;
                const pieceId = match[2];

                const original = document.getElementById('orig_' + pieceId).checked;
                const copie = document.getElementById('copie_' + pieceId).checked;
                const conforme = document.getElementById('valide_' + pieceId);

                if (original || copie) {
                    conforme.disabled = false;
                } else {
                    conforme.checked = false;
                    conforme.disabled = true;
                }
            });
        });

        // État initial au chargement
        document.querySelectorAll('.valide-checkbox').forEach(conforme => {
            const pieceId = conforme.id.replace('valide_', '');
            const original = document.getElementById('orig_' + pieceId);
            const copie = document.getElementById('copie_' + pieceId);

            if (original && copie) {
                if (!(original.checked || copie.checked)) {
                    conforme.checked = false;
                    conforme.disabled = true;
                }
            }
        });
    </script>
</body>
</html>