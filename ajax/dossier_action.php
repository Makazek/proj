<?php
session_start();
require '../config.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

$dossier_id = (int)($data['dossier_id'] ?? 0);
$action = $data['action'] ?? '';
$commentaire = trim($data['commentaire'] ?? '');

if (!$dossier_id || !$action) {
    echo json_encode(['success'=>false,'message'=>'Requête invalide']);
    exit;
}

// Récupération dossier
$stmt = $pdo->prepare("SELECT * FROM dossiers WHERE id = ?");
$stmt->execute([$dossier_id]);
$dossier = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$dossier) {
    echo json_encode(['success'=>false,'message'=>'Dossier introuvable']);
    exit;
}

$role = $_SESSION['role'] ?? '';
$statut = $dossier['statut'];

$transitionOK = false;

// RÈGLES MÉTIER
if ($role === 'controleur') {
    if ($statut === 'recu' && $action === 'en_cours') $transitionOK = true;
    if ($statut === 'en_cours' && in_array($action,['termine','rejete'])) $transitionOK = true;
    if ($statut === 'rejete' && $action === 'en_cours') $transitionOK = true;
}

if (in_array($role,['superviseur','admin'])) {
    if ($statut === 'termine' && in_array($action,['valide','rejete'])) $transitionOK = true;
}

if (!$transitionOK) {
    echo json_encode(['success'=>false,'message'=>'Action non autorisée']);
    exit;
}

// Update dossier
$stmt = $pdo->prepare("
    UPDATE dossiers
    SET statut = ?, motif_rejet = ?
    WHERE id = ?
");
$stmt->execute([
    $action,
    $action === 'rejete' ? $commentaire : null,
    $dossier_id
]);

// Historique
$stmt = $pdo->prepare("
    INSERT INTO historique (dossier_id, user_id, action, commentaire, ip_address)
    VALUES (?, ?, ?, ?, ?)
");
$stmt->execute([
    $dossier_id,
    $_SESSION['user_id'],
    strtoupper($action),
    $commentaire,
    $_SERVER['REMOTE_ADDR'] ?? null
]);

echo json_encode(['success'=>true]);
