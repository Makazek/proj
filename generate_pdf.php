<?php
ob_start(); // 🔴 OBLIGATOIRE – bloque toute sortie parasite

session_start();
require_once __DIR__ . '/../config.php';

// Sécurité
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['superviseur', 'admin'])) {
    ob_end_clean();
    die('Accès refusé');
}

// TCPDF
require_once __DIR__ . '/../vendors/tcpdf/tcpdf.php';

/* =========================
   PARAMÈTRES PÉRIODE
========================= */
$mois = $_GET['mois'] ?? date('Y-m');
$debut = $mois . '-01';
$fin = date('Y-m-t', strtotime($debut));
$periode = date('F Y', strtotime($debut));

/* =========================
   KPI SERVICE
========================= */
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) AS total,
        SUM(statut = 'valide') AS valides,
        SUM(statut = 'rejete') AS rejetes,
        ROUND(
            AVG(
                CASE 
                    WHEN statut = 'valide' 
                    THEN DATEDIFF(date_validation, date_reception)
                END
            ), 1
        ) AS delai_moyen
    FROM dossiers
    WHERE date_reception BETWEEN ? AND ?
");
$stmt->execute([$debut, $fin]);
$kpi = $stmt->fetch(PDO::FETCH_ASSOC);

/* =========================
   PERFORMANCE AGENTS
========================= */
$stmt = $pdo->prepare("
    SELECT 
        u.nom_complet,
        COUNT(d.id) AS total,
        SUM(d.statut = 'valide') AS valides,
        SUM(d.statut = 'rejete') AS rejetes,
        ROUND(
            AVG(
                CASE 
                    WHEN d.statut = 'valide'
                    THEN DATEDIFF(d.date_validation, d.date_reception)
                END
            ), 1
        ) AS delai_moyen
    FROM users u
    LEFT JOIN dossiers d 
        ON d.controleur_id = u.id
        AND d.date_reception BETWEEN ? AND ?
    WHERE u.role = 'controleur'
    GROUP BY u.id
    ORDER BY valides DESC
");
$stmt->execute([$debut, $fin]);
$agents = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   CRÉATION PDF
========================= */
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

$pdf->SetCreator('CNSS');
$pdf->SetAuthor('CNSS');
$pdf->SetTitle('Rapport mensuel CNSS');
$pdf->SetMargins(15, 20, 15);
$pdf->AddPage();

/* =========================
   CONTENU PDF
========================= */
$html = "
<h1 style='text-align:center;color:#007bff;'>Rapport mensuel CNSS</h1>
<h3 style='text-align:center;'>$periode</h3>
<hr>

<h3>KPI du service</h3>
<table border='1' cellpadding='8'>
<tr style='background:#007bff;color:white'>
    <th>Indicateur</th>
    <th>Valeur</th>
</tr>
<tr><td>Total dossiers</td><td>{$kpi['total']}</td></tr>
<tr><td>Validés</td><td>{$kpi['valides']}</td></tr>
<tr><td>Rejetés</td><td>{$kpi['rejetes']}</td></tr>
<tr><td>Délai moyen</td><td>{$kpi['delai_moyen']} jours</td></tr>
</table>

<br><h3>Performance par contrôleur</h3>
<table border='1' cellpadding='8'>
<tr style='background:#28a745;color:white'>
    <th>Contrôleur</th>
    <th>Dossiers</th>
    <th>Validés</th>
    <th>Rejetés</th>
    <th>Délai moyen</th>
</tr>
";

foreach ($agents as $a) {
    $html .= "
    <tr>
        <td>{$a['nom_complet']}</td>
        <td>{$a['total']}</td>
        <td>{$a['valides']}</td>
        <td>{$a['rejetes']}</td>
        <td>{$a['delai_moyen']} j</td>
    </tr>
    ";
}

$html .= "</table>";

$pdf->writeHTML($html);

/* =========================
   SORTIE PDF
========================= */
ob_end_clean(); // 🔴 TRÈS IMPORTANT
$pdf->Output("rapport_cnss_$mois.pdf", 'D');
exit;
