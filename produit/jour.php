<?php
session_start();
require_once __DIR__ . "/../includes/db.php";
if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
    exit("Accès refusé");
}
date_default_timezone_set('Africa/Abidjan');
$user_id = (int)$_SESSION['user']['id'];
// ==========================
// AJOUT DEPENSE
// ==========================
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['libelle'])) {
    $libelle = trim($_POST['libelle']);
    $montant = floatval($_POST['montant']);
    if ($libelle && $montant > 0) {
        $stmt = $connexion->prepare("INSERT INTO depenses (user_id, libelle, montant, date_depense) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$user_id, $libelle, $montant]);
        $message = "<div class='alert alert-success'>Dépense ajoutée avec succès</div>";
    } else {
        $message = "<div class='alert alert-danger'>Veuillez remplir correctement les champs</div>";
    }
}
// ==========================
// MARGE TOTALE
// ==========================
$stmt = $connexion->prepare("
    SELECT COALESCE(SUM(quantite * (prix_unitaire - prix_achat)),0)
    FROM produit
    WHERE user_id = ?
");
$stmt->execute([$user_id]);
$marge_totale = (float)$stmt->fetchColumn();
// ==========================
// DEPENSES DU JOUR
// ==========================
$stmt = $connexion->prepare("
    SELECT COALESCE(SUM(montant),0)
    FROM depenses
    WHERE DATE(date_depense) = CURDATE()
    AND user_id = ?
");
$stmt->execute([$user_id]);
$depenses_jour_total = (float)$stmt->fetchColumn();
// ==========================
// DEPENSES DU MOIS
// ==========================
$mois_courant = date('Y-m');
$stmt = $connexion->prepare("
    SELECT COALESCE(SUM(montant),0)
    FROM depenses
    WHERE DATE_FORMAT(date_depense,'%Y-%m') = ?
    AND user_id = ?
");
$stmt->execute([$mois_courant, $user_id]);
$depenses_mois_total = (float)$stmt->fetchColumn();
// ==========================
// BENEFICE NET
// ==========================
$benefice_net_total = $marge_totale - $depenses_mois_total;
// ==========================
// LISTE DES DEPENSES DU JOUR
// ==========================
$stmt = $connexion->prepare("
    SELECT *
    FROM depenses
    WHERE DATE(date_depense) = CURDATE()
    AND user_id = ?
    ORDER BY date_depense DESC
");
$stmt->execute([$user_id]);
$depenses_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dépenses du jour</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
/* ===== Global ===== */
body {
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
    background: #f9fafb;
    color: #1f2937;
    line-height: 1.6;
}
h2 {
    font-weight: 700;
    letter-spacing: -0.025em;
    color: #111827;
}
/* ===== Messages ===== */
.alert {
    border-radius: 8px;
    padding: 12px 18px;
    font-weight: 500;
    max-width: 650px;
    margin: 15px auto;
}
/* ===== Formulaire ===== */
#depenseForm {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    justify-content: center;
    margin-bottom: 40px;
}
#depenseForm .form-control {
    border-radius: 6px;
    border: 1px solid #ced4da;
    padding: 10px 12px;
    transition: 0.2s;
}
#depenseForm .form-control:focus {
    border-color: #495057;
    box-shadow: 0 0 6px rgba(73,80,87,0.2);
}
#depenseForm button {
    border-radius: 6px;
    background-color: #495057;
    color: #fff;
    font-weight: 500;
    transition: 0.2s;
}
#depenseForm button:hover {
    background-color: #343a40;
}
/* ===== Cartes statistiques ===== */
.row .card {
    border-radius: 8px;
    min-height: 130px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: 0.2s;
    text-align: center;
}
.row .card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.08);
}
.card h6 {
    font-weight: 500;
    color: #495057;
}
.card h4 {
    font-weight: 600;
    margin-top: 8px;
    font-size: 1.25rem;
}
/* Couleurs sobres */
.bg-success { background-color: #dff0d8 !important; color: #3c763d; }
.bg-danger { background-color: #f2dede !important; color: #a94442; }
.bg-warning { background-color: #fcf8e3 !important; color: #8a6d3b; }
.bg-info { background-color: #d9edf7 !important; color: #31708f; }
/* ===== Tableau ===== */
.table {
    border-radius: 8px;
    overflow: hidden;
    background: #fff;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
.table thead {
    background: #343a40;
    color: #fff;
}
.table th, .table td {
    vertical-align: middle;
    text-align: center;
}
.table tbody tr:hover {
    background: #f1f3f5;
}
/* ===== Responsive ===== */
@media (max-width: 768px) {
    #depenseForm {
        flex-direction: column;
        align-items: stretch;
    }
    #depenseForm .col-md-6,
    #depenseForm .col-md-4,
    #depenseForm .col-md-2 {
        width: 100%;
    }
    .row .col-md-3 { margin-bottom: 15px; }
}
</style>
</head>
<body>
<div class="container py-4">
    <h2 class="mb-4">💸 Dépenses & Bénéfice net</h2>
    <?= $message ?? '' ?>
    <!-- FORMULAIRE AJOUT DEPENSE -->
    <form id="depenseForm" method="POST">
        <div class="col-md-3">
            <input type="text" name="libelle" class="form-control" placeholder="Libellé dépense" required>
        </div>
        <div class="col-md-3">
            <input type="number" name="montant" class="form-control" placeholder="Montant en FCFA" step="0.01" required>
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn w-100">Ajouter</button>
        </div>
    </form>
    <!-- CARTES STATISTIQUES -->
    <div class="row mb-4 g-3">
        <div class="col-md-3">
            <div class="card bg-info">
                <div class="card-body">
                    <i class="bi bi-bar-chart-fill fs-3 mb-2 d-block"></i>
                    <h6>Bénéfice du stock</h6>
                    <div class="small text-muted">Bénéfice potentiel total</div>
                    <h4><?= number_format($marge_totale,0,',',' ') ?> FCFA</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card <?= $benefice_net_total >= 0 ? 'bg-success' : 'bg-danger' ?>">
                <div class="card-body">
                    <i class="bi bi-wallet2 fs-3 mb-2 d-block"></i>
                    <h6>Bénéfice net</h6>
                    <div class="small text-muted">Marge - Dépenses mois</div>
                    <h4><?= number_format($benefice_net_total,0,',',' ') ?> FCFA</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger">
                <div class="card-body">
                    <i class="bi bi-arrow-down-circle fs-3 mb-2 d-block"></i>
                    <h6>Dépenses du jour</h6>
                    <div class="small text-muted">Total aujourd'hui</div>
                    <h4><?= number_format($depenses_jour_total,0,',',' ') ?> FCFA</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning">
                <div class="card-body">
                    <i class="bi bi-calendar3 fs-3 mb-2 d-block"></i>
                    <h6>Dépenses du mois</h6>
                    <div class="small text-muted">Total mensuel</div>
                    <h4><?= number_format($depenses_mois_total,0,',',' ') ?> FCFA</h4>
                </div>
            </div>
        </div>
    </div>
    <!-- LISTE DES DEPENSES DU JOUR -->
    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Libellé</th>
                    <th>Montant</th>
                    <th>Date/Heure</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!empty($depenses_list)): ?>
                <?php foreach ($depenses_list as $index => $dep): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= htmlspecialchars($dep['libelle'] ?? '—') ?></td>
                        <td><?= number_format((float)$dep['montant'], 0, ',', ' ') ?> FCFA</td>
                        <td><?= date('d/m/Y H:i', strtotime($dep['date_depense'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" class="text-center py-4 text-muted">
                        Aucune dépense enregistrée aujourd'hui
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>