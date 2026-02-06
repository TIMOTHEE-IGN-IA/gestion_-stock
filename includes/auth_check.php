<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']) || empty($_SESSION['user']['id'])) {
    header("Location: ../index.php");
    exit;
}

$user = $_SESSION['user'];
$user_id   = $user['id'];
$role      = $user['role'];
$admin_id  = ($role === 'Employe') ? $user['admin_parent_id'] ?? 0 : $user_id;

// Fonction pratique pour vérifier si l'enregistrement appartient à l'utilisateur ou à son admin
function appartient_a_moi_ou_mon_admin($record_user_id, $current_user_id, $current_role, $current_admin_id) {
    if ($current_role === 'Admin') {
        return $record_user_id == $current_user_id;
    }
    // Employé → doit appartenir à son admin
    return $record_user_id == $current_admin_id;
}
?>