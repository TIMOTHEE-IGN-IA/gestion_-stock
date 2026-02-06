<?php
session_start();
if (isset($_SESSION['user'])) {
    header("Location: dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Connexion | Nova Stock</title>
<!-- Favicon classique -->
<link rel="icon" type="image/png" sizes="32x32" href="nova.png">
<link rel="icon" type="image/png" sizes="192x192" href="nova.png">
<!-- Icône pour mobile / ajout écran d’accueil -->
<link rel="apple-touch-icon" sizes="180x180" href="nova.png">
<!-- Lien vers le manifest PWA -->
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#00A651">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<!-- Bootstrap -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<style>
html, body {
    height:100%;
    margin:0;
    font-family:"Segoe UI", Arial, sans-serif;
    overflow:hidden; /* Empêche le scroll */
}
/* Slider d'images en fond */
.bg-slider {
    position: fixed;
    top:0; left:0;
    width:100%;
    height:100%;
    z-index:-1;
    overflow:hidden;
}
.bg-slider img {
    position:absolute;
    width:100%;
    height:100%;
    object-fit:cover;
    opacity:0;
    animation: fade 15s infinite;
}
.bg-slider img:nth-child(1){ animation-delay:0s; }
.bg-slider img:nth-child(2){ animation-delay:5s; }
.bg-slider img:nth-child(3){ animation-delay:10s; }
@keyframes fade {
    0% {opacity:0;}
    8% {opacity:1;}
    33% {opacity:1;}
    41% {opacity:0;}
    100% {opacity:0;}
}
/* Carte login centrée et responsive */
.login-card {
    width:90%;
    max-width:450px;
    background: rgba(255,255,255,0.95);
    border-radius:15px;
    padding:120px 30px 40px 30px; /* padding top pour le logo */
    box-shadow:0 20px 50px rgba(0,0,0,0.3);
    position:fixed;
    top:50%;
    left:50%;
    transform:translate(-50%,-50%);
    text-align:center;
    z-index:10;
}
/* Logo Nova Stock fixe au-dessus de la carte */
.login-logo {
    position:absolute;
    top:-100px; /* ajuste si besoin */
    left:50%;
    transform:translateX(-50%);
    width:80%;
    max-width:400px;
}
.login-logo img {
    width:100%;
    height:auto;
}
/* Titres et textes */
.login-card h3 {
    margin-bottom:10px;
    font-weight:bold;
    color:#2c3e50;
    font-size:clamp(1.5rem, 2vw, 2rem); /* responsive */
}
.login-card p {
    font-size:clamp(1rem, 1.5vw, 1.2rem);
    color:#555;
    margin-bottom:30px;
}
/* Champs et boutons */
.form-control { height:50px; border-radius:10px; font-size:1rem; }
.input-group-text { background:#f1f3f5; border-radius:10px 0 0 10px; font-size:1.1rem; }
.btn-login {
    background:#2a5298;
    color:#fff;
    font-weight:bold;
    height:55px;
    border-radius:10px;
    font-size:1.1rem;
    transition:.3s;
}
.btn-login:hover { background:#1e3c72; }
.error {
    background:#fdecea;
    color:#b71c1c;
    padding:12px;
    border-radius:8px;
    text-align:center;
    margin-bottom:20px;
    font-size:14px;
}
.footer-text {
    text-align:center;
    margin-top:20px;
    font-size:13px;
    color:#999;
}
/* Responsive mobile */
@media (max-width:768px){
    .login-card{
        padding:100px 20px 30px 20px;
    }
    .login-logo{
        top:-80px;
        max-width:300px;
    }
}
@media (max-width:480px){
    .login-card{
        padding:80px 15px 25px 15px;
    }
    .login-logo{
        top:-70px;
        max-width:250px;
    }
    .btn-login{
        height:50px;
        font-size:1rem;
    }
}

/* Logo WhatsApp flottant */
.whatsapp-float {
    position: fixed;
    width: 60px;
    height: 60px;
    bottom: 25px;
    right: 25px;
    background: #25D366;
    color: white;
    border-radius: 50%;
    text-align: center;
    font-size: 30px;
    box-shadow: 2px 2px 10px rgba(0,0,0,0.3);
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
}
.whatsapp-float:hover {
    background: #20b858;
    transform: scale(1.1);
}

/* Style amélioré pour le bouton WhatsApp flottant */
.whatsapp-float {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 65px;
    height: 65px;
    background: linear-gradient(135deg, #25D366 0%, #20b858 100%);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    box-shadow: 0 8px 25px rgba(37, 211, 102, 0.45);
    z-index: 1000;
    text-decoration: none;
    transition: all 0.35s ease;
}

.whatsapp-float:hover {
    transform: translateY(-8px) scale(1.12);
    box-shadow: 0 15px 35px rgba(37, 211, 102, 0.55);
}

.whatsapp-float:active {
    transform: translateY(-2px) scale(1.05);
}

/* Petite pulsation subtile pour attirer l'attention */
@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(37, 211, 102, 0.5); }
    70% { box-shadow: 0 0 0 15px rgba(37, 211, 102, 0); }
    100% { box-shadow: 0 0 0 0 rgba(37, 211, 102, 0); }
}

.whatsapp-float {
    animation: pulse 2.2s infinite;
}

.whatsapp-float i {
    transition: transform 0.4s ease;
}

.whatsapp-float:hover i {
    transform: rotate(15deg) scale(1.15);
}

/* Ajustement mobile */
@media (max-width: 768px) {
    .whatsapp-float {
        width: 58px;
        height: 58px;
        bottom: 20px;
        right: 20px;
        font-size: 28px;
    }
}

/* Bouton WhatsApp qui clignote vraiment pour attirer l'œil */
.whatsapp-float {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 70px;
    height: 70px;
    background: #25D366;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 36px;
    box-shadow: 0 6px 20px rgba(37, 211, 102, 0.6);
    z-index: 1000;
    text-decoration: none;
    transition: all 0.3s ease;
}

/* Effet clignotant / pulsation plus forte */
@keyframes clignote-fort {
    0%, 100% {
        box-shadow: 0 0 0 0 rgba(37, 211, 102, 0.7);
        transform: scale(1);
    }
    50% {
        box-shadow: 0 0 0 25px rgba(37, 211, 102, 0);
        transform: scale(1.15);
    }
}

.whatsapp-float {
    animation: clignote-fort 1.8s infinite ease-in-out;
}

.whatsapp-float:hover {
    transform: scale(1.25);
    background: #20b858;
    box-shadow: 0 12px 35px rgba(37, 211, 102, 0.8);
    animation: none; /* Arrête le clignotement au survol pour plus de confort */
}

.whatsapp-float:active {
    transform: scale(1.1);
}

.whatsapp-float i {
    transition: transform 0.3s;
}

.whatsapp-float:hover i {
    transform: scale(1.2) rotate(10deg);
}

/* Plus petit sur mobile */
@media (max-width: 768px) {
    .whatsapp-float {
        width: 60px;
        height: 60px;
        bottom: 20px;
        right: 20px;
        font-size: 30px;
    }
}
@keyframes clignote-extreme {
    0%, 100% { background: #25D366; box-shadow: 0 0 0 0 rgba(37, 211, 102, 0.8); }
    50%      { background: #128c3f; box-shadow: 0 0 0 30px rgba(37, 211, 102, 0.1); }
}

.whatsapp-float {
    animation: clignote-extreme 1.4s infinite;
}

/* Conteneur pour aligner logo + texte */
.whatsapp-container {
    position: fixed;
    bottom: 35px;
    right: 30px;
    display: flex;
    align-items: center;
    gap: 12px;           /* espace entre le texte et le cercle */
    z-index: 1000;
}

/* Le bouton rond WhatsApp */
.whatsapp-float {
    width: 62px;
    height: 62px;
    background: #25D366;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 30px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.35);
    text-decoration: none;
    transition: all 0.3s ease;
}

.whatsapp-float:hover {
    transform: scale(1.15);
    box-shadow: 0 10px 30px rgba(37, 211, 102, 0.5);
}

/* Texte à côté */
.whatsapp-text {
    background: rgba(0,0,0,0.75);
    color: white;
    padding: 8px 14px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 500;
    white-space: nowrap;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    pointer-events: none; /* pour ne pas gêner les clics */
}

/* Effet clignotant léger sur le conteneur entier */
@keyframes pulse-contact {
    0%, 100% { opacity: 1; }
    50%      { opacity: 0.75; }
}

.whatsapp-container {
    animation: pulse-contact 3s infinite ease-in-out;
}

/* Sur mobile : on cache le texte ou on le met plus petit */
@media (max-width: 768px) {
    .whatsapp-text {
        font-size: 13px;
        padding: 6px 12px;
    }
    
    .whatsapp-container {
        right: 20px;
        bottom: 25px;
        gap: 10px;
    }
    
    .whatsapp-float {
        width: 55px;
        height: 55px;
        font-size: 26px;
    }
}
</style>
</head>
<body>
<!-- Carte login -->
<div class="login-card">
    <div class="login-logo">
        <img src="nova.png" alt="Nova Stock Logo">
    </div>
    <h3>🔐 Connexion</h3>
    <p>Application de gestion commerciale</p>
    <?php if (isset($_GET['error'])): ?>
        <div class="error">❌ Email ou mot de passe incorrect</div>
    <?php endif; ?>
    <form method="POST" action="auth/login.php">
    <div class="input-group mb-3">
        <span class="input-group-text"><i class="bi bi-person"></i></span>
        <input type="text" name="nom" class="form-control"
               placeholder="Nom d'utilisateur" required>
    </div>
    <div class="input-group mb-3">
        <span class="input-group-text"><i class="bi bi-lock"></i></span>
        <input type="password" name="password" class="form-control"
               placeholder="Mot de passe" required>
    </div>
    <button type="submit" class="btn btn-login w-100">
        Se connecter
    </button>
</form>
    <div class="footer-text">
        © <?= date('Y') ?> – Nova Stock
    </div>
</div>

<a href="https://wa.me/2250500503133" class="whatsapp-float" target="_blank">
    <i class="bi bi-whatsapp"></i>
</a>

<!-- MODAL BIENVENUE -->
<div class="modal fade" id="welcomeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-4 text-center">
      <h3>Bienvenue sur Nova Stock !</h3>
      <p>Gérez vos produits et ventes facilement.</p>
      <button class="btn btn-primary" data-bs-dismiss="modal">Commencer</button>
    </div>
  </div>
</div>
<!-- Conteneur pour le WhatsApp + texte -->
<div class="whatsapp-container">
    <a href="https://wa.me/2250500503133" class="whatsapp-float" target="_blank" title="Contactez-nous sur WhatsApp">
        <i class="bi bi-whatsapp"></i>
    </a>
    <span class="whatsapp-text"> Contactez Nova Code ici</span>&nbsp &nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Modal automatique au chargement, pas de fermeture par clic ou ESC
document.addEventListener('DOMContentLoaded', function() {
    const modal = new bootstrap.Modal(document.getElementById('welcomeModal'), {
        backdrop: 'static',
        keyboard: false
    });
    modal.show();
});
</script>
</body>
</html>