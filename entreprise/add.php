<?php
// Démarrer la session si elle n'est pas déjà active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../includes/db.php";

// Protection : Admin ou Controle uniquement
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['Admin', 'Controle'])) {
    header("Location: ../login.php");
    exit("Accès refusé");
}

// ID de l'utilisateur connecté (Admin ou Controle)
$admin_id = $_SESSION['user']['id'];

$message = "";
$nom = $email = $telephone = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom       = trim($_POST['nom'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');

    // Validation
    if (empty($nom)) {
        $message = '<div class="alert alert-danger">Le nom de l’entreprise est obligatoire.</div>';
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = '<div class="alert alert-danger">L’adresse email n’est pas valide.</div>';
    } else {
        try {
            $sql = "INSERT INTO entreprise 
                    (nom, email, telephone, admin_id, created_at) 
                    VALUES (?, ?, ?, ?, NOW())";

            $stmt = $connexion->prepare($sql);
            $stmt->execute([$nom, $email, $telephone, $admin_id]);

            // Succès → redirection vers la liste
            $_SESSION['success_message'] = "Entreprise ajoutée avec succès !";
            header("Location: liste.php");
            exit;
        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger">Erreur lors de l’ajout : ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter une Entreprise - Nova</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .form-container { max-width: 600px; margin: 5rem auto; }
        .card-header { background: #0d6efd; color: white; font-weight: bold; }
    </style>
</head>
<body class="bg-light">

<div class="container form-container">
    <div class="card shadow-lg">
        <div class="card-header text-center py-3">
            <h4 class="mb-0">Ajouter une nouvelle entreprise</h4>
        </div>
        <div class="card-body p-4">

            <?php if (!empty($message)): ?>
                <?= $message ?>
            <?php endif; ?>

            <form method="POST" novalidate>
                <div class="mb-4">
                    <label for="nom" class="form-label fw-bold">Nom de l’entreprise <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-lg" id="nom" name="nom" 
                           value="<?= htmlspecialchars($nom) ?>" required autofocus>
                    <div class="invalid-feedback">Ce champ est obligatoire.</div>
                </div>

                <div class="mb-4">
                    <label for="email" class="form-label fw-bold">Email</label>
                    <input type="email" class="form-control form-control-lg" id="email" name="email" 
                           value="<?= htmlspecialchars($email) ?>" placeholder="exemple@domaine.com">
                </div>

                <div class="mb-4">
                    <label for="telephone" class="form-label fw-bold">Téléphone</label>
                    <input type="tel" class="form-control form-control-lg" id="telephone" name="telephone" 
                           value="<?= htmlspecialchars($telephone) ?>" placeholder="07 XX XX XX XX">
                </div>

                <div class="d-grid gap-2 mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">Ajouter l’entreprise</button>
                </div>
            </form>

            <div class="text-center mt-4">
                <a href="liste.php" class="text-muted">← Retour à la liste de mes entreprises</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Validation Bootstrap côté client
(function () {
    'use strict';
    const form = document.querySelector('form');
    form.addEventListener('submit', function (event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        form.classList.add('was-validated');
    }, false);
})();
</script>
</body>
</html>