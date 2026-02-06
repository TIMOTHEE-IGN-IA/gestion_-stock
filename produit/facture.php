



        <?php
session_start();
require_once __DIR__ . "/../includes/db.php";
date_default_timezone_set('Africa/Abidjan');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) die("Facture invalide");

// Récupération de la vente et produits associés
$stmt = $connexion->prepare("
    SELECT v.*, p.nom AS produit, p.code, u.nom AS vendeur, u.whatsapp AS whatsapp_vendeur
    FROM vente v
    JOIN produit p ON p.id = v.produit_id
    JOIN utilisateur u ON u.id = v.utilisateur_id
    WHERE v.id = ?
");
$stmt->execute([$id]);
$vente = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!$vente) die("Facture introuvable");

// Heure exacte
$date_heure = date('d/m/Y H:i', strtotime($vente[0]['date']));

// WhatsApp vendeur
$whatsapp_numero = $vente[0]['whatsapp_vendeur'] ?? '0500503133';
$whatsapp_numero = preg_replace('/[^0-9]/', '', $whatsapp_numero);
$whatsapp_numero = '225' . ltrim($whatsapp_numero, '');
$whatsapp_link = "https://wa.me/{$whatsapp_numero}";

// Calcul total général
$totalGeneral = array_sum(array_map(fn($v) => $v['total'], $vente));

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Facture N°<?= str_pad($id, 6, '0', STR_PAD_LEFT) ?></title>
<style>
body { font-family: "Courier New", Courier, monospace; background:#fff; color:#000; margin:0; padding:10px; }
.ticket { max-width:320px; margin:auto; }
.header { text-align:center; margin-bottom:5px; }
.header h2 { margin:0; font-size:16px; }
.header small { font-size:12px; color:#555; }
hr { border:none; border-top:1px dashed #000; margin:5px 0; }
.info, .total, .products { font-size:13px; }
.products table { width:100%; border-collapse:collapse; font-size:13px; }
.products td { padding:2px 0; text-align:left; }
.products td.qty, .products td.total { text-align:right; }
.total { font-weight:bold; margin-top:5px; text-align:right; }
.actions { text-align:center; margin:10px 0; }
.btn { padding:6px 10px; font-size:12px; border:none; border-radius:3px; cursor:pointer; color:#fff; margin:2px; text-decoration:none; display:inline-block; }
.btn-print { background:#0d6efd; }
.btn-pdf { background:#198754; }
.btn-whatsapp { background:#25D366; color:#fff; display:inline-flex; align-items:center; gap:4px; }
.btn-whatsapp i { font-size:14px; }
@media print { .actions { display:none; } }
</style>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
        
<div class="ticket">
    <div class="header">
        <h2> QUINCAILLERIE GÉNÉRALE</h2>
        <small>Abidjan – Yopougon / Flameriba<br>📞 07 08 31 44 37 • 05 05 40 65 34</small>
    </div>
    <hr>
    <div class="info">
        <div>FACTURE N°: <?= str_pad($id, 6, '0', STR_PAD_LEFT) ?></div>
        <div>Date: <?= $date_heure ?></div>
        <div>Vendeur: <?= htmlspecialchars($vente[0]['vendeur']) ?></div>
    </div>
    <hr>
    <div class="products">
        <table>
            <?php foreach($vente as $item): ?>
            <tr>
                <td><?= htmlspecialchars($item['produit']) ?> <small><?= htmlspecialchars($item['code']) ?></small></td>
            </tr>
            <tr>
                <td class="qty"><?= $item['quantite'] ?> x <?= number_format($item['prix_unitaire'],0,',',' ') ?> FCFA</td>
                <td class="total"><?= number_format($item['total'],0,',',' ') ?> FCFA</td>
            </tr>
            <tr><td colspan="2"><hr></td></tr>
            <?php endforeach; ?>
        </table>
    </div>
    <div class="total">
        TOTAL: <?= number_format($totalGeneral,0,',',' ') ?> FCFA
    </div>
    <div class="total">
        Moyen de paiement: <?= htmlspecialchars($vente[0]['paiement']) ?>
    </div>
    <hr>
    <div class="actions">
        <button class="btn btn-print" onclick="window.print()">🖨 Imprimer</button>
        <button class="btn btn-pdf" onclick="telechargerPDF()">📄 PDF</button>
        <a href="<?= $whatsapp_link ?>" target="_blank" class="btn btn-whatsapp"><i class="bi bi-whatsapp"></i> WhatsApp</a>
    </div>
    <div style="text-align:center; font-size:11px; margin-top:10px;">
        Paiement comptant • Marchandises vendues sans reprise ni échange<br>
        Merci de votre confiance – Nova Stock © 2026
    </div>
</div>
<div style="text-align:center; margin-bottom:10px;">
    <a href="https://immo225.42web.io/produit/vente.php" 
       style="display:inline-flex; align-items:center; gap:5px; text-decoration:none; color:#fff; background:#6c757d; padding:6px 10px; border-radius:5px;">
        <img src="https://cdn-icons-png.flaticon.com/512/271/271220.png" alt="Retour" width="18" height="18">
        <span style="font-size:12px;">Retour</span>
    </a>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
function telechargerPDF(){
    const { jsPDF } = window.jspdf;
    const ticket = document.querySelector('.ticket');
    html2canvas(ticket, { scale:2 }).then(canvas=>{
        const imgData = canvas.toDataURL('image/png');
        const pdf = new jsPDF('p','mm',[canvas.width/3, canvas.height/3]);
        pdf.addImage(imgData,'PNG',0,0,canvas.width/3,canvas.height/3);
        pdf.save('facture_<?= str_pad($id, 6, '0', STR_PAD_LEFT) ?>.pdf');
    });
}
</script>
</body>
</html>
