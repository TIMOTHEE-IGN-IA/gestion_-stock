<?php
session_start();
require_once __DIR__ . "/../includes/db.php";

// Toujours répondre en JSON
header('Content-Type: application/json; charset=utf-8');

$response = [
    'success' => false,
    'message' => 'Erreur inconnue'
];

http_response_code(400); // par défaut

// 1. Vérifications de sécurité
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'Admin') {
    http_response_code(403);
    $response['message'] = 'Accès réservé aux administrateurs';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $response['message'] = 'Méthode non autorisée – POST requis';
    echo json_encode($response);
    exit;
}

// 2. Récupération sécurisée de l’ID (depuis POST de préférence)
$id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);

if ($id <= 0) {
    $response['message'] = 'ID produit invalide';
    echo json_encode($response);
    exit;
}

try {
    $connexion->beginTransaction();

    // ────────────────────────────────────────────────
    // Option A : Vérifier si le produit existe
    // ────────────────────────────────────────────────
    $stmt = $connexion->prepare("SELECT id, nom FROM produit WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $produit = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$produit) {
        $response['message'] = 'Produit introuvable ou déjà supprimé';
        echo json_encode($response);
        $connexion->rollBack();
        exit;
    }

    // ────────────────────────────────────────────────
    // Option B : Vérifier s’il y a des dépendances (ventes, mouvements, etc.)
    // ────────────────────────────────────────────────
    // Exemple : vérifier s’il y a des ventes associées
    $stmt = $connexion->prepare("SELECT COUNT(*) FROM vente WHERE produit_id = :id");
    $stmt->execute([':id' => $id]);
    $ventesCount = (int) $stmt->fetchColumn();

    if ($ventesCount > 0) {
        $response['message'] = 'Impossible de supprimer : ce produit a déjà été vendu (' . $ventesCount . ' vente(s))';
        echo json_encode($response);
        $connexion->rollBack();
        exit;
    }

    // Si tu as une table historique_stock, tu peux aussi vérifier ici...

    // ────────────────────────────────────────────────
    // Suppression effective
    // ────────────────────────────────────────────────
    $stmt = $connexion->prepare("DELETE FROM produit WHERE id = :id");
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() === 0) {
        $response['message'] = 'Aucune ligne supprimée (produit introuvable)';
    } else {
        $response = [
            'success' => true,
            'message' => 'Produit « ' . htmlspecialchars($produit['nom'] ?? '—') . ' » supprimé avec succès',
            'deleted_id' => $id
        ];
        http_response_code(200);
    }

    $connexion->commit();

} catch (PDOException $e) {
    $connexion->rollBack();

    // Log détaillé (ne jamais montrer les détails techniques à l’utilisateur)
    error_log("[" . date('Y-m-d H:i:s') . "] Erreur suppression produit ID $id : " . $e->getMessage());

    http_response_code(500);
    $response['message'] = 'Erreur serveur lors de la suppression. Contactez l’administrateur.';
}

echo json_encode($response);
exit;