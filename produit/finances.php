<?php
require_once __DIR__ . "/../includes/db.php";

// Total ventes
$sqlVente = "SELECT SUM(montant) as total_ventes FROM vente";
$totalVentes = $connexion->query($sqlVente)->fetch(PDO::FETCH_ASSOC)['total_ventes'] ?? 0;

// Total dépenses
$sqlDep = "SELECT SUM(montant) as total_dep FROM depenses";
$totalDepenses = $connexion->query($sqlDep)->fetch(PDO::FETCH_ASSOC)['total_dep'] ?? 0;

// Total crédits (reste à payer)
$sqlCred = "SELECT SUM(reste) as total_credits FROM credits WHERE statut='actif'";
$totalCredits = $connexion->query($sqlCred)->fetch(PDO::FETCH_ASSOC)['total_credits'] ?? 0;

// Solde
$solde = $totalVentes - $totalDepenses - $totalCredits;
?>

<h3>Résumé Financier</h3>

<div class="row">
    <div class="col-md-3">
        <div class="card text-center bg-success text-white p-3">
            <h5>Total Ventes</h5>
            <h3><?= number_format($totalVentes, 0, ',', ' ') ?> F</h3>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center bg-danger text-white p-3">
            <h5>Total Dépenses</h5>
            <h3><?= number_format($totalDepenses, 0, ',', ' ') ?> F</h3>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center bg-warning text-dark p-3">
            <h5>Total Crédits</h5>
            <h3><?= number_format($totalCredits, 0, ',', ' ') ?> F</h3>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center bg-primary text-white p-3">
            <h5>Solde</h5>
            <h3><?= number_format($solde, 0, ',', ' ') ?> F</h3>
        </div>
    </div>
</div>
