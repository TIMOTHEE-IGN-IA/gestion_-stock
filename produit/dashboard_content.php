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

    $stmt = $connexion->prepare("SELECT COALESCE(SUM(quantite * prix_achat),0) FROM produit WHERE user_id IN ($in_clause)");
    $stmt->execute($placeholders);
    $valeur_achat_stock = $stmt->fetchColumn() ?: 0;

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

    $stmt = $connexion->prepare("
        SELECT COUNT(*) FROM vente
        WHERE utilisateur_id IN ($in_clause)
        AND MONTH(date)=MONTH(CURDATE())
        AND YEAR(date)=YEAR(CURDATE())
    ");
    $stmt->execute($placeholders);
    $ventes_mois = $stmt->fetchColumn() ?: 0;

    // UTILISATEURS (corrigé)
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

    // VENTES PAR MOIS
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

    // TOP PRODUITS
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

    // MOUVEMENTS
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

    $entrees_sorties_data = [(int)$entrees,(int)$sorties];

    // CATÉGORIES
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
    <title>Tableau de bord - Stock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        .bg-purple { background-color: #6f42c1 !important; }
        .text-purple { color: #6f42c1 !important; }
        .card { transition: all 0.2s; border: none; border-radius: 10px; overflow: hidden; }
        .card:hover { transform: translateY(-4px); box-shadow: 0 10px 20px rgba(0,0,0,0.08) !important; }
        .card-header { border-bottom: none; font-weight: 600; }
        small { font-size: 0.82rem !important; line-height: 1.1; }
        h4 { font-size: 1.65rem; font-weight: 700; }
        @media (max-width: 576px) {
            h4 { font-size: 1.4rem; }
            .card-body { padding: 1.25rem 0.9rem; }
        }
        /* Conteneur d'alertes fixé en haut */
#alertes-fixed {
    position: fixed;
    top: 1rem;
    left: 50%;
    transform: translateX(-50%);
    width: 95%;
    max-width: 600px;
    z-index: 1050; /* Au-dessus du reste */
}

/* Animation d'apparition */
.alert-fixed {
    opacity: 0;
    transform: translateY(-20px);
    animation: slideDown 0.5s forwards;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    font-weight: 500;
}

/* Slide down */
@keyframes slideDown {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Icônes et pulse danger */
.alert-fixed i {
    margin-right: 0.5rem;
    font-size: 1.3rem;
    transition: transform 0.3s;
}

.alert-fixed.alert-danger i {
    animation: pulse 1s infinite;
    color: #dc3545;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.3); }
}

/* Disparition automatique */
.alert-fixed.fade-out {
    opacity: 0 !important;
    transform: translateY(-20px);
    transition: all 0.5s;
}

    </style>
</head>
<body class="bg-light">

<div class="container py-4">

    <h2 class="mb-4 d-flex align-items-center">
        <i class="bi bi-speedometer2 fs-2 me-3 text-primary"></i>
        Tableau de bord
    </h2>

    <div id="loading" class="text-center py-5 my-5">
        <div class="spinner-border text-primary" style="width: 3.5rem; height: 3.5rem;"></div>
        <p class="mt-4 lead text-muted">Chargement du tableau de bord...</p>
    </div>

    <div id="content" style="display:none;">

       <?php if (!empty($alertes)): ?>
<div id="alertes-fixed">
    <?php foreach ($alertes as $a): ?>
    <div class="alert alert-<?= $a['type'] ?> alert-dismissible fade show alert-fixed" role="alert">
        <i class="bi <?= $a['type'] === 'danger' ? 'bi-exclamation-triangle-fill' : 'bi-exclamation-circle-fill' ?>"></i>
        <?= htmlspecialchars($a['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endforeach; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Supprimer automatiquement les alertes après 5s
    setTimeout(() => {
        document.querySelectorAll('.alert-fixed').forEach(a => {
            a.classList.add('fade-out');
            setTimeout(() => a.remove(), 500);
        });
    }, 5000);
});
</script>
<?php endif; ?>


        <!-- Cartes principales -->
        <div class="row g-3 mb-4">

            <div class="col-6 col-sm-6 col-md-4 col-lg-3">
                <div class="card bg-primary bg-opacity-10 h-100 text-center">
                    <div class="card-body py-4">
                        <i class="bi bi-boxes fs-3 text-primary mb-3 d-block"></i>
                        <h6 class="text-primary mb-1">Produits</h6>
                        <div class="small text-muted mb-2">Total</div>
                        <h4><?= number_format($produits_total) ?></h4>
                    </div>
                </div>
            </div>

            <div class="col-6 col-sm-6 col-md-4 col-lg-3">
                <div class="card bg-danger bg-opacity-10 h-100 text-center">
                    <div class="card-body py-4">
                        <i class="bi bi-exclamation-triangle-fill fs-3 text-danger mb-3 d-block"></i>
                        <h6 class="text-danger mb-1">En rupture</h6>
                        <div class="small text-muted mb-2">Stock = 0</div>
                        <h4><?= number_format($produits_rupture) ?></h4>
                    </div>
                </div>
            </div>

            <div class="col-6 col-sm-6 col-md-4 col-lg-3">
                <div class="card bg-warning bg-opacity-10 h-100 text-center">
                    <div class="card-body py-4">
                        <i class="bi bi-exclamation-circle-fill fs-3 text-warning mb-3 d-block"></i>
                        <h6 class="text-warning mb-1">Stock faible</h6>
                        <div class="small text-muted mb-2">≤ 5 unités</div>
                        <h4><?= number_format($produits_faible) ?></h4>
                    </div>
                </div>
            </div>

            <div class="col-6 col-sm-6 col-md-4 col-lg-3">
                <div class="card bg-info bg-opacity-10 h-100 text-center">
                    <div class="card-body py-4">
                        <i class="bi bi-stack fs-3 text-info mb-3 d-block"></i>
                        <h6 class="text-info mb-1">Unités en stock</h6>
                        <div class="small text-muted mb-2">Total pièces</div>
                        <h4><?= number_format($unites_stock) ?></h4>
                    </div>
                </div>
            </div>

            <div class="col-6 col-sm-6 col-md-4 col-lg-3">
                <div class="card bg-success bg-opacity-10 h-100 text-center">
                    <div class="card-body py-4">
                        <i class="bi bi-cart fs-3 text-success mb-3 d-block"></i>
                        <h6 class="text-success mb-1">Valeur totale achat</h6>
                        <div class="small text-muted mb-2">Coût d'achat stock</div>
                        <h4><?= number_format($valeur_achat_stock, 0, ',', ' ') ?> FCFA</h4>
                    </div>
                </div>
            </div>

            <div class="col-6 col-sm-6 col-md-4 col-lg-3">
                <div class="card bg-purple bg-opacity-10 h-100 text-center">
                    <div class="card-body py-4">
                        <i class="bi bi-graph-up-arrow fs-3 text-purple mb-3 d-block"></i>
                        <h6 class="text-white mb-1">Marge totale</h6>
                        <div class="small text-muted mb-2">Benefice total</div>
                        <h4><?= number_format($marge_totale, 0, ',', ' ') ?> FCFA</h4>
                    </div>
                </div>
            </div>

            <div class="col-6 col-sm-6 col-md-4 col-lg-3">
                <div class="card bg-info bg-opacity-10 h-100 text-center">
                    <div class="card-body py-4">
                        <i class="bi bi-cart-check-fill fs-3 text-info mb-3 d-block"></i>
                        <h6 class="text-info mb-1">Ventes aujourd'hui</h6>
                        <div class="small text-muted mb-2">Nombre de tickets</div>
                        <h4><?= number_format($ventes_aujourdhui) ?></h4>
                    </div>
                </div>
            </div>

            <div class="col-6 col-sm-6 col-md-4 col-lg-3">
                <div class="card bg-success bg-opacity-10 h-100 text-center">
                    <div class="card-body py-4">
                        <i class="bi bi-cash-stack fs-3 text-success mb-3 d-block"></i>
                        <h6 class="text-success mb-1">CA aujourd'hui</h6>
                        <div class="small text-muted mb-2">Chiffre d'affaires</div>
                        <h4><?= number_format($montant_ventes_aujourdhui, 0, ',', ' ') ?> FCFA</h4>
                    </div>
                </div>
            </div>

            <div class="col-6 col-sm-6 col-md-4 col-lg-3">
                <div class="card bg-secondary bg-opacity-10 h-100 text-center">
                    <div class="card-body py-4">
                        <i class="bi bi-calendar3-week fs-3 text-secondary mb-3 d-block"></i>
                        <h6 class="text-secondary mb-1">Ventes ce mois</h6>
                        <div class="small text-muted mb-2">Nombre de tickets</div>
                        <h4><?= number_format($ventes_mois) ?></h4>
                    </div>
                </div>
            </div>

            <div class="col-6 col-sm-6 col-md-4 col-lg-3">
                <div class="card bg-purple bg-opacity-10 h-100 text-center">
                    <div class="card-body py-4">
                        <i class="bi bi-people-fill fs-3 text-purple mb-3 d-block"></i>
                        <h6 class="text-white mb-1">Utilisateurs</h6>
                        <div class="small text-muted mb-2">Total</div>
                        <h4><?= number_format($utilisateurs_total) ?></h4>
                    </div>
                </div>
            </div>

        </div>

        <!-- Graphiques -->
        <div class="row g-3">

            <div class="col-lg-6">
                <div class="card h-100 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <i class="bi bi-graph-up-arrow me-2"></i>Évolution des ventes (6 mois)
                    </div>
                    <div class="card-body">
                        <canvas id="ventesChart" height="180"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card h-100 shadow-sm">
                    <div class="card-header bg-success text-white">
                        <i class="bi bi-award me-2"></i>Top 5 produits vendus
                    </div>
                    <div class="card-body">
                        <canvas id="produitsVendusChart" height="180"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card h-100 shadow-sm">
                    <div class="card-header bg-warning text-dark">
                        <i class="bi bi-arrow-left-right me-2"></i>Mouvements de stock (ce mois)
                    </div>
                    <div class="card-body">
                        <canvas id="entreesSortiesChart" height="180"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card h-100 shadow-sm">
                    <div class="card-header bg-info text-white">
                        <i class="bi bi-tags me-2"></i>Répartition par catégorie
                    </div>
                    <div class="card-body">
                        <canvas id="categoriesChart" height="180"></canvas>
                    </div>
                </div>
            </div>

        </div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        document.getElementById('loading').style.display = 'none';
        document.getElementById('content').style.display = 'block';
        initCharts();
    }, 800);
});

function initCharts() {
    const mois = <?= json_encode($mois_labels) ?>;
    const ventesData = <?= json_encode($ventes_par_mois) ?>;
    
    const categoriesLabels = <?= json_encode($categories_labels) ?>;
    const categoriesData = <?= json_encode($categories_data) ?>;
    
    const topLabels = <?= json_encode($top_produits_labels) ?>;
    const topData = <?= json_encode($top_produits_data) ?>;
    
    const mouvementsData = <?= json_encode($entrees_sorties_data) ?>;

    // 1. Évolution ventes
    new Chart(document.getElementById('ventesChart'), {
        type: 'line',
        data: {
            labels: mois,
            datasets: [{
                label: 'Ventes',
                data: ventesData,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13,110,253,0.12)',
                tension: 0.3,
                fill: true
            }]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });

    // 2. Top produits
    new Chart(document.getElementById('produitsVendusChart'), {
        type: 'bar',
        data: {
            labels: topLabels,
            datasets: [{
                label: 'Quantité vendue',
                data: topData,
                backgroundColor: 'rgba(25,135,84,0.7)',
                borderColor: 'rgba(25,135,84,1)',
                borderWidth: 1
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
    });

    // 3. Entrées / Sorties
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
        options: { responsive: true, maintainAspectRatio: false, cutout: '65%' }
    });

    // 4. Répartition catégories
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
        options: { responsive: true, maintainAspectRatio: false, cutout: '65%' }
    });
}
</script>
</body>
</html>