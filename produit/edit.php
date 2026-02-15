<?php
session_start();
require_once __DIR__ . "/../includes/db.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'Admin') {
    echo json_encode(["status" => "error", "message" => "Accès refusé"]);
    exit;
}

try {

    $id            = $_POST['id'];
    $code          = $_POST['code'];
    $nom           = $_POST['nom'];
    $categorie     = $_POST['categorie'];
    $quantite      = (int)$_POST['quantite'];
    $prix_achat    = (float)$_POST['prix_achat'];
    $prix_unitaire = (float)$_POST['prix_unitaire'];
    $fournisseur   = $_POST['fournisseur'];

    // 🔹 Calcul bénéfice réel
    $beneficeAjoute = $quantite * ($prix_unitaire - $prix_achat);

    // 🔹 Récupérer ancien bénéfice
    $stmtOld = $connexion->prepare("SELECT benefice_net FROM produit WHERE id = ?");
    $stmtOld->execute([$id]);
    $oldData = $stmtOld->fetch(PDO::FETCH_ASSOC);

    $ancienBenefice = $oldData['benefice_net'] ?? 0;

    $nouveauBenefice = $ancienBenefice + $beneficeAjoute;

    // 🔹 Mise à jour produit
    $stmt = $connexion->prepare("
        UPDATE produit SET
            code = ?,
            nom = ?,
            categorie = ?,
            quantite = ?,
            prix_achat = ?,
            prix_unitaire = ?,
            fournisseur = ?,
            benefice_net = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $code,
        $nom,
        $categorie,
        $quantite,
        $prix_achat,
        $prix_unitaire,
        $fournisseur,
        $nouveauBenefice,
        $id
    ]);

    echo json_encode([
        "status" => "success",
        "message" => "Produit modifié avec succès"
    ]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
