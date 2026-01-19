<?php
session_start();
require '../config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['superviseur', 'admin'])) {
    header('Location: ../index.php');
    exit;
}

// Paramètres
$search = trim($_GET['search'] ?? '');
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$filter_regime = $_GET['filter_regime'] ?? '';
$filter_type = intval($_GET['filter_type'] ?? 0);
$filter_controleur = intval($_GET['filter_controleur'] ?? 0);
$filter_statut = $_GET['filter_statut'] ?? ''; // Nouveau filtre statut

// Requête – TOUS les dossiers
$sql = "
    SELECT d.id, d.numero_dossier, d.nom_assure, d.regime, tp.nom AS type_prestation, d.statut, d.date_reception,
           DATEDIFF(CURDATE(), d.date_reception) AS jours_ecoules, u.nom_complet AS controleur_nom
    FROM dossiers d
    JOIN types_prestation tp ON tp.id = d.type_prestation_id
    LEFT JOIN users u ON u.id = d.controleur_id
    WHERE 1=1
";

$params = [];

if ($search !== '') {
    $sql .= " AND (d.numero_dossier LIKE ? OR d.nom_assure LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($date_from !== '') {
    $sql .= " AND d.date_reception >= ?";
    $params[] = $date_from;
}

if ($date_to !== '') {
    $sql .= " AND d.date_reception <= ?";
    $params[] = $date_to;
}

if ($filter_regime !== '') {
    $sql .= " AND d.regime = ?";
    $params[] = $filter_regime;
}

if ($filter_type > 0) {
    $sql .= " AND d.type_prestation_id = ?";
    $params[] = $filter_type;
}

if ($filter_controleur > 0) {
    $sql .= " AND d.controleur_id = ?";
    $params[] = $filter_controleur;
}

if ($filter_statut !== '') {
    $sql .= " AND d.statut = ?";
    $params[] = $filter_statut;
}

$sql .= " ORDER BY d.date_reception DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$dossiers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Liste des contrôleurs
$controleurs = $pdo->query("SELECT id, nom_complet FROM users WHERE role = 'controleur' ORDER BY nom_complet")->fetchAll(PDO::FETCH_ASSOC);

// Types prestation
$types = $pdo->query("SELECT id, nom FROM types_prestation ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tous les dossiers - Superviseur CNSS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="with-sidebar">
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid py-4">
            <h2 class="h3 fw-bold text-primary mb-4">Tous les dossiers du service</h2>

            <!-- Filtres (avec filtre statut) -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label fw-medium">Recherche (numéro ou nom)</label>
                            <input type="text" name="search" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Ex: 2025-001 ou Ahmed">
                        </div>

                        <div class="col-lg-2 col-md-4">
                            <label class="form-label fw-medium">Régime</label>
                            <select name="filter_regime" class="form-select" onchange="this.form.submit()">
                                <option value="">Tous</option>
                                <option value="RP" <?= $filter_regime === 'RP' ? 'selected' : '' ?>>RP</option>
                                <option value="RG" <?= $filter_regime === 'RG' ? 'selected' : '' ?>>RG</option>
                            </select>
                        </div>

                        <div class="col-lg-2 col-md-6">
                            <label class="form-label fw-medium">Type prestation</label>
                            <select name="filter_type" class="form-select" onchange="this.form.submit()">
                                <option value="0">Tous</option>
                                <?php foreach ($types as $t): ?>
                                    <option value="<?= $t['id'] ?>" <?= $filter_type == $t['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($t['nom']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-lg-2 col-md-6">
                            <label class="form-label fw-medium">Statut</label>
                            <select name="filter_statut" class="form-select" onchange="this.form.submit()">
                                <option value="">Tous</option>
                                <option value="recu" <?= $filter_statut === 'recu' ? 'selected' : '' ?>>Reçu</option>
                                <option value="en_cours" <?= $filter_statut === 'en_cours' ? 'selected' : '' ?>>En cours</option>
                                <option value="soumis_chef" <?= $filter_statut === 'soumis_chef' ? 'selected' : '' ?>>Soumis chef</option>
                                <option value="valide" <?= $filter_statut === 'valide' ? 'selected' : '' ?>>Validé</option>
                                <option value="rejete" <?= $filter_statut === 'rejete' ? 'selected' : '' ?>>Rejeté</option>
                            </select>
                        </div>

                        <div class="col-lg-2 col-md-6">
                            <label class="form-label fw-medium">Contrôleur</label>
                            <select name="filter_controleur" class="form-select" onchange="this.form.submit()">
                                <option value="0">Tous</option>
                                <?php foreach ($controleurs as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= $filter_controleur == $c['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['nom_complet']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-lg-1 col-md-3">
                            <label class="form-label fw-medium">Date de</label>
                            <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>">
                        </div>

                        <div class="col-lg-1 col-md-3">
                            <label class="form-label fw-medium">à</label>
                            <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>">
                        </div>

                        <div class="col-lg-1 col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-funnel me-1"></i> Appliquer
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tableau dossiers -->
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-primary">
                                <tr>
                                    <th>Numéro</th>
                                    <th>Assuré</th>
                                    <th>Régime</th>
                                    <th>Type</th>
                                    <th>Contrôleur</th>
                                    <th>Date réception</th>
                                    <th>Délai</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($dossiers)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-muted py-5">
                                            Aucun dossier trouvé
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($dossiers as $d): 
                                        $jours = $d['jours_ecoules'];
                                        $delai = 10 - $jours;
                                        $badge = $delai > 2 ? 'bg-success' : ($delai > 0 ? 'bg-warning' : 'bg-danger');
                                        $delai_text = $delai > 0 ? "$delai jours restants" : "Retard " . abs($delai) . " j";
                                    ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($d['numero_dossier']) ?></strong></td>
                                            <td><?= htmlspecialchars($d['nom_assure'] ?: 'Non renseigné') ?></td>
                                            <td>
                                                <span class="badge <?= $d['regime'] === 'RP' ? 'bg-info' : 'bg-warning' ?>">
                                                    <?= $d['regime'] ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($d['type_prestation']) ?></td>
                                            <td><?= htmlspecialchars($d['controleur_nom'] ?? 'Non assigné') ?></td>
                                            <td><?= date('d/m/Y', strtotime($d['date_reception'])) ?></td>
                                            <td>
                                                <span class="badge <?= $badge ?>">
                                                    <?= $delai_text ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge <?= $d['statut'] === 'valide' ? 'bg-success' : ($d['statut'] === 'rejete' ? 'bg-danger' : ($d['statut'] === 'soumis_chef' ? 'bg-info' : 'bg-warning')) ?>">
                                                    <?= ucfirst(str_replace('_', ' ', $d['statut'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-info me-2" onclick="ouvrirEtatPieces(<?= $d['id'] ?>)">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <a href="../dossiers/detail.php?id=<?= $d['id'] ?>" class="btn btn-sm btn-primary">
                                                    <i class="bi bi-arrow-right-circle"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal état pièces (inchangé) -->
    <div class="modal fade" id="modalEtatPieces" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content rounded-4">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-list-check me-2"></i> État des pièces du dossier
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="etatPiecesModalContent" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-3 text-muted">Chargement...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function ouvrirEtatPieces(dossierId) {
            const modalEl = document.getElementById('modalEtatPieces');
            const modal = new bootstrap.Modal(modalEl);
            const content = document.getElementById('etatPiecesModalContent');
            content.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div><p class="mt-3 text-muted">Chargement...</p></div>';
            modal.show();
            fetch('../ajax/ajax_etat_pieces.php?id=' + dossierId)
                .then(res => res.text())
                .then(html => content.innerHTML = html)
                .catch(() => content.innerHTML = '<div class="alert alert-danger text-center">Erreur de chargement</div>');
        }
    </script>
</body>
</html>