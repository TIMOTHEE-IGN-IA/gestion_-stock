<?php
session_start();
require_once __DIR__ . "/../includes/db.php";

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'Admin') {
    echo "<div class='alert alert-danger text-center m-5'>Accès refusé. Seuls les administrateurs peuvent ajouter des produits.</div>";
    exit;
}

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom           = trim($_POST['nom'] ?? '');
    $description   = trim($_POST['description'] ?? '');
    $categorie     = trim($_POST['categorie'] ?? '');
    $quantite      = (int)($_POST['quantite'] ?? 0);
    $prix_unitaire = (float)($_POST['prix'] ?? 0);
    $prix_achat    = (float)($_POST['prix_achat'] ?? 0);
    $fournisseur   = trim($_POST['fournisseur'] ?? '');
    $stock_alerte  = (int)($_POST['stock_alerte'] ?? 5); // nouveau champ

    $errors = [];
    if (empty($nom) || strlen($nom) < 2) $errors[] = "Nom du produit obligatoire (≥ 2 caractères).";
    if (empty($categorie)) $errors[] = "La catégorie est obligatoire.";
    elseif (!in_array($categorie, ['Fer','Ciment','Peinture','Électricité','Visses et quincaillerie','Outils','Divers']))
        $errors[] = "Catégorie non valide.";
    if ($quantite < 0) $errors[] = "Quantité ne peut pas être négative.";
    if ($prix_unitaire < 0) $errors[] = "Prix de vente doit être positif.";
    if ($prix_achat < 0) $errors[] = "Prix d'achat doit être positif.";
    if ($stock_alerte < 0) $errors[] = "Seuil d'alerte stock ne peut pas être négatif.";

    if (empty($errors)) {
        try {
            $connexion->beginTransaction();
            $stmt = $connexion->prepare("SELECT derniere_valeur FROM compteur_code WHERE id = 1 FOR UPDATE");
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new Exception("Compteur de codes introuvable.");
            $nouveau_numero = (int)$row['derniere_valeur'] + 1;
            $code = sprintf("PROD-%04d", $nouveau_numero);
            $stmt = $connexion->prepare("UPDATE compteur_code SET derniere_valeur = ? WHERE id = 1");
            $stmt->execute([$nouveau_numero]);

           // Ajout du user_id
$sql = "INSERT INTO produit 
        (code, nom, description, categorie, quantite, prix_unitaire, prix_achat, fournisseur, stock_alerte, user_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $connexion->prepare($sql);
$stmt->execute([
    $code,
    $nom,
    $description ?: null,
    $categorie,
    $quantite,
    $prix_unitaire,
    $prix_achat,
    $fournisseur ?: null,
    $stock_alerte,
    $_SESSION['user']['id'] // <-- ici
]);


            $connexion->commit();
            $success = true;

            $message = "
            <div class='alert alert-success alert-dismissible fade show shadow-sm' role='alert'>
                <i class='bi bi-check-circle-fill me-3 fs-4'></i>
                <div>
                    <strong>Produit ajouté avec succès !</strong><br>
                    <span class='d-block mt-1'>
                        <strong>{$nom}</strong> – <span class='badge bg-info ms-1'>{$categorie}</span><br>
                        Code : <strong class='font-monospace'>{$code}</strong>
                    </span>
                    <hr class='my-2 opacity-50'>
                    <small>
                        Qté : <strong>" . number_format($quantite) . "</strong> |
                        Seuil alerte : <strong>{$stock_alerte}</strong> unités |
                        Achat : <strong>" . number_format($prix_achat, 2, ',', ' ') . " FCFA</strong> |
                        Vente : <strong>" . number_format($prix_unitaire, 2, ',', ' ') . " FCFA</strong>
                    </small>
                </div>
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>";

            $_POST = []; // reset formulaire
        } catch (Exception $e) {
            $connexion->rollBack();
            $message = "
            <div class='alert alert-danger alert-dismissible fade show shadow-sm' role='alert'>
                <i class='bi bi-exclamation-triangle-fill me-3 fs-4'></i>
                <strong>Erreur :</strong> " . htmlspecialchars($e->getMessage()) . "
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>";
        }
    } else {
        $message = "
        <div class='alert alert-warning alert-dismissible fade show shadow-sm' role='alert'>
            <i class='bi bi-exclamation-circle-fill me-3 fs-4'></i>
            <strong>Attention :</strong>
            <ul class='mb-0 mt-2 ps-4'>" . implode('', array_map(fn($e) => "<li>$e</li>", $errors)) . "</ul>
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
        </div>";
    }
}
?>

<!DOCTYPE html>
<html lang="fr" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter Produit – Nova Stock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Ton style reste identique, je ne le répète pas ici -->
</head>
<body>
<div class="card-form">
    <div class="card-header text-center">
        <h1><i class="bi bi-plus-circle-fill me-2 btn btn-primary flex-fill"></i>&nbsp<b>  Ajouter un Produit</b></font></h1>
        <p>Remplissez les informations ci-dessous pour enregistrer un nouveau produit</p>
    </div>

    <div class="card-body">
        <?= $message ?>

        <form id="add-product-form" method="POST" class="needs-validation" novalidate>
            <!-- Nom -->
            <div class="mb-4">
                <label for="nom" class="form-label">Nom du produit *</label>
                <div class="input-group">
                    <span class="input-group-text form-icon"><i class="bi bi-tag-fill"></i></span>
                    <input type="text" class="form-control" id="nom" name="nom"
                           value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>"
                           required maxlength="100" placeholder="Ex : Poudre à canon 42,5">
                </div>
            </div>

            <!-- Description -->
            <div class="mb-4">
                <label for="description" class="form-label">Description</label>
                <div class="input-group">
                    <span class="input-group-text form-icon"><i class="bi bi-file-text"></i></span>
                    <textarea class="form-control" id="description" name="description" rows="3"
                              placeholder="Détails, caractéristiques, usage..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Catégorie -->
            <div class="mb-4">
                <label for="categorie" class="form-label">Catégorie *</label>
                <div class="input-group">
                    <span class="input-group-text form-icon"><i class="bi bi-tags-fill"></i></span>
                    <select class="form-select" id="categorie" name="categorie" required>
                        <option value="">Choisir une catégorie</option>
                        <?php
                        $cats = ['Fer','Ciment','Peinture','Électricité','Visses et quincaillerie','Outils','Divers'];
                        foreach ($cats as $cat) {
                            $sel = ($_POST['categorie'] ?? '') === $cat ? 'selected' : '';
                            echo "<option value='$cat' $sel>$cat</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>

            <!-- Quantité initiale -->
            <div class="mb-4">
                <label for="quantite" class="form-label">Stock *</label>
                <div class="input-group">
                    <span class="input-group-text form-icon"><i class="bi bi-stack"></i></span>
                    <input type="number" class="form-control" id="quantite" name="quantite"
                           min="0" value="<?= htmlspecialchars($_POST['quantite'] ?? '0') ?>" required>
                    <span class="input-group-text currency-unit">unités</span>
                </div>
            </div>

            <!-- Nouveau champ : Stock alerte -->
            <div class="mb-4">
                <label for="stock_alerte" class="form-label">Seuil d'alerte stock</label>
                <div class="input-group">
                    <span class="input-group-text form-icon"><i class="bi bi-bell-fill"></i></span>
                    <input type="number" class="form-control" id="stock_alerte" name="stock_alerte"
                           min="0" value="<?= htmlspecialchars($_POST['stock_alerte'] ?? '5') ?>" 
                           <?= !isset($_POST['stock_alerte']) ? 'disabled' : '' ?>> <!-- grisé par défaut -->
                    <span class="input-group-text currency-unit">unités</span>
                </div>
                <small class="form-text text-muted">
                    Valeur par défaut : 5 unités (quand le stock ≤ cette valeur → alerte)
                </small>
            </div>

            <!-- Prix -->
            <div class="price-group mb-4">
                <div>
                    <label for="prix_achat" class="form-label">Prix d'achat unitaire</label>
                    <div class="input-group">
                        <span class="input-group-text form-icon"><i class="bi bi-arrow-down-circle"></i></span>
                        <input type="number" step="0.01" class="form-control" id="prix_achat" name="prix_achat"
                               min="0" value="<?= htmlspecialchars($_POST['prix_achat'] ?? '') ?>" required>
                        <span class="input-group-text currency-unit">FCFA</span>
                    </div>
                </div>
                <div>
                    <label for="prix" class="form-label">Prix de vente unitaire *</label>
                    <div class="input-group">
                        <span class="input-group-text form-icon"><i class="bi bi-arrow-up-circle"></i></span>
                        <input type="number" step="0.01" class="form-control" id="prix" name="prix"
                               min="0" value="<?= htmlspecialchars($_POST['prix'] ?? '') ?>" required>
                        <span class="input-group-text currency-unit">FCFA</span>
                    </div>
                </div>
            </div>

            <!-- Fournisseur -->
            <div class="mb-4">
                <label for="fournisseur" class="form-label">Fournisseur</label>
                <div class="input-group">
                    <span class="input-group-text form-icon"><i class="bi bi-truck"></i></span>
                    <input type="text" class="form-control" id="fournisseur" name="fournisseur"
                           value="<?= htmlspecialchars($_POST['fournisseur'] ?? '') ?>"
                           placeholder="Ex : SODICAM, CFAO, ...">
                </div>
            </div>

            <!-- Actions -->
            <div class="d-flex gap-3 mt-5">
                <button type="submit" class="btn btn-primary flex-fill" id="submitBtn">
                    <i class="bi bi-save me-2"></i>
                    <span id="btnText">Enregistrer Produit</span>
                </button>
                <a href="list.php" class="btn btn-outline-secondary flex-fill">
                    <i class="bi bi-arrow-left me-2"></i>Annuler
                </a>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Validation prix + feedback
document.addEventListener('DOMContentLoaded', () => {
    const pa = document.getElementById('prix_achat');
    const pv = document.getElementById('prix');
    
    const checkPrices = () => {
        const vA = parseFloat(pa.value) || 0;
        const vV = parseFloat(pv.value) || 0;
        pv.classList.toggle('is-warning', vV > 0 && vA > 0 && vV <= vA);
    };

    pa.addEventListener('input', checkPrices);
    pv.addEventListener('input', checkPrices);
    checkPrices();

    // Soumission formulaire → anti double-clic
    document.getElementById('add-product-form').addEventListener('submit', e => {
        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        document.getElementById('btnText').innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enregistrement...';
    });

    // Auto-close alertes succès
    document.querySelectorAll('.alert-success').forEach(alert => {
        setTimeout(() => bootstrap.Alert.getOrCreateInstance(alert).close(), 7000);
    });
});
</script>
</body>
</html>