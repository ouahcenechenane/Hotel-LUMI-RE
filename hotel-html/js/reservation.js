// ============================================================
//  reservation.js — Calcul prix + soumission formulaire
// ============================================================

document.addEventListener('DOMContentLoaded', () => {

  const form        = document.getElementById('form-reservation');
  if (!form) return;

  const selectType  = document.getElementById('type-chambre');
  const dateArrivee = document.getElementById('date-arrivee');
  const dateDepart  = document.getElementById('date-depart');
  const nbPersonnes = document.getElementById('nb-personnes');
  const prixRecap   = document.getElementById('prix-recap');
  const prixNuit    = document.getElementById('recap-nuit');
  const prixNuits   = document.getElementById('recap-nuits');
  const prixSup     = document.getElementById('recap-supplement');
  const prixTotal   = document.getElementById('recap-total');
  const btnSubmit   = document.getElementById('btn-reserver');
  const confirmBox  = document.getElementById('confirmation');

  // Date min = aujourd'hui
  const today = new Date().toISOString().split('T')[0];
  if (dateArrivee) dateArrivee.min = today;
  if (dateDepart)  dateDepart.min  = today;

  dateArrivee?.addEventListener('change', () => {
    if (dateDepart) {
      const min = new Date(dateArrivee.value);
      min.setDate(min.getDate() + 1);
      dateDepart.min = min.toISOString().split('T')[0];
      if (dateDepart.value && dateDepart.value <= dateArrivee.value) dateDepart.value = '';
    }
    calculerPrix();
  });
  dateDepart?.addEventListener('change', calculerPrix);
  selectType?.addEventListener('change', calculerPrix);

  async function calculerPrix() {
    const type    = selectType?.value;
    const arrivee = dateArrivee?.value;
    const depart  = dateDepart?.value;

    if (!type || !arrivee || !depart) {
      if (prixRecap) prixRecap.style.display = 'none';
      return;
    }

    try {
      const res  = await fetch(`/hotel-luxe/api/calcul_prix.php?type=${type}&date_arrivee=${arrivee}&date_depart=${depart}`);
      const data = await res.json();

      if (!data.success) return;

      if (prixRecap) prixRecap.style.display = 'block';
      if (prixNuit)  prixNuit.textContent  = data.prix_nuit.toFixed(2) + ' €';
      if (prixNuits) prixNuits.textContent = data.nuits + ' nuit(s) × ' + data.prix_nuit.toFixed(2) + ' € = ' + data.sous_total.toFixed(2) + ' €';
      if (prixSup)   prixSup.textContent   = data.supplement > 0 ? 'Supplément weekend : +' + data.supplement.toFixed(2) + ' €' : '';
      if (prixTotal) prixTotal.textContent  = data.prix_total.toFixed(2) + ' €';

      // Mettre à jour le max de personnes
      if (nbPersonnes && data.capacite_max) {
        nbPersonnes.max = data.capacite_max;
        if (parseInt(nbPersonnes.value) > data.capacite_max) nbPersonnes.value = data.capacite_max;
      }

      // Vérifier la disponibilité
      verifierDispo(type, arrivee, depart);

    } catch (err) {
      console.error('Erreur calcul prix:', err);
    }
  }

  let dispoBadge = document.getElementById('dispo-badge');
  async function verifierDispo(type, arrivee, depart) {
    if (!dispoBadge) return;
    dispoBadge.textContent = '⏳ Vérification…';
    dispoBadge.style.color = '#FF9800';

    try {
      const res  = await fetch('/hotel-luxe/api/verifier_disponibilite.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ type, date_arrivee: arrivee, date_depart: depart })
      });
      const data = await res.json();

      if (data.disponible) {
        dispoBadge.textContent = '✓ Disponible';
        dispoBadge.style.color = '#4CAF50';
        if (btnSubmit) btnSubmit.disabled = false;
      } else {
        dispoBadge.textContent = '✗ Indisponible pour ces dates';
        dispoBadge.style.color = '#E53935';
        if (btnSubmit) btnSubmit.disabled = true;
      }
    } catch { dispoBadge.textContent = ''; }
  }

  // ── Soumission ────────────────────────────────────────────
  form.addEventListener('submit', async e => {
    e.preventDefault();
    if (!validerForm()) return;

    const orig = btnSubmit.innerHTML;
    btnSubmit.innerHTML = '<span class="spinner"></span> Réservation en cours…';
    btnSubmit.disabled  = true;

    const payload = {
      nom:              document.getElementById('nom')?.value,
      email:            document.getElementById('email')?.value,
      telephone:        document.getElementById('telephone')?.value,
      type:             selectType?.value,
      date_arrivee:     dateArrivee?.value,
      date_depart:      dateDepart?.value,
      nb_personnes:     nbPersonnes?.value,
      demandes_speciales: document.getElementById('demandes')?.value,
    };

    try {
      const res  = await fetch('/hotel-luxe/api/reserver_chambre.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const data = await res.json();

      if (data.success) {
        afficherConfirmation(data);
        form.reset();
        if (prixRecap) prixRecap.style.display = 'none';
      } else {
        window.afficherToast?.(data.message, 'erreur');
        btnSubmit.innerHTML = orig;
        btnSubmit.disabled  = false;
      }
    } catch {
      window.afficherToast?.('Erreur réseau. Veuillez réessayer.', 'erreur');
      btnSubmit.innerHTML = orig;
      btnSubmit.disabled  = false;
    }
  });

  function validerForm() {
    let ok = true;
    form.querySelectorAll('[required]').forEach(el => {
      el.style.borderColor = '';
      if (!el.value.trim()) {
        el.style.borderColor = '#E53935';
        ok = false;
      }
    });
    if (!ok) window.afficherToast?.('Veuillez remplir tous les champs obligatoires.', 'erreur');
    return ok;
  }

  function afficherConfirmation(data) {
    if (!confirmBox) {
      window.afficherToast?.(`✓ Réservation ${data.reference} confirmée ! Total : ${data.prix_total}€`, 'succes');
      return;
    }
    confirmBox.innerHTML = `
      <div style="text-align:center;padding:3rem;">
        <div style="font-size:3rem;margin-bottom:1rem;">✦</div>
        <h3 style="font-family:'Cormorant Garamond',serif;font-size:2rem;color:#C9A84C;margin-bottom:1rem;">Réservation Confirmée</h3>
        <p style="color:#8A8A8A;margin-bottom:0.5rem;">Référence</p>
        <p style="font-family:'Cinzel',serif;font-size:1.2rem;color:#F5F0E8;margin-bottom:1.5rem;">${data.reference}</p>
        <p style="color:#8A8A8A;">Montant total : <strong style="color:#C9A84C;">${data.prix_total} €</strong></p>
        <p style="color:#8A8A8A;margin-top:0.5rem;">Un email de confirmation vous a été envoyé.</p>
      </div>
    `;
    confirmBox.style.display = 'block';
    form.style.display = 'none';
  }
});
