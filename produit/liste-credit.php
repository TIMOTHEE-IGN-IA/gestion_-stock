<?php
session_start();
require_once __DIR__ . "/../includes/db.php";

// Configuration fuseau horaire
date_default_timezone_set('Africa/Abidjan');
$now = new DateTime();

// Vérification utilisateur
if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
    header("Location: ../index.php");
    exit;
}

$user_id = $_SESSION['user']['id'];

try {
    $stmt = $connexion->prepare("
        SELECT * FROM credits 
        WHERE user_id = ? 
        ORDER BY date_credit DESC
    ");
    $stmt->execute([$user_id]);
    $credits = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $credits = [];
    $error = "Erreur lors de la récupération des crédits : " . $e->getMessage();
}

// Statistiques
$totalCredits = count($credits);
$totalMontant = array_sum(array_map(fn($c) => (float)($c['montant'] ?? 0), $credits));

// Détection crédits échus
$creditsEchus = [];
foreach ($credits as $c) {
    if (!empty($c['date_paiement_prevu']) && strtolower($c['statut'] ?? '') !== 'payé') {
        $dueDate = new DateTime($c['date_paiement_prevu']);
        if ($dueDate <= $now) {
            $creditsEchus[] = [
                'client'   => $c['client'] ?? 'Client inconnu',
                'montant'  => (float)($c['montant'] ?? 0),
                'date_echu'=> $dueDate->format('d/m/Y'),
                'reste'    => (float)($c['reste'] ?? $c['montant'] ?? 0)
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Crédits</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .alert-fixed-top {
            position: sticky;
            top: 0;
            z-index: 1030;
            margin-bottom: 1.5rem;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .toast-container { 
            position: fixed; 
            top: 80px; 
            right: 20px; 
            z-index: 1055; 
        }
        .table-danger { background-color: #fff5f5 !important; }
        .text-due { color: #dc3545; font-weight: bold; }
    </style>
</head>
<body>

<!-- ALERTE PRINCIPALE FIXE EN HAUT (si crédits échus) -->
<?php if (!empty($creditsEchus)): ?>
<div class="alert alert-danger alert-dismissible fade show alert-fixed-top mb-0" role="alert">
    <strong>⚠️ <?= count($creditsEchus) ?> crédit(s) ÉCHU(S) !</strong><br>
    <small>Date du jour : <?= $now->format('d/m/Y') ?></small><br><br>
    
    <strong>Clients concernés :</strong><br>
    <ul class="mb-2 ps-4">
        <?php foreach ($creditsEchus as $e): ?>
            <li>
                <strong><?= htmlspecialchars($e['client']) ?></strong>  
                – <?= number_format($e['reste'], 0, ',', ' ') ?> FCFA  
                (échu depuis le <?= $e['date_echu'] ?>)
            </li>
        <?php endforeach; ?>
    </ul>
    
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<!-- Alerte si aucun crédit -->
<?php if (empty($credits) && empty($error)): ?>
<div class="alert alert-warning alert-dismissible fade show" role="alert">
    <strong>Aucun crédit enregistré pour le moment.</strong><br>
    Vous pouvez ajouter un nouveau crédit depuis la page de vente ou via le bouton dédié.
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="container-fluid py-4">

    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-2">💳 Gestion des Crédits</h2>
            <p class="text-muted mb-0">Date du jour : <?= $now->format('d/m/Y') ?></p>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Cartes statistiques -->
    <div class="row mb-4 g-3">
        <div class="col-6 col-md-4 col-lg-3">
            <div class="card bg-primary text-white shadow-sm">
                <div class="card-body text-center">
                    <h4 class="mb-1"><?= number_format($totalCredits) ?></h4>
                    <small>Nombre de crédits</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <div class="card bg-success text-white shadow-sm">
                <div class="card-body text-center">
                    <h4 class="mb-1"><?= number_format($totalMontant, 0, ',', ' ') ?> FCFA</h4>
                    <small>Montant total crédité</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <div class="card bg-danger text-white shadow-sm">
                <div class="card-body text-center">
                    <h4 class="mb-1"><?= count($creditsEchus) ?></h4>
                    <small>Crédits échus</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Tableau -->
    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Client</th>
                    <th>WhatsApp</th>
                    <th>Libellé</th>
                    <th>Montant</th>
                    <th>Reste</th>
                    <th>Date crédit</th>
                    <th>Date paiement prévue</th>
                    <th>Statut</th>
                    <th>Reçu</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!empty($credits)): ?>
                <?php foreach ($credits as $index => $c):
                    $dateCredit   = !empty($c['date_credit'])   ? (new DateTime($c['date_credit']))->format('d/m/Y') : '—';
                    $datePaiement = !empty($c['date_paiement_prevu']) ? (new DateTime($c['date_paiement_prevu']))->format('d/m/Y') : '—';
                    $isDue = !empty($c['date_paiement_prevu']) && (new DateTime($c['date_paiement_prevu']) <= $now) && strtolower($c['statut'] ?? '') !== 'payé';
                ?>
                    <tr class="<?= $isDue ? 'table-danger' : '' ?>">
                        <td><?= $index + 1 ?></td>
                        <td><?= htmlspecialchars($c['client'] ?? '—') ?></td>
                        <td class="text-center">
                            <?php if (!empty($c['telephone'])):
                                $tel = preg_replace('/\D/', '', $c['telephone']);
                                if (substr($tel, 0, 3) !== '225') $tel = '225' . $tel;
                            ?>
                                <a href="https://wa.me/<?= $tel ?>" target="_blank" class="text-success fs-4">
                                    <i class="bi bi-whatsapp"></i>
                                </a>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($c['libelle'] ?? '—') ?></td>
                        <td><?= number_format((float)$c['montant'], 0, ',', ' ') ?> FCFA</td>
                        <td class="<?= ($c['reste'] ?? 0) > 0 ? 'text-danger fw-bold' : 'text-success' ?>">
                            <?= number_format((float)($c['reste'] ?? 0), 0, ',', ' ') ?> FCFA
                        </td>
                        <td><?= $dateCredit ?></td>
                        <td class="<?= $isDue ? 'text-due' : '' ?>">
                            <?= $datePaiement ?>
                            <?php if ($isDue): ?>
                                <span class="badge bg-danger ms-1">ÉCHU</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= strtolower($c['statut'] ?? '') === 'payé' ? 'bg-success' : 'bg-warning' ?>">
                                <?= htmlspecialchars($c['statut'] ?? 'En cours') ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-primary btn-recu"
                                    data-id="<?= $c['id'] ?>"
                                    data-due="<?= $isDue ? '1' : '0' ?>">
                                Reçu
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="10" class="text-center py-5 text-muted">
                        Aucun crédit enregistré pour le moment.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Toast en haut à droite -->
<div class="toast-container">
    <?php if (!empty($creditsEchus)): ?>
    <div class="toast align-items-center text-bg-danger border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="false">
        <div class="d-flex">
            <div class="toast-body">
                <strong>⚠️ <?= count($creditsEchus) ?> crédit(s) ÉCHU(S) !</strong><br>
                <small>Date : <?= $now->format('d/m/Y') ?></small><br><br>
                <strong>Clients concernés :</strong><br>
                <?php foreach ($creditsEchus as $i => $e): ?>
                    <?= ($i+1) ?>. <strong><?= htmlspecialchars($e['client']) ?></strong>  
                    – <?= number_format($e['reste'], 0, ',', ' ') ?> FCFA  
                    (échu depuis le <?= $e['date_echu'] ?>)<br>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Afficher le toast au chargement
document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll('.toast').forEach(toastEl => {
        new bootstrap.Toast(toastEl).show();
    });
});
</script>

</body>
</html>