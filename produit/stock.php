<?php
session_start();
require_once __DIR__ . "/../includes/db.php";

date_default_timezone_set('Africa/Abidjan');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'Admin') {
    exit("<div class='alert alert-danger text-center py-5'>Accès réservé aux administrateurs.</div>");
}

$user_id = (int)$_SESSION['user']['id'];
$message = "";

// ==========================
// AJOUT ENTRÉE STOCK
// ==========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['produit_id'])) {
    try {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception("Erreur de validation CSRF.");
        }

        $connexion->beginTransaction();

        $produit_id   = (int)($_POST['produit_id'] ?? 0);
        $quantite     = (int)($_POST['quantite'] ?? 0);
        $prix_achat   = (float) str_replace([' ', ','], ['', '.'], $_POST['prix_achat'] ?? 0);
        $prix_vente   = (float) str_replace([' ', ','], ['', '.'], $_POST['prix_vente'] ?? 0);
        $fournisseur  = trim($_POST['fournisseur'] ?? '');
        $commentaire  = trim($_POST['commentaire'] ?? '');

        if ($produit_id <= 0)  throw new Exception("Produit invalide.");
        if ($quantite   <= 0)  throw new Exception("Quantité invalide.");
        if ($prix_achat <= 0)  throw new Exception("Prix d'achat invalide.");
        if ($prix_vente <= 0)  throw new Exception("Prix de vente invalide.");

        $stmt = $connexion->prepare(
            "SELECT id, code, nom, quantite, prix_achat AS prix_achat_actuel,
                    prix_unitaire AS prix_vente_actuel, benefice_potentiel_cumule
             FROM produit WHERE id = ? AND user_id = ?"
        );
        $stmt->execute([$produit_id, $user_id]);
        $produit = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$produit) throw new Exception("Produit non trouvé.");

        $new_quantite = $produit['quantite'] + $quantite;
        $benefice_entree = $quantite * ($prix_vente - $prix_achat);
        $nouveau_benefice_cumule = $produit['benefice_potentiel_cumule'] + $benefice_entree;

        $connexion->prepare(
            "UPDATE produit 
             SET quantite = ?, 
                 benefice_potentiel_cumule = ?
             WHERE id = ?"
        )->execute([$new_quantite, $nouveau_benefice_cumule, $produit_id]);

        $connexion->prepare(
            "INSERT INTO historique_stock
             (produit_id, utilisateur_id, type, quantite, fournisseur, commentaire,
              prix_achat, prix_vente, date_mouvement)
             VALUES (?, ?, 'Entree', ?, ?, ?, ?, ?, NOW())"
        )->execute([
            $produit_id, $user_id, $quantite, $fournisseur ?: null, $commentaire ?: null,
            $prix_achat, $prix_vente
        ]);

        $connexion->commit();

        $valeur_entree = $quantite * $prix_achat;

        // MESSAGE DE SUCCÈS COMPLET ET RÉSUMÉ
        $message = "
        <div class='alert alert-success alert-dismissible fade show shadow-sm' role='alert'>
            <strong>Entrée enregistrée avec succès !</strong><br>
            <hr class='my-2'>
            <strong>Produit :</strong> {$produit['nom']} ({$produit['code']})<br>
            <strong>Quantité entrée :</strong> " . number_format($quantite) . " unités<br>
            <strong>Fournisseur :</strong> " . ($fournisseur ?: '—') . "<br>
            <strong>Nouveau stock total :</strong> " . number_format($new_quantite) . " unités<br>
            <strong>Prix d'achat utilisé :</strong> " . number_format($prix_achat, 0, ',', ' ') . " FCFA<br>
            <strong>Prix de vente utilisé :</strong> " . number_format($prix_vente, 0, ',', ' ') . " FCFA<br>
            <strong>Valeur totale entrée :</strong> " . number_format($valeur_entree, 0, ',', ' ') . " FCFA<br>
            <strong>Bénéfice potentiel ajouté :</strong> <span class='text-success fw-bold'>" . number_format($benefice_entree, 0, ',', ' ') . " FCFA</span><br>
            <strong>Bénéfice potentiel cumulé du produit :</strong> <span class='fw-bold'>" . number_format($nouveau_benefice_cumule, 0, ',', ' ') . " FCFA</span><br>
            <small class='text-muted'>(Ancien prix d'achat de référence : " . number_format($produit['prix_achat_actuel'], 0, ',', ' ') . " FCFA)</small><br>
            <small class='text-muted d-block mt-2'>Heure : " . date('H:i:s – d/m/Y') . "</small>
            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
        </div>";

        $_POST = []; // Reset formulaire

    } catch (Exception $e) {
        $connexion->rollBack();
        $message = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
            " . htmlspecialchars($e->getMessage()) . "
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
        </div>";
    }
}

// ==========================
// LISTE PRODUITS POUR SELECT
// ==========================
$stmt = $connexion->prepare(
    "SELECT id, code, nom, quantite, prix_achat, prix_unitaire 
     FROM produit 
     WHERE user_id = ? 
     ORDER BY nom ASC"
);
$stmt->execute([$user_id]);
$produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Entrées Stock</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
    body {
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
        background: #f9fafb;
        color: #1f2937;
        min-height: 100vh;
        padding: 2rem 1rem;
    }
    h2 {
        font-weight: 700;
        letter-spacing: -0.025em;
        color: #111827;
        text-align: center;
        margin-bottom: 2.5rem;
    }
    .card {
        border: none;
        border-radius: 12px;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
        overflow: hidden;
        max-width: 900px;
        margin: 0 auto;
    }
    .card-body {
        padding: 2rem;
    }
    .form-label {
        font-weight: 500;
        color: #374151;
    }
    .form-control, .form-select {
        border-radius: 8px;
        border: 1px solid #d1d5db;
        padding: 0.65rem 1rem;
    }
    .form-control:focus, .form-select:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
    }
    .btn-primary {
        background: #2563eb;
        border: none;
        padding: 0.75rem 1.5rem;
        font-weight: 500;
        border-radius: 8px;
    }
    .btn-primary:hover {
        background: #1d4ed8;
    }
    .alert {
        border-radius: 8px;
        padding: 1.25rem 1.5rem;
        max-width: 900px;
        margin: 0 auto 2rem;
        line-height: 1.6;
    }
    .input-group-text {
        background: #f3f4f6;
        border: 1px solid #d1d5db;
        border-left: none;
    }
</style>
</head>
<body>

<div class="container">
    <h2>📥 Enregistrer une entrée de stock</h2>

    <?= $message ?>

    <div class="card">
        <div class="card-body">
            <form id="stockForm" method="POST" action="" class="row g-4 needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                <div class="mb-4">
                    <label class="form-label">Produit <span class="text-danger">*</span></label>
                    <select name="produit_id" class="form-select form-select-lg" required>
                        <option value="">Choisir un produit...</option>
                        <?php foreach ($produits as $p): ?>
                            <option value="<?= $p['id'] ?>"
                                    data-prix-achat="<?= $p['prix_achat'] ?>"
                                    data-prix-vente="<?= $p['prix_unitaire'] ?>">
                                <?= htmlspecialchars($p['code']) ?> - <?= htmlspecialchars($p['nom']) ?>
                                (stock : <?= number_format($p['quantite']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="row g-4">
                    <div class="col-md-4">
                        <label class="form-label">Quantité <span class="text-danger">*</span></label>
                        <input type="number" name="quantite" class="form-control form-control-lg" min="1" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Prix d'achat unitaire <span class="text-danger">*</span></label>
                        <div class="input-group input-group-lg">
                            <input type="number" name="prix_achat" id="prix_achat" class="form-control" step="1" min="0" required>
                            <span class="input-group-text">FCFA</span>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Prix de vente unitaire <span class="text-danger">*</span></label>
                        <div class="input-group input-group-lg">
                            <input type="number" name="prix_vente" id="prix_vente" class="form-control" step="1" min="0" required>
                            <span class="input-group-text">FCFA</span>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <label class="form-label">Fournisseur</label>
                    <input type="text" name="fournisseur" class="form-control form-control-lg" placeholder="ex: MLKM, Yao...">
                </div>

                <div class="mt-4">
                    <label class="form-label">Commentaire / référence</label>
                    <textarea name="commentaire" class="form-control" rows="2"></textarea>
                </div>

                <div class="mt-5 text-center">
                    <button type="submit" class="btn btn-primary btn-lg px-5">
                        Enregistrer l'entrée
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="text-center mt-5">
        <a href="../dashboard.php" class="btn btn-outline-secondary px-5">
            ← Retour au dashboard
        </a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const select = document.querySelector('select[name="produit_id"]');
    const prixAchat = document.getElementById('prix_achat');
    const prixVente = document.getElementById('prix_vente');

    select.addEventListener('change', () => {
        const opt = select.options[select.selectedIndex];
        prixAchat.value = opt.dataset.prixAchat || '';
        prixVente.value = opt.dataset.prixVente || '';
    });

    if (select.value) select.dispatchEvent(new Event('change'));

    // Masquer alertes sur saisie
    document.querySelector('form').addEventListener('input', () => {
        document.querySelectorAll('.alert').forEach(el => el.style.display = 'none');
    });
});
</script>
</body>
</html>