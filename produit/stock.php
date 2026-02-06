<?php
session_start();
require_once __DIR__ . "/../includes/db.php";

// Fuseau horaire Côte d'Ivoire (UTC+0)
date_default_timezone_set('Africa/Abidjan');

// Token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Vérification rôle Admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'Admin') {
    echo "<div class='alert alert-danger text-center py-5'>Accès réservé aux administrateurs.</div>";
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Vérification CSRF
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception("Erreur de validation de la requête.");
        }

        $connexion->beginTransaction();

        $produit_id = (int)($_POST['produit_id'] ?? 0);
        $type = 'Entree'; // FORCÉ : plus de Sortie possible
        $quantite = (int)($_POST['quantite'] ?? 0);
        $commentaire = trim($_POST['commentaire'] ?? '');
        $user_id = (int)($_SESSION['user']['id'] ?? 0);
        $fournisseur = trim($_POST['fournisseur'] ?? '');

        if ($produit_id <= 0) throw new Exception("ID produit invalide.");
        if ($quantite <= 0) throw new Exception("Quantité invalide.");

        $stmt = $connexion->prepare(
            "SELECT id, code, nom, quantite, prix_achat, prix_unitaire FROM produit WHERE id = ?"
        );
        $stmt->execute([$produit_id]);
        $produit = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$produit) throw new Exception("Produit introuvable.");

        $newQty = $produit['quantite'] + $quantite;

        $connexion->prepare("UPDATE produit SET quantite = ? WHERE id = ?")
            ->execute([$newQty, $produit_id]);

        $connexion->prepare(
            "INSERT INTO historique_stock
            (produit_id, utilisateur_id, type, quantite, fournisseur, commentaire, date_mouvement)
            VALUES (?, ?, ?, ?, ?, ?, NOW())"
        )->execute([
            $produit_id,
            $user_id,
            $type,
            $quantite,
            $fournisseur ?: null,
            $commentaire ?: null
        ]);

        $facture_id = $connexion->lastInsertId();
        $connexion->commit();

        // Récupération du mouvement pour affichage
        $stmt = $connexion->prepare("
            SELECT hs.*, p.code, p.nom, p.prix_achat, p.prix_unitaire, u.nom AS user_nom
            FROM historique_stock hs
            JOIN produit p ON hs.produit_id = p.id
            JOIN utilisateur u ON hs.utilisateur_id = u.id
            WHERE hs.id = ?
        ");
        $stmt->execute([$facture_id]);
        $mouvement = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$mouvement) {
            throw new Exception("Impossible de récupérer les détails du mouvement.");
        }

        $valeur = $mouvement['quantite'] * $mouvement['prix_achat'];

        // Message succès
        $message = "
        <div class='alert alert-success alert-dismissible fade show mb-4 shadow-lg border-0' style='border-left: 6px solid #198754;'>
            <h5 class='alert-heading mb-3'>
                <i class='bi bi-check-circle-fill me-2 fs-4'></i>
                Succès !
            </h5>
            <div class='ps-4'>
                <i class='bi bi-box-arrow-in-right text-success fs-5 me-3'></i>
                <strong>Entrée enregistrée</strong> de " . number_format($mouvement['quantite'], 0, ',', ' ') . " unités<br>
                <i class='bi bi-gem text-primary fs-5 me-3'></i>
                Produit : <strong>" . htmlspecialchars($mouvement['nom']) . "</strong><br>
                <i class='bi bi-truck text-info fs-5 me-3'></i>
                Fournisseur : <strong>" . htmlspecialchars($mouvement['fournisseur'] ?: '—') . "</strong><br>
                <i class='bi bi-stack text-warning fs-5 me-3'></i>
                Nouveau stock : <strong>" . number_format($newQty, 0, ',', ' ') . "</strong><br>
                <i class='bi bi-cash-stack text-success fs-5 me-3'></i>
                Valeur : <strong>" . number_format($valeur, 0, ',', ' ') . " FCFA</strong><br>
                <i class='bi bi-clock-history text-secondary fs-5 me-3'></i>
                <strong>Heure (CI) :</strong> " . (new DateTime())->format('H:i:s – d/m/Y') . "
            </div>
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
        </div>";

        $_POST = [];

    } catch (Exception $e) {
        $connexion->rollBack();
        $error = "
        <div class='alert alert-danger alert-dismissible fade show mb-4 shadow-lg border-0' style='border-left: 6px solid #dc3545;'>
            <h5 class='alert-heading'>
                <i class='bi bi-exclamation-triangle-fill me-2 fs-4'></i>
                Erreur
            </h5>
            " . htmlspecialchars($e->getMessage()) . "
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
        </div>";
    }
}

// Produits pour le select
$produits = $connexion->query(
    "SELECT id, code, nom, quantite FROM produit ORDER BY nom ASC"
)->fetchAll(PDO::FETCH_ASSOC);

// Historique récent
$historique = $connexion->query(
    "SELECT hs.*, p.code, p.nom, u.nom AS user_nom
     FROM historique_stock hs
     JOIN produit p ON hs.produit_id = p.id
     JOIN utilisateur u ON hs.utilisateur_id = u.id
     ORDER BY hs.date_mouvement DESC
     LIMIT 10"
)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestion Stock - Nova</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
body { 
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); 
    padding: 2.5rem 1rem; 
    min-height: 100vh;
}
.container { max-width: 1200px; }
h3 { color: #0d6efd; font-weight: 700; letter-spacing: 0.5px; text-shadow: 1px 1px 2px rgba(0,0,0,0.05);}
.card { border: none; border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.08); overflow: hidden;}
.form-label { font-weight: 600; color: #343a40;}
.form-control, .form-select { border-radius: 12px; border: 1px solid #ced4da; transition: all 0.3s; padding: 0.75rem 1rem;}
.form-control:focus, .form-select:focus { border-color: #0d6efd; box-shadow: 0 0 0 0.25rem rgba(13,110,253,.25);}
.btn-primary { border-radius: 12px; font-weight: 600; padding: 0.8rem 1.5rem; transition: all 0.3s;}
.btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(13,110,253,0.3);}
.alert { border-radius: 12px; border: none; box-shadow: 0 6px 20px rgba(0,0,0,0.1);}

<style>
/* === GLOBAL === */
body { 
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    padding: 1rem;
    min-height: 100vh;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.container { max-width: 1100px; margin: auto; }
h3 { color: #0d6efd; font-weight: 700; text-shadow: 1px 1px 2px rgba(0,0,0,0.05); margin-bottom: 2rem; }

/* === CARD FORM === */
.card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 8px 30px rgba(0,0,0,0.08);
    overflow: hidden;
    padding: 2rem;
}

.form-label { font-weight: 600; color: #343a40; margin-bottom: 0.5rem; }
.form-control, .form-select {
    border-radius: 12px;
    border: 1px solid #ced4da;
    transition: all 0.3s;
    padding: 0.65rem 1rem;
}
.form-control:focus, .form-select:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.25rem rgba(13,110,253,.25);
}

.btn-primary {
    border-radius: 12px;
    font-weight: 600;
    padding: 0.7rem 1.2rem;
    transition: all 0.3s;
}
.btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(13,110,253,0.3); }

.alert { border-radius: 12px; border: none; box-shadow: 0 6px 20px rgba(0,0,0,0.1); }

/* === RESPONSIVE === */
@media (max-width: 992px) {
    .card { padding: 1.5rem; }
}

@media (max-width: 768px) {
    .row.g-4 > div { width: 100% !important; }
    .btn-lg { width: 100%; font-size: 1rem; padding: 0.65rem; }
    .form-control, .form-select { font-size: 0.95rem; padding: 0.55rem 0.75rem; }
    h3 { font-size: 1.4rem; margin-bottom: 1.5rem; }
}

/* === TABLETS & MOBILE SMALL === */
@media (max-width: 576px) {
    .btn-lg { font-size: 0.9rem; padding: 0.5rem 0.8rem; }
    .form-label { font-size: 0.9rem; }
    .form-control, .form-select { font-size: 0.9rem; padding: 0.5rem 0.7rem; }
    .card { padding: 1rem; }
}
</style>

</style>
</head>
<body>
<div class="container">

<h3 class="text-center mb-5">
    <i class="bi bi-boxes me-3 text-primary fs-2"></i>
    Gestion des Entrées Stock
</h3>

<?= $message ?>
<?= $error ?>

<div class="card p-4 shadow-lg">
<form id="stockForm" method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="row g-4 needs-validation" novalidate>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="type" value="Entree">

    <div class="col-md-6">
        <label class="form-label fs-5">
            <i class="bi bi-gem text-success me-2 fs-4"></i>
            Produit <span class="text-danger">*</span>
        </label>
        <select class="form-select form-select-lg" id="produit_id" name="produit_id" required>
            <option value="">Choisir un produit...</option>
            <?php foreach ($produits as $p): ?>
                <option value="<?= $p['id'] ?>" data-quantite="<?= $p['quantite'] ?>">
                    <?= htmlspecialchars($p['code']) ?> - <?= htmlspecialchars($p['nom']) ?> (Stock actuel : <?= $p['quantite'] ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <div class="invalid-feedback">Veuillez sélectionner un produit.</div>
    </div>

    <div class="col-md-3">
        <label class="form-label fs-5">
            <i class="bi bi-arrow-down-circle-fill text-primary me-2 fs-4"></i>
            Type de mouvement
        </label>
        <button type="button" class="btn btn-outline-success btn-lg w-100 active" disabled>
            <i class="bi bi-box-arrow-in-right me-2"></i> Entrée
        </button>
    </div>

    <div class="col-md-3">
        <label class="form-label fs-5">
            <i class="bi bi-123 text-danger me-2 fs-4"></i>
            Quantité <span class="text-danger">*</span>
        </label>
        <input type="number" class="form-control form-control-lg" id="quantite" name="quantite" min="1" required>
    </div>

    <div class="col-12 col-md-6">
        <label class="form-label fs-5">
            <i class="bi bi-building text-info me-2 fs-4"></i>
            Fournisseur
        </label>
        <input type="text" class="form-control form-control-lg" name="fournisseur" placeholder="Nom du fournisseur (optionnel)">
    </div>

    <div class="col-12 col-md-6 d-flex align-items-end">
        <button type="submit" class="btn btn-primary btn-lg w-100">
            <i class="bi bi-check2-circle me-2 fs-4"></i>
            Enregistrer l'Entrée
        </button>
    </div>
</form>
</div>

<div class="text-center mt-5">
    <a href="../dashboard.php" class="btn btn-outline-secondary btn-lg">
        <i class="bi bi-arrow-left-circle-fill me-2"></i>
        Retour a liste
    </a>
</div>

<hr class="my-5">

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Max qty si besoin
    const quant = document.getElementById('quantite');
    quant.value = 1;
});
</script>
<script>
// Quand l'utilisateur modifie un champ, on masque les alertes
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('stockForm');
    const alerts = document.querySelectorAll('.alert');

    form.addEventListener('input', () => {
        alerts.forEach(alert => alert.style.display = 'none');
    });
});
</script>

</div>
</body>
</html>
