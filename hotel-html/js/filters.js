// ============================================================
//  filters.js — Filtres chambres par catégorie
// ============================================================

document.addEventListener('DOMContentLoaded', () => {

  const filtres = document.querySelectorAll('.filtre-btn');
  const cards   = document.querySelectorAll('.chambre-card');

  if (!filtres.length || !cards.length) return;

  filtres.forEach(btn => {
    btn.addEventListener('click', () => {
      // Activer le bouton
      filtres.forEach(b => b.classList.remove('actif'));
      btn.classList.add('actif');

      const filtre = btn.dataset.filtre;

      cards.forEach(card => {
        const type = card.dataset.type;
        const match = filtre === 'tous' || type === filtre;

        if (match) {
          card.style.display = '';
          // Animation d'apparition
          card.style.opacity = '0';
          card.style.transform = 'translateY(20px)';
          requestAnimationFrame(() => {
            card.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
            card.style.opacity    = '1';
            card.style.transform  = 'translateY(0)';
          });
        } else {
          card.style.display = 'none';
        }
      });

      // Mise à jour du compteur
      const visible = [...cards].filter(c => c.style.display !== 'none').length;
      const compteur = document.getElementById('compteur-chambres');
      if (compteur) {
        compteur.textContent = `${visible} chambre${visible > 1 ? 's' : ''} disponible${visible > 1 ? 's' : ''}`;
      }
    });
  });

  // Déclencher le filtre initial
  const premier = document.querySelector('.filtre-btn[data-filtre="tous"]');
  if (premier) premier.click();

  // ── Tri par prix ──────────────────────────────────────────
  const selectTri = document.getElementById('tri-prix');
  if (selectTri) {
    selectTri.addEventListener('change', () => {
      const grille  = document.getElementById('grille-chambres');
      if (!grille) return;
      const items   = [...grille.querySelectorAll('.chambre-card:not([style*="display: none"])')];
      const order   = selectTri.value;

      items.sort((a, b) => {
        const pa = parseFloat(a.dataset.prix || 0);
        const pb = parseFloat(b.dataset.prix || 0);
        return order === 'asc' ? pa - pb : pb - pa;
      });

      items.forEach(item => grille.appendChild(item));
    });
  }
});
