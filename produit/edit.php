<?php
session_start();
require_once __DIR__ . "/../includes/db.php";

// Génération du token CSRF si non existant
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Classe pour gérer l'édition des produits
class ProductEditor {
    private $connexion;
    private $erreurs = [];
    private $success = false;
    private $message = '';
    private $produit = [];
    
    public function __construct($connexion) {
        $this->connexion = $connexion;
        $this->validateAccess();
    }
    
    private function validateAccess() {
        if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
            $this->erreurs[] = "Vous devez être connecté pour accéder à cette page.";
        } elseif ($_SESSION['user']['role'] !== 'Admin') {
            $this->erreurs[] = "Accès réservé aux administrateurs.";
        }
    }
    
    public function loadProduct($id) {
        if ($id <= 0) {
            $this->erreurs[] = "ID produit invalide.";
            return false;
        }
        
        try {
            $stmt = $this->connexion->prepare("SELECT * FROM produit WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $this->produit = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$this->produit) {
                $this->erreurs[] = "Produit introuvable.";
                return false;
            }
            return true;
        } catch (PDOException $e) {
            $this->addError("Erreur lecture produit : " . htmlspecialchars($e->getMessage()));
            return false;
        }
    }
    
    public function processForm($id) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !empty($this->erreurs)) {
            return false;
        }
        
        // Vérification CSRF
        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
            $this->erreurs[] = "Token de sécurité invalide. Veuillez réessayer.";
            return false;
        }
        
        $data = $this->validateInput();
        if (!empty($this->erreurs)) {
            return false;
        }
        
        if ($this->checkCodeUniqueness($data['code'], $id)) {
            return false;
        }
        
        if ($this->updateProduct($data, $id)) {
            $this->success = true;
            $this->message = "Produit modifié avec succès !";
            // Recharger les données pour affichage à jour
            $this->loadProduct($id);
            return true;
        }
        
        return false;
    }
    
    private function validateInput() {
        $data = [
            'code' => trim($_POST['code'] ?? ''),
            'nom' => trim($_POST['nom'] ?? ''),
            'categorie' => trim($_POST['categorie'] ?? 'Non classé'),
            'description' => trim($_POST['description'] ?? ''),
            'fournisseur' => trim($_POST['fournisseur'] ?? ''),
            'prix_achat' => $this->parsePrice($_POST['prix_achat'] ?? '0'),
            'prix_unitaire' => $this->parsePrice($_POST['prix_unitaire'] ?? '0')
        ];
        
        // Validations
        if (empty($data['nom'])) {
            $this->erreurs[] = "Le nom du produit est obligatoire.";
        }
        if (empty($data['code'])) {
            $this->erreurs[] = "Le code produit est obligatoire.";
        }
        if (strlen($data['code']) > 50) {
            $this->erreurs[] = "Le code produit ne peut pas dépasser 50 caractères.";
        }
        if (strlen($data['nom']) > 255) {
            $this->erreurs[] = "Le nom du produit ne peut pas dépasser 255 caractères.";
        }
        if ($data['prix_achat'] < 0) {
            $this->erreurs[] = "Le prix d'achat ne peut pas être négatif.";
        }
        if ($data['prix_unitaire'] <= 0) {
            $this->erreurs[] = "Le prix de vente doit être supérieur à 0.";
        }
        if ($data['prix_unitaire'] < $data['prix_achat']) {
            $this->erreurs[] = "Le prix de vente devrait être supérieur au prix d'achat.";
        }
        if ($data['prix_achat'] > 999999999 || $data['prix_unitaire'] > 999999999) {
            $this->erreurs[] = "Les prix ne peuvent pas dépasser 999 millions FCFA.";
        }
        
        // Nettoyage de la description (limite 1000 caractères)
        if (strlen($data['description']) > 1000) {
            $data['description'] = substr($data['description'], 0, 1000);
        }
        
        return $data;
    }
    
    private function parsePrice($priceStr) {
        $priceStr = trim($priceStr);
        if (empty($priceStr)) {
            return 0.0;
        }
        
        // Supprimer les espaces et remplacer la virgule par le point pour le décimal
        $cleaned = str_replace(' ', '', $priceStr);
        $cleaned = str_replace(',', '.', $cleaned);
        
        // Valider si c'est un nombre valide
        if (!is_numeric($cleaned)) {
            $this->erreurs[] = "Format de prix invalide. Utilisez le format 1 234,56";
            return 0.0;
        }
        
        $price = (float) $cleaned;
        
        // Arrondir à 2 décimales
        return round($price, 2);
    }
    
    private function checkCodeUniqueness($code, $id) {
        try {
            $stmt = $this->connexion->prepare("SELECT id FROM produit WHERE code = :code AND id != :id");
            $stmt->execute([':code' => $code, ':id' => $id]);
            if ($stmt->fetch()) {
                $this->erreurs[] = "Ce code existe déjà pour un autre produit.";
                return true;
            }
        } catch (PDOException $e) {
            $this->addError("Erreur vérification code : " . htmlspecialchars($e->getMessage()));
            return true;
        }
        return false;
    }
    
    private function updateProduct($data, $id) {
        try {
            $stmt = $this->connexion->prepare("
                UPDATE produit SET
                    code = :code,
                    nom = :nom,
                    categorie = :categorie,
                    description = :description,
                    prix_achat = :prix_achat,
                    prix_unitaire = :prix_unitaire,
                    fournisseur = :fournisseur,
                    updated_at = NOW()
                WHERE id = :id
            ");
            
            $params = array_merge($data, [':id' => $id]);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            $this->addError("Erreur lors de la mise à jour : " . htmlspecialchars($e->getMessage()));
            return false;
        }
    }
    
    private function addError($message) {
        $this->erreurs[] = $message;
        // Log pour debug (optionnel)
        error_log(date('Y-m-d H:i:s') . " - Product Editor: " . $message);
    }
    
    // Getters
    public function getErreurs() { return $this->erreurs; }
    public function isSuccess() { return $this->success; }
    public function getMessage() { return $this->message; }
    public function getProduit() { return $this->produit; }
    public function getCsrfToken() { return $_SESSION['csrf_token']; }
}

// Initialisation
$editor = new ProductEditor($connexion);

// Récupération et traitement
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editor->loadProduct($id);
$editor->processForm($id);

// Debug temporaire (à supprimer en production)
// error_reporting(E_ALL);
// ini_set('display_errors', 1);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier Produit - Nova</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { 
            background: #f8f9fa; 
            font-family: system-ui, sans-serif; 
            padding: 1.5rem; 
        }
        h2 { 
            color: #0d6efd; 
        }
        .form-control { 
            border-radius: 6px; 
        }
        .input-group-text { 
            background: #e9ecef; 
        }
        .alert { 
            border-radius: 6px; 
        }
        .price-input {
            font-family: monospace;
        }
        .form-label {
            font-weight: 500;
        }
        .required::after {
            content: " *";
            color: #dc3545;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h2 class="my-4 text-center">✏️ Modifier le produit</h2>
            
            <?php if ($editor->isSuccess()): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <strong>Succès !</strong> <?= htmlspecialchars($editor->getMessage()) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($editor->getErreurs())): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <strong>Erreur<?= count($editor->getErreurs()) > 1 ? 's' : '' ?> :</strong>
                <ul class="mb-0 ps-3 mt-2">
                    <?php foreach ($editor->getErreurs() as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($editor->getProduit())): ?>
            <form method="post" id="edit-product-form">

                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($editor->getCsrfToken()) ?>">
                <input type="hidden" name="id" value="<?= $id ?>">
                
                <div class="col-md-6">
                    <label for="code" class="form-label required">Code produit</label>
                    <input type="text" 
                           class="form-control" 
                           id="code" 
                           name="code" 
                           required 
                           maxlength="50"
                           value="<?= htmlspecialchars($editor->getProduit()['code'] ?? '') ?>"
                           pattern="[A-Za-z0-9\-_]{1,50}">
                    <div class="invalid-feedback">
                        Le code produit est obligatoire (max 50 caractères, lettres, chiffres, tirets, underscores)
                    </div>
                    <div class="form-text">Ex: PROD001, FER-123</div>
                </div>
                
                <div class="col-md-6">
                    <label for="nom" class="form-label required">Nom du produit</label>
                    <input type="text" 
                           class="form-control" 
                           id="nom" 
                           name="nom" 
                           required 
                           maxlength="255"
                           value="<?= htmlspecialchars($editor->getProduit()['nom'] ?? '') ?>">
                    <div class="invalid-feedback">Le nom du produit est obligatoire (max 255 caractères)</div>
                </div>
                
                <div class="col-md-6">
                    <label for="categorie" class="form-label">Catégorie</label>
                    <input type="text" 
                           class="form-control" 
                           id="categorie" 
                           name="categorie"
                           value="<?= htmlspecialchars($editor->getProduit()['categorie'] ?? 'Non classé') ?>"
                           list="cat-list"
                           maxlength="100">
                    <datalist id="cat-list">
                        <option value="Fer">
                        <option value="Ciment">
                        <option value="Peinture">
                        <option value="Électricité">
                        <option value="Vis et quincaillerie">
                        <option value="Outils">
                        <option value="Autres">
                    </datalist>
                    <div class="form-text">Sélectionnez ou tapez une nouvelle catégorie</div>
                </div>
                
                <div class="col-md-6">
                    <label for="fournisseur" class="form-label">Fournisseur</label>
                    <input type="text" 
                           class="form-control" 
                           id="fournisseur" 
                           name="fournisseur"
                           value="<?= htmlspecialchars($editor->getProduit()['fournisseur'] ?? '') ?>"
                           maxlength="255">
                    <div class="form-text">Nom du fournisseur (optionnel)</div>
                </div>
                
                <div class="col-md-6">
                    <label for="prix_achat" class="form-label">Prix d'achat (FCFA)</label>
                    <div class="input-group">
                        <span class="input-group-text">FCFA</span>
                        <input type="text" 
                               inputmode="decimal" 
                               class="form-control text-end price-input" 
                               name="prix_achat"
                               value="<?= number_format($editor->getProduit()['prix_achat'] ?? 0, 2, ',', ' ') ?>"
                               pattern="^\d{1,3}( \d{3})*(,\d{2})?$"
                               title="Format: 1 234,56">
                    </div>
                    <div class="form-text">Prix d'achat hors TVA</div>
                </div>
                
                <div class="col-md-6">
                    <label for="prix_unitaire" class="form-label required">Prix de vente (FCFA)</label>
                    <div class="input-group">
                        <span class="input-group-text">FCFA</span>
                        <input type="text" 
                               inputmode="decimal" 
                               class="form-control text-end price-input" 
                               name="prix_unitaire" 
                               required
                               value="<?= number_format($editor->getProduit()['prix_unitaire'] ?? 0, 2, ',', ' ') ?>"
                               pattern="^\d{1,3}( \d{3})*(,\d{2})?$"
                               title="Format: 1 234,56">
                    </div>
                    <div class="invalid-feedback">Le prix de vente est obligatoire et doit être > 0</div>
                    <div class="form-text">Prix de vente unitaire TTC</div>
                </div>
                
                <div class="col-12">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" 
                              id="description" 
                              name="description" 
                              rows="4" 
                              maxlength="1000"
                              placeholder="Description détaillée du produit..."><?= htmlspecialchars($editor->getProduit()['description'] ?? '') ?></textarea>
                    <div class="form-text">Maximum 1000 caractères (optionnel)</div>
                </div>
                
                <div class="col-12 mt-4">
                    <div class="d-grid d-md-flex gap-2 justify-content-md-start">
                        <button type="submit" class="btn btn-primary px-4 py-2" id="btnSave">
                            <i class="bi bi-save me-2"></i> Enregistrer les modifications
                        </button>
                        <a href="list.php" class="btn btn-outline-secondary px-4 py-2">
                            <i class="bi bi-arrow-left me-2"></i> Retour à la liste
                        </a>
                    </div>
                </div>
            </form>
            <?php else: ?>
            <div class="alert alert-warning text-center">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong>Produit non trouvé</strong><br>
                <small>L'ID du produit est invalide ou le produit n'existe plus.</small>
                <hr>
                <a href="list.php" class="btn btn-outline-primary">
                    <i class="bi bi-list-ul me-2"></i>Retour à la liste des produits
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const btnSave = document.getElementById('btnSave');
    
    if (!form) return;
    
    // Fonction pour formater les prix
    function formatPrice(input) {
        let value = input.value.replace(/\s/g, '').replace(',', '.');
        if (value === '' || isNaN(value)) {
            input.value = '';
            return;
        }
        
        let numValue = parseFloat(value);
        if (isNaN(numValue)) return;
        
        // Formater au format français
        input.value = numValue.toLocaleString('fr-FR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
    
    // Écouteurs pour les champs de prix
    const priceInputs = form.querySelectorAll('.price-input');
    priceInputs.forEach(input => {
        // Au focus : nettoyer le format
        input.addEventListener('focus', function() {
            let value = this.value.replace(/\s/g, '').replace(',', '.');
            if (value && !isNaN(value)) {
                this.value = parseFloat(value).toFixed(2);
            }
        });
        
        // Au blur : formater
        input.addEventListener('blur', function() {
            formatPrice(this);
            
            // Validation comparaison prix pour prix_unitaire
            if (this.name === 'prix_unitaire') {
                const prixAchatInput = form.querySelector('input[name="prix_achat"]');
                if (prixAchatInput) {
                    let prixAchat = parseFloat(prixAchatInput.value.replace(/\s/g, '').replace(',', '.'));
                    let prixUnitaire = parseFloat(this.value.replace(/\s/g, '').replace(',', '.'));
                    
                    if (!isNaN(prixAchat) && !isNaN(prixUnitaire) && prixUnitaire < prixAchat) {
                        this.setCustomValidity('Le prix de vente doit être supérieur au prix d\'achat');
                        this.classList.add('is-invalid');
                    } else {
                        this.setCustomValidity('');
                        this.classList.remove('is-invalid');
                    }
                }
            }
        });
        
        // Limitation aux chiffres, virgules et points
        input.addEventListener('input', function(e) {
            let value = this.value;
            // Autoriser seulement chiffres, espaces, virgule et point
            this.value = value.replace(/[^0-9,\s.]/g, '');
        });
    });
    
    // Validation du formulaire
    form.addEventListener('submit', function(e) {
        // Validation HTML5
        if (!form.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }
        
        // Validation personnalisée prix
        const prixUnitaireInput = form.querySelector('input[name="prix_unitaire"]');
        const prixAchatInput = form.querySelector('input[name="prix_achat"]');
        
        if (prixUnitaireInput && prixAchatInput) {
            let prixAchat = parseFloat(prixAchatInput.value.replace(/\s/g, '').replace(',', '.'));
            let prixUnitaire = parseFloat(prixUnitaireInput.value.replace(/\s/g, '').replace(',', '.'));
            
            if (!isNaN(prixAchat) && !isNaN(prixUnitaire) && prixUnitaire <= prixAchat) {
                e.preventDefault();
                prixUnitaireInput.setCustomValidity('Le prix de vente doit être supérieur au prix d\'achat');
                prixUnitaireInput.reportValidity();
                return false;
            }
        }
        
        // Désactiver le bouton et afficher le loading
        if (btnSave) {
            btnSave.disabled = true;
            btnSave.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Enregistrement en cours...';
        }
        
        form.classList.add('was-validated');
        
        return true;
    });
    
    // Compteur de caractères pour la description
    const descriptionTextarea = form.querySelector('#description');
    if (descriptionTextarea) {
        const maxLength = 1000;
        const counter = document.createElement('div');
        counter.className = 'form-text text-muted small mt-1';
        counter.id = 'charCounter';
        counter.textContent = `0 / ${maxLength} caractères`;
        descriptionTextarea.parentNode.appendChild(counter);
        
        function updateCounter() {
            const length = descriptionTextarea.value.length;
            counter.textContent = `${length} / ${maxLength} caractères`;
            if (length > maxLength * 0.9) {
                counter.className = 'form-text text-warning small mt-1';
            } else {
                counter.className = 'form-text text-muted small mt-1';
            }
        }
        
        descriptionTextarea.addEventListener('input', updateCounter);
        updateCounter();
    }
});
</script>
</body>
</html>