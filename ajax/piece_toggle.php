<?php
session_start();
require '../config.php';

if ($_SESSION['role'] !== 'controleur') {
    http_response_code(403);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$dossier_id = intval($data['dossier_id']);
$piece_id   = intval($data['piece_id']);
$valide     = intval($data['valide']);

$stmt = $pdo->prepare("
    INSERT INTO pieces_fournies (dossier_id, piece_requise_id, valide)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE valide = VALUES(valide)
");
$stmt->execute([$dossier_id, $piece_id, $valide]);
