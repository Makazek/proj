<?php
session_start();
require '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'controleur') {
    echo json_encode(['success' => false, 'message' => 'Accès refusé']);
    exit;
}

$numero_dossier     = trim($_POST['numero_dossier'] ?? '');
$nom_assure         = trim($_POST['nom_assure'] ?? '');
$regime             = $_POST['regime'] ?? '';
$type_prestation_id = intval($_POST['type_prestation_id'] ?? 0);
$date_reception     = $_POST['date_reception'] ?? '';

// Validation
if ($numero_dossier === '' || $nom_assure === '' || !in_array($regime, ['RP', 'RG']) || $type_prestation_id <= 0 || $date_reception === '') {
    echo json_encode(['success' => false, 'message' => 'Tous les champs sont obligatoires']);
    exit;
}

// Unicité numéro dossier
$stmt = $pdo->prepare("SELECT id FROM dossiers WHERE numero_dossier = ?");
$stmt->execute([$numero_dossier]);
if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Ce numéro de dossier existe déjà']);
    exit;
}

// Insertion
$stmt = $pdo->prepare("
    INSERT INTO dossiers 
    (numero_dossier, nom_assure, regime, type_prestation_id, controleur_id, statut, date_reception)
    VALUES (?, ?, ?, ?, ?, 'recu', ?)
");
$stmt->execute([
    $numero_dossier,
    $nom_assure,
    $regime,
    $type_prestation_id,
    $_SESSION['user_id'],
    $date_reception
]);

$dossier_id = $pdo->lastInsertId();

// Insertion automatique des pièces
$stmt = $pdo->prepare("SELECT id FROM pieces_requises WHERE type_prestation_id = ?");
$stmt->execute([$type_prestation_id]);
$pieces = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (!empty($pieces)) {
    $insert = $pdo->prepare("
        INSERT INTO pieces_fournies (dossier_id, piece_requise_id, original_fourni, copie_fourni, valide)
        VALUES (?, ?, 0, 0, 0)
    ");
    foreach ($pieces as $piece_id) {
        $insert->execute([$dossier_id, $piece_id]);
    }
}

// Log
$log = $pdo->prepare("INSERT INTO historique (dossier_id, user_id, action) VALUES (?, ?, 'Création dossier')");
$log->execute([$dossier_id, $_SESSION['user_id']]);

echo json_encode([
    'success' => true,
    'message' => 'Dossier créé avec succès !',
    'dossier_id' => $dossier_id
]);
?>