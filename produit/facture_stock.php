<?php
// facture_stock.php - Bon de mouvement de stock (style identique à facture.php)
ob_start();

session_start();
require_once __DIR__ . "/../includes/db.php";

// Fuseau horaire Côte d'Ivoire
date_default_timezone_set('Africa/Abidjan');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    ob_end_clean();
    die("Bon invalide");
}

// Requête adaptée pour mouvement de stock
$stmt = $connexion->prepare("
    SELECT hs.*,
           p.nom AS produit, p.code, p.prix_achat, p.prix_unitaire,
           u.nom AS responsable, u.whatsapp AS whatsapp_responsable
    FROM historique_stock hs
    JOIN produit p ON p.id = hs.produit_id
    JOIN utilisateur u ON u.id = hs.utilisateur_id
    WHERE hs.id = ?
");
$stmt->execute([$id]);
$f = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$f) {
    ob_end_clean();
    die("Bon de mouvement introuvable");
}

// Heure locale CI
try {
    $date = new DateTime($f['date_mouvement']);
    $date->setTimezone(new DateTimeZone('Africa/Abidjan'));
    $date_heure = $date->format('d/m/Y H:i');
} catch (Exception $e) {
    $date_heure = "Date invalide";
}

// Calcul valeur
$valeur_unitaire = ($f['type'] === 'Entree') ? $f['prix_achat'] : $f['prix_unitaire'];
$valeur_totale   = $f['quantite'] * $valeur_unitaire;

// WhatsApp du responsable (si disponible)
$whatsapp_numero = $f['whatsapp_responsable'] ?? '0500503133';
$whatsapp_numero = preg_replace('/[^0-9]/', '', $whatsapp_numero);
$whatsapp_numero = '225' . ltrim($whatsapp_numero, '');
$whatsapp_link = "https://wa.me/{$whatsapp_numero}";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bon N°<?= str_pad($id, 6, '0', STR_PAD_LEFT) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <!-- jsPDF + html2canvas pour Télécharger PDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <style>
        @page { size: A4; margin: 12mm; }
        body { font-family: Arial, sans-serif; font-size: 13px; color: #000; margin: 0; }
        .facture { max-width: 800px; margin: 0 auto; padding: 15px; }
        .header { text-align: center; margin-bottom: 15px; }
        .header h2 { margin: 0; font-size: 18px; }
        .header .sous { font-size: 13px; color: #333; }
        .info { margin: 15px 0; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { border: 1px solid #000; padding: 6px; text-align: center; }
        th { background: #f0f0f0; font-weight: bold; }
        td.left { text-align: left; }
        .total { text-align: right; font-size: 15px; font-weight: bold; margin: 15px 0; }
        .signature { margin-top: 40px; display: flex; justify-content: space-between; }
        .signature div { width: 45%; text-align: center; }
        .signature hr { margin: 35px 0 10px; }
        .mentions { margin-top: 30px; font-size: 11px; color: #444; text-align: center; }
        .actions { text-align: center; margin: 30px 0; }
        .btn {
            padding: 10px 20px;
            margin: 0 10px;
            font-size: 14px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            color: white;
        }
        .btn-imprimer { background: #0d6efd; }
        .btn-pdf { background: #198754; }
        .btn-whatsapp {
            background: #25D366;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-retour { background: #6c757d; }
        .btn-whatsapp img { width: 24px; height: 24px; }
        @media print { .actions { display: none; } }
        @media (max-width: 768px) { .btn-imprimer { display: none; } }
    </style>
</head>
<body>
<div class="facture">
    <div class="header">
        <h2>QUINCAILLERIE GÉNÉRALE NOVA</h2>
        <div class="sous">
            Bon de Mouvement de Stock<br>
            Abidjan – Yopougon / Flameriba – Côte d’Ivoire<br>
            📞 07 08 31 44 37 • 05 05 40 65 34 • Chez AB – N’GATTACKO
        </div>
    </div>

    <div class="info">
        <strong>BON N° :</strong> <?= str_pad($id, 6, '0', STR_PAD_LEFT) ?><br>
        <strong>Date :</strong> <?= $date_heure ?><br>
        <strong>Responsable :</strong> <?= htmlspecialchars($f['responsable'] ?? '—') ?>
    </div>

    <table>
        <thead>
            <tr>
                <th>DÉSIGNATION</th>
                <th>QTÉ</th>
                <th>PRIX UNITAIRE</th>
                <th>VALEUR TOTALE</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="left">
                    <?= htmlspecialchars($f['produit'] ?? '—') ?><br>
                    <small><?= htmlspecialchars($f['code'] ?? '—') ?></small>
                </td>
                <td><?= $f['quantite'] ?? '—' ?></td>
                <td><?= number_format($valeur_unitaire, 0, ',', ' ') ?> FCFA</td>
                <td><?= number_format($valeur_totale, 0, ',', ' ') ?> FCFA</td>
            </tr>
        </tbody>
    </table>

    <div class="total">
        VALEUR TOTALE : <?= number_format($valeur_totale, 0, ',', ' ') ?> FCFA
    </div>

    <div class="signature">
        <div>
            <strong>Responsable</strong><br>
            <hr style="width:70%;">
            <small><?= htmlspecialchars($f['responsable'] ?? '—') ?></small>
        </div>
        <div>
            <strong>Contrôle</strong><br>
            <hr style="width:70%;">
            <small>Signature / Validation</small>
        </div>
    </div>

    <div class="mentions">
        Document interne • Mouvement enregistré dans Nova Stock<br>
        Merci de votre confiance – Nova Stock © 2026
    </div>

    <div class="actions">
        <button class="btn btn-imprimer" onclick="window.print()">🖨 Imprimer</button>
        <button class="btn btn-pdf" onclick="telechargerPDF()">📄 PDF</button>
        <a href="<?= $whatsapp_link ?>" target="_blank" class="btn btn-whatsapp px-4 py-2">
            <i class="bi bi-whatsapp me-2 fs-4"></i> WhatsApp
        </a>
        <a href="../dashboard.php" class="btn btn-retour">← Retour</a>
    </div>
</div>

<script>
function telechargerPDF() {
    const { jsPDF } = window.jspdf;
    const element = document.querySelector('.facture');
    html2canvas(element, { scale: 2 }).then(canvas => {
        const imgData = canvas.toDataURL('image/png');
        const pdf = new jsPDF('p', 'mm', 'a4');
        const imgWidth = 190;
        const imgHeight = (canvas.height * imgWidth) / canvas.width;
        pdf.addImage(imgData, 'PNG', 10, 10, imgWidth, imgHeight);
        pdf.save('bon_mouvement_<?= str_pad($id, 6, '0', STR_PAD_LEFT) ?>.pdf');
        
        alert("PDF téléchargé !\n\nVous pouvez l'envoyer via WhatsApp.");
    }).catch(err => {
        alert("Erreur PDF : " + err.message);
    });
}
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
</body>
</html>

<?php
ob_end_flush();
?>