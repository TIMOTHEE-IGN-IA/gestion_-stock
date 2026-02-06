<?php
session_start();
require_once __DIR__ . "/includes/db.php";
if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}
$user = $_SESSION['user'];

$user = $_SESSION['user'];

// ⚠️ Si tu ne stockes pas la photo dans la session, tu dois la récupérer depuis la base
$sql = "SELECT * FROM utilisateur WHERE id = ?";
$stmt = $connexion->prepare($sql);
$stmt->execute([$user['id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);


// ===== AJOUTÉ juste après $user = $_SESSION['user']; =====
$isAdmin   = ($user['role'] === 'Admin');
$isEmploye = in_array(strtolower($user['role']), ['employe', 'employer', 'employé']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard | Nova Stock</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">


<style>
:root {
    --bg:#f3f3f3;
    --sidebar:#ffffff;
    --card:#ffffff;
    --border:#e5e7eb;
    --text:#1f2937;
    --muted:#374151;
    --primary:#185ABD;
    --hover:#e8f0fe;
    /* Couleurs inspirées de votre app */
    --navbar-bg: #00A651;
    --navbar-text: #FFFFFF;
    --navbar-accent: #FFD700;
    --sidebar-mobile-bg: #F8F9FA;
}
/* MODE NUIT */
body.dark {
    --bg:#0f172a;
    --sidebar:#020617;
    --card:#020617;
    --border:#1e293b;
    --text:#e5e7eb;
    --muted:#9ca3af;
    --primary:#3b82f6;
    --hover:#1e293b;
    --navbar-bg: #0D5D2E;
    --sidebar-mobile-bg: #1E293B;
}
/* BASE */
html, body { height:100%; margin:0; font-family:"Segoe UI", system-ui, sans-serif; }
body { background:var(--bg); color:var(--text); }
/* NAVBAR MOBILE */
.navbar-mobile {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 60px;
    background: var(--navbar-bg);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 15px;
    z-index: 1003;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    color: var(--navbar-text);
}
.navbar-mobile.hidden { display: none; }
.navbar-mobile .navbar-brand {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--navbar-text);
    font-weight: 600;
    font-size: 16px;
}
.navbar-mobile .navbar-brand img {
    width: 30px;
    height: 30px;
    border-radius: 50%;
}
.navbar-mobile .navbar-title {
    margin: 0;
    color: var(--navbar-text);
    font-size: 16px;
    font-weight: 500;
}
.navbar-mobile .version-badge {
    background: var(--navbar-accent);
    color: #000;
    border-radius: 12px;
    padding: 2px 8px;
    font-size: 12px;
    font-weight: bold;
    margin-left: 5px;
}
/* BOUTON HAMBURGER GAUCHE */
.mobile-hamburger {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    width: 25px;
    height: 20px;
    cursor: pointer;
    z-index: 1004;
}
.mobile-hamburger span {
    width: 100%;
    height: 3px;
    background-color: var(--navbar-text);
    margin: 2px 0;
    transition: all 0.3s ease;
    border-radius: 2px;
}
/* Animation hamburger vers X */
body.sidebar-open .mobile-hamburger span:nth-child(1) {
    transform: rotate(45deg) translate(6px, 6px);
}
body.sidebar-open .mobile-hamburger span:nth-child(2) {
    opacity: 0;
}
body.sidebar-open .mobile-hamburger span:nth-child(3) {
    transform: rotate(-45deg) translate(7px, -6px);
}
/* SIDEBAR */
.sidebar {
    width:280px;
    height:100vh;
    position:fixed;
    left:0;
    top:0;
    padding:20px;
    background:var(--sidebar-mobile-bg);
    border-right:1px solid var(--border);
    overflow-y: auto;
    z-index: 1001;
    transition: transform 0.3s ease;
    box-shadow: 4px 0 15px rgba(0,0,0,0.1);
}
.sidebar-logo {
    margin-bottom: 20px;
    text-align: center;
    padding-top: 10px;
}
.sidebar-logo img {
    width:45px;
    height:45px;
    border-radius: 50%;
    margin-bottom: 8px;
}
.sidebar-logo b {
    color: var(--primary);
    font-weight:700;
    font-size: 18px;
    display: block;
}
.sidebar-user-info {
    text-align: center;
    margin-bottom: 20px;
    padding: 15px;
    background: rgba(255,255,255,0.1);
    border-radius: 10px;
}
.sidebar-user-info p {
    margin: 0;
    color: var(--text);
    font-size: 14px;
    font-weight: 500;
}
.sidebar-user-info .badge {
    background: var(--primary);
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    margin-top: 5px;
}
.sidebar hr {
    border-color:var(--border);
    margin: 20px 0;
    opacity: 0.5;
}
.sidebar a {
    color:var(--text);
    text-decoration:none;
    display:flex;
    align-items:center;
    gap:12px;
    padding:12px 15px;
    border-radius:8px;
    margin-bottom:8px;
    transition:.2s;
    font-size: 14px;
}
.sidebar a i {
    color:var(--primary);
    width: 20px;
    text-align: center;
    font-size: 16px;
}
.sidebar a:hover {
    background:var(--hover);
    transform: translateX(5px);
}
/* SUBMENU */
.submenu {
    display:none;
    margin-left:15px;
    background: rgba(0,0,0,0.05);
    border-radius: 6px;
    padding: 5px 0;
}
.submenu a {
    padding: 8px 15px 8px 30px;
    margin-bottom: 4px;
    font-size: 13px;
}
/* BOUTON FERMER */
.mobile-close-btn {
    position: absolute;
    top: 15px;
    right: 15px;
    background: none;
    border: none;
    font-size: 1.8rem;
    color: var(--muted);
    cursor: pointer;
    z-index: 1002;
    padding: 5px;
    border-radius: 50%;
    transition: all 0.3s ease;
}
.mobile-close-btn:hover {
    background: rgba(0,0,0,0.1);
    color: var(--primary);
}
/* OVERLAY */
.sidebar-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 999;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}
.sidebar-overlay.show {
    opacity: 1;
    visibility: visible;
}
/* MAIN CONTENT */
.main-content {
    margin: 0;
    padding: 80px 15px 15px; /* Espace pour navbar */
    min-height: 100vh;
}
.main-content > * {
    background:var(--card);
    border:1px solid var(--border);
    border-radius:8px;
    padding:25px;
    margin-bottom: 20px;
}
/* BOUTONS */
.btn-primary {
    background:var(--primary);
    border-color:var(--primary);
    border-radius: 6px;
}
.form-control, .form-select {
    border-radius:6px;
    border:1px solid var(--border);
    background:var(--card);
    color:var(--text);
}
/* DESKTOP SIDEBAR */
@media (min-width: 993px) {
    .navbar-mobile { display: none; } /* Cacher navbar sur desktop */
   
    .sidebar {
        transform: none !important;
        width: 240px;
        height: 100vh;
        position: fixed;
        background: var(--sidebar);
    }
    .main-content {
        margin-left: 240px;
        padding: 30px;
    }
    /* SIDEBAR COLLAPSÉ */
    body.sidebar-collapsed .sidebar{
        width:70px;
        padding:16px 10px;
    }
    body.sidebar-collapsed .sidebar .label,
    body.sidebar-collapsed .sidebar p,
    body.sidebar-collapsed .sidebar hr,
    body.sidebar-collapsed .sidebar .submenu,
    body.sidebar-collapsed .sidebar-user-info p {
        display:none;
    }
    body.sidebar-collapsed .sidebar a{
        justify-content:center;
        padding: 12px;
    }
    body.sidebar-collapsed .sidebar i{
        font-size:20px;
    }
    body.sidebar-collapsed .main-content{
        margin-left:70px;
    }
    /* BOUTON TOGGLE DESKTOP */
    #sidebarToggleMenu {
        position: absolute;
        top: 10px;
        right: 10px;
        z-index: 999;
        font-size: 1.4rem;
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 50%;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary);
        text-decoration: none;
    }
}
/* MOBILE SPECIFIQUE */
@media (max-width:992px){
    .sidebar{
        transform: translateX(-100%);
    }
    body.sidebar-open .sidebar{
        transform: translateX(0);
    }
    .main-content{
        padding-top: 80px;
    }
    .sidebar a {
        border-radius: 10px;
    }
}
/* PETIT MOBILE */
@media (max-width:576px){
    .navbar-mobile {
        padding: 0 10px;
        height: 55px;
    }
    .main-content {
        padding: 75px 10px 10px;
    }
    .sidebar {
        width: 260px;
        padding: 15px;
    }
    .sidebar a {
        padding: 10px 12px;
        gap: 10px;
    }
}
/* TRANSITIONS */
.sidebar, .main-content, .mobile-hamburger span {
    transition:all .3s ease;
}
/* Rotation icône pour sous-menu */
.menu-toggle i {
    transition: transform 0.3s ease;
}
.menu-toggle.open i {
    transform: rotate(90deg);
}
/* LOADING */
.loading {
    display: none;
    text-align: center;
    padding: 20px;
}
.loading.show {
    display: block;
}
/* STYLES POUR LES ALERTES */
.alert {
    border-radius: 8px;
    border: none;
    padding: 1rem 1.5rem;
    margin-bottom: 1rem;
    animation: slideInDown 0.3s ease-out;
}
.alert-success {
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
    color: #155724;
    box-shadow: 0 2px 8px rgba(40, 167, 69, 0.15);
}
.alert-danger {
    background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
    color: #721c24;
    box-shadow: 0 2px 8px rgba(220, 53, 69, 0.15);
}
.alert i {
    font-size: 1.2rem;
}
.spinner-border-sm {
    width: 1rem;
    height: 1rem;
}
@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
</head>
<body>
<!-- NAVBAR MOBILE -->
<nav class="navbar-mobile" id="navbarMobile">
    <!-- Bouton Hamburger à GAUCHE -->
    <div class="mobile-hamburger" id="mobileHamburger">
        <span></span>
        <span></span>
        <span></span>
    </div>
   
    <!-- Titre central -->
    <div class="navbar-brand">
        <img src="nova.png" alt="Nova Stock">
        <span class="navbar-title">Nova Stock</span>
        <span class="version-badge"> V one </span>
    </div>
   
    <!-- Espace vide à droite pour équilibrer -->
    <div style="width: 25px;"></div>
</nav>

<!-- OVERLAY POUR MOBILE -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- SIDEBAR MOBILE/DRAWER -->
<div class="sidebar">
    <!-- Bouton Fermer -->
    <button class="mobile-close-btn" id="mobileCloseBtn">
        <i class="bi bi-x-lg"></i>
    </button>
   
<!-- Logo et Infos Utilisateur -->
<div class="sidebar-logo text-center" style="margin-top:80px;"> <!-- <-- on descend le bloc -->
    <?php if (!empty($user['photo'])): ?>
        <img src="data:image/jpeg;base64,<?= base64_encode($user['photo']) ?>" 
             alt="<?= htmlspecialchars($user['nom']) ?>" 
             style="width:60px; height:60px; border-radius:50%; object-fit:cover;">
    <?php else: ?>
        <img src="nova.png" alt="Utilisateur" style="width:60px; height:60px; border-radius:50%; object-fit:cover;">
    <?php endif; ?>
    <b><?= htmlspecialchars($user['nom']) ?></b><br>
    <span style="font-size:12px; color:var(--muted);"><?= htmlspecialchars($user['role']) ?></span>
</div>


   
    <hr>

<!-- Dashboard – visible uniquement pour Admin -->
<?php if ($isAdmin): ?>
<a href="produit/dashboard_content.php" class="ajax-link">
    <i class="bi bi-grid-1x2-fill"></i>
    <span class="label">Dashboard</span>
</a>
<?php endif; ?>

<!-- Produits – visible uniquement pour Admin -->
<?php if ($isAdmin): ?>
<a href="#" class="menu-toggle" data-target="produits-submenu">
    <i class="bi bi-boxes"></i>
    <span class="label">Produits</span>
</a>
<div class="submenu" id="produits-submenu">
    <a href="produit/add.php" class="ajax-link"><i class="bi bi-plus-circle"></i><span class="label">Ajouter</span></a>
    <a href="produit/list.php" class="ajax-link"><i class="bi bi-list-check"></i><span class="label">Liste</span></a>
</div>
<?php endif; ?>

<!-- VENTES – toggle d’abord, puis sous-menu -->
<a href="#" class="menu-toggle" data-target="ventes-submenu">
    <i class="bi bi-cash-stack"></i>
    <span class="label">Ventes</span>
</a>
<div class="submenu" id="ventes-submenu">
    <?php if ($isEmploye): ?>
    <a href="produit/vente.php" class="ajax-link">
        <i class="bi bi-receipt"></i>
        <span class="label">Au comptoir</span>
    </a>
    <?php endif; ?>

    <?php if ($isAdmin): ?>
    <a href="produit/vente.php" class="ajax-link">
        <i class="bi bi-receipt"></i>
        <span class="label">Au comptoir</span>
        <a href="produit/Historiquev.php" class="ajax-link">
        <i class="bi bi-clock-history"></i>
        <span class="label">Historique</span>
    </a>
    </a>
    
    <?php endif; ?>
</div>

<!-- GESTION DE STOCK – uniquement Admin -->
<?php if ($isAdmin): ?>
<a href="#" class="menu-toggle" data-target="stock-submenu">
    <i class="bi bi-arrow-left-right"></i>
    <span class="label">Gestion Stock</span>
</a>

<div class="submenu" id="stock-submenu">
    <a href="produit/stock.php" class="ajax-link">
        <i class="bi bi-box-arrow-in-down"></i>
        <span class="label">Entrée/Sortie</span>
    </a>
    <a href="produit/historique.php" class="ajax-link">
        <i class="bi bi-clock-history"></i>
        <span class="label">Historique</span>
    </a>
</div>
<?php endif; ?>


<!-- PARAMÈTRES -->
<a href="#" class="menu-toggle" data-target="param-submenu">
    <i class="bi bi-gear-fill"></i>
    <span class="label">Paramètres</span>
</a>
<div class="submenu" id="param-submenu">
    <a href="#" id="themeToggleMenu"><i class="bi bi-moon-stars"></i><span class="label">Mode Nuit / Jour</span></a>
</div>

<hr>
<a href="logout.php" class="text-danger"><i class="bi bi-box-arrow-right"></i><span class="label">Déconnexion</span></a>
</div>

<!-- BOUTON TOGGLE DESKTOP (caché sur mobile) -->
<a href="#" id="sidebarToggleMenu" style="display: none;">
    <i class="bi bi-toggle2-on"></i>
</a>

<!-- MAIN CONTENT -->
<div class="main-content" id="main-content">
    <!-- LOADING INDICATOR -->
    <div class="loading show" id="loadingIndicator">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Chargement...</span>
        </div>

        <p class="mt-2">Chargement du dashboard...</p>
    </div>
   
    <!-- CONTENU PRINCIPAL -->
   <!-- ===== AJOUTÉ : Chargement par défaut selon rôle ===== -->
<div id="mainContentWrapper">
    <?php
    if ($isAdmin) {
       include __DIR__ . "/produit/dashboard_content.php";

    } else {
        include "produit/vente.php";
    }
    ?>
</div>

<script>
// Variables globales pour les fonctions du sidebar
let sidebarOpen = false;
let isMobile = window.innerWidth <= 992;

// FONCTION UTILITAIRE POUR AFFICHER LE LOADING
function showLoading(show = true) {
    const loading = document.getElementById('loadingIndicator');
    const wrapper = document.getElementById('mainContentWrapper');
    
    if (show) {
        loading.classList.add('show');
        wrapper.style.display = 'none';
    } else {
        loading.classList.remove('show');
        wrapper.style.display = 'block';
    }
}

// FONCTIONS SIDEBAR (sûres et définies globalement)
function openSidebar() {
    if (isMobile) {
        document.body.classList.add('sidebar-open');
        document.getElementById('sidebarOverlay').classList.add('show');
        sidebarOpen = true;
        console.log('Sidebar ouverte sur mobile');
    }
}

function closeSidebar() {
    if (isMobile && sidebarOpen) {
        document.body.classList.remove('sidebar-open');
        document.getElementById('sidebarOverlay').classList.remove('show');
        sidebarOpen = false;
        console.log('Sidebar fermée sur mobile');
    }
}

// TOGGLE sous-menus avec rotation icône
document.addEventListener('DOMContentLoaded', function() {
    // Re-query après DOM loaded pour s'assurer que les éléments existent
    document.querySelectorAll('.menu-toggle').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const target = btn.dataset.target;
            const submenu = document.getElementById(target);
            if (submenu) {
                submenu.style.display = submenu.style.display === 'block' ? 'none' : 'block';
                btn.classList.toggle('open');
            }
        });
    });
});

// NAVIGATION AJAX AMÉLIORÉE (corrigée pour mobile)
document.addEventListener('click', function(e){
    const link = e.target.closest('.ajax-link');
    if(!link) return;
    
    e.preventDefault();
    const url = link.getAttribute('href');
    
    console.log('Navigation AJAX vers:', url);
    
    showLoading(true);
    
    fetch(url)
        .then(response => {
            if (!response.ok) {
                throw new Error(`Erreur HTTP: ${response.status}`);
            }
            return response.text();
        })
       .then(html => {
    document.getElementById('mainContentWrapper').innerHTML = html;
    
    // Ligne ESSENTIELLE : ré-attache tous les listeners de vente.js
    if (typeof initVenteForm === 'function') {
        initVenteForm();
    }
    
    showLoading(false);
    // ... le reste
})
        .catch(error => {
            console.error('Erreur lors du chargement:', error);
            document.getElementById('mainContentWrapper').innerHTML = 
                '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>Erreur lors du chargement de la page. Veuillez réessayer.</div>';
            showLoading(false);
        });
});

// INTERCEPTION FORMULAIRES AMÉLIORÉE (corrigée)
document.addEventListener('submit', function(e){
    const forms = { 
        'historiqueForm':'produit/historique.php', 
        'add-product-form':'produit/add.php', 
        'venteForm':'produit/vente.php',
    };
    
    if(forms[e.target.id]){
        e.preventDefault();
        const formId = e.target.id;
        const url = forms[formId];
        const isPost = formId === 'add-product-form' || formId === 'venteForm';
        
        console.log('Soumission formulaire:', formId, 'vers', url);
        
        // Désactiver le bouton pendant l'envoi
        const submitBtn = e.target.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.dataset.originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enregistrement...';
        }
        
        showLoading(true);
        
        fetch(url, {
            method: isPost ? 'POST' : 'GET',
            body: isPost ? new FormData(e.target) : null,
            headers: isPost ? {} : { 'Content-Type': 'application/x-www-form-urlencoded' }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`Erreur HTTP: ${response.status}`);
            }
            return response.text();
        })
        .then(html => {
            document.getElementById('mainContentWrapper').innerHTML = html;
            
            // Réactiver le bouton après succès
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = submitBtn.dataset.originalText || 'Enregistrer';
            }
            
            // Scroll vers le message si présent
            const messageDiv = document.getElementById('message');
            if (messageDiv && messageDiv.innerHTML.trim()) {
                messageDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
                
                // Auto-dismiss après 5 secondes pour les messages de succès
                if (messageDiv.querySelector('.alert-success')) {
                    setTimeout(() => {
                        const alertElement = messageDiv.querySelector('.alert');
                        if (alertElement) {
                            const alert = new bootstrap.Alert(alertElement);
                            alert.close();
                        }
                    }, 5000);
                }
            }
            
            // Fermer sidebar mobile après soumission réussie
            setTimeout(() => {
                if (isMobile && sidebarOpen) {
                    closeSidebar();
                }
            }, 100);
            
            showLoading(false);
            
            // Re-attacher les event listeners
            reattachEventListeners();
        })
        .catch(error => {
            console.error('Erreur lors de la soumission:', error);
            
            // Réactiver le bouton en cas d'erreur
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = submitBtn.dataset.originalText || 'Enregistrer';
            }
            
            // Afficher un message d'erreur
            const errorDiv = document.createElement('div');
            errorDiv.id = 'message';
            errorDiv.className = 'alert alert-danger alert-dismissible fade show';
            errorDiv.innerHTML = `
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                Erreur lors de l'envoi du formulaire. Vérifiez votre connexion et réessayez.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            // Insérer avant le formulaire
            const formContainer = e.target.closest('.container');
            if (formContainer) {
                formContainer.insertBefore(errorDiv, e.target);
            }
            
            showLoading(false);
        });
    }
});

// FONCTION POUR RE-ATTACHER LES EVENT LISTENERS APRÈS AJAX
function reattachEventListeners() {
    // Re-attacher les event listeners pour les sous-menus
    document.querySelectorAll('.menu-toggle').forEach(btn => {
        // Supprimer les anciens listeners
        btn.replaceWith(btn.cloneNode(true));
        const newBtn = document.querySelector(`[data-target="${btn.dataset.target}"]`);
        if (newBtn) {
            newBtn.addEventListener('click', (e) => {
                e.preventDefault();
                const target = newBtn.dataset.target;
                const submenu = document.getElementById(target);
                if (submenu) {
                    submenu.style.display = submenu.style.display === 'block' ? 'none' : 'block';
                    newBtn.classList.toggle('open');
                }
            });
        }
    });
    
    // Re-attacher les event listeners pour les formulaires si nécessaire
    // (Les formulaires seront gérés par l'event listener global)
}

// GESTION DRAWER ET NAVBAR (corrigée)
document.addEventListener('DOMContentLoaded', function() {
    const themeToggle = document.getElementById('themeToggleMenu');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const mobileHamburger = document.getElementById('mobileHamburger');
    const mobileCloseBtn = document.getElementById('mobileCloseBtn');
    const sidebarToggleMenu = document.getElementById('sidebarToggleMenu');
    const navbarMobile = document.getElementById('navbarMobile');
    
    // Vérifier que tous les éléments existent
    if (!mobileHamburger) {
        console.error('Élément mobileHamburger non trouvé');
        return;
    }
    if (!sidebarOverlay) {
        console.error('Élément sidebarOverlay non trouvé');
        return;
    }
    
    // Fonctions pour le sidebar (maintenant définies globalement)
    window.openSidebar = openSidebar;
    window.closeSidebar = closeSidebar;
    
    // Événements mobile
    mobileHamburger.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        openSidebar();
    });
    
    if (mobileCloseBtn) {
        mobileCloseBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            closeSidebar();
        });
    }
    
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function(e) {
            e.preventDefault();
            closeSidebar();
        });
    }
    
    // Thème
    if (themeToggle) {
        themeToggle.addEventListener('click', (e) => {
            e.preventDefault();
            document.body.classList.toggle('dark');
            localStorage.setItem('theme', document.body.classList.contains('dark') ? 'dark' : 'light');
        });
    }
    
    // Toggle desktop
    if (sidebarToggleMenu) {
        const toggleIcon = sidebarToggleMenu.querySelector('i');
        if (toggleIcon) {
            sidebarToggleMenu.addEventListener('click', (e) => {
                e.preventDefault();
                document.body.classList.toggle('sidebar-collapsed');
                const collapsed = document.body.classList.contains('sidebar-collapsed');
                localStorage.setItem("sidebar", collapsed ? "collapsed" : "open");
                toggleIcon.classList.toggle('bi-toggle2-on', !collapsed);
                toggleIcon.classList.toggle('bi-toggle2-off', collapsed);
            });
        }
    }


        // Dans le script (à l'intérieur de DOMContentLoaded) :
    <?php if (!$isAdmin): ?>
    const toggleBtn = document.getElementById('sidebarToggleMenu');
    if (toggleBtn) {
        toggleBtn.style.display = 'none';
    }
    <?php endif; ?>
    
    // CHARGEMENT INITIAL
    setTimeout(() => {
        showLoading(false);
    }, 500);
    
    // Appliquer thème
    if(localStorage.getItem('theme') === 'dark') {
        document.body.classList.add('dark');
    }
    
    // GESTION RESPONSIVE (corrigée)
    function handleResponsiveLayout() {
        isMobile = window.innerWidth <= 992;
        
        if (isMobile) {
            // Mobile - Montrer navbar et cacher toggle desktop
            if (navbarMobile) {
                navbarMobile.classList.remove('hidden');
            }
            if (sidebarToggleMenu) {
                sidebarToggleMenu.style.display = 'none';
            }
            document.body.classList.remove('sidebar-collapsed');
            closeSidebar(); // Fermer drawer par défaut
        } else {
            // Desktop - Cacher navbar et montrer toggle
            if (navbarMobile) {
                navbarMobile.classList.add('hidden');
            }
            if (sidebarToggleMenu) {
                sidebarToggleMenu.style.display = 'flex';
            }
            
            // Appliquer état sidebar desktop
            const savedState = localStorage.getItem('sidebar');
            if(savedState === 'collapsed') {
                document.body.classList.add('sidebar-collapsed');
            } else {
                document.body.classList.remove('sidebar-collapsed');
            }
            
            // S'assurer que le drawer est fermé sur desktop
            closeSidebar();
        }
    }
    
    // Initialiser
    handleResponsiveLayout();
    
    // Événements resize
    window.addEventListener("resize", handleResponsiveLayout);
    window.addEventListener('orientationchange', function() {
        setTimeout(handleResponsiveLayout, 100);
    });
    
    // Fermer sidebar sur escape (seulement mobile)
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && isMobile && sidebarOpen) {
            closeSidebar();
        }
    });
    
    // Logs pour debug
    console.log('Initialisation complète. Mode mobile:', isMobile);
});

// Gestion des liens de déconnexion (non-AJAX)
document.addEventListener('click', function(e) {
    if (e.target.closest('a[href="logout.php"]')) {
        // Ne pas intercepter les liens de déconnexion
        const forms = { 
    'historiqueForm':'produit/historique.php', 
    'add-product-form':'produit/add.php', 
    'venteForm':'produit/vente.php',
    'edit-product-form':'produit/edit.php'
};
        return;
    }
});
</script>

</body>
</html>