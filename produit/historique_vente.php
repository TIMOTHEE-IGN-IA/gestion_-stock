<?php
session_start();

/* ✅ Timezone Côte d’Ivoire */
date_default_timezone_set('Africa/Abidjan');

require_once __DIR__ . "/../includes/db.php";

if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit;
}

$user = $_SESSION['user'];

/* ============================
   RÉCUPÉRATION DES VENTES
============================ */

// ADMIN : toutes les ventes
if ($user['role'] === 'Admin') {

    $stmt = $connexion->query("
        SELECT 
            v.*, 
            p.nom AS produit, 
            u.nom AS employe, 
            v.date AS date_vente
        FROM vente v
        JOIN produit p ON v.produit_id = p.id
        JOIN utilisateur u ON v.utilisateur_id = u.id
        ORDER BY v.date DESC
    ");

    $ventes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} 
// EMPLOYÉ : ses ventes seulement
else {

    $stmt = $connexion->prepare("
        SELECT 
            v.*, 
            p.nom AS produit, 
            v.date AS date_vente
        FROM vente v
        JOIN produit p ON v.produit_id = p.id
        WHERE v.utilisateur_id = ?
        ORDER BY v.date DESC
    ");

    $stmt->execute([$user['id']]);
    $ventes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Sécurité
if (!$ventes) {
    $ventes = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Historique des ventes</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
<div class="container mt-5">

    <h2>🧾 Historique des ventes</h2>
    <p>
        Connecté en tant que :
        <strong><?= htmlspecialchars($user['nom']) ?></strong>
        (<?= htmlspecialchars($user['role']) ?>)
    </p>

    <?php if (empty($ventes)): ?>
        <div class="alert alert-info">
            Aucune vente enregistrée pour le moment.
        </div>
    <?php else: ?>

    <table class="table table-bordered table-striped">
        <thead class="table-dark">
        <tr>
            <th>Date & Heure</th>
            <th>Produit</th>
            <?php if ($user['role'] === 'Admin'): ?>
                <th>Employé</th>
            <?php endif; ?>
            <th>Quantité</th>
            <th>Prix unitaire (FCFA)</th>
            <th>TVA (%)</th>
            <th>Total (FCFA)</th>
        </tr>
        </thead>

        <tbody>
        <?php foreach ($ventes as $v): ?>
        <tr>
            <td>
                <?= date('d/m/Y H:i:s', strtotime($v['date_vente'])) ?>
            </td>
            <td><?= htmlspecialchars($v['produit']) ?></td>

            <?php if ($user['role'] === 'Admin'): ?>
                <td><?= htmlspecialchars($v['employe']) ?></td>
            <?php endif; ?>

            <td><?= (int)$v['quantite'] ?></td>
            <td><?= number_format($v['prix_unitaire'], 2, ',', ' ') ?></td>
            <td><?= number_format($v['tva'], 2) ?></td>
            <td><?= number_format($v['total'], 2, ',', ' ') ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php endif; ?>

    <a href="../dashboard.php" class="btn btn-secondary mt-3">
        ⬅ Retour Dashboard
    </a>

</div>
</body>
</html>
