<?php
session_start();
require_once __DIR__ . "/../includes/db.php";

if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
    header("Location: ../index.php");
    exit;
}

// Fonction slug catégorie
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

// Récupération produits

   // Récupération produits (PAR ADMIN CONNECTÉ)
try {
    $user_id = $_SESSION['user']['id'];

    $stmt = $connexion->prepare("
        SELECT * 
        FROM produit 
        WHERE user_id = ?
        ORDER BY nom ASC
    ");
    $stmt->execute([$user_id]);
    $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $produits = [];
    $error = "Erreur BDD";
}


// Statistiques
$totalProduits = count($produits);
$totalStock = array_sum(array_column($produits, 'quantite'));
$totalValeurAchat = array_sum(array_map(fn($p) => ($p['quantite']??0)*($p['prix_achat']??0), $produits));
$totalValeurVente = array_sum(array_map(fn($p) => ($p['quantite']??0)*($p['prix_unitaire']??0), $produits));
$margeBruteTotale = $totalValeurVente - $totalValeurAchat;
$margePourcent = $totalValeurAchat > 0 ? round(($margeBruteTotale / $totalValeurAchat) * 100, 1) : 0;

// Catégories
$categoriesUniques = [];
foreach ($produits as $p) {
    $cat = $p['categorie'] ?? 'Non classé';
    $categoriesUniques[$cat] = ($categoriesUniques[$cat] ?? 0) + 1;
}
ksort($categoriesUniques);
?>

<div class="container-fluid">

    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-2">📦 Gestion du Stock des Produits</h2>
            <p class="text-muted mb-0">Suivi en temps réel de votre inventaire</p>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Boutons admin conservés -->
    <?php if ($_SESSION['user']['role'] === 'Admin'): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex flex-wrap gap-2">
                <a href="produit/add.php" class="btn btn-success ajax-link">
                    <i class="bi bi-plus-circle me-2"></i>Ajouter
                </a>
                <a href="produit/stock.php" class="btn btn-primary ajax-link">
                    <i class="bi bi-arrow-repeat me-2"></i>Gestion stock
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="row mb-3 g-2 g-md-3">
        <div class="col-6 col-md">
            <div class="card bg-primary text-white text-center h-100">
                <div class="card-body p-3">
                    <h5><?= number_format($totalProduits) ?></h5>
                    <small>Produits</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="card bg-success text-white text-center h-100">
                <div class="card-body p-3">
                    <h5><?= number_format($totalStock) ?></h5>
                    <small>Unités</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="card bg-info text-white text-center h-100">
                <div class="card-body p-3">
                    <h5><?= number_format($totalValeurAchat, 0, ',', ' ') ?> FCFA</h5>
                    <small>Achat</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="card <?= $margeBruteTotale >= 0 ? 'bg-warning' : 'bg-danger' ?> text-white text-center h-100">
                <div class="card-body p-3">
                    <h5><?= number_format($margeBruteTotale, 0, ',', ' ') ?> FCFA</h5>
                    <small>Marge (<?= $margePourcent ?>%)</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="mb-3" id="categoryFilters">
        <a href="#" class="btn btn-outline-primary btn-sm filter-btn active" data-categorie="all">
            Tous (<?= $totalProduits ?>)
        </a>
        <?php foreach ($categoriesUniques as $cat => $count): ?>
            <a href="#" class="btn btn-outline-secondary btn-sm filter-btn" data-categorie="<?= slugCategorie($cat) ?>">
                <?= htmlspecialchars($cat) ?> (<?= $count ?>)
            </a>
        <?php endforeach; ?>
    </div>

    <!-- TABLEAU (ACTIONS SUPPRIMÉES) -->
    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Code</th>
                    <th>Produit</th>
                    <th class="text-center">Catégorie</th>
                    <th class="text-center">Stock</th>
                    <th class="text-center">Prix Achat</th>
                    <th class="text-center">Prix Vente</th>
                    <?php if ($_SESSION['user']['role'] === 'Admin'): ?>
                        <th>Fournisseur</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($produits as $p):
                $slug = slugCategorie($p['categorie'] ?? '');
                $qte = (int)($p['quantite'] ?? 0);
                $badge = $qte == 0 ? 'bg-danger' : ($qte <= 5 ? 'bg-warning' : 'bg-success');
            ?>
                <tr class="produit-row" data-categorie="<?= $slug ?>">
                    <td class="fw-bold"><?= htmlspecialchars($p['code']) ?></td>
                    <td><?= htmlspecialchars($p['nom']) ?></td>
                    <td class="text-center"><?= htmlspecialchars($p['categorie']) ?></td>
                    <td class="text-center">
                        <span class="badge <?= $badge ?>"><?= $qte ?></span>
                    </td>
                    <td class="text-center"><?= number_format($p['prix_achat'], 0, ',', ' ') ?> FCFA</td>
                    <td class="text-center"><?= number_format($p['prix_unitaire'], 0, ',', ' ') ?> FCFA</td>
                    <?php if ($_SESSION['user']['role'] === 'Admin'): ?>
                        <td><?= htmlspecialchars($p['fournisseur']) ?></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- JS filtres UNIQUEMENT -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const filterBtns = document.querySelectorAll('.filter-btn');
    const rows = document.querySelectorAll('.produit-row');

    filterBtns.forEach(btn => {
        btn.addEventListener('click', e => {
            e.preventDefault();
            filterBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            const cat = btn.dataset.categorie;
            rows.forEach(row => {
                row.style.display = (cat === 'all' || row.dataset.categorie === cat) ? '' : 'none';
            });
        });
    });
});
</script>
