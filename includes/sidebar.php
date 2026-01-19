<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage = basename($_SERVER['PHP_SELF']);
$baseUrl = '/cnss_suivi';
?>

<nav class="sidebar bg-white shadow-sm d-flex flex-column"
     style="width:280px; min-height:100vh; border-right:1px solid #dee2e6;
            position:fixed; left:0; top:0; padding-top:40px;">

    <!-- LOGO -->
    <div class="text-center mb-4 px-4">
        <img src="<?= $baseUrl ?>/assets/images/logo_cnss.png"
             alt="CNSS"
             class="img-fluid"
             style="max-height:90px;">
        <h5 class="mt-3 text-primary fw-bold">Suivi Dossiers</h5>
    </div>

    <!-- MENU -->
    <ul class="nav flex-column px-3 flex-grow-1">

        <!-- Dashboard (redirigé selon rôle) -->
        <li class="nav-item mb-2">
            <a class="nav-link d-flex align-items-center px-3 py-3 rounded fw-medium sidebar-link
               <?= in_array($currentPage, ['dashboard_admin.php','dashboard_chef.php','dashboard_agent.php']) ? 'active' : '' ?>"
               href="<?= 
                    $_SESSION['role'] === 'admin'
                        ? $baseUrl.'/admin/dashboard_admin.php'
                        : ($_SESSION['role'] === 'superviseur'
                            ? $baseUrl.'/chef/dashboard_chef.php'
                            : $baseUrl.'/agent/dashboard_agent.php')
               ?>">
                <i class="bi bi-speedometer2 fs-5 me-3"></i>
                Tableau de bord
            </a>
        </li>

        <!-- AGENT : les 4 pages état -->
        <?php if ($_SESSION['role'] === 'controleur'): ?>
            <li class="nav-item mb-2">
                <a class="nav-link d-flex align-items-center px-3 py-3 rounded fw-medium sidebar-link
                   <?= $currentPage === 'dossiers_tous.php' ? 'active' : '' ?>"
                   href="<?= $baseUrl ?>/agent/dossiers_tous.php">
                    <i class="bi bi-grid-3x3-gap fs-5 me-3"></i>
                    Tous mes dossiers
                </a>
            </li>

            <li class="nav-item mb-2">
                <a class="nav-link d-flex align-items-center px-3 py-3 rounded fw-medium sidebar-link
                   <?= $currentPage === 'dossiers_en_cours.php' ? 'active' : '' ?>"
                   href="<?= $baseUrl ?>/agent/dossiers_en_cours.php">
                    <i class="bi bi-pencil-square fs-5 me-3"></i>
                    En cours de traitement
                </a>
            </li>

            <li class="nav-item mb-2">
                <a class="nav-link d-flex align-items-center px-3 py-3 rounded fw-medium sidebar-link
                   <?= $currentPage === 'dossiers_soumis.php' ? 'active' : '' ?>"
                   href="<?= $baseUrl ?>/agent/dossiers_soumis.php">
                    <i class="bi bi-clock-history fs-5 me-3"></i>
                    En attente validation chef
                </a>
            </li>

            <li class="nav-item mb-2">
                <a class="nav-link d-flex align-items-center px-3 py-3 rounded fw-medium sidebar-link
                   <?= $currentPage === 'dossiers_rejetes.php' ? 'active' : '' ?>"
                   href="<?= $baseUrl ?>/agent/dossiers_rejetes.php">
                    <i class="bi bi-exclamation-triangle fs-5 me-3"></i>
                    Rejetés (à corriger)
                </a>
            </li>
        <?php endif; ?>

        <!-- CHEF / ADMIN -->
        <?php if (in_array($_SESSION['role'], ['superviseur','admin'])): ?>
            <li class="nav-item mb-2">
                <a class="nav-link d-flex align-items-center px-3 py-3 rounded fw-medium sidebar-link
                   <?= $currentPage === 'dossiers_tous.php' ? 'active' : '' ?>"
                   href="<?= $baseUrl ?>/chef/dossiers_tous.php">
                    <i class="bi bi-folder-fill fs-5 me-3"></i>
                    Tous les dossiers
                </a>
            </li>

            <li class="nav-item mb-2">
                <a class="nav-link d-flex align-items-center px-3 py-3 rounded fw-medium sidebar-link
                   <?= $currentPage === 'service.php' ? 'active' : '' ?>"
                   href="<?= $baseUrl ?>/rapports/service.php">
                    <i class="bi bi-graph-up fs-5 me-3"></i>
                    Rapports service
                </a>
            </li>
        <?php endif; ?>

        <!-- ADMIN ONLY -->
        <?php if ($_SESSION['role'] === 'admin'): ?>
            <li class="nav-item mb-2">
                <a class="nav-link d-flex align-items-center px-3 py-3 rounded fw-medium sidebar-link
                   <?= $currentPage === 'gestion_users.php' ? 'active' : '' ?>"
                   href="<?= $baseUrl ?>/admin/gestion_users.php">
                    <i class="bi bi-people fs-5 me-3"></i>
                    Gestion utilisateurs
                </a>
            </li>
        <?php endif; ?>

    </ul>

    <!-- USER PROFILE (BOTTOM) -->
    <div class="mt-auto px-3 pb-4">
        <div class="d-flex align-items-center p-3 rounded-4 bg-light shadow-sm">
            <!-- Avatar -->
            <div class="me-3">
                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center"
                     style="width:44px; height:44px; font-weight:600;">
                    <?= strtoupper(substr($_SESSION['username'], 0, 1)) ?>
                </div>
            </div>

            <!-- Infos -->
            <div class="flex-grow-1">
                <div class="fw-semibold">
                    <?= htmlspecialchars($_SESSION['nom_complet'] ?? $_SESSION['username']) ?>
                </div>
                <small class="text-muted text-capitalize">
                    <?= $_SESSION['role'] === 'controleur' ? 'Contrôleur' : ($_SESSION['role'] === 'superviseur' ? 'Superviseur' : 'Administrateur') ?>
                </small>
            </div>

            <!-- Logout -->
            <a href="<?= $baseUrl ?>/logout.php"
               class="btn btn-sm btn-outline-danger rounded-circle"
               title="Déconnexion">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </div>
</nav>