<?php
session_start();
require_once __DIR__ . "/../includes/db.php";

$nom = trim($_POST['nom'] ?? '');
$password = trim($_POST['password'] ?? '');

if (empty($nom) || empty($password)) {
    header("Location: ../index.php?error=1");
    exit;
}

$sql = "SELECT * FROM utilisateur WHERE nom = ? AND mot_de_passe = ?";
$stmt = $connexion->prepare($sql);
$stmt->execute([$nom, $password]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    $_SESSION['user'] = [
        'id'   => $user['id'],
        'nom'  => $user['nom'],
        'role' => $user['role']
    ];

    header("Location: ../dashboard.php");
    exit;
}

header("Location: ../index.php?error=1");
exit;
