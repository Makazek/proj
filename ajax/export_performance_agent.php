<?php
ob_start();
session_start();
require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['superviseur','admin'])) {
    die('Accès refusé');
}

require_once __DIR__ . '/../vendors/tcpdf/tcpdf.php';

/* =========================
   PARAMÈTRES
========================= */
$controleur_id = intval($_GET['controleur_id'] ?? 0);
$debut = $_GET['debut'] ?? null;
$fin   = $_GET['fin'] ?? null;

if ($controleur_id <= 0) die('Contrôleur invalide');

$stmt = $pdo->prepare("SELECT nom_complet FROM users WHERE id = ? AND role = 'controleur'");
$stmt->execute([$controleur_id]);
$agent = $stmt->fetch();
if (!$agent) die('Agent introuvable');

/* =========================
   PÉRIODE (compatible avec la page rapport service)
========================= */
if (empty($debut) || empty($fin)) {
    $mois = date('Y-m');
    $debut = $mois . '-01';
    $fin   = date('Y-m-t', strtotime($debut));
}

$whereDate = " AND date_reception BETWEEN ? AND ? ";
$params = [$controleur_id, $debut, $fin];

/* =========================
   KPI AGENT
========================= */
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(statut = 'valide') AS valides,
        SUM(statut = 'rejete') AS rejetes,
        SUM(statut = 'soumis_chef') AS attente,
        ROUND(AVG(CASE WHEN statut = 'valide' THEN DATEDIFF(date_validation, date_reception) END),1) AS delai_moyen
    FROM dossiers
    WHERE controleur_id = ?
    $whereDate
");
$stmt->execute($params);
$kpi = $stmt->fetch();

$total       = (int)$kpi['total'];
$valides     = (int)$kpi['valides'];
$rejetes     = (int)$kpi['rejetes'];
$attente     = (int)$kpi['attente'];
$delai_moyen = $kpi['delai_moyen'] ?? 0;

$en_cours    = $total - $valides - $rejetes - $attente;

$taux_prod    = $total > 0 ? round(($valides / $total) * 100, 1) : 0;
$taux_qualite = ($valides + $rejetes) > 0 ? round(($valides / ($valides + $rejetes)) * 100, 1) : 0;
$taux_rejet   = ($valides + $rejetes) > 0 ? round(($rejetes / ($valides + $rejetes)) * 100, 1) : 0;

/* Jours avec validation */
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT DATE(date_reception)) AS jours FROM dossiers WHERE controleur_id = ? AND statut = 'valide' $whereDate");
$stmt->execute([$controleur_id, $debut, $fin]);
$jours_valides = (int)$stmt->fetchColumn();
$dossiers_par_jour = $jours_valides > 0 ? round($valides / $jours_valides, 1) : 0;

/* Répartition graphique */
$repartition = [$valides, $rejetes, $attente, $en_cours];

/* Labels */
$period_label = date('d/m/Y', strtotime($debut)) . ' → ' . date('d/m/Y', strtotime($fin));
$generated_on = date('d F Y à H:i');

/* =========================
   PDF CONFIG
========================= */
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('CNSS');
$pdf->SetAuthor('Chef de Service');
$pdf->SetTitle('Rapport de Performance Individuelle - CNSS');
$pdf->SetMargins(18, 15, 18);
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(10);
$pdf->SetAutoPageBreak(TRUE, 18); // marge basse réduite

$pdf->setHeaderData(__DIR__ . '/../assets/images/logo_cnss.png', 30, 'CNSS - Rapport de Performance Individuelle', "Agent : {$agent['nom_complet']}");

$pdf->AddPage();

/* Couleurs */
$blue   = [0, 51, 102];
$green  = [46, 125, 50];
$orange = [239, 108, 0];
$red    = [198, 40, 40];
$grey   = [68, 68, 68];
$light  = [244, 246, 248];

/* =========================
   TITRE
========================= */
$pdf->SetFont('helvetica', 'B', 22);
$pdf->SetTextColorArray($blue);
$pdf->Cell(0, 15, 'Rapport de Performance Individuelle', 0, 1, 'C');
$pdf->Ln(5);

$pdf->SetFont('helvetica', '', 11);
$pdf->SetTextColorArray($grey);
$pdf->Cell(0, 8, "Période : $period_label • Usage Exécutif", 0, 1, 'C');
$pdf->Ln(12);

/* =========================
   SYNTHÈSE EXÉCUTIVE
========================= */
$pdf->SetFont('helvetica', 'B', 15);
$pdf->SetTextColorArray($blue);
$pdf->Cell(0, 10, 'Synthèse Exécutive', 0, 1, 'L');
$pdf->Ln(4);

$pdf->SetFont('helvetica', '', 10.5);
$pdf->SetTextColorArray($grey);
$summary = "Au cours de la période analysée, l'agent <b>{$agent['nom_complet']}</b> a traité <b>" . number_format($total) . "</b> dossiers. "
         . "<b>{$valides}</b> ont été validés (<b>{$taux_prod}%</b> de productivité). "
         . "Le délai moyen de validation est de <b>{$delai_moyen}</b> jours.";

$pdf->writeHTML($summary, true, false, true, false, '');
$pdf->Ln(12);

/* =========================
   KPI EN LIGNE
========================= */
$kpi_cards = [
    ['Total Dossiers', number_format($total), $blue],
    ['Validés', number_format($valides), $green],
    ['Rejetés', number_format($rejetes), $red],
    ['En attente chef', number_format($attente), $orange]
];

$html_kpi = '<table border="0" cellpadding="10">';
$html_kpi .= '<tr bgcolor="#f4f6f8">';
foreach ($kpi_cards as $card) {
    $html_kpi .= '<td width="25%" align="center"><b style="font-size:16pt;">' . $card[1] . '</b><br><span style="color:rgb(' . implode(',', $card[2]) . ')">' . $card[0] . '</span></td>';
}
$html_kpi .= '</tr></table>';

$pdf->writeHTML($html_kpi, true, false, true, false, '');
$pdf->Ln(15);

/* =========================
   ÉVALUATION DU RISQUE
========================= */
$pdf->SetFont('helvetica', 'B', 15);
$pdf->SetTextColorArray($blue);
$pdf->Cell(0, 10, 'Évaluation du Risque Opérationnel', 0, 1, 'L');
$pdf->Ln(4);

$risk_level = "FAIBLE";
$risk_color = $green;
if ($taux_rejet > 25) {
    $risk_level = "ÉLEVÉ";
    $risk_color = $red;
} elseif ($taux_rejet > 15) {
    $risk_level = "MOYEN";
    $risk_color = $orange;
}

$html_risk = '<table border="1" cellpadding="10">
<tr bgcolor="#f4f6f8">
<td width="60%"><b>Niveau de Risque Global</b></td>
<td width="40%" align="center"><b style="font-size:16pt; color:rgb(' . implode(',', $risk_color) . ')">' . $risk_level . '</b></td>
</tr>
<tr><td>Taux de Qualité</td><td align="center"><b>' . $taux_qualite . '%</b></td></tr>
<tr><td>Taux de Rejet</td><td align="center"><b>' . $taux_rejet . '%</b></td></tr>
<tr><td>Délai Moyen</td><td align="center"><b>' . $delai_moyen . ' jours</b></td></tr>
</table>';

$pdf->writeHTML($html_risk, true, false, true, false, '');
$pdf->Ln(10);

$interpretation = $taux_rejet >= 25
    ? "Le taux de rejet élevé (<b>{$taux_rejet}%</b>) indique un risque opérationnel important. Une analyse approfondie des motifs de rejet et une formation ciblée sont fortement recommandées."
    : ($taux_qualite < 80
        ? "Une proportion significative de dossiers nécessite une intervention du chef de service. Cela représente une opportunité d'améliorer la qualité des décisions initiales."
        : "La performance est globalement satisfaisante avec un taux de productivité de <b>{$taux_prod}%</b>. Les pratiques actuelles sont efficaces.");

$pdf->writeHTML($interpretation, true, false, true, false, '');
$pdf->Ln(15);
// ===== FIX TCPDF : vérifier l'espace avant de dessiner le graphique =====
$required_space = 120; // hauteur réelle du graphique + labels + marge
$available_space = $pdf->getPageHeight() - $pdf->GetY() - $pdf->getBreakMargin();

if ($available_space < $required_space) {
    $pdf->AddPage();
}
/* =========================
   SYNTHÈSE DE PERFORMANCE PAR STATUT
========================= */
$pdf->SetFont('helvetica', 'B', 15);
$pdf->SetTextColorArray($blue);
$pdf->Cell(0, 10, 'Synthèse de Performance par Statut', 0, 1, 'L');
$pdf->Ln(6);

$html_perf = '
<table border="1" cellpadding="10">
<tr bgcolor="#f4f6f8">
    <th width="40%">Statut</th>
    <th width="20%" align="center">Volume</th>
    <th width="40%" align="center">Lecture Managériale</th>
</tr>

<tr>
    <td><b>Validés</b></td>
    <td align="center"><b style="color:rgb('.implode(',',$green).')">'.$valides.'</b></td>
    <td>Décisions conformes et exploitables</td>
</tr>

<tr>
    <td><b>Rejetés</b></td>
    <td align="center"><b style="color:rgb('.implode(',',$red).')">'.$rejetes.'</b></td>
    <td>Non-conformités détectées</td>
</tr>

<tr>
    <td><b>En attente chef</b></td>
    <td align="center"><b style="color:rgb('.implode(',',$orange).')">'.$attente.'</b></td>
    <td>Dossiers nécessitant arbitrage</td>
</tr>

<tr>
    <td><b>En cours</b></td>
    <td align="center"><b>'.$en_cours.'</b></td>
    <td>Traitement non finalisé</td>
</tr>
</table>
';

$pdf->writeHTML($html_perf, false, false, true, false, '');
$pdf->Ln(8); // séparation réelle après le tableau
/* =========================
   RECOMMANDATIONS EN TABLEAU
========================= */
$pdf->SetFont('helvetica', 'B', 15);
$pdf->SetTextColorArray($blue);
$pdf->Cell(0, 10, 'Recommandations', 0, 1, 'L');
$pdf->Ln(4);

$recs = [];
if ($taux_rejet > 20) $recs[] = "Renforcer la formation sur les motifs de rejet les plus fréquents.";
if ($delai_moyen > 6) $recs[] = "Optimiser les workflows pour réduire les délais de validation.";
if ($taux_prod < 75) $recs[] = "Augmenter le volume quotidien traité via une meilleure organisation.";
if ($attente > 10) $recs[] = "Réduire les dossiers en attente de décision du chef de service.";
if (empty($recs)) $recs[] = "Maintenir les excellentes pratiques actuelles.";

$html_recs = '<table border="1" cellpadding="8">
<tr bgcolor="#003366" style="color:white;">
<th width="100%">Recommandation</th>
</tr>';
foreach ($recs as $rec) {
    $html_recs .= '<tr><td>' . $rec . '</td></tr>';
}
$html_recs .= '</table>';

$pdf->writeHTML($html_recs, true, false, true, false, '');

/* =========================
   FOOTER
========================= */
$pdf->SetY(-18);
$pdf->SetFont('helvetica', 'I', 10);
$pdf->SetTextColorArray($grey);
$pdf->Cell(0, 10, "Généré le $generated_on • CNSS • Confidentiel", 0, 0, 'C');

ob_end_clean();
$pdf->Output('performance_' . str_replace([' ', '.'], '_', $agent['nom_complet']) . '.pdf', 'D');
exit;