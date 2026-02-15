<?php
session_start();
require_once __DIR__ . "/../includes/db.php";

// Vérifie que l'utilisateur est Controle (Nova)
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'Controle') {
    exit("Accès refusé");
}

$BASE_MONTANT = 500;

// Récupérer tous les admins
$stmt = $connexion->prepare("SELECT id, nom, photo FROM utilisateur WHERE role='Admin' ORDER BY nom ASC");
$stmt->execute();
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

$admins_data = [];
$current_month = date('F Y'); // Mois en cours, ex: Février 2026

foreach($admins as $admin){
    // Nombre d'entreprises de l'admin
    $stmt = $connexion->prepare("SELECT COUNT(*) FROM entreprise WHERE admin_id=?");
    $stmt->execute([$admin['id']]);
    $nb_entreprises = (int)$stmt->fetchColumn();

    // Nombre d'employés sous cet admin
    $stmt = $connexion->prepare("SELECT COUNT(*) FROM utilisateur WHERE admin_parent_id=?");
    $stmt->execute([$admin['id']]);
    $nb_employes = (int)$stmt->fetchColumn();

    // Montant à payer
    $montant = $BASE_MONTANT * $nb_entreprises * $nb_employes;

    // Statut paiement
    $stmt = $connexion->prepare("
        SELECT COUNT(*) FROM subscription s
        JOIN utilisateur u2 ON s.user_id=u2.id
        WHERE u2.admin_parent_id=? 
          AND MONTH(s.date_paiement)=MONTH(CURDATE())
          AND YEAR(s.date_paiement)=YEAR(CURDATE())
    ");
    $stmt->execute([$admin['id']]);
    $nb_payes = (int)$stmt->fetchColumn();

    $today_day = (int)date('d');
    $last_day = (int)date('t');

    $status = 'attente'; // bleu
    if($nb_payes >= $nb_employes) $status='paye'; // vert
    elseif($today_day >= $last_day) $status='non'; // rouge

    $admins_data[] = [
        'id'=>$admin['id'],
        'nom'=>$admin['nom'],
        'photo'=>$admin['photo'],
        'nb_entreprises'=>$nb_entreprises,
        'nb_employes'=>$nb_employes,
        'montant'=>$montant,
        'status'=>$status,
        'month'=>$current_month
    ];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Admins - Nova</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.card { margin-bottom:1rem; text-align:center; }
.badge-paye { animation: blinkGreen 1s infinite; cursor:pointer; }
.badge-non { animation: blinkRed 1s infinite; cursor:pointer; }
.badge-attente { animation: blinkBlue 1s infinite; cursor:pointer; }

@keyframes blinkGreen { 0%,50%,100% {opacity:1;} 25%,75% {opacity:0.3;} }
@keyframes blinkRed { 0%,50%,100% {opacity:1;} 25%,75% {opacity:0.3;} }
@keyframes blinkBlue { 0%,50%,100% {opacity:1;} 25%,75% {opacity:0.3;} }

</style>
</head>
<body>
<div class="container mt-3">
<h4>Admins et leurs employés</h4>
<div class="row">
<?php foreach($admins_data as $a): ?>
    <div class="col-md-4 col-sm-6">
        <div class="card p-3">
            <?php if($a['photo']): ?>
                <img src="data:image/jpeg;base64,<?= base64_encode($a['photo']) ?>" style="width:80px;height:80px;border-radius:50%;margin-bottom:10px;">
            <?php else: ?>
                <img src="default-avatar.png" style="width:80px;height:80px;border-radius:50%;margin-bottom:10px;">
            <?php endif; ?>
            <h5><?= htmlspecialchars($a['nom']) ?></h5>
            <p>Mois: <strong><?= $a['month'] ?></strong></p>
            <p>Entreprises: <?= $a['nb_entreprises'] ?></p>
            <p>Employés: <?= $a['nb_employes'] ?></p>
            <p>Montant: <?= number_format($a['montant'],0,',',' ') ?> F</p>
            <span 
                class="<?= $a['status']=='paye' ? 'badge-paye bg-success' : ($a['status']=='non' ? 'badge-non bg-danger' : 'badge-attente bg-primary') ?>" 
                id="badge-<?= $a['id'] ?>"
                onclick="togglePayment(<?= $a['id'] ?>)">
                <?= $a['status']=='paye' ? 'Payé' : ($a['status']=='non' ? 'Non' : 'En attente') ?>
            </span>
        </div>
    </div>
<?php endforeach; ?>
</div>
</div>

<script>
function togglePayment(adminId){
    fetch('toggle_payment.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({user_id:adminId})
    })
    .then(res=>res.json())
    .then(data=>{
        const badge = document.getElementById('badge-'+adminId);
        if(data.success){
            if(data.paye){
                badge.className = 'badge-paye bg-success';
                badge.textContent = 'Payé';
            } else {
                badge.className = 'badge-non bg-danger';
                badge.textContent = 'Non';
            }
        }
    });
}
</script>
</body>
</html>
