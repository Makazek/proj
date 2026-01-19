<?php
session_start();
require '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'controleur') {
    header('Location: ../index.php');
    exit;
}

// KPI agent (seulement ses dossiers)
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total_dossiers,
        SUM(statut = 'en_cours') AS en_cours,
        SUM(statut = 'valide') AS valides,
        SUM(statut = 'rejete') AS rejetes,
        SUM(statut = 'soumis_chef') AS soumis_chef,
        SUM(DATEDIFF(CURDATE(), date_reception) > 10 AND statut IN ('recu', 'en_cours')) AS en_retard,
        SUM(DATEDIFF(CURDATE(), date_reception) BETWEEN 8 AND 10 AND statut IN ('recu', 'en_cours')) AS proches_delai
    FROM dossiers
    WHERE controleur_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$kpi = $stmt->fetch(PDO::FETCH_ASSOC);

// Dossiers récents (avec regime)
$stmt = $pdo->prepare("
    SELECT 
        id, numero_dossier, nom_assure, regime, statut, date_reception,
        DATEDIFF(CURDATE(), date_reception) AS jours_ecoules
    FROM dossiers
    WHERE controleur_id = ?
    ORDER BY date_reception DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$dossiers_recents = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon espace – Agent CNSS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="with-sidebar">
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h3 fw-bold text-primary">
                Bonjour <?= htmlspecialchars($_SESSION['nom_complet'] ?? $_SESSION['username']) ?>
            </h2>
            <div class="text-end">
                <small class="text-muted">Aujourd'hui : <?= date('d/m/Y') ?></small>
            </div>
        </div>

        <!-- Alertes délais -->
        <?php if ($kpi['en_retard'] > 0 || $kpi['proches_delai'] > 0): ?>
            <div class="alert <?= $kpi['en_retard'] > 0 ? 'alert-danger' : 'alert-warning' ?> alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <strong>Attention :</strong>
                <?php if ($kpi['en_retard'] > 0): ?>
                    <?= $kpi['en_retard'] ?> dossier(s) en retard (>10 jours)
                <?php endif; ?>
                <?php if ($kpi['proches_delai'] > 0): ?>
                    <?= $kpi['proches_delai'] ?> dossier(s) proche(s) du délai (8-10 jours)
                <?php endif; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php else: ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill me-2"></i>
                Tous vos dossiers sont dans les délais. Bon travail !
            </div>
        <?php endif; ?>

        <!-- KPI Cards -->
        <div class="row g-4 mb-5">
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="me-4">
                            <i class="bi bi-folder fs-1 text-primary"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Total dossiers assignés</h6>
                            <h3 class="mb-0 text-primary"><?= $kpi['total_dossiers'] ?? 0 ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="me-4">
                            <i class="bi bi-hourglass-split fs-1 text-warning"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">En cours</h6>
                            <h3 class="mb-0 text-warning"><?= $kpi['en_cours'] ?? 0 ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="me-4">
                            <i class="bi bi-clock-history fs-1 text-info"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">En attente chef</h6>
                            <h3 class="mb-0 text-info"><?= $kpi['soumis_chef'] ?? 0 ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="me-4">
                            <i class="bi bi-x-circle fs-1 text-danger"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Rejetés</h6>
                            <h3 class="mb-0 text-danger"><?= $kpi['rejetes'] ?? 0 ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions rapides (ton visuel AJAX intact) -->
        <div class="row g-3 mb-5">
            <div class="col-md-6">
                <a href="#" class="card action-card border-0 shadow-sm text-decoration-none" data-bs-toggle="modal" data-bs-target="#modalAjouterDossier">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="icon-box bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width:50px;height:50px;">
                            <i class="bi bi-folder-plus fs-3"></i>
                        </div>
                        <div>
                            <h6 class="mb-1 text-dark fw-semibold">Ajouter un dossier</h6>
                            <small class="text-muted">Créer un nouveau dossier CNSS</small>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-md-6">
                <a href="dossiers_tous.php" class="card action-card border-0 shadow-sm text-decoration-none">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="icon-box bg-success text-white rounded-circle d-flex align-items-center justify-content-center" style="width:50px;height:50px;">
                            <i class="bi bi-folder2-open fs-3"></i>
                        </div>
                        <div>
                            <h6 class="mb-1 text-dark fw-semibold">Mes dossiers</h6>
                            <small class="text-muted">Consulter et suivre mes dossiers</small>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <!-- Dossiers récents -->
        <div class="card shadow-sm mt-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Dossiers récents</h5>
                <a href="dossiers_tous.php" class="btn btn-sm btn-outline-primary">Voir tout</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Numéro (compte assuré)</th>
                                <th>Nom de l'assuré</th>
                                <th>Régime</th>
                                <th>Statut</th>
                                <th>Date réception</th>
                                <th>Délai</th>
                                <th>État pièces</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($dossiers_recents)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        Aucun dossier assigné
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($dossiers_recents as $d):
                                    $jours = (int)$d['jours_ecoules'];
                                    $reste = 10 - $jours;
                                    if ($reste > 2) {
                                        $delaiClass = 'bg-success';
                                        $delaiText = $reste . ' jours restants';
                                    } elseif ($reste > 0) {
                                        $delaiClass = 'bg-warning';
                                        $delaiText = $reste . ' jour(s)';
                                    } else {
                                        $delaiClass = 'bg-danger';
                                        $delaiText = 'Retard ' . abs($reste) . ' j';
                                    }
                                ?>
                                    <tr>
    <td><strong><?= htmlspecialchars($d['numero_dossier']) ?></strong></td>
    <td><?= htmlspecialchars($d['nom_assure'] ?: 'Non renseigné') ?></td>
    <td>
        <span class="badge <?= $d['regime'] === 'RP' ? 'bg-info' : 'bg-warning' ?>">
            <?= $d['regime'] === 'RP' ? 'RP' : 'RG' ?>
        </span>
    </td>
    <td>
        <span class="badge <?= $d['statut'] === 'valide' ? 'bg-success' : ($d['statut'] === 'rejete' ? 'bg-danger' : 'bg-warning') ?>">
            <?= ucfirst(str_replace('_', ' ', $d['statut'])) ?>
        </span>
    </td>
    <td><?= date('d/m/Y', strtotime($d['date_reception'])) ?></td>
    <td>
        <span class="badge <?= $delaiClass ?>">
            <?= $delaiText ?>
        </span>
    </td>
    <td>
        <button class="btn btn-sm btn-outline-primary" onclick="ouvrirEtatPieces(<?= $d['id'] ?>)">
            <i class="bi bi-list-check"></i>
        </button>
    </td>
</tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal ajout dossier -->
<div class="modal fade" id="modalAjouterDossier" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-4">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-folder-plus me-2"></i> Nouveau dossier reçu
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formAjouterDossier">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Numéro de dossier (Compte assuré)</label>
                            <input type="text" name="numero_dossier" class="form-control form-control-lg" placeholder="Ex: 2025-00123" required>
                            <small class="text-muted">Numéro d'immatriculation CNSS</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Nom de l'assuré</label>
                            <input type="text" name="nom_assure" class="form-control form-control-lg" placeholder="Ex: Ahmed Mohamed" required>
                        </div>
                    </div>

                    <div class="row g-3 mt-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Régime</label>
                            <select name="regime" class="form-select form-select-lg" required>
                                <option value="">Choisir le régime</option>
                                <option value="RP">Régime Fonctionnaire (RP)</option>
                                <option value="RG">Régime Général (RG)</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Type de prestation</label>
                            <select name="type_prestation_id" class="form-select form-select-lg" required>
                                <option value="">Choisir le type</option>
                                <?php
                                $types = $pdo->query("SELECT id, nom FROM types_prestation ORDER BY nom")->fetchAll();
                                foreach ($types as $t) {
                                    echo "<option value='{$t['id']}'>" . htmlspecialchars($t['nom']) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Date de réception</label>
                            <input type="date" name="date_reception" class="form-control form-control-lg" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                </form>

                <div id="ajoutFeedback" class="alert d-none mt-4"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary btn-lg" data-bs-dismiss="modal">Annuler</button>
                <button class="btn btn-primary btn-lg" onclick="ajouterDossier()">
                    <i class="bi bi-check-circle me-2"></i> Enregistrer le dossier
                </button>
            </div>
        </div>
    </div>
</div>

    <!-- Modal état pièces -->
    <div class="modal fade" id="modalEtatPieces" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content rounded-4">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-list-check me-2 text-primary"></i> État des pièces du dossier
                    </h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="etatPiecesModalContent" class="text-muted text-center">
                        Chargement…
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        function ajouterDossier() {
            const form = document.getElementById('formAjouterDossier');
            const feedback = document.getElementById('ajoutFeedback');
            const data = new FormData(form);

            fetch('../ajax/ajouter_dossier.php', {
                method: 'POST',
                body: data
            })
            .then(res => res.json())
            .then(json => {
                feedback.classList.remove('d-none');
                if (json.success) {
                    feedback.className = 'alert alert-success';
                    feedback.innerHTML = json.message;
                    setTimeout(() => {
                        window.location.href = '../dossiers/detail.php?id=' + json.dossier_id;
                    }, 800);
                } else {
                    feedback.className = 'alert alert-danger';
                    feedback.innerHTML = json.message;
                }
            })
            .catch(() => {
                feedback.className = 'alert alert-danger';
                feedback.innerHTML = 'Erreur de connexion au serveur';
                feedback.classList.remove('d-none');
            });
        }

        function ouvrirEtatPieces(dossierId) {
            const modalEl = document.getElementById('modalEtatPieces');
            const modal = new bootstrap.Modal(modalEl);
            const content = document.getElementById('etatPiecesModalContent');

            content.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div><p class="mt-2 text-muted">Chargement…</p></div>';
            modal.show();

            fetch('../ajax/ajax_etat_pieces.php?id=' + dossierId)
                .then(res => res.text())
                .then(html => {
                    content.innerHTML = html;
                })
                .catch(() => {
                    content.innerHTML = '<div class="alert alert-danger text-center">Erreur de chargement</div>';
                });
        }
    </script>
</body>
</html>