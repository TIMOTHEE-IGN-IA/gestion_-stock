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
// Fonction pour initialiser le formulaire AJAX
function initHistoriqueForm() {
    const form = document.getElementById('historiqueForm');
    if (!form) return;

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(form);
        const url = new URL(window.location.href);

        // Mettre à jour les paramètres de l'URL actuelle
        for (const [key, value] of formData.entries()) {
            if (value) {
                url.searchParams.set(key, value);
            } else {
                url.searchParams.delete(key);
            }
        }

        // Charger la nouvelle version
        fetch(url)
            .then(response => {
                if (!response.ok) throw new Error('Erreur réseau : ' + response.status);
                return response.text();
            })
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newContainer = doc.querySelector('#historiqueContainer');

                if (newContainer) {
                    const oldContainer = document.getElementById('historiqueContainer');
                    if (oldContainer) {
                        oldContainer.replaceWith(newContainer);
                    }
                    // Ré-attacher le listener sur le nouveau formulaire
                    initHistoriqueForm();
                } else {
                    console.error("Conteneur #historiqueContainer non trouvé dans la réponse");
                }
            })
            .catch(err => {
                console.error('Erreur AJAX :', err);
                alert('Une erreur est survenue lors du filtrage. Veuillez réessayer.');
            });
    });
}

// Lancer au chargement de la page
document.addEventListener('DOMContentLoaded', initHistoriqueForm);
</script>
