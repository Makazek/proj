<?php
if (!isset($page_title)) {
    $page_title = 'Tableau de bord';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include 'head.php'; ?>
</head>
<body class="with-sidebar">

<?php include 'sidebar.php'; ?>
<?php include 'header.php'; ?>

<main class="main-content">
    <?= $content ?>
</main>

<?php include 'scripts.php'; ?>
</body>
</html>
