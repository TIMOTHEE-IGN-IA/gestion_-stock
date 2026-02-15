<?php
session_start();
require_once __DIR__ . "/../includes/db.php";

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'Controle') {
    exit(json_encode(['error'=>'Accès refusé']));
}

// Récupère le JSON envoyé depuis fetch()
$input = json_decode(file_get_contents('php://input'), true);
$admin_id = $input['user_id'] ?? null;

if(!$admin_id){
    exit(json_encode(['error'=>'Admin ID manquant']));
}

// Mois en cours
$current_month = date('M Y'); // ex: "Feb 2026"

// Vérifie si une subscription existe pour ce mois
$stmt = $connexion->prepare("SELECT id, status FROM subscription WHERE admin_id=? AND month_year=?");
$stmt->execute([$admin_id, $current_month]);
$sub = $stmt->fetch(PDO::FETCH_ASSOC);

if($sub){
    // Toggle status
    $new_status = ($sub['status']=='paye') ? 'non' : 'paye';
    $update = $connexion->prepare("UPDATE subscription SET status=? WHERE id=?");
    $update->execute([$new_status, $sub['id']]);
} else {
    // Crée une subscription pour ce mois
    $new_status = 'paye';
    $insert = $connexion->prepare("INSERT INTO subscription (admin_id, month_year, montant, status) VALUES (?, ?, ?, ?)");
    
    // On récupère nombre d'employés et d'entreprises pour calculer le montant
    $stmt_emp = $connexion->prepare("SELECT COUNT(*) FROM utilisateur WHERE admin_parent_id=?");
    $stmt_emp->execute([$admin_id]);
    $nb_employes = (int)$stmt_emp->fetchColumn();

    $stmt_ent = $connexion->prepare("SELECT COUNT(*) FROM entreprise WHERE admin_id=?");
    $stmt_ent->execute([$admin_id]);
    $nb_entreprises = (int)$stmt_ent->fetchColumn();

    $BASE_MONTANT = 5000;
    $montant = $BASE_MONTANT * $nb_employes * max($nb_entreprises,1);

    $insert->execute([$admin_id, $current_month, $montant, $new_status]);
}

echo json_encode([
    'success'=>true,
    'paye'=>($new_status=='paye')
]);
