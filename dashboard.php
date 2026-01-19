<?php
session_start();
require '../config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['superviseur', 'admin'])) {
    header('Location: ../index.php');
    exit;
}

/* =========================
   KPI SERVICE
========================= */
$stmt = $pdo->query("
    SELECT 
        COUNT(*) AS total_dossiers,
        SUM(statut = 'soumis_chef') AS en_attente_validation,
        SUM(statut = 'valide') AS valides,
        SUM(statut = 'rejete') AS rejetes,
        SUM(DATEDIFF(CURDATE(), date_reception) > 10 AND statut NOT IN ('valide', 'rejete')) AS en_retard,
        ROUND(AVG(CASE WHEN statut = 'valide' THEN DATEDIFF(date_validation, date_reception) END), 1) AS delai_moyen_jours
    FROM dossiers
");
$kpi = $stmt->fetch(PDO::FETCH_ASSOC);

/* =========================
   CLASSEMENT AGENTS
========================= */
$stmt = $pdo->query("
    SELECT u.nom_complet,
           COUNT(d.id) AS total_dossiers,
           SUM(d.statut = 'valide') AS valides,
           SUM(d.statut = 'rejete') AS rejetes
    FROM users u
    LEFT JOIN dossiers d ON d.controleur_id = u.id
    WHERE u.role = 'controleur'
    GROUP BY u.id
    ORDER BY valides DESC, total_dossiers DESC
    LIMIT 10
");
$classement_agents = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   DOSSIERS EN ATTENTE DE VALIDATION
========================= */
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
$dossiers_attente = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   ACTIONS VALIDATION / REJET
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $dossier_id = intval($_POST['dossier_id'] ?? 0);

    if ($dossier_id <= 0) {
        header("Location: dashboard_chef.php?error=invalid_id");
        exit;
    }

    // Vérifie que le dossier est bien en attente
    $check = $pdo->prepare("SELECT statut FROM dossiers WHERE id = ?");
    $check->execute([$dossier_id]);
    if ($check->fetchColumn() !== 'soumis_chef') {
        header("Location: dashboard_chef.php?error=invalid_statut");
        exit;
    }

    if ($action === 'valider') {
        $pdo->prepare("
            UPDATE dossiers 
            SET statut = 'valide', date_validation = CURDATE() 
            WHERE id = ?
        ")->execute([$dossier_id]);

        $log = $pdo->prepare("
            INSERT INTO historique (dossier_id, user_id, action, commentaire)
            VALUES (?, ?, 'Validation définitive', 'Validé par le superviseur')
        ");
        $log->execute([$dossier_id, $_SESSION['user_id']]);

        header("Location: dashboard_chef.php?success=validated");
        exit;
    }

    if ($action === 'rejeter_chef') {
        $motif = trim($_POST['motif_rejet_chef'] ?? '');
        if ($motif === '') {
            header("Location: dashboard_chef.php?error=motif_required");
            exit;
        }

        $pdo->prepare("UPDATE dossiers SET statut = 'rejete' WHERE id = ?")->execute([$dossier_id]);

        $pdo->prepare("
            INSERT INTO dossier_rejets (dossier_id, user_id, motif)
            VALUES (?, ?, ?)
        ")->execute([$dossier_id, $_SESSION['user_id'], $motif]);

        $log = $pdo->prepare("
            INSERT INTO historique (dossier_id, user_id, action, commentaire)
            VALUES (?, ?, 'Rejet par le superviseur', ?)
        ");
        $log->execute([$dossier_id, $_SESSION['user_id'], $motif]);

        header("Location: dashboard_chef.php?success=rejected");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - Superviseur CNSS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="with-sidebar">
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid py-4">
            <div class="text-center mb-5">
                <h1 class="fw-bold text-primary mb-1">Tableau de bord Superviseur</h1>
                <p class="text-muted fs-5">Vue globale du service de contrôle</p>
            </div>

            <!-- Messages -->
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?php if ($_GET['success'] === 'validated'): ?>
                        Dossier validé avec succès
                    <?php elseif ($_GET['success'] === 'rejected'): ?>
                        Dossier rejeté avec succès
                    <?php endif; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?php if ($_GET['error'] === 'motif_required'): ?>
                        Le motif du rejet est obligatoire
                    <?php elseif ($_GET['error'] === 'invalid_id'): ?>
                        ID dossier invalide
                    <?php elseif ($_GET['error'] === 'invalid_statut'): ?>
                        Ce dossier n'est plus en attente de validation
                    <?php endif; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- KPI -->
            <div class="row g-4 justify-content-center mb-5">
                <div class="col-xl-9 col-lg-10">
                    <div class="row g-4">
                        <div class="col-md-6 col-lg-3">
                            <div class="card action-card border-0 shadow-sm h-100">
                                <div class="card-body text-center py-5">
                                    <i class="bi bi-folder fs-1 text-primary mb-3"></i>
                                    <h2 class="fw-bold mb-1"><?= $kpi['total_dossiers'] ?? 0 ?></h2>
                                    <p class="fs-5 mb-0 text-muted">Total dossiers service</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-lg-3">
                            <div class="card action-card border-0 shadow-sm h-100">
                                <div class="card-body text-center py-5">
                                    <i class="bi bi-clock-history fs-1 text-info mb-3"></i>
                                    <h2 class="fw-bold mb-1"><?= $kpi['en_attente_validation'] ?? 0 ?></h2>
                                    <p class="fs-5 mb-0 text-muted">En attente validation</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-lg-3">
                            <div class="card action-card border-0 shadow-sm h-100">
                                <div class="card-body text-center py-5">
                                    <i class="bi bi-exclamation-triangle fs-1 text-danger mb-3"></i>
                                    <h2 class="fw-bold mb-1"><?= $kpi['en_retard'] ?? 0 ?></h2>
                                    <p class="fs-5 mb-0 text-muted">En retard (>10 jours)</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-lg-3">
                            <div class="card action-card border-0 shadow-sm h-100">
                                <div class="card-body text-center py-5">
                                    <i class="bi bi-stopwatch fs-1 text-success mb-3"></i>
                                    <h2 class="fw-bold mb-1"><?= $kpi['delai_moyen_jours'] ?? 'N/A' ?> j</h2>
                                    <p class="fs-5 mb-0 text-muted">Délai moyen traitement</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Classement des contrôleurs -->
            <h2 class="h3 fw-bold text-primary text-center mb-4">Classement des contrôleurs</h2>
            <div class="row justify-content-center mb-5">
                <div class="col-xl-8 col-lg-10">
                    <?php if (empty($classement_agents)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-people fs-1 mb-3"></i>
                            <p>Aucun contrôleur trouvé</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($classement_agents as $i => $a): ?>
                            <div class="card action-card border-0 shadow-sm mb-3">
                                <div class="card-body d-flex align-items-center">
                                    <div class="me-4">
                                        <h3 class="text-primary fw-bold mb-0">#<?= $i + 1 ?></h3>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h5 class="mb-1"><?= htmlspecialchars($a['nom_complet']) ?></h5>
                                        <div class="d-flex flex-wrap gap-4 text-muted small">
                                            <span><i class="bi bi-folder me-1"></i> <?= $a['total_dossiers'] ?> dossiers</span>
                                            <span class="text-success"><i class="bi bi-check-circle me-1"></i> <?= $a['valides'] ?> validés</span>
                                            <span class="text-danger"><i class="bi bi-x-circle me-1"></i> <?= $a['rejetes'] ?> rejetés</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Dossiers en attente de validation -->
            <h2 class="h3 fw-bold text-primary text-center mb-4">Dossiers en attente de validation</h2>
            <div class="row justify-content-center">
                <div class="col-xl-8 col-lg-10">
                    <?php if (empty($dossiers_attente)): ?>
                        <div class="card text-center py-5 shadow-sm">
                            <i class="bi bi-check2-all text-success fs-1 mb-3"></i>
                            <h5>Tout est à jour !</h5>
                            <p class="text-muted">Aucun dossier en attente de validation</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($dossiers_attente as $d): ?>
                            <div class="card action-card border-0 shadow-sm mb-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h5 class="fw-bold mb-1"><?= htmlspecialchars($d['numero_dossier']) ?></h5>
                                            <p class="mb-1"><?= htmlspecialchars($d['nom_assure']) ?></p>
                                            <div class="d-flex flex-wrap gap-3 text-muted small mb-2">
                                                <span><strong>Régime :</strong> <?= $d['regime'] ?></span>
                                                <span><strong>Type :</strong> <?= htmlspecialchars($d['type_prestation']) ?></span>
                                            </div>
                                            <small class="text-muted">
                                                Par <?= htmlspecialchars($d['agent_nom']) ?> • Soumis le <?= date('d/m/Y', strtotime($d['date_soumission_chef'])) ?>
                                            </small>
                                        </div>
                                        <div class="d-flex flex-column gap-2">
                                            <button class="btn btn-success" onclick="validerDossier(<?= $d['id'] ?>)">
                                                <i class="bi bi-check-lg me-1"></i> Valider
                                            </button>
                                            <button class="btn btn-danger" onclick="rejeterDossier(<?= $d['id'] ?>)">
                                                <i class="bi bi-x-lg me-1"></i> Rejeter
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal Valider -->
    <div class="modal fade" id="modalValider" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Valider le dossier</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center py-5">
                    <i class="bi bi-check2-circle text-success fs-1 mb-3"></i>
                    <p>Confirmez la validation définitive de ce dossier ?</p>
                </div>
                <div class="modal-footer">
                    <form method="POST" id="formValider">
                        <input type="hidden" name="action" value="valider">
                        <input type="hidden" name="dossier_id" id="validerId">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-success">Oui, valider</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Rejeter -->
    <div class="modal fade" id="modalRejeter" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Rejeter le dossier</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="formRejeter">
                        <input type="hidden" name="action" value="rejeter_chef">
                        <input type="hidden" name="dossier_id" id="rejeterId">
                        <div class="mb-3">
                            <label class="form-label">Motif du rejet (obligatoire)</label>
                            <textarea name="motif_rejet_chef" class="form-control" rows="4" required></textarea>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" class="btn btn-danger">Rejeter</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function validerDossier(id) {
            document.getElementById('validerId').value = id;
            new bootstrap.Modal(document.getElementById('modalValider')).show();
        }

        function rejeterDossier(id) {
            document.getElementById('rejeterId').value = id;
            new bootstrap.Modal(document.getElementById('modalRejeter')).show();
        }
    </script>
</body>
</html>