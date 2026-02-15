<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../includes/db.php";

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'Controle') {
    exit("Accès refusé");
}

$BASE_MONTANT = 5000;

$formatter = new IntlDateFormatter(
    'fr_FR',
    IntlDateFormatter::LONG,
    IntlDateFormatter::NONE,
    'Africa/Abidjan'
);
$current_month_fr = ucfirst($formatter->format(time()));

// Récup admins
$stmt = $connexion->prepare("
    SELECT id, nom, photo, whatsapp 
    FROM utilisateur 
    WHERE role = 'Admin' 
    ORDER BY nom ASC
");
$stmt->execute();
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

$admins_data = [];
$total_montant = 0;

foreach ($admins as $admin) {
    $stmt = $connexion->prepare("SELECT COUNT(*) FROM entreprise WHERE admin_id = ?");
    $stmt->execute([$admin['id']]);
    $nb_entreprises = (int)$stmt->fetchColumn();

    $montant = $BASE_MONTANT * max(1, $nb_entreprises);
    $total_montant += $montant;

    $stmt = $connexion->prepare("SELECT COUNT(*) FROM utilisateur WHERE admin_parent_id = ?");
    $stmt->execute([$admin['id']]);
    $nb_employes = (int)$stmt->fetchColumn();

    $stmt = $connexion->prepare("
        SELECT COUNT(*) 
        FROM subscription s
        JOIN utilisateur u ON s.user_id = u.id
        WHERE u.admin_parent_id = ?
          AND MONTH(s.date_paiement) = MONTH(CURDATE())
          AND YEAR(s.date_paiement) = YEAR(CURDATE())
    ");
    $stmt->execute([$admin['id']]);
    $nb_payes = (int)$stmt->fetchColumn();

    $today_day = (int)date('d');
    $last_day   = (int)date('t');

    $status = 'attente';
    if ($nb_payes >= $nb_employes && $nb_employes > 0) $status = 'paye';
    elseif ($today_day >= $last_day) $status = 'non';

    $whatsapp_link = '#';
    $has_whatsapp = false;
    if (!empty($admin['whatsapp'])) {
        $num = preg_replace('/\D/', '', trim($admin['whatsapp']));
        if (strlen($num) === 10 && $num[0] === '0') {
            $num = '2250' . substr($num, 1);  // Correction : '225' + 9 chiffres
        } elseif (strlen($num) === 9) {
            $num = '225' . $num;
        }
        if (strlen($num) === 12 && str_starts_with($num, '225')) {
            $has_whatsapp = true;
            $msg = urlencode(
                "Bonjour {$admin['nom']},\n\n" .
                "Point Nova – {$current_month_fr}\n" .
                "Montant : " . number_format($montant, 0, ',', ' ') . " FCFA\n" .
                "Statut : " . ucfirst($status) . "\n\n" .
                "Merci de confirmer !"
            );
            $whatsapp_link = "https://wa.me/{$num}?text={$msg}";
        }
    }

    $admins_data[] = [
        'id'            => $admin['id'],
        'nom'           => $admin['nom'],
        'photo'         => $admin['photo'],
        'nb_entreprises'=> $nb_entreprises,
        'nb_employes'   => $nb_employes,
        'montant'       => $montant,
        'status'        => $status,
        'month'         => $current_month_fr,
        'whatsapp_link' => $whatsapp_link,
        'has_whatsapp'  => $has_whatsapp,
    ];
}

// ────────────────────────────────────────────────
// Graphiques : données réelles depuis la base
// ────────────────────────────────────────────────

$labels = [];
$admins_data_graph = [];
$employes_data_graph = [];
$entreprises_data_graph = [];

$now = new DateTime();
for ($i = 11; $i >= 0; $i--) {
    $date = (clone $now)->modify("-$i months");
    $mois_key  = $date->format('Y-m');
    $mois_label = $date->format('M Y');  // ex: Fév 2026
    $labels[] = $mois_label;
}

// Admins créés par mois
$stmt = $connexion->prepare("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS mois, COUNT(*) AS nb
    FROM utilisateur
    WHERE role = 'Admin'
    GROUP BY mois
    ORDER BY mois DESC
    LIMIT 12
");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($labels as $label) {
    $mois_key = (new DateTime())->modify('first day of ' . $label)->format('Y-m');
    $found = false;
    foreach ($rows as $row) {
        if ($row['mois'] === $mois_key) {
            $admins_data_graph[] = (int)$row['nb'];
            $found = true;
            break;
        }
    }
    if (!$found) $admins_data_graph[] = 0;
}

// Employés créés par mois
$stmt = $connexion->prepare("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS mois, COUNT(*) AS nb
    FROM utilisateur
    WHERE role = 'Employe'
    GROUP BY mois
    ORDER BY mois DESC
    LIMIT 12
");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($labels as $label) {
    $mois_key = (new DateTime())->modify('first day of ' . $label)->format('Y-m');
    $found = false;
    foreach ($rows as $row) {
        if ($row['mois'] === $mois_key) {
            $employes_data_graph[] = (int)$row['nb'];
            $found = true;
            break;
        }
    }
    if (!$found) $employes_data_graph[] = 0;
}

// Entreprises créées par mois
$stmt = $connexion->prepare("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS mois, COUNT(*) AS nb
    FROM entreprise
    GROUP BY mois
    ORDER BY mois DESC
    LIMIT 12
");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($labels as $label) {
    $mois_key = (new DateTime())->modify('first day of ' . $label)->format('Y-m');
    $found = false;
    foreach ($rows as $row) {
        if ($row['mois'] === $mois_key) {
            $entreprises_data_graph[] = (int)$row['nb'];
            $found = true;
            break;
        }
    }
    if (!$found) $entreprises_data_graph[] = 0;
}

// Inverser pour ordre chronologique (plus ancien → plus récent)
$labels                = array_reverse($labels);
$admins_data_graph     = array_reverse($admins_data_graph);
$employes_data_graph   = array_reverse($employes_data_graph);
$entreprises_data_graph = array_reverse($entreprises_data_graph);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admins - Nova</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .badge-paye   { animation: blinkGreen 1.5s infinite; cursor:pointer; }
        .badge-non    { animation: blinkRed   1.5s infinite; cursor:pointer; }
        .badge-attente{ animation: blinkBlue  1.5s infinite; cursor:pointer; }
        @keyframes blinkGreen { 0%,100% {opacity:1;} 50% {opacity:0.4;} }
        @keyframes blinkRed   { 0%,100% {opacity:1;} 50% {opacity:0.4;} }
        @keyframes blinkBlue  { 0%,100% {opacity:1;} 50% {opacity:0.4;} }
        .chart-container { max-width: 920px; margin: 2.5rem auto; background:white; padding:1.8rem; border-radius:10px; box-shadow:0 3px 15px rgba(0,0,0,0.08); }
    </style>
</head>
<body class="bg-light">

<div class="container mt-4">
    <h3 class="text-center mb-4 fw-bold">Dashboard Admins – Nova</h3>
    <h5 class="text-center mb-5">Mois : <strong><?= htmlspecialchars($current_month_fr) ?></strong></h5>

    <!-- Cartes admins (inchangé) -->
    <div class="row g-4">
    <?php foreach ($admins_data as $a): ?>
        <div class="col-md-4 col-sm-6">
            <div class="card h-100 p-3 text-center shadow-sm">
                <?php if ($a['photo']): ?>
                    <img src="data:image/jpeg;base64,<?= base64_encode($a['photo']) ?>" class="rounded-circle mb-3 mx-auto d-block" style="width:90px;height:90px;object-fit:cover;">
                <?php else: ?>
                    <img src="default-avatar.png" class="rounded-circle mb-3 mx-auto d-block" style="width:90px;height:90px;object-fit:cover;">
                <?php endif; ?>
                <h5><?= htmlspecialchars($a['nom']) ?></h5>
                <p class="text-muted small mb-2">Mois : <?= htmlspecialchars($a['month']) ?></p>
                <p class="mb-1">Entreprises : <strong><?= $a['nb_entreprises'] ?></strong></p>
                <p class="mb-1">Employés : <strong><?= $a['nb_employes'] ?></strong></p>
                <p class="mb-3 fw-bold">Montant : <?= number_format($a['montant'], 0, ',', ' ') ?> FCFA</p>
                <div class="mb-3">
                    <span class="badge rounded-pill fs-5 px-4 py-2 <?= 
                        $a['status'] == 'paye' ? 'badge-paye bg-success' :
                        ($a['status'] == 'non' ? 'badge-non bg-danger' : 'badge-attente bg-primary')
                    ?>" 
                    id="badge-<?= $a['id'] ?>" 
                    onclick="togglePayment(<?= $a['id'] ?>)">
                        <?= ucfirst($a['status']) ?>
                    </span>
                </div>
                <?php if ($a['has_whatsapp']): ?>
                    <a href="<?= htmlspecialchars($a['whatsapp_link']) ?>" target="_blank" class="btn btn-success btn-sm d-inline-flex align-items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M13.601 2.326A7.854 7.854 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.057 3.965L0 16l4.204-1.102a7.933 7.933 0 0 0 3.79.965h.004c4.368 0 7.926-3.558 7.93-7.93A7.898 7.898 0 0 0 13.6 2.326zM7.994 14.521a6.573 6.573 0 0 1-3.356-.92l-.24-.144-2.494.654.666-2.433-.156-.251a6.56 6.56 0 0 1-1.007-3.505c0-3.626 2.957-6.584 6.591-6.584a6.56 6.56 0 0 1 4.66 1.931 6.557 6.557 0 0 1 1.928 4.66c-.004 3.639-2.961 6.592-6.592 6.592zm3.615-4.934c-.197-.099-1.17-.578-1.353-.646-.182-.065-.315-.099-.445.099-.133.197-.513.646-.627.775-.114.133-.232.148-.43.05-.197-.1-.836-.308-1.592-.985-.59-.525-.985-1.175-1.103-1.372-.114-.198-.011-.304.088-.403.087-.088.197-.232.296-.346.1-.114.133-.198.198-.33.065-.134.034-.248-.015-.347-.05-.099-.445-1.076-.612-1.47-.16-.389-.323-.335-.445-.34-.114-.007-.247-.007-.38-.007a.729.729 0 0 0-.529.247c-.182.198-.691.677-.691 1.654 0 .977.71 1.916.81 2.049.098.133 1.394 2.132 3.383 2.992.47.205.84.326 1.129.418.475.152.904.129 1.246.078.38-.058 1.17-.48 1.338-.943.164-.464.164-.86.114-.943-.049-.084-.182-.133-.38-.232z"/></svg>
                        WhatsApp
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    </div>

    <div class="text-center my-5">
        <h4 class="fw-bold">Total Nova ce mois : <span class="text-primary"><?= number_format($total_montant, 0, ',', ' ') ?> FCFA</span></h4>
    </div>

    <!-- 3 Graphiques séparés -->
    <div class="chart-container">
        <h5 class="text-center mb-4">Évolution du nombre d'Admins créés</h5>
        <canvas id="adminsChart"></canvas>
    </div>

    <div class="chart-container">
        <h5 class="text-center mb-4">Évolution du nombre d'Employés créés</h5>
        <canvas id="employesChart"></canvas>
    </div>

    <div class="chart-container">
        <h5 class="text-center mb-4">Évolution du nombre d'Entreprises créées</h5>
        <canvas id="entreprisesChart"></canvas>
    </div>
</div>

<script>
function togglePayment(adminId) {
    const badge = document.getElementById('badge-' + adminId);
    if (badge.textContent.trim() === 'Payé') {
        badge.className = 'badge rounded-pill fs-5 px-4 py-2 badge-non bg-danger';
        badge.textContent = 'Non payé';
    } else {
        badge.className = 'badge rounded-pill fs-5 px-4 py-2 badge-paye bg-success';
        badge.textContent = 'Payé';
    }
}

const labels = <?= json_encode($labels) ?>;

new Chart(document.getElementById('adminsChart'), {
    type: 'line',
    data: {
        labels: labels,
        datasets: [{
            label: 'Admins créés',
            data: <?= json_encode($admins_data_graph) ?>,
            borderColor: '#198754',
            backgroundColor: 'rgba(25,135,84,0.15)',
            fill: true,
            tension: 0.3
        }]
    },
    options: { responsive: true, scales: { y: { beginAtZero: true } } }
});

new Chart(document.getElementById('employesChart'), {
    type: 'line',
    data: {
        labels: labels,
        datasets: [{
            label: 'Employés créés',
            data: <?= json_encode($employes_data_graph) ?>,
            borderColor: '#0d6efd',
            backgroundColor: 'rgba(13,110,253,0.15)',
            fill: true,
            tension: 0.3
        }]
    },
    options: { responsive: true, scales: { y: { beginAtZero: true } } }
});

new Chart(document.getElementById('entreprisesChart'), {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [{
            label: 'Entreprises créées',
            data: <?= json_encode($entreprises_data_graph) ?>,
            backgroundColor: '#fd7e14'
        }]
    },
    options: { responsive: true, scales: { y: { beginAtZero: true } } }
});
</script>
</body>
</html>