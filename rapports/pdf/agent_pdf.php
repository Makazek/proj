<?php
session_start();
require '../../config.php';
require '../../vendors/tcpdf/tcpdf.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    die('ID agent invalide');
}

// Infos agent
$stmt = $pdo->prepare("SELECT nom_complet FROM users WHERE id = ? AND role = 'controleur'");
$stmt->execute([$id]);
$agent = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$agent) {
    die('Contrôleur introuvable');
}

// KPI agent
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) AS total,
        SUM(statut = 'valide') AS valides,
        SUM(statut = 'rejete') AS rejetes,
        ROUND(AVG(DATEDIFF(date_validation, date_reception)), 1) AS delai_moyen
    FROM dossiers
    WHERE controleur_id = ?
");
$stmt->execute([$id]);
$kpi = $stmt->fetch(PDO::FETCH_ASSOC);

$pdf = new TCPDF();
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 12);

$html = '<h1>Rapport performance - ' . htmlspecialchars($agent['nom_complet']) . '</h1>';
$html .= '<h2>Period : ' . date('F Y') . '</h2>';

$html .= '<h3>KPI</h3>';
$html .= '<table border="1" cellpadding="10">';
$html .= '<tr><th>Indicateur</th><th>Valeur</th></tr>';
$html .= '<tr><td>Dossiers assignés</td><td>' . $kpi['total'] . '</td></tr>';
$html .= '<tr><td>Dossiers validés</td><td>' . $kpi['valides'] . '</td></tr>';
$html .= '<tr><td>Dossiers rejetés</td><td>' . $kpi['rejetes'] . '</td></tr>';
$html .= '<tr><td>Délai moyen</td><td>' . ($kpi['delai_moyen'] ?? 'N/A') . ' jours</td></tr>';
$html .= '</table>';

$pdf->writeHTML($html);

$pdf->Output('rapport_agent_' . $id . '.pdf', 'D');