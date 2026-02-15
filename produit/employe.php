<?php
session_start();
require_once __DIR__ . "/../includes/db.php";

// Vérification utilisateur
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'Admin') {
    exit('Accès refusé');
}

$admin_id = $_SESSION['user']['id'];
$aujourdhui = date('Y-m-d');
$mois_courant = date('Y-m');

/* =========================
   Récupération des employés
========================= */
$stmt = $connexion->prepare("
    SELECT id, nom, photo 
    FROM utilisateur
    WHERE role = 'Employe' AND admin_parent_id = ?
    ORDER BY nom
");
$stmt->execute([$admin_id]);
$employes = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   Calcul des ventes et début de vente
========================= */
foreach ($employes as &$e) {
    // Total ventes du jour
    $stmtJour = $connexion->prepare("
        SELECT COALESCE(SUM(total), 0) 
        FROM vente
        WHERE utilisateur_id = ? AND DATE(date) = ?
    ");
    $stmtJour->execute([$e['id'], $aujourdhui]);
    $e['ventes_jour'] = (float)$stmtJour->fetchColumn();

    // Total ventes du mois
    $stmtMois = $connexion->prepare("
        SELECT COALESCE(SUM(total), 0) 
        FROM vente
        WHERE utilisateur_id = ? AND DATE_FORMAT(date, '%Y-%m') = ?
    ");
    $stmtMois->execute([$e['id'], $mois_courant]);
    $e['ventes_mois'] = (float)$stmtMois->fetchColumn();

    // Heure du début de vente du jour
    $stmtHeure = $connexion->prepare("
        SELECT MIN(date) 
        FROM vente
        WHERE utilisateur_id = ? AND DATE(date) = ?
    ");
    $stmtHeure->execute([$e['id'], $aujourdhui]);
    $e['debut_vente'] = $stmtHeure->fetchColumn();

    $e['actif_aujourdhui'] = !empty($e['debut_vente']);
}
unset($e);

/* =========================
   Totaux globaux
========================= */
$totalJour = array_sum(array_column($employes, 'ventes_jour'));
$totalMois = array_sum(array_column($employes, 'ventes_mois'));
$nbActifsAujourdHui = count(array_filter($employes, fn($e) => $e['actif_aujourdhui']));
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suivi des ventes – Employés</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fc;
            font-family: system-ui, -apple-system, sans-serif;
            min-height: 100vh;
        }
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: transform 0.2s;
        }
        .card:hover {
            transform: translateY(-4px);
        }
        .card-body {
            padding: 1.25rem;
        }
        h3 {
            font-weight: 700;
            color: #111827;
        }
        .table-responsive {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        }
        .table thead {
            background: #343a40;
            color: white;
        }
        .table tbody tr:hover {
            background: #f1f5f9;
        }
        .photo-employe {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #e9ecef;
        }
        .badge-active {
            background: #198754;
            font-size: 0.85rem;
            padding: 0.4em 0.8em;
        }
        .text-muted-small {
            font-size: 0.8rem;
            opacity: 0.8;
        }
        /* Responsive ajustements */
        @media (max-width: 576px) {
            .card-body {
                padding: 1rem;
            }
            h3 {
                font-size: 1.5rem;
            }
            .card h4 {
                font-size: 1.4rem;
            }
            .card h6 {
                font-size: 0.95rem;
            }
            .photo-employe {
                width: 40px;
                height: 40px;
            }
            .table th, .table td {
                font-size: 0.85rem;
                padding: 0.5rem;
            }
        }
        @media (max-width: 768px) {
            .d-flex.flex-wrap {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body class="p-2 p-md-4">

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <h3 class="mb-0 fw-semibold">
            <i class="bi bi-people-fill text-primary me-2"></i>
            Suivi des ventes – Employés
        </h3>
        <div class="d-flex gap-2 flex-wrap">
            <span class="badge bg-primary fs-6 px-3 py-2">
                <i class="bi bi-calendar-month me-1"></i>
                <?= date('F Y') ?>
            </span>
            <button class="btn btn-outline-secondary btn-sm" onclick="location.reload()">
                <i class="bi bi-arrow-repeat me-1"></i>Rafraîchir
            </button>
        </div>
    </div>

    <!-- Cartes statistiques (empilées sur mobile) -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card bg-primary text-white h-100">
                <div class="card-body text-center">
                    <i class="bi bi-currency-dollar fs-3 mb-2 d-block"></i>
                    <h6 class="mb-1">Ventes jour</h6>
                    <h4 class="mb-0"><?= number_format($totalJour, 0, ',', ' ') ?> FCFA</h4>
                    <small class="text-white-75"><?= $nbActifsAujourdHui ?> actif(s)</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card bg-success text-white h-100">
                <div class="card-body text-center">
                    <i class="bi bi-graph-up fs-3 mb-2 d-block"></i>
                    <h6 class="mb-1">Ventes mois</h6>
                    <h4 class="mb-0"><?= number_format($totalMois, 0, ',', ' ') ?> FCFA</h4>
                    <small class="text-white-75">Cumul <?= date('F Y') ?></small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card bg-info text-white h-100">
                <div class="card-body text-center">
                    <i class="bi bi-people fs-3 mb-2 d-block"></i>
                    <h6 class="mb-1">Employés</h6>
                    <h4 class="mb-0"><?= count($employes) ?></h4>
                    <small class="text-white-75"><?= $nbActifsAujourdHui ?> ont vendu</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card bg-warning text-dark h-100">
                <div class="card-body text-center">
                    <i class="bi bi-clock-history fs-3 mb-2 d-block"></i>
                    <h6 class="mb-1">Activité</h6>
                    <h4 class="mb-0"><?= $nbActifsAujourdHui > 0 ? 'En cours' : '—' ?></h4>
                    <small class="text-dark-75">Aujourd’hui</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Tableau -->
    <div class="table-responsive">
        <table class="table table-hover table-bordered align-middle mb-0">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Photo</th>
                    <th>Nom</th>
                    <th class="text-center">Début vente</th>
                    <th class="text-center">Ventes jour</th>
                    <th class="text-center">Ventes mois</th>
                    <th class="text-center">Statut</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($employes)): ?>
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="bi bi-people fs-1 d-block mb-3 opacity-50"></i>
                        Aucun employé trouvé
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($employes as $index => $e): ?>
                    <tr class="<?= $e['ventes_jour'] > 0 ? 'table-success' : '' ?>">
                        <td class="fw-medium"><?= $index + 1 ?></td>
                        <td>
                            <?php if (!empty($e['photo'])): ?>
                                <img src="data:image/jpeg;base64,<?= base64_encode($e['photo']) ?>" 
                                     class="photo-employe" alt="<?= htmlspecialchars($e['nom']) ?>">
                            <?php else: ?>
                                <i class="bi bi-person-circle fs-3 text-secondary"></i>
                            <?php endif; ?>
                        </td>
                        <td class="fw-medium"><?= htmlspecialchars($e['nom']) ?></td>
                        <td class="text-center">
                            <?php if ($e['debut_vente']): ?>
                                <span class="badge bg-secondary">
                                    <i class="bi bi-clock me-1"></i>
                                    <?= (new DateTime($e['debut_vente']))->format('H:i') ?>
                                </span>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td class="text-center fw-bold">
                            <?= number_format($e['ventes_jour'], 0, ',', ' ') ?> FCFA
                        </td>
                        <td class="text-center">
                            <?= number_format($e['ventes_mois'], 0, ',', ' ') ?> FCFA
                        </td>
                        <td class="text-center">
                            <?php if ($e['actif_aujourdhui']): ?>
                                <span class="badge badge-active">
                                    <i class="bi bi-check-circle-fill me-1"></i>Actif
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactif</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>