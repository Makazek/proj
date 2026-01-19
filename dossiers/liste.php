<?php
session_start();
require '../config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$where = "";
$params = [];

if ($_SESSION['role'] === 'controleur') {
    // Agent normal → seulement ses dossiers
    $where = "WHERE d.controleur_id = ?";
    $params[] = $_SESSION['user_id'];
} else {
    // Superviseur ou admin → voit tout
    $where = "";
}

$stmt = $pdo->prepare("
    SELECT d.*, tp.nom as type_prestation_nom 
    FROM dossiers d 
    LEFT JOIN types_prestation tp ON d.type_prestation_id = tp.id 
    $where 
    ORDER BY d.date_reception DESC
");
$stmt->execute($params);
$dossiers = $stmt->fetchAll();
?>