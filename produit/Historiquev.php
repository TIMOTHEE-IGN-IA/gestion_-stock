<?php
session_start();
require_once __DIR__ . "/../includes/db.php";

if (!isset($_SESSION['user']['id'])) {
    header('Location: ../index.php');
    exit;
}

$user_id = (int) $_SESSION['user']['id'];
$role = $_SESSION['user']['role'];

// ================= UTILISATEURS AUTORISÉS =================

$allowed_users = [];

if ($role === 'Admin') {
    // admin lui-même
    $allowed_users[] = $user_id;

    // ses employés
    $stmt = $connexion->prepare("
        SELECT id FROM utilisateur
        WHERE admin_parent_id = ?
    ");
    $stmt->execute([$user_id]);

    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $allowed_users[] = (int)$r['id'];
    }

} else {
    // employé → seulement lui
    $allowed_users[] = $user_id;
}

if (empty($allowed_users)) {
    $allowed_users = [0];
}

$in_clause = implode(',', array_fill(0, count($allowed_users), '?'));

// ================= DATE =================

$today = date('Y-m-d');

// ================= VENTES =================

$params = array_merge([$today], $allowed_users);

$stmt = $connexion->prepare("
    SELECT 
        v.id,
        v.quantite,
        v.prix_unitaire,
        v.total,
        v.date,
        p.code,
        p.nom AS produit,
        u.nom AS vendeur
    FROM vente v
    JOIN produit p ON p.id = v.produit_id
    JOIN utilisateur u ON u.id = v.utilisateur_id
    WHERE DATE(v.date) = ?
    AND v.utilisateur_id IN ($in_clause)
    ORDER BY v.date DESC
");

$stmt->execute($params);
$ventes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================= TOTAL =================

$stmtTotal = $connexion->prepare("
    SELECT COALESCE(SUM(total),0)
    FROM vente
    WHERE DATE(date) = ?
    AND utilisateur_id IN ($in_clause)
");

$stmtTotal->execute($params);
$totalJour = $stmtTotal->fetchColumn();

?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Historique des ventes</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container mt-4">

    <h3 class="mb-4 text-primary text-center">
        <i class="bi bi-clock-history me-2"></i>
        Historique des ventes du jour (<?= date('d/m/Y') ?>)
    </h3>

<?php if (empty($ventes)): ?>

    <div class="alert alert-info text-center">
        Aucune vente enregistrée aujourd’hui.
    </div>

<?php else: ?>

<div class="table-responsive">
<table class="table table-bordered table-hover align-middle">

<thead class="table-dark">
<tr>
    <th>#</th>
    <th>Heure</th>
    <th>Produit</th>
    <th class="text-center">Qté</th>
    <th>Prix unitaire</th>
    <th>Total</th>
    <th>Vendeur</th>
</tr>
</thead>

<tbody>
<?php foreach ($ventes as $v): ?>
<tr>
    <td><?= $v['id'] ?></td>
    <td><?= date('H:i:s', strtotime($v['date'])) ?></td>

    <td>
        <?= htmlspecialchars($v['code'].' - '.$v['produit']) ?>
    </td>

    <td class="text-center fw-bold">
        <?= $v['quantite'] ?>
    </td>

    <td>
        <?= number_format($v['prix_unitaire'], 0, ',', ' ') ?> FCFA
    </td>

    <td class="fw-bold text-success">
        <?= number_format($v['total'], 0, ',', ' ') ?> FCFA
    </td>

    <td>
        <span class="badge bg-secondary">
            <?= htmlspecialchars($v['vendeur']) ?>
        </span>
    </td>

</tr>
<?php endforeach; ?>
</tbody>

</table>
</div>

<div class="alert alert-success text-end fs-5">
    💰 <strong>Total du jour :</strong>
    <?= number_format($totalJour, 0, ',', ' ') ?> FCFA
</div>

<?php endif; ?>

<div class="text-center mt-3">
    <a href="../dashboard.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Retour
    </a>
</div>

</div>

</body>
</html>
