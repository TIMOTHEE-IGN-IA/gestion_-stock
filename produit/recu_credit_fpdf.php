<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../vendor/fpdf186/fpdf.php';

if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
    exit('Accès refusé');
}

if (!isset($_GET['id'])) exit('ID manquant');
$id = (int)$_GET['id'];

// Récupération crédit
$stmt = $connexion->prepare("SELECT * FROM credits WHERE id = ?");
$stmt->execute([$id]);
$credit = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$credit) exit('Crédit introuvable');


// ===== Format ticket thermique =====
$pdf = new FPDF('P','mm',[80,200]);
$pdf->AddPage();
$pdf->SetMargins(4,4,4);



$pdf->Ln(2);
$pdf->Cell(0,0,'','T',1);
$pdf->Ln(2);

$pdf->SetFont('Arial','B',10);
$pdf->Cell(0,5,'RECU DE CREDIT',0,1,'C');

$pdf->Ln(2);

$pdf->SetFont('Arial','',9);

// ===== Infos client =====
$pdf->Cell(0,5,'Client : '.utf8_decode($credit['client']),0,1);

$tel = !empty($credit['telephone'])
    ? '225'.preg_replace('/\D/','',$credit['telephone'])
    : '---';

$pdf->Cell(0,5,'WhatsApp : '.$tel,0,1);

$pdf->Cell(0,5,'Date credit : '.$credit['date_credit'],0,1);
$pdf->Cell(0,5,'Paiement prevu : '.$credit['date_paiement_prevu'],0,1);

$pdf->Ln(1);
$pdf->Cell(0,0,'','T',1);
$pdf->Ln(2);

// ===== Détails =====
$pdf->MultiCell(0,5,'Libelle : '.utf8_decode($credit['libelle']));

$pdf->Ln(1);

$pdf->SetFont('Arial','B',9);
$pdf->Cell(0,5,'Montant : '.number_format($credit['montant'],0,',',' ').' FCFA',0,1);

$pdf->Cell(0,5,'Reste : '.number_format($credit['reste'] ?? 0,0,',',' ').' FCFA',0,1);

$pdf->Cell(0,5,'Statut : '.utf8_decode($credit['statut']),0,1);

$pdf->Ln(2);
$pdf->Cell(0,0,'','T',1);
$pdf->Ln(3);

// ===== Footer =====
$pdf->SetFont('Arial','',8);
$pdf->MultiCell(0,4,utf8_decode(
    "Paiement a credit\n".
    "Marchandises vendues sans reprise\n".
    "Merci pour votre confiance\n".
    "Nova Stock © 2026"
),0,'C');

$pdf->Output('I','recu_credit_'.$credit['id'].'.pdf');
