<?php
session_start();
require_once __DIR__ . "/../includes/db.php";
// Vérification utilisateur
if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Accès refusé']);
    exit;
}
$user_id = $_SESSION['user']['id'];
// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
date_default_timezone_set('Africa/Abidjan');
// ────────────────────────────────
// POST AJAX - Ajout crédit
// ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    // Vérification CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Jeton invalide']);
        exit;
    }
    $client = trim($_POST['client'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $libelle = trim($_POST['libelle'] ?? 'Crédit standard');
    $montant = floatval($_POST['montant'] ?? 0);
    $date_paiement = !empty($_POST['date_paiement']) ? $_POST['date_paiement'] : null;
    if (empty($client) || $montant <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Client et montant obligatoires']);
        exit;
    }
    try {
        $connexion->beginTransaction();
        // ✅ INSERT avec user_id
        $stmt = $connexion->prepare("
            INSERT INTO credits
            (user_id, client, telephone, libelle, montant, reste, date_credit, date_paiement_prevu, statut)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, 'actif')
        ");
        $stmt->execute([$user_id, $client, $telephone, $libelle, $montant, $montant, $date_paiement]);
        $newId = $connexion->lastInsertId();
        $connexion->commit();
        echo json_encode([
            'status' => 'success',
            'message' => 'Crédit ajouté',
            'id' => $newId,
            'client' => htmlspecialchars($client),
            'telephone' => htmlspecialchars($telephone),
            'libelle' => $libelle,
            'montant' => $montant,
            'date_credit' => date('Y-m-d'),
            'date_paiement_prevu' => $date_paiement
        ]);
    } catch (Exception $e) {
        $connexion->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Erreur : ' . $e->getMessage()]);
    }
    exit;
}
// ────────────────────────────────
// GET - Formulaire d’ajout crédit
// ────────────────────────────────
?>
<div class="container py-4">
    <h2 class="text-center mb-4">
        <i class="bi bi-plus-circle-fill text-primary me-2"></i>
        Ajouter un crédit
    </h2>
    <div id="message" class="mb-3"></div>
    <form id="creditForm" class="row g-3 mb-4">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <div class="col-md-4">
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                <input type="text" name="client" class="form-control" placeholder="Nom du client *" required autofocus>
            </div>
        </div>
        <div class="col-md-3">
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-telephone-fill"></i></span>
                <input type="text" name="telephone" class="form-control" placeholder="Téléphone">
            </div>
        </div>
        <div class="col-md-3">
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-tag-fill"></i></span>
                <input type="text" name="libelle" class="form-control" placeholder="Libellé">
            </div>
        </div>
        <div class="col-md-2">
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-currency-exchange"></i></span>
                <input type="number" name="montant" class="form-control" placeholder="Montant *" min="1" step="1" required>
            </div>
        </div>
        <div class="col-md-3">
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-calendar-date-fill"></i></span>
                <input type="date" name="date_paiement" class="form-control" title="Date prévue de paiement (optionnel)">
            </div>
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary w-100" id="btnSubmit">
                <i class="bi bi-save me-2"></i>Ajouter
            </button>
        </div>
    </form>
    <div id="reloadSpinner"
         class="position-fixed top-50 start-50 translate-middle bg-white bg-opacity-75 p-4 rounded shadow-lg"
         style="display:none; z-index:9999; min-width: 250px; text-align:center;">
        <div class="spinner-border text-primary mb-3" style="width: 5rem; height: 5rem;"></div>
        <div class="fs-5 fw-bold text-primary">Enregistrement en cours...</div>
        <div class="text-muted mt-2">Veuillez patienter</div>
    </div>
</div>
<script>
document.getElementById('creditForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const form = this;
    const btn = document.getElementById('btnSubmit');
    const message = document.getElementById('message');
    const spinner = document.getElementById('reloadSpinner');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enregistrement...';
    try {
        const res = await fetch('produit/credit.php', {
            method: 'POST',
            body: new FormData(form)
        }).then(r => r.json());
        if (res.status === 'success') {
            message.innerHTML = '<div class="alert alert-success">Crédit ajouté avec succès !</div>';
            spinner.style.display = 'block';
            setTimeout(() => location.reload(), 1500);
        } else {
            message.innerHTML = `<div class="alert alert-danger">${res.message}</div>`;
        }
    } catch (err) {
        message.innerHTML = '<div class="alert alert-danger">Erreur réseau. Réessayez.</div>';
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-save me-2"></i>Ajouter';
    }
});
</script>