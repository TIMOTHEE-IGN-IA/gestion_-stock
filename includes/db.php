<?php
// Paramètres de connexion à la base de données
$host = "sql213.infinityfree.com";       // host fourni par InfinityFree
$dbname = "if0_40715639_stock_app";      // nom exact de la base
$username = "if0_40715639";    // utilisateur exact
$password = "Yaoko2025Immo";             // mot de passe fourni

try {
    $connexion = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("❌ Erreur de connexion à la base de données : " . $e->getMessage());
}
