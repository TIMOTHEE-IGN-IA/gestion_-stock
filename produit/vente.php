<?php
session_start();
require_once __DIR__ . "/../includes/db.php";

date_default_timezone_set('Africa/Abidjan');

if (!isset($_SESSION['user'])) {
    header('Location: ../index.php');
    exit;
}

$user_id = $_SESSION['user']['id'];
$message = $error = "";
$vente_validee_id = null; // Pour le bouton "Imprimer"

/* =========================
   Initialisation valeurs formulaire
========================= */
$produit_sel   = $_POST['produit_id'] ?? '';
$quantite_val  = $_POST['quantite'] ?? 1;
$rabais_val    = $_POST['rabais'] ?? 0;
$paiement_val  = $_POST['paiement'] ?? '';



// Supprimer un produit du panier
if(isset($_POST['supprimer_panier'])){
    $id = (int)$_POST['supprimer_panier'];
    $stmt = $connexion->prepare("DELETE FROM panier WHERE id = ? AND utilisateur_id = ?");
    $stmt->execute([$id, $user_id]);
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

// Mettre à jour les quantités et rabais
if(isset($_POST['mettre_a_jour'])){
    $quantites = $_POST['quantite'] ?? [];
    $rabais = $_POST['rabais'] ?? [];
    foreach($quantites as $id => $q){
        $id = (int)$id;
        $q = max(1,(int)$q);
        $r = max(0,(float)($rabais[$id] ?? 0));
        $stmt = $connexion->prepare("UPDATE panier SET quantite = ?, rabais = ? WHERE id = ? AND utilisateur_id = ?");
        $stmt->execute([$q,$r,$id,$user_id]);
    }
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}


/* =========================
   AJOUT AU PANIER
/* =========================
   AJOUT AU PANIER (sans doublons)
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['produit_id']) && !isset($_POST['valider_vente'])) {
    try {
        $produit_id = (int)$_POST['produit_id'];
        $quantite   = (int)$_POST['quantite'];
        $rabais     = (float)($_POST['rabais'] ?? 0);
        $paiement   = trim($_POST['paiement']);

        if ($produit_id <= 0 || $quantite <= 0 || $paiement === '') {
            throw new Exception("Veuillez remplir tous les champs obligatoires.");
        }

        $stmt = $connexion->prepare("SELECT * FROM produit WHERE id = ?");
        $stmt->execute([$produit_id]);
        $produit = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$produit) throw new Exception("Produit introuvable.");
        if ($quantite > $produit['quantite']) throw new Exception("Stock insuffisant.");

        // Vérifier si le produit est déjà dans le panier de cet utilisateur
        $stmt = $connexion->prepare("SELECT * FROM panier WHERE utilisateur_id = ? AND produit_id = ?");
        $stmt->execute([$user_id, $produit_id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Mettre à jour la quantité et le rabais
            $nouvelle_quantite = $existing['quantite'] + $quantite;
            if ($nouvelle_quantite > $produit['quantite']) {
                throw new Exception("Stock insuffisant pour ajouter cette quantité.");
            }
            $stmt = $connexion->prepare("UPDATE panier SET quantite = ?, rabais = ? WHERE id = ?");
            $stmt->execute([$nouvelle_quantite, $rabais, $existing['id']]);
        } else {
            // Sinon, on insère normalement
            $stmt = $connexion->prepare("
                INSERT INTO panier (utilisateur_id, produit_id, quantite, prix_unitaire, rabais, paiement, date_ajout)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$user_id, $produit_id, $quantite, $produit['prix_unitaire'], $rabais, $paiement]);
        }

        $message = "<div class='alert alert-success'>✅ Produit ajouté au panier</div>";

    } catch (Exception $e) {
        $error = "<div class='alert alert-danger'>⚠ {$e->getMessage()}</div>";
    }
}

/* =========================
/* =========================
   VALIDATION DE LA VENTE
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['valider_vente'])) {
    try {
        $connexion->beginTransaction();

        $stmt = $connexion->prepare("
            SELECT p.*, pr.nom, pr.code
            FROM panier p
            JOIN produit pr ON pr.id = p.produit_id
            WHERE p.utilisateur_id = ?
        ");
        $stmt->execute([$user_id]);
        $panier = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($panier)) throw new Exception("Le panier est vide.");

        $totalVente = 0;
        $vente_ids = []; // tableau pour stocker tous les IDs des produits vendus

        foreach ($panier as $item) {
            $total = ($item['prix_unitaire'] * $item['quantite']) - $item['rabais'];
            $totalVente += $total;

            $stmt = $connexion->prepare("
                INSERT INTO vente (produit_id, utilisateur_id, quantite, prix_unitaire, total, paiement, date)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $item['produit_id'],
                $user_id,
                $item['quantite'],
                $item['prix_unitaire'],
                $total,
                $item['paiement']
            ]);

            // Stocker l'ID de la vente
            $vente_ids[] = $connexion->lastInsertId();

            // Mise à jour du stock
            $stmt = $connexion->prepare("UPDATE produit SET quantite = quantite - ? WHERE id = ?");
            $stmt->execute([$item['quantite'], $item['produit_id']]);
        }

        $connexion->commit();

        // Vider le panier après le commit
        $stmt = $connexion->prepare("DELETE FROM panier WHERE utilisateur_id = ?");
        $stmt->execute([$user_id]);

        // On prend **le premier ID de vente** comme référence pour la facture
        $vente_validee_id = $vente_ids[0] ?? null;


        // Message de succès
        $message = "
        <div class='alert alert-success alert-dismissible fade show mb-4 shadow-lg border-0' style='border-left: 6px solid #198754;'>
            <h5 class='alert-heading mb-3'>
                <i class='bi bi-check-circle-fill me-2 fs-4'></i>
                Succès ! Vente validée
            </h5>
            <div class='ps-4'>
                <i class='bi bi-box-arrow-in-right text-success fs-5 me-3'></i>
                <strong>Quantité vendue :</strong> " . array_sum(array_column($panier, 'quantite')) . " unités<br>
                <i class='bi bi-gem text-primary fs-5 me-3'></i>
                Produits : <strong>" . implode(', ', array_map(fn($p) => htmlspecialchars($p['nom']), $panier)) . "</strong><br>
                <i class='bi bi-cash-stack text-success fs-5 me-3'></i>
                Valeur totale : <strong>" . number_format($totalVente, 0, ',', ' ') . " FCFA</strong><br>
                <i class='bi bi-clock-history text-secondary fs-5 me-3'></i>
                <strong>Heure (CI) :</strong> " . (new DateTime())->format('H:i:s – d/m/Y') . "
            </div>
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
        </div>";

        $venteValidee = true; // uniquement après succès

    } catch (Exception $e) {
        $connexion->rollBack();
        $error = "<div class='alert alert-danger'>⚠ Erreur : {$e->getMessage()}</div>";
    }
}

/* =========================
   DONNÉES AFFICHAGE
========================= */
$produits = $connexion->query("SELECT * FROM produit WHERE quantite > 0 ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);

$stmt = $connexion->prepare("
    SELECT p.*, pr.nom, pr.code
    FROM panier p
    JOIN produit pr ON pr.id = p.produit_id
    WHERE p.utilisateur_id = ?
    ORDER BY p.id DESC
");
$stmt->execute([$user_id]);
$panier = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vente au comptoir - Nova Stock</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
body { background: #f8f9fa; padding: 1rem; }
h3 { color: #0d6efd; }
.form-select, .form-control { border-radius: 8px; }
.form-control[readonly],
.form-control[readonly]:focus {
    background-color: #fff;
    color: #212529;
    opacity: 1;
    cursor: default;
    border-color: #ced4da;
}
.form-control[readonly]:focus {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.25rem rgba(13,110,253,.25);
}
.panier-table { margin-top: 30px; }
.panier-total { font-size: 1.2rem; font-weight: bold; text-align: right; }
@media (max-width:768px){
    .btn-flex { flex:1 1 100%; margin-bottom:0.5rem; }
}

/* Ajoute dans ton <style> */
.alert-success.fade {
    transition: opacity 0.5s ease-out;
}
.alert-success.hide {
    opacity: 0;
    display: none;
}

/* =========================
   RESPONSIVE DESIGN – MOBILE & TABLET
========================= */

/* Global */
@media (max-width: 992px) {
    body {
        padding: 0.5rem;
    }

    h3 {
        font-size: 1.4rem;
    }
}

/* Formulaire */
@media (max-width: 768px) {
    form .col-md-5,
    form .col-md-3,
    form .col-md-2,
    form .col-md-6,
    form .col-12,
    form .col-6 {
        width: 100% !important;
    }

    .form-label {
        font-size: 0.9rem;
    }

    .form-control,
    .form-select {
        font-size: 0.95rem;
        padding: 0.6rem 0.75rem;
    }
}

/* Boutons */
@media (max-width: 768px) {
    .btn-flex {
        width: 100%;
        justify-content: center;
        font-size: 0.95rem;
        padding: 0.65rem;
    }

    .btn-lg {
        width: 100%;
        font-size: 1rem;
    }
}

/* Panier badge */
@media (max-width: 576px) {
    #panierCount {
        font-size: 0.65rem;
        padding: 0.35em 0.45em;
    }
}

/* Alertes */
@media (max-width: 768px) {
    .alert {
        font-size: 0.9rem;
        padding: 0.75rem;
    }

    .alert h5 {
        font-size: 1rem;
    }
}

/* Table responsive (future-proof) */
.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

/* Facture / bouton impression */
@media (max-width: 576px) {
    .btn-primary.btn-lg {
        padding: 0.75rem;
        font-size: 1rem;
    }
}
/* Bouton Imprimer – fond jaune clignotant */
@keyframes clignoter {
    0%, 50%, 100% { background-color: #ffc107; color: #212529; }
    25%, 75% { background-color: #fff700; color: #212529; }
}

.btn-clignotant {
    animation: clignoter 1s infinite;
    border: 1px solid #ffc107;
    font-weight: bold;
}

/* Ajustement responsive */
@media (max-width: 768px) {
    .btn-clignotant {
        width: 100%;
        font-size: 1rem;
        padding: 0.75rem;
    }
}

@media (max-width: 576px) {
    .btn-clignotant {
        font-size: 0.9rem;
        padding: 0.65rem;
    }
}


/* Bouton Imprimer plus petit */
.btn-clignotant {
    animation: clignoter 1s infinite;
    border: 1px solid #ffc107;
    font-weight: bold;
    padding: 0.5rem 0.75rem; /* réduit la hauteur */
    font-size: 0.9rem;       /* texte un peu plus petit */
}

/* Bouton Imprimer responsive - même largeur que bouton Panier sur mobile */
@media (max-width: 768px) {
    #btnImprimer {
        width: 100%;       /* plein largeur */
        display: block;    /* s'assure qu'il s'affiche comme un bloc */
        text-align: center; /* centre le texte et icône */
        font-size: 0.95rem; /* ajuste la taille du texte */
        padding: 0.65rem;   /* ajustement hauteur */
        margin-bottom: 0.5rem; /* espace sous le bouton */
    }
}

.modal input[type="number"] {
    touch-action: manipulation;
}

</style>
</head>
<body>
<div><?php if (!empty($vente_validee_id)): ?>
<a href="facture.php?id=<?= $vente_validee_id ?>" 
   target="_blank"
   id="btnImprimer"
   class="btn btn-primary btn-clignotant btn-lg">
    <i class="bi bi-printer me-2"></i>
</a>
<?php endif; ?>

</div>
<!-- Bouton Panier -->
<div class="text-end mb-3">
    <button class="btn btn-primary position-relative" 
            data-bs-toggle="modal" 
            data-bs-target="#panierModal">
        <i class="bi bi-cart3"></i> Panier
        <span id="panierCount" 
              class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
            <?= array_sum(array_column($panier, 'quantite')) ?? 0 ?>
        </span>
    </button>
</div>

<!-- Modal Panier -->
<div class="modal fade" id="panierModal" tabindex="-1" aria-labelledby="panierModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable modal-fullscreen-sm-down">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="panierModalLabel"><i class="bi bi-cart-check-fill me-2"></i> Panier</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <?php if (!empty($panier)): ?>
        <form id="panierUpdateForm" method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
          <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead>
              <tr>
                <th>Produit</th>
                <th>Quantité</th>
                <th>Prix Unitaire</th>
                <th>Rabais</th>
                <th>Total</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($panier as $item): ?>
              <tr>
                <td><?= htmlspecialchars($item['nom']) ?></td>
                <td>
                  <input type="number" 
                         name="quantite[<?= $item['id'] ?>]" 
                         min="1" 
                         max="<?= $item['quantite'] ?>" 
                         class="form-control form-control-sm w-100" 
                         value="<?= $item['quantite'] ?>">
                </td>
                <td><?= number_format($item['prix_unitaire'], 0, ',', ' ') ?> FCFA</td>
                <td>
                  <input type="number" 
                         name="rabais[<?= $item['id'] ?>]" 
                         min="0" 
                         class="form-control form-control-sm w-100" 
                         value="<?= $item['rabais'] ?>">
                </td>
                <td><?= number_format(($item['prix_unitaire'] * $item['quantite']) - $item['rabais'], 0, ',', ' ') ?> FCFA</td>
                <td>
                  <button type="submit" name="supprimer_panier" value="<?= $item['id'] ?>" class="btn btn-danger btn-sm">
                    <i class="bi bi-trash"></i>
                  </button>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          </div>
          <button type="submit" name="mettre_a_jour" class="btn btn-success w-100 mb-2">
            <i class="bi bi-arrow-repeat me-2"></i> Mettre à jour le panier
          </button>
          <button type="submit" name="valider_vente" class="btn btn-primary w-100">
            <i class="bi bi-check2-circle me-2"></i> Valider la vente
          </button>
        </form>
        <?php else: ?>
          <div class="alert alert-info text-center">Le panier est vide.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Assure-toi que le JS Bootstrap est chargé -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>


<div class="container">
<h3 class="text-center mb-4"><i class="bi bi-cart4 me-2"></i> Vente au comptoir</h3>

<?= $message ?>
<?= $error ?>

<form id="venteForm" method="POST" class="row g-3 mb-5 needs-validation" novalidate>
    <div class="col-md-5 col-12">
        <label class="form-label"><i class="bi bi-box-seam me-2 text-primary"></i> Produit <span class="text-danger">*</span></label>
        <select class="form-select" id="produit_id" name="produit_id" required>
            <option value="">->Sélectionner👇</option>
            <?php foreach($produits as $p): ?>
                <option value="<?= $p['id'] ?>" data-prix="<?= $p['prix_unitaire'] ?>" data-quantite="<?= $p['quantite'] ?>" <?= ($produit_sel == $p['id']) ? 'selected' : '' ?>>
                    ✅<?= htmlspecialchars($p['nom']) ?> (Stock : <?= $p['quantite'] ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-md-2 col-6">
        <label class="form-label"><i class="bi bi-plus-square me-2 text-info"></i>Quantité <span class="text-danger">*</span></label>
        <input type="number" id="quantite" name="quantite" class="form-control" min="1" value="<?= $quantite_val ?>" required>
    </div>

    <div class="col-md-2 col-6">
        <label class="form-label"><i class="bi bi-dash-circle me-2 text-warning"></i>Rabais (FCFA)</label>
        <input type="number" id="rabais" name="rabais" class="form-control" min="0" step="any" value="<?= $rabais_val ?>">
    </div>

    <div class="col-md-3 col-12" style="display:none;">
    <label class="form-label"><i class="bi bi-currency-exchange me-2 text-success"></i>Total TTC</label>
    <input type="text" id="total_ttc" class="form-control fw-bold text-success" readonly>
</div>


    <div class="col-md-6 col-12" style="display:none;">
    <label class="form-label"><i class="bi bi-wallet2 me-2 text-primary"></i>Moyen de paiement</label>
    <select class="form-select" id="paiement" name="paiement">
        <option value="Espèce" selected>💵 Espèce</option>
        <option value="Orange Money">🟧 Orange Money</option>
        <option value="MTN Money">🟨 MTN Money</option>
        <option value="Wave">🟦 Wave</option>
        <option value="Moov Money">🟥 Moov Money</option>
        <option value="Chèque">📝 Chèque</option>
    </select>
</div>


    <div class="col-12 d-flex flex-wrap gap-2">
        <button type="submit" name="ajouter_panier" class="btn btn-success btn-flex"><i class="bi bi-cart-plus me-1"></i> Ajouter au panier</button>

        <a href="../dashboard.php" class="btn btn-outline-secondary btn-flex"><i class="bi bi-arrow-left-circle me-1"></i> Retour</a>


    </div>
</form>

<div class="panier-table mt-5">
<h4 class="mb-3"><i class="bi bi-cart-check-fill me-2"></i> Panier</h4>


<?php if (!empty($panier)): ?>
<div class="table-responsive">
<form id="stockForm" method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="row g-4 needs-validation" novalidate>
    <button type="submit" name="valider_vente" value="1" class="btn btn-primary w-100">
        <i class="bi bi-check2-circle me-2"></i> Valider la vente complète
    </button>
</form>






<?php else: ?>
<div class="alert alert-info text-center">Le panier est vide. Ajoutez des produits ci-dessus.</div>
<?php endif; ?>
</div>




<script>
// Calcul automatique du total
function calculTotal() {
    const produit = document.querySelector('#produit_id');
    const quantite = parseFloat(document.querySelector('#quantite').value || 0);
    const rabais = parseFloat(document.querySelector('#rabais').value || 0);
    if (!produit.value) { document.querySelector('#total_ttc').value = ''; return; }
    const prix = parseFloat(produit.selectedOptions[0].dataset.prix);
    const total = (prix * quantite) - rabais;
    document.querySelector('#total_ttc').value = total > 0 ? total.toLocaleString('fr-FR') + ' FCFA' : '0 FCFA';
}

window.addEventListener('load', calculTotal);
document.querySelector('#produit_id').addEventListener('change', calculTotal);
document.querySelector('#quantite').addEventListener('input', calculTotal);
document.querySelector('#rabais').addEventListener('input', calculTotal);
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const alertSuccess = document.querySelector('.alert-success');
    const btnImprimer = document.querySelector('#btnImprimer');

    // Fonction pour masquer message et bouton
    const hideAlertAndButton = () => {
        if(alertSuccess){
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alertSuccess);
            bsAlert.close();
        }
        if(btnImprimer){
            btnImprimer.style.display = 'none';
        }
    };

    // 1️⃣ Disparition automatique après 2 minutes
    if(alertSuccess){
        setTimeout(hideAlertAndButton, 120000); // 120 000 ms = 2 minutes
    }

    // 2️⃣ Disparition immédiate si l'utilisateur commence une nouvelle saisie
    const inputs = [
        document.querySelector('#quantite'),
        document.querySelector('#rabais'),
        document.querySelector('#produit_id'),
        document.querySelector('#paiement')
    ];

    inputs.forEach(input => {
        if(input){
            input.addEventListener('input', hideAlertAndButton);
            input.addEventListener('change', hideAlertAndButton);
        }
    });
});
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('#venteForm');
    const btnImprimer = document.querySelector('#btnImprimer');

    if(form && btnImprimer){
        // Dès qu'on commence à modifier un champ du formulaire, on cache le bouton Imprimer
        form.addEventListener('input', () => {
            btnImprimer.style.display = 'none';
        });
        form.addEventListener('change', () => {
            btnImprimer.style.display = 'none';
        });
    }
});
</script>

</script>

</body>
</html>
