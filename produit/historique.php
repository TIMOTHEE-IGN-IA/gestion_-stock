<?php
session_start();
require_once __DIR__ . "/../includes/db.php";

if (!isset($_SESSION['user']['id'])) {
    echo "<div class='alert alert-danger'>Accès refusé.</div>";
    exit;
}

$user_id = (int) $_SESSION['user']['id'];
$role = $_SESSION['user']['role'];

// ================= FILTRE ADMIN / EMPLOYÉS =================
$allowed_users = [];

if ($role === 'Admin') {
    // Admin lui-même
    $allowed_users[] = $user_id;

    // Ses employés
    $stmt = $connexion->prepare("SELECT id FROM utilisateur WHERE admin_parent_id = ?");
    $stmt->execute([$user_id]);
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $allowed_users[] = (int)$r['id'];
    }
} else {
    // Employé → seulement lui
    $allowed_users[] = $user_id;
}

if (empty($allowed_users)) {
    $allowed_users = [0]; // Sécurité
}

$in_clause = implode(',', array_fill(0, count($allowed_users), '?'));

// ================= FILTRES =================
$produit_id = $_GET['produit_id'] ?? '';
$type = $_GET['type'] ?? '';
$date_debut = $_GET['date_debut'] ?? '';
$date_fin = $_GET['date_fin'] ?? '';

// Récupérer tous les produits pour le filtre
$produits = $connexion->query("SELECT id, nom FROM produit ORDER BY nom ASC")->fetchAll();

// Construire la requête SQL avec sécurité
$sql = "SELECT h.id, p.code, p.nom, h.type, h.quantite, h.date, u.nom AS utilisateur
        FROM historique_stock h
        JOIN produit p ON h.produit_id = p.id
        JOIN utilisateur u ON h.utilisateur_id = u.id
        WHERE h.utilisateur_id IN ($in_clause)";
$params = $allowed_users;

// Appliquer les filtres supplémentaires
if ($produit_id) { $sql .= " AND p.id=?"; $params[] = $produit_id; }
if ($type) { $sql .= " AND h.type=?"; $params[] = $type; }
if ($date_debut) { $sql .= " AND DATE(h.date) >= ?"; $params[] = $date_debut; }
if ($date_fin) { $sql .= " AND DATE(h.date) <= ?"; $params[] = $date_fin; }

$sql .= " ORDER BY h.date DESC";

$stmt = $connexion->prepare($sql);
$stmt->execute($params);
$historiques = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div id="historiqueContainer">

    <h3>📦 Historique des mouvements</h3>

    <!-- Formulaire de filtre -->
    <form id="historiqueForm" class="row g-3 mb-4">
        <div class="col-md-3">
            <select name="produit_id" class="form-select">
                <option value="">-- Tous les produits --</option>
                <?php foreach ($produits as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $produit_id == $p['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['nom']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-2">
            <select name="type" class="form-select">
                <option value="">-- Tous --</option>
                <option value="Entree" <?= $type === 'Entree' ? 'selected' : '' ?>>Entrée</option>
                <option value="Sortie" <?= $type === 'Sortie' ? 'selected' : '' ?>>Sortie</option>
            </select>
        </div>

        <div class="col-md-2">
            <input type="date" name="date_debut" value="<?= $date_debut ?>" class="form-control">
        </div>

        <div class="col-md-2">
            <input type="date" name="date_fin" value="<?= $date_fin ?>" class="form-control">
        </div>

        <div class="col-md-3">
            <button type="submit" class="btn btn-primary w-100">Filtrer</button>
        </div>
    </form>

    <!-- Tableau -->
    <div class="table-responsive">
        <table class="table table-striped table-bordered" id="historiqueTable">
            <thead class="table-dark">
                <tr>
                    <th>Code</th>
                    <th>Produit</th>
                    <th>Type</th>
                    <th>Qté</th>
                    <th>Utilisateur</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($historiques as $h): ?>
                    <tr>
                        <td><?= htmlspecialchars($h['code']) ?></td>
                        <td><?= htmlspecialchars($h['nom']) ?></td>
                        <td>
                            <span class="badge <?= $h['type'] == 'Entree' ? 'bg-success' : 'bg-danger' ?>">
                                <?= $h['type'] ?>
                            </span>
                        </td>
                        <td><?= $h['quantite'] ?></td>
                        <td><?= htmlspecialchars($h['utilisateur']) ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($h['date'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Fonction pour filtrer via AJAX
function initHistoriqueForm() {
    const form = document.getElementById('historiqueForm');
    if (!form) return;

    form.addEventListener('submit', function(e){
        e.preventDefault();

        const formData = new FormData(form);
        const params = new URLSearchParams();
        for(const [k,v] of formData.entries()) if(v) params.append(k,v);

        fetch('produit/historique.php?' + params.toString())
            .then(res => res.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newContainer = doc.querySelector('#historiqueContainer');
                const oldContainer = document.getElementById('historiqueContainer');
                if(newContainer && oldContainer) oldContainer.replaceWith(newContainer);

                // Réinitialiser le formulaire AJAX sur le nouveau DOM
                initHistoriqueForm();
            })
            .catch(err => console.error(err));
    });
}

// Initialiser si page chargée directement
initHistoriqueForm();
</script>
