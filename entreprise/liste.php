<?php
// Forcer l'affichage des erreurs (très important pour debug)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../includes/db.php";

// Protection accès
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['Admin', 'Controle'])) {
    header("Location: ../login.php");
    exit("Accès refusé");
}

// ID de l'utilisateur connecté
$admin_id = $_SESSION['user']['id'] ?? 0;

// Déterminer le filtre selon le rôle
$is_controle = ($_SESSION['user']['role'] === 'Controle');
$where = $is_controle ? "1=1" : "admin_id = ?";
$params = $is_controle ? [] : [$admin_id];

// Requête sécurisée
try {
    $sql = "
        SELECT 
            e.id, 
            e.nom, 
            e.email, 
            e.telephone, 
            e.created_at, 
            u.nom AS admin_nom
        FROM entreprise e
        LEFT JOIN utilisateur u ON e.admin_id = u.id
        WHERE $where
        ORDER BY e.created_at DESC
    ";

    $stmt = $connexion->prepare($sql);
    $stmt->execute($params);
    $entreprises = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Erreur SQL : " . htmlspecialchars($e->getMessage()));
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des Entreprises - Nova</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; font-family: system-ui, sans-serif; }
        .table th { background: #e9ecef; font-weight: 600; }
        .card-header { background: #0d6efd; color: white; }
        .empty-state { text-align: center; padding: 5rem 1rem; color: #6c757d; }
        .btn-sm { font-size: 0.85rem; }
    </style>
</head>
<body>

<div class="container mt-5 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <h3 class="mb-0">
            <?= $is_controle ? 'Toutes les entreprises' : 'Mes entreprises' ?>
        </h3>
        <a href="ajouter.php" class="btn btn-success">+ Ajouter une entreprise</a>
    </div>

    <?php if (empty($entreprises)): ?>
        <div class="alert alert-info empty-state shadow-sm">
            <h4>Aucune entreprise trouvée</h4>
            <p>
                <?= $is_controle 
                    ? "Aucune entreprise n'a encore été créée." 
                    : "Vous n'avez pas encore ajouté d'entreprise." ?>
            </p>
            <a href="ajouter.php" class="btn btn-primary mt-3">Créer une entreprise</a>
        </div>
    <?php else: ?>
        <div class="card shadow">
            <div class="card-header">
                <h5 class="mb-0">
                    <?= $is_controle 
                        ? 'Liste complète (' . count($entreprises) . ')' 
                        : 'Vos entreprises (' . count($entreprises) . ')' ?>
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nom</th>
                                <th>Email</th>
                                <th>Téléphone</th>
                                <th>Créée le</th>
                                <?php if ($is_controle): ?>
                                    <th>Créée par</th>
                                <?php endif; ?>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $i = 1; foreach ($entreprises as $ent): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><strong><?= htmlspecialchars($ent['nom'] ?? '—') ?></strong></td>
                                <td><?= htmlspecialchars($ent['email'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($ent['telephone'] ?? '—') ?></td>
                                <td><?= $ent['created_at'] ? date('d/m/Y à H\hi', strtotime($ent['created_at'])) : '—' ?></td>
                                <?php if ($is_controle): ?>
                                    <td><?= htmlspecialchars($ent['admin_nom'] ?? '—') ?></td>
                                <?php endif; ?>
                                <td>
                                    <a href="voir.php?id=<?= $ent['id'] ?>" class="btn btn-sm btn-outline-primary">Voir</a>
                                    <!-- <a href="modifier.php?id=<?= $ent['id'] ?>" class="btn btn-sm btn-outline-warning">Modifier</a> -->
                                    <!-- <a href="#" onclick="return confirm('Supprimer ?');" class="btn btn-sm btn-outline-danger">Supprimer</a> -->
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="text-center mt-5">
        <a href="../dashboard.php" class="btn btn-outline-secondary">← Retour au dashboard</a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>