$dossier_id = $pdo->lastInsertId();

// Récupère les pièces requises pour ce type de prestation
$stmt = $pdo->prepare("SELECT id FROM pieces_requises WHERE type_prestation_id = ?");
$stmt->execute([$type_prestation_id]);
$pieces_requises = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (!empty($pieces_requises)) {
    $insert = $pdo->prepare("
        INSERT INTO pieces_fournies (dossier_id, piece_requise_id, original_fourni, copie_fourni, valide)
        VALUES (?, ?, 0, 0, 0)
    ");
    foreach ($pieces_requises as $piece_id) {
        $insert->execute([$dossier_id, $piece_id]);
    }
}