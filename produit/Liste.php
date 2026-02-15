<?php
// ================= DEBUG (retirer en prod) =================
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ================= SESSION =================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================= INCLUDES ROBUSTES =================
require_once __DIR__ . "/../includes/db.php";

if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
    header("Location: ../index.php");
    exit;
}
$user_id = $_SESSION['user']['id'];

// Fonction pour générer un slug de catégorie
function slugCategorie($cat) {
    $cat = trim($cat ?? 'Non classé');
    $cat = str_replace(
        ['é','è','ê','ë','à','â','î','ï','ô','û','ù','ç',' '],
        ['e','e','e','e','a','a','i','i','o','u','u','c','-'],
        mb_strtolower($cat, 'UTF-8')
    );
    $cat = preg_replace('/[^a-z0-9-]/', '', $cat);
    $cat = preg_replace('/-+/', '-', $cat);
    return $cat;
}

$error = "";
$produits = [];

// 1. Produits de l'utilisateur connecté
try {
    $stmt = $connexion->prepare("
        SELECT *
        FROM produit
        WHERE user_id = ?
        ORDER BY nom ASC
    ");
    $stmt->execute([$user_id]);
    $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Erreur produits : " . $e->getMessage();
}

// Statistiques stock actuel
$totalProduits = count($produits);
$totalStock = array_sum(array_column($produits, 'quantite'));
$totalValeurAchat = array_sum(array_map(fn($p) => ($p['quantite']??0) * ($p['prix_achat']??0), $produits));
$totalValeurVente = array_sum(array_map(fn($p) => ($p['quantite']??0) * ($p['prix_unitaire']??0), $produits));
$margeBruteTotale = $totalValeurVente - $totalValeurAchat;

// 2. Statistiques ventes déjà réalisées (approximation car pas de prix_achat historique)
$totalUnitesVendues = 0;
$totalChiffreAffaires = 0;
$beneficeRealiseApprox = 0;
try {
    $stmtVentes = $connexion->prepare("
        SELECT
            COALESCE(SUM(v.quantite), 0) AS total_unites_vendues,
            COALESCE(SUM(v.total), 0) AS total_chiffre_affaires,
            COALESCE(SUM(v.quantite * p.prix_achat), 0) AS cout_achat_approx,
            COALESCE(SUM(v.total - (v.quantite * p.prix_achat)), 0) AS benefice_realise_approx
        FROM vente v
        LEFT JOIN produit p ON p.id = v.produit_id
        WHERE v.utilisateur_id = ?
    ");
    $stmtVentes->execute([$user_id]);
    $stats = $stmtVentes->fetch(PDO::FETCH_ASSOC);
    $totalUnitesVendues = (int) $stats['total_unites_vendues'];
    $totalChiffreAffaires = (float) $stats['total_chiffre_affaires'];
    $beneficeRealiseApprox = (float) $stats['benefice_realise_approx'];
} catch (PDOException $e) {
    $error .= ($error ? " | " : "") . "Erreur stats ventes : " . $e->getMessage();
}

// Catégories uniques pour le filtre
$categoriesUniques = [];
foreach ($produits as $p) {
    $cat = $p['categorie'] ?? 'Non classé';
    $categoriesUniques[$cat] = ($categoriesUniques[$cat] ?? 0) + 1;
}
ksort($categoriesUniques);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - Stock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f8f9fc; font-family: system-ui, sans-serif; }
        .card { border: none; border-radius: 12px; overflow: hidden; transition: transform 0.18s; box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
        .card:hover { transform: translateY(-4px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .card-body { padding: 1.5rem; }
        .text-muted-small { font-size: 0.875rem; opacity: 0.85; }
        .badge-qte { font-size: 1rem; padding: 0.6em 1em; min-width: 60px; }
        .filter-btn.active { background: #0d6efd; color: white; border-color: #0d6efd; }
    </style>
</head>
<body>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1 fw-semibold">
                <i class="bi bi-box-seam-fill text-primary me-2"></i>
                Mon Stock
            </h2>
            <p class="text-muted mb-0">
                <i class="bi bi-calendar-event me-1"></i>
                Vue d'ensemble – <?= date('d/m/Y') ?>
            </p>
        </div>
        <?php if ($_SESSION['user']['role'] === 'Admin'): ?>
        <a href="produit/add.php" class="btn btn-primary px-4">
            <i class="bi bi-plus-lg me-1"></i> Ajouter produit
        </a>
        <?php endif; ?>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-warning d-flex align-items-center">
        <i class="bi bi-exclamation-triangle-fill me-2 fs-4"></i>
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <!-- Cartes statistiques -->
    <div class="row g-3 mb-5">
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card bg-primary text-white shadow">
                <div class="card-body text-center">
                    <i class="bi bi-boxes fs-3 mb-2 d-block"></i>
                    <h3 class="mb-1 fw-bold"><?= number_format($totalProduits) ?></h3>
                    <div class="text-white-75 text-muted-small">Produits</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card bg-success text-white shadow">
                <div class="card-body text-center">
                    <i class="bi bi-stack fs-3 mb-2 d-block"></i>
                    <h3 class="mb-1 fw-bold"><?= number_format($totalStock) ?></h3>
                    <div class="text-white-75 text-muted-small">Unités en stock</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card bg-info text-white shadow">
                <div class="card-body text-center">
                    <i class="bi bi-bag-check fs-3 mb-2 d-block"></i>
                    <h3 class="mb-1 fw-bold"><?= number_format($totalUnitesVendues) ?></h3>
                    <div class="text-white-75 text-muted-small">Unités vendues</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card bg-secondary text-white shadow">
                <div class="card-body text-center">
                    <i class="bi bi-currency-dollar fs-3 mb-2 d-block"></i>
                    <h4 class="mb-1 fw-bold"><?= number_format($totalChiffreAffaires, 0, ',', ' ') ?> FCFA</h4>
                    <div class="text-white-75 text-muted-small">Chiffre d'affaires</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card <?= $beneficeRealiseApprox >= 0 ? 'bg-success' : 'bg-danger' ?> text-white shadow">
                <div class="card-body text-center">
                    <i class="bi bi-graph-up-arrow fs-3 mb-2 d-block"></i>
                    <h4 class="mb-1 fw-bold"><?= number_format($beneficeRealiseApprox, 0, ',', ' ') ?> FCFA</h4>
                    <div class="text-white-75 text-muted-small">Bénéfice approx.</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card bg-warning text-dark shadow">
                <div class="card-body text-center">
                    <i class="bi bi-wallet2 fs-3 mb-2 d-block"></i>
                    <h4 class="mb-1 fw-bold"><?= number_format($margeBruteTotale, 0, ',', ' ') ?> FCFA</h4>
                    <div class="text-dark-75 text-muted-small">Potentiel restant</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtres catégories -->
    <div class="mb-4">
        <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-outline-primary btn-sm active filter-btn" data-categorie="all">
                <i class="bi bi-list-ul me-1"></i> Tous (<?= $totalProduits ?>)
            </button>
            <?php foreach ($categoriesUniques as $cat => $count): ?>
            <button class="btn btn-outline-secondary btn-sm filter-btn" data-categorie="<?= slugCategorie($cat) ?>">
                <i class="bi bi-tag me-1"></i> <?= htmlspecialchars($cat) ?> (<?= $count ?>)
            </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Tableau -->
    <div class="card shadow border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th><i class="bi bi-hash me-1"></i>Code</th>
                            <th><i class="bi bi-box me-1"></i>Produit</th>
                            <th class="text-center"><i class="bi bi-tags me-1"></i>Catégorie</th>
                            <th class="text-center"><i class="bi bi-stack me-1"></i>Stock</th>
                            <th class="text-center"><i class="bi bi-currency-dollar me-1"></i>Prix achat</th>
                            <th class="text-center"><i class="bi bi-cart me-1"></i>Prix vente</th>
                            <th class="text-center"><i class="bi bi-graph-up-arrow me-1"></i>Bénéfice unitaire</th>
                            <?php if ($_SESSION['user']['role'] === 'Admin'): ?>
                            <th><i class="bi bi-building me-1"></i>Fournisseur</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($produits)): ?>
                        <tr>
                            <td colspan="<?= $_SESSION['user']['role'] === 'Admin' ? 8 : 7 ?>" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-3 opacity-50"></i>
                                Aucun produit pour le moment
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($produits as $p):
                            $slug = slugCategorie($p['categorie'] ?? '');
                            $qte = (int)($p['quantite'] ?? 0);
                            $pa = (float)($p['prix_achat'] ?? 0);
                            $pv = (float)($p['prix_unitaire'] ?? 0);
                            $benef = $pv - $pa;
                            $badgeQte = $qte == 0 ? 'bg-danger' : ($qte <= 5 ? 'bg-warning' : 'bg-success');
                        ?>
                        <tr class="produit-row" data-categorie="<?= $slug ?>">
                            <td class="fw-medium"><?= htmlspecialchars($p['code'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($p['nom'] ?? '—') ?></td>
                            <td class="text-center"><?= htmlspecialchars($p['categorie'] ?? '—') ?></td>
                            <td class="text-center">
                                <span class="badge <?= $badgeQte ?> badge-qte"><?= $qte ?></span>
                            </td>
                            <td class="text-center"><?= number_format($pa, 0, ',', ' ') ?></td>
                            <td class="text-center"><?= number_format($pv, 0, ',', ' ') ?></td>
                            <td class="text-center">
                                <span class="badge <?= $benef > 0 ? 'bg-success' : ($benef < 0 ? 'bg-danger' : 'bg-secondary') ?>">
                                    <?= number_format($benef, 0, ',', ' ') ?>
                                </span>
                            </td>
                            <?php if ($_SESSION['user']['role'] === 'Admin'): ?>
                            <td><?= htmlspecialchars($p['fournisseur'] ?? '—') ?></td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', e => {
            e.preventDefault();
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const cat = btn.dataset.categorie;
            document.querySelectorAll('.produit-row').forEach(row => {
                row.style.display = (cat === 'all' || row.dataset.categorie === cat) ? '' : 'none';
            });
        });
    });
});
</script>
</body>
</html>