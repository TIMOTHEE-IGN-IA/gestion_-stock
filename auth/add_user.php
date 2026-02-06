<?php
session_start();
require_once __DIR__ . "/../includes/db.php";

// Vérifie si l'utilisateur connecté est Admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'Admin') {
    die("❌ Accès refusé. Seul un Admin peut ajouter des utilisateurs.");
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = $_POST['nom'] ?? '';
    $email = $_POST['email'] ?? '';
    $mot_de_passe = $_POST['mot_de_passe'] ?? '';
    $role = $_POST['role'] ?? '';

    if ($nom && $email && $mot_de_passe && $role) {
        $stmt = $connexion->prepare(
            "INSERT INTO utilisateur (nom, email, mot_de_passe, role) VALUES (?, ?, MD5(?), ?)"
        );
        $stmt->execute([$nom, $email, $mot_de_passe, $role]);
        $success = "Utilisateur ajouté avec succès !";
    } else {
        $error = "Tous les champs sont obligatoires.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Ajouter un utilisateur</title>
</head>
<body>

<h2>➕ Ajouter un utilisateur</h2>

<?php if(isset($success)) echo "<p style='color:green;'>$success</p>"; ?>
<?php if(isset($error)) echo "<p style='color:red;'>$error</p>"; ?>

<form method="POST">
    <input type="text" name="nom" placeholder="Nom complet" required><br><br>
    <input type="email" name="email" placeholder="Email" required><br><br>
    <input type="password" name="mot_de_passe" placeholder="Mot de passe" required><br><br>
    <select name="role" required>
        <option value="">-- Sélectionner le rôle --</option>
        <option value="Admin">Admin</option>
        <option value="Employe">Employé</option>
    </select><br><br>
    <button type="submit">Ajouter utilisateur</button>
</form>

<br>
<a href="../dashboard.php">⬅ Retour dashboard</a>

</body>
</html>
