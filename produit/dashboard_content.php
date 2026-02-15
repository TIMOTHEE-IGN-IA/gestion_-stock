<?php
// ================= DEBUG (retirer en prod) =================
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ================= SESSION =================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================= INCLUDES ROBUSTES =================
require_once $_SERVER['DOCUMENT_ROOT'] . "/includes/auth_check.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/includes/db.php";

// ================= USER =================
if (!isset($_SESSION['user']['id'])) {
    header("Location: /index.php");
    exit;
}
$user = $_SESSION['user'];
$user_id = (int)$user['id'];
$role = $user['role'];

// ================= UTILISATEURS AUTORISÉS =================
$allowed_users = [];
if ($role === 'Admin') {
    $allowed_users[] = $user_id;
    $stmt = $connexion->prepare("SELECT id FROM utilisateur WHERE admin_parent_id = ?");
    $stmt->execute([$user_id]);
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $allowed_users[] = (int)$r['id'];
    }
} else {
    $allowed_users[] = $user_id;
}
if (empty($allowed_users)) {
    $allowed_users = [0];
}
$in_clause = implode(',', array_fill(0, count($allowed_users), '?'));
$placeholders = $allowed_users;

// ================= REQUÊTES =================
try {
    // PRODUITS
    $stmt = $connexion->prepare("SELECT COUNT(*) FROM produit WHERE user_id IN ($in_clause)");
    $stmt->execute($placeholders);
    $produits_total = $stmt->fetchColumn() ?: 0;

    $stmt = $connexion->prepare("SELECT COUNT(*) FROM produit WHERE user_id IN ($in_clause) AND quantite = 0");
    $stmt->execute($placeholders);
    $produits_rupture = $stmt->fetchColumn() ?: 0;

    $stmt = $connexion->prepare("SELECT COUNT(*) FROM produit WHERE user_id IN ($in_clause) AND quantite BETWEEN 1 AND 5");
    $stmt->execute($placeholders);
    $produits_faible = $stmt->fetchColumn() ?: 0;

    $alertes = [];
    if ($produits_rupture > 0) {
        $alertes[] = ['type'=>'danger','message'=>"Attention : $produits_rupture produit(s) en rupture de stock !"];
    }
    if ($produits_faible > 0) {
        $alertes[] = ['type'=>'warning','message'=>"Stock faible : $produits_faible produit(s) ≤ 5 unités"];
    }

    $stmt = $connexion->prepare("SELECT COALESCE(SUM(quantite),0) FROM produit WHERE user_id IN ($in_clause)");
    $stmt->execute($placeholders);
    $unites_stock = $stmt->fetchColumn() ?: 0;

    // VALEUR D'ACHAT DU STOCK RESTANT (non vendu)
    $stmt = $connexion->prepare("SELECT COALESCE(SUM(quantite * prix_achat),0) FROM produit WHERE user_id IN ($in_clause)");
    $stmt->execute($placeholders);
    $valeur_achat_stock = $stmt->fetchColumn() ?: 0;

    // VALEUR D'ACHAT DES PRODUITS DÉJÀ VENDUS
    $stmt = $connexion->prepare("
        SELECT COALESCE(SUM(v.quantite * p.prix_achat), 0) AS valeur_achat_vendu
        FROM vente v
        JOIN produit p ON v.produit_id = p.id
        WHERE v.utilisateur_id IN ($in_clause)
    ");
    $stmt->execute($placeholders);
    $valeur_achat_vendu = (float)$stmt->fetchColumn() ?: 0;

    // VALEUR D'ACHAT TOTALE (stock restant + déjà vendu)
    $valeur_achat_totale = $valeur_achat_stock + $valeur_achat_vendu;

    $stmt = $connexion->prepare("
        SELECT COALESCE(SUM(quantite*(prix_unitaire-prix_achat)),0)
        FROM produit
        WHERE user_id IN ($in_clause)
    ");
    $stmt->execute($placeholders);
    $marge_totale = $stmt->fetchColumn() ?: 0;

    // VENTES
    $stmt = $connexion->prepare("
        SELECT COUNT(*) FROM vente
        WHERE utilisateur_id IN ($in_clause)
        AND DATE(date)=CURDATE()
    ");
    $stmt->execute($placeholders);
    $ventes_aujourdhui = $stmt->fetchColumn() ?: 0;

    $stmt = $connexion->prepare("
        SELECT COALESCE(SUM(total),0) FROM vente
        WHERE utilisateur_id IN ($in_clause)
        AND DATE(date)=CURDATE()
    ");
    $stmt->execute($placeholders);
    $montant_ventes_aujourdhui = $stmt->fetchColumn() ?: 0;

    // BÉNÉFICE RÉALISÉ AUJOURD'HUI
    $stmt = $connexion->prepare("
        SELECT COALESCE(SUM(v.quantite * (v.prix_unitaire - p.prix_achat)), 0) AS benefice_aujourdhui
        FROM vente v
        JOIN produit p ON v.produit_id = p.id
        WHERE v.utilisateur_id IN ($in_clause)
          AND DATE(v.date) = CURDATE()
    ");
    $stmt->execute($placeholders);
    $benefice_aujourdhui = (float)$stmt->fetchColumn() ?: 0;

    $stmt = $connexion->prepare("
        SELECT COUNT(*) FROM vente
        WHERE utilisateur_id IN ($in_clause)
        AND MONTH(date)=MONTH(CURDATE())
        AND YEAR(date)=YEAR(CURDATE())
    ");
    $stmt->execute($placeholders);
    $ventes_mois = $stmt->fetchColumn() ?: 0;

    // BÉNÉFICE TOTAL DES VENTES DÉJÀ RÉALISÉES
    $stmt = $connexion->prepare("
        SELECT COALESCE(SUM(v.quantite * (v.prix_unitaire - p.prix_achat)), 0) AS benefice_realise
        FROM vente v
        JOIN produit p ON v.produit_id = p.id
        WHERE v.utilisateur_id IN ($in_clause)
    ");
    $stmt->execute($placeholders);
    $benefice_realise = (float)$stmt->fetchColumn() ?: 0;

    // BÉNÉFICE TOTAL (RÉALISÉ + POTENTIEL)
    $benefice_total = $benefice_realise + $marge_totale;

    // UTILISATEURS
    if ($role === 'Admin') {
        $stmt = $connexion->prepare("
            SELECT COUNT(*)
            FROM utilisateur
            WHERE id = ? OR admin_parent_id = ?
        ");
        $stmt->execute([$user_id, $user_id]);
        $utilisateurs_total = $stmt->fetchColumn() ?: 0;
    } else {
        $utilisateurs_total = 1;
    }

    // ================= GRAPHES =================
    $stmt = $connexion->prepare("
        SELECT DATE_FORMAT(date,'%b %Y') mois, COUNT(*) nb
        FROM vente
        WHERE utilisateur_id IN ($in_clause)
        AND date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY YEAR(date),MONTH(date)
        ORDER BY date ASC
    ");
    $stmt->execute($placeholders);
    $mois_labels = [];
    $ventes_par_mois = [];
    foreach ($stmt as $r) {
        $mois_labels[] = $r['mois'];
        $ventes_par_mois[] = (int)$r['nb'];
    }

    $stmt = $connexion->prepare("
        SELECT p.nom, SUM(v.quantite) qte
        FROM vente v
        JOIN produit p ON v.produit_id=p.id
        WHERE v.utilisateur_id IN ($in_clause)
        GROUP BY p.id
        ORDER BY qte DESC
        LIMIT 5
    ");
    $stmt->execute($placeholders);
    $top_produits_labels = [];
    $top_produits_data = [];
    foreach ($stmt as $r) {
        $top_produits_labels[] = $r['nom'];
        $top_produits_data[] = (int)$r['qte'];
    }

    $stmt = $connexion->prepare("
        SELECT type, SUM(quantite) qte
        FROM historique_stock
        WHERE utilisateur_id IN ($in_clause)
        AND MONTH(date)=MONTH(CURDATE())
        GROUP BY type
    ");
    $stmt->execute($placeholders);
    $entrees = 0;
    $sorties = 0;
    foreach ($stmt as $r) {
        if ($r['type'] === 'Entree') $entrees = $r['qte'];
        if ($r['type'] === 'Sortie') $sorties = $r['qte'];
    }
    $entrees_sorties_data = [(int)$entrees, (int)$sorties];

    $stmt = $connexion->prepare("
        SELECT categorie, COUNT(*) nb
        FROM produit
        WHERE user_id IN ($in_clause)
        GROUP BY categorie
    ");
    $stmt->execute($placeholders);
    $categories_labels = [];
    $categories_data = [];
    foreach ($stmt as $r) {
        $categories_labels[] = $r['categorie'] ?: 'Non classé';
        $categories_data[] = (int)$r['nb'];
    }
} catch(Exception $e) {
    die("Erreur SQL: ".$e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - Nova Stock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary: #0d6efd;
            --success: #198754;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #0dcaf0;
            --purple: #6f42c1;
            --gray: #6c757d;
        }
        body {
            background: #f4f6f9;
            font-family: system-ui, -apple-system, sans-serif;
            color: #1f2937;
            min-height: 100vh;
        }
        .container-fluid { max-width: 1400px; }
        h2 {
            font-weight: 700;
            color: #111827;
            margin-bottom: 2rem;
        }
        .card {
            border: none;
            border-radius: 14px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.08);
            transition: all 0.25s ease;
            overflow: hidden;
            background: white;
        }
        .card:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 28px rgba(0,0,0,0.12);
        }
        .card-body {
            padding: 1.4rem 1.2rem;
        }
        .card h6 {
            font-weight: 600;
            font-size: 0.9rem;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            opacity: 0.9;
            margin-bottom: 0.4rem;
        }
        .card h4, .card h5 {
            font-weight: 700;
            margin: 0;
        }
        .alert-fixed {
            position: fixed;
            top: 1rem;
            left: 50%;
            transform: translateX(-50%);
            width: 90%;
            max-width: 600px;
            z-index: 1050;
            border-radius: 10px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            animation: slideDown 0.5s forwards;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translate(-50%, -30px); }
            to   { opacity: 1; transform: translate(-50%, 0); }
        }
        .alert-fixed.fade-out {
            opacity: 0;
            transform: translate(-50%, -30px);
            transition: all 0.6s ease;
        }
        .card i.bi {
            font-size: 2.2rem;
            padding: 16px;
            border-radius: 50%;
            background: rgba(255,255,255,0.9);
            display: inline-block;
            margin-bottom: 0.9rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .bg-soft-primary { background: #eef4ff !important; }
        .bg-soft-success { background: #eafaf1 !important; }
        .bg-soft-danger { background: #fdeaea !important; }
        .bg-soft-warning { background: #fff7e6 !important; }
        .bg-soft-info { background: #eaf6fb !important; }
        .bg-soft-purple { background: #f3ecff !important; }
        @media (max-width: 576px) {
            h2 { font-size: 1.5rem; margin-bottom: 1.5rem; }
            .card h4, .card h5 { font-size: 1.35rem; }
            .card h6 { font-size: 0.85rem; }
            .card-body { padding: 1rem 0.9rem; }
            .card i.bi { font-size: 1.8rem; padding: 12px; }
            .alert-fixed { top: 0.8rem; width: 95%; font-size: 0.95rem; }
        }
        @media (max-width: 768px) {
            .row.g-3 > div { margin-bottom: 1rem; }
            .card { margin-bottom: 1rem; }
        }
    </style>
</head>
<body>

<!-- Spinner de chargement -->
<div id="loading" class="position-fixed top-50 start-50 translate-middle text-center">
    <div class="spinner-border text-primary" style="width: 4rem; height: 4rem;" role="status">
        <span class="visually-hidden">Chargement...</span>
    </div>
    <p class="mt-4 lead text-muted">Chargement du tableau de bord...</p>
</div>

<!-- Contenu principal -->
<div id="content" class="container-fluid py-4 py-md-5" style="display:none;">

    <h2 class="mb-4 d-flex align-items-center">
        <i class="bi bi-speedometer2 fs-2 me-3 text-primary"></i>
        Tableau de bord
    </h2>

    <!-- Alertes fixes -->
    <?php if (!empty($alertes)): ?>
    <div id="alertes-fixed">
        <?php foreach ($alertes as $a): ?>
        <div class="alert alert-<?= $a['type'] ?> alert-dismissible fade show alert-fixed mb-2" role="alert">
            <i class="bi <?= $a['type'] === 'danger' ? 'bi-exclamation-triangle-fill' : 'bi-exclamation-circle-fill' ?> me-2"></i>
            <?= htmlspecialchars($a['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Cartes principales -->
    <div class="row g-3 mb-5">
        <div class="col-6 col-sm-6 col-md-4 col-lg-3">
            <div class="card bg-soft-danger h-100 text-center">
                <div class="card-body">
                    <i class="bi bi-exclamation-triangle-fill text-danger"></i>
                    <h6 class="text-danger">En rupture</h6>
                    <div class="small text-muted mb-2">Stock = 0</div>
                    <h4 class="text-danger"><?= number_format($produits_rupture) ?></h4>
                </div>
            </div>
        </div>

        <div class="col-6 col-sm-6 col-md-4 col-lg-3">
            <div class="card bg-soft-warning h-100 text-center">
                <div class="card-body">
                    <i class="bi bi-exclamation-circle-fill text-warning"></i>
                    <h6 class="text-warning">Stock faible</h6>
                    <div class="small text-muted mb-2">≤ 5 unités</div>
                    <h4 class="text-warning"><?= number_format($produits_faible) ?></h4>
                </div>
            </div>
        </div>

        <div class="col-6 col-sm-6 col-md-4 col-lg-3">
            <div class="card bg-soft-info h-100 text-center">
                <div class="card-body">
                    <i class="bi bi-stack text-info"></i>
                    <h6 class="text-info">Unités en stock</h6>
                    <div class="small text-muted mb-2">Total pièces</div>
                    <h4 class="text-info"><?= number_format($unites_stock) ?></h4>
                </div>
            </div>
        </div>

        <!-- CARTE : VALEUR D'ACHAT TOTALE (stock restant + déjà vendu) -->
        <div class="col-6 col-sm-6 col-md-4 col-lg-3">
            <div class="card bg-soft-success h-100 text-center">
                <div class="card-body">
                    <i class="bi bi-cart text-success"></i>
                    <h6 class="text-success">Valeur achat totale</h6>
                    <div class="small text-muted mb-2">Stock + Ventes réalisées</div>
                    <h4 class="text-success"><?= number_format($valeur_achat_totale, 0, ',', ' ') ?> FCFA</h4>
                </div>
            </div>
        </div>

        <div class="col-6 col-sm-6 col-md-4 col-lg-3">
            <div class="card bg-soft-primary h-100 text-center">
                <div class="card-body">
                    <i class="bi bi-graph-up-arrow text-primary"></i>
                    <h6 class="text-primary">Marge totale</h6>
                    <div class="small text-muted mb-2">Bénéfice potentiel</div>
                    <h4 class="text-primary"><?= number_format($marge_totale, 0, ',', ' ') ?> FCFA</h4>
                </div>
            </div>
        </div>

        <div class="col-6 col-sm-6 col-md-4 col-lg-3">
            <div class="card bg-soft-success h-100 text-center">
                <div class="card-body">
                    <i class="bi bi-currency-dollar text-success"></i>
                    <h6 class="text-success">Bénéfice réalisé</h6>
                    <div class="small text-muted mb-2">Ventes déjà effectuées</div>
                    <h4 class="text-success"><?= number_format($benefice_realise, 0, ',', ' ') ?> FCFA</h4>
                </div>
            </div>
        </div>

        <div class="col-6 col-sm-6 col-md-4 col-lg-3">
            <div class="card bg-soft-purple h-100 text-center">
                <div class="card-body">
                    <i class="bi bi-wallet2 text-purple"></i>
                    <h6 class="text-purple">Bénéfice total</h6>
                    <div class="small text-muted mb-2">Réalisé + Potentiel</div>
                    <h4 class="text-purple"><?= number_format($benefice_total, 0, ',', ' ') ?> FCFA</h4>
                </div>
            </div>
        </div>

        <!-- CARTE MODIFIÉE : VENTES AUJOURD'HUI + BÉNÉFICE DU JOUR -->
        <div class="col-6 col-sm-6 col-md-4 col-lg-3">
            <div class="card bg-soft-info h-100 text-center">
                <div class="card-body">
                    <i class="bi bi-cart-check-fill text-info"></i>
                    <h6 class="text-info">Ventes aujourd'hui</h6>
                    <div class="small text-muted mb-1">Montant total</div>
                    <h5 class="text-info mb-1"><?= number_format($montant_ventes_aujourdhui, 0, ',', ' ') ?> FCFA</h5>
                    <div class="small text-muted mb-1">Bénéfice réalisé</div>
                    <h5 class="text-success"><?= number_format($benefice_aujourdhui, 0, ',', ' ') ?> FCFA</h5>
                </div>
            </div>
        </div>

       
    <!-- Graphiques -->
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card h-100 shadow-sm">
                <div class="card-header bg-primary text-white d-flex align-items-center">
                    <i class="bi bi-graph-up-arrow me-2"></i> Évolution des ventes (6 derniers mois)
                </div>
                <div class="card-body p-3">
                    <div style="height: 280px;">
                        <canvas id="ventesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100 shadow-sm">
                <div class="card-header bg-success text-white d-flex align-items-center">
                    <i class="bi bi-award me-2"></i> Top 5 produits vendus
                </div>
                <div class="card-body p-3">
                    <div style="height: 280px;">
                        <canvas id="produitsVendusChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100 shadow-sm">
                <div class="card-header bg-warning text-dark d-flex align-items-center">
                    <i class="bi bi-arrow-left-right me-2"></i> Mouvements de stock (ce mois)
                </div>
                <div class="card-body p-3">
                    <div style="height: 280px;">
                        <canvas id="entreesSortiesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100 shadow-sm">
                <div class="card-header bg-info text-white d-flex align-items-center">
                    <i class="bi bi-tags me-2"></i> Répartition par catégorie
                </div>
                <div class="card-body p-3">
                    <div style="height: 280px;">
                        <canvas id="categoriesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
// Afficher contenu après chargement
document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        const loading = document.getElementById('loading');
        const content = document.getElementById('content');
        if (loading) loading.remove();
        if (content) content.style.display = 'block';
        initCharts();
    }, 800);

    // Auto-disparition alertes
    setTimeout(() => {
        document.querySelectorAll('.alert-fixed').forEach(a => {
            a.classList.add('fade-out');
            setTimeout(() => a.remove(), 600);
        });
    }, 5000);
});

function initCharts() {
    const mois = <?= json_encode($mois_labels) ?>;
    const ventesData = <?= json_encode($ventes_par_mois) ?>;
    const categoriesLabels = <?= json_encode($categories_labels) ?>;
    const categoriesData = <?= json_encode($categories_data) ?>;
    const topLabels = <?= json_encode($top_produits_labels) ?>;
    const topData = <?= json_encode($top_produits_data) ?>;
    const mouvementsData = <?= json_encode($entrees_sorties_data) ?>;

    new Chart(document.getElementById('ventesChart'), {
        type: 'line',
        data: {
            labels: mois,
            datasets: [{
                label: 'Ventes',
                data: ventesData,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13,110,253,0.15)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true } },
            plugins: { legend: { display: false } }
        }
    });

    new Chart(document.getElementById('produitsVendusChart'), {
        type: 'bar',
        data: {
            labels: topLabels,
            datasets: [{
                label: 'Quantité vendue',
                data: topData,
                backgroundColor: 'rgba(25,135,84,0.7)',
                borderColor: '#198754',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true } },
            plugins: { legend: { display: false } }
        }
    });

    new Chart(document.getElementById('entreesSortiesChart'), {
        type: 'doughnut',
        data: {
            labels: ['Entrées', 'Sorties'],
            datasets: [{
                data: mouvementsData,
                backgroundColor: ['#ffc107', '#dc3545'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            plugins: { legend: { position: 'bottom' } }
        }
    });

    new Chart(document.getElementById('categoriesChart'), {
        type: 'doughnut',
        data: {
            labels: categoriesLabels,
            datasets: [{
                data: categoriesData,
                backgroundColor: [
                    '#0d6efd', '#198754', '#ffc107', '#dc3545',
                    '#6f42c1', '#fd7e14', '#20c997', '#e83e8c'
                ],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            plugins: { legend: { position: 'bottom' } }
        }
    });
}
</script>
</body>
</html>