// js/vente.js
function updatePrix() {
    const select = document.getElementById('produit_id');
    if (!select) return;

    const prixEl   = document.getElementById('prix_unitaire');
    const qteEl    = document.getElementById('quantite');
    const totalEl  = document.getElementById('total_ttc');

    if (select.value === "") {
        prixEl && (prixEl.value = "");
        qteEl && (qteEl.max = 999999);
        totalEl && (totalEl.value = "");
        return;
    }

    const option = select.options[select.selectedIndex];
    const prix   = parseFloat(option.dataset.prix) || 0;
    const stock  = parseInt(option.dataset.quantite) || 999999;

    if (prixEl) {
        prixEl.value = prix.toLocaleString('fr-FR', { minimumFractionDigits: 0 });
    }
    if (qteEl) {
        qteEl.max = stock;
        qteEl.value = Math.min(parseInt(qteEl.value) || 1, stock);
    }

    calculTotal();
}

function calculTotal() {
    const prixEl   = document.getElementById('prix_unitaire');
    if (!prixEl) return;

    const prixTexte = prixEl.value.replace(/\s/g, '').replace(/[^0-9]/g, '');
    const prix      = parseFloat(prixTexte) || 0;
    const quantite  = parseInt(document.getElementById('quantite')?.value) || 0;
    const rabais    = parseFloat(document.getElementById('rabais')?.value) || 0;
    const total     = Math.max(0, (prix * quantite) - rabais);

    const totalEl = document.getElementById('total_ttc');
    if (totalEl) {
        totalEl.value = total.toLocaleString('fr-FR', { minimumFractionDigits: 0 });
    }
}

function initVenteForm() {
    const selectProduit = document.getElementById('produit_id');
    if (selectProduit) {
        // Supprime les anciens listeners pour éviter les doublons
        selectProduit.removeEventListener('change', updatePrix);
        selectProduit.addEventListener('change', updatePrix);
        updatePrix(); // Mise à jour initiale
    }

    const inputs = ['quantite', 'rabais'];
    inputs.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.removeEventListener('input', calculTotal);
            el.addEventListener('input', calculTotal);
        }
 

    });


    // Gestion du bouton "Ajouter au panier" en AJAX (sans recharger la page)
document.addEventListener('click', function(e) {
    if (e.target.id === 'btnAjouterPanier' || e.target.closest('#btnAjouterPanier')) {
        e.preventDefault();
        
        const form = document.getElementById('venteForm');
        if (!form) return;

        const formData = new FormData(form);

        // Désactiver le bouton
        const btn = e.target.closest('button');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Ajout...';

        fetch('', {  // '' = même page (vente.php)
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(html => {
            // Remplace tout le contenu de la page pour voir le nouveau panier + message
            document.querySelector('.container').innerHTML = html;

            // Ré-initialise les scripts
            if (typeof initVenteForm === 'function') {
                initVenteForm();
            }

            // Réactive le bouton
            btn.disabled = false;
            btn.innerHTML = originalText;
        })
        .catch(err => {
            console.error('Erreur ajout panier:', err);
            btn.disabled = false;
            btn.innerHTML = originalText;
        });
    }
});
}

// Exécuter au chargement normal + après chaque chargement AJAX
document.addEventListener('DOMContentLoaded', initVenteForm);

// Si ton dashboard utilise un événement custom après AJAX, tu peux aussi écouter :
window.addEventListener('ajaxContentLoaded', initVenteForm); // optionnel