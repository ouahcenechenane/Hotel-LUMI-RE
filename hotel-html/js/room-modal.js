// ============================================================
//  room-modal.js — Détail interactif des chambres
//  Hôtel Lumière — Fenêtre modale avec animations
// ============================================================

(function initRoomModal() {

  // ── Données enrichies par type de chambre ─────────────────
  const CHAMBRES_DATA = {
    'simple': {
      nom:         'Chambre Simple',
      description: 'Un refuge intime où l\'élégance se conjugue au singulier. Nichée dans notre aile historique classée, cette chambre individuelle vous enveloppe dans une atmosphère feutrée avec vue apaisante sur nos jardins botaniques privés.',
      superficie:  '28 m²',
      etage:       '2e au 4e étage',
      vue:         'Jardins botaniques',
      capacite:    1,
      prix:        190,
      equipements: [
        { icone: '📶', label: 'Wi-Fi Fibre Haut Débit' },
        { icone: '🧴', label: 'Produits Hermès' },
        { icone: '🔒', label: 'Coffre-fort numérique' },
        { icone: '📺', label: 'Smart TV 4K 55"' },
        { icone: '❄️', label: 'Climatisation silencieuse' },
        { icone: '☕', label: 'Machine Nespresso' },
        { icone: '🍷', label: 'Minibar sélectionné' },
        { icone: '🛁', label: 'Douche à l\'italienne' },
      ],
      avantages: [
        'Service de couverture du soir',
        'Petit-déjeuner continental inclus',
        'Accès au fitness center',
        'Journal quotidien offert',
      ],
      badge: 'Simple',
      image: 'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?w=900&q=85',
    },
    'double': {
      nom:         'Chambre Double',
      description: 'L\'espace et le raffinement réunis pour deux. Un lit king-size trône au centre d\'un décor Art Déco soigneusement restauré. La terrasse privée s\'ouvre sur les toits de Paris, invitation à des matins inoubliables.',
      superficie:  '42 m²',
      etage:       '3e au 6e étage',
      vue:         'Toits de Paris',
      capacite:    2,
      prix:        290,
      equipements: [
        { icone: '🛏️', label: 'Lit King-Size (200×200)' },
        { icone: '🌿', label: 'Terrasse privée' },
        { icone: '🛁', label: 'Baignoire îlot' },
        { icone: '📶', label: 'Wi-Fi Fibre Haut Débit' },
        { icone: '🍷', label: 'Minibar Premium' },
        { icone: '📺', label: 'Smart TV 4K 65"' },
        { icone: '🧴', label: 'Produits Chanel' },
        { icone: '❄️', label: 'Double climatisation' },
      ],
      avantages: [
        'Accueil champagne à l\'arrivée',
        'Petit-déjeuner gastronomique inclus',
        'Accès spa (2h/séjour)',
        'Service de majordome 12h/24h',
        'Transfert aéroport inclus',
      ],
      badge: 'Double',
      image: 'https://images.unsplash.com/photo-1586105251261-72a756497a11?w=900&q=85',
    },
    'couple': {
      nom:         'Chambre Couple',
      description: 'Une ode à l\'amour parisien. Jacuzzi privatif sous les étoiles, champagne rosé et un tapis de pétales de roses rouges vous attendent. Chaque détail a été pensé pour créer des instants magiques à deux.',
      superficie:  '52 m²',
      etage:       '5e au 7e étage',
      vue:         'Panorama Paris & Tour Eiffel',
      capacite:    2,
      prix:        390,
      equipements: [
        { icone: '🛁', label: 'Jacuzzi privatif' },
        { icone: '🥂', label: 'Champagne à l\'arrivée' },
        { icone: '🌹', label: 'Décoration romantique' },
        { icone: '🛏️', label: 'Lit Emperor (220×220)' },
        { icone: '🌃', label: 'Vue Tour Eiffel' },
        { icone: '🎵', label: 'Système audio Bose' },
        { icone: '🕯️', label: 'Bougies & aromathérapie' },
        { icone: '🍓', label: 'Plateau fruits & chocolats' },
      ],
      avantages: [
        'Dîner romantique inclus (1 soir)',
        'Accès spa illimité',
        'Petit-déjeuner au lit chaque matin',
        'Séance photo souvenir offerte',
        'Service de majordome 24h/24h',
        'Sortie en barque sur la Seine',
      ],
      badge: 'Couple',
      image: 'https://images.unsplash.com/photo-1522771739844-6a9f6d5f14af?w=900&q=85',
    },
    'suite-luxe': {
      nom:         'Suite Luxe',
      description: 'Un appartement parisien d\'exception. La Suite Luxe déploie ses 85m² en deux espaces distincts — un salon Louis XV et une chambre panoramique — sous la garde attentive de votre majordome personnel disponible à toute heure.',
      superficie:  '85 m²',
      etage:       '6e au 8e étage',
      vue:         'Vue 180° sur Paris',
      capacite:    3,
      prix:        590,
      equipements: [
        { icone: '🛋️', label: 'Salon privé Louis XV' },
        { icone: '👔', label: 'Majordome 24h/24' },
        { icone: '🌃', label: 'Vue panoramique 180°' },
        { icone: '🛁', label: 'Spa bain & hammam privé' },
        { icone: '🍽️', label: 'Salle à manger privée' },
        { icone: '🎭', label: 'Conciergerie dédiée' },
        { icone: '🚗', label: 'Voiture avec chauffeur' },
        { icone: '🍷', label: 'Cave à vins privée' },
      ],
      avantages: [
        'Petit-déjeuner gastronomique 3 étoiles',
        'Accès spa & fitness illimité',
        'Transferts privés inclus',
        'Blanchisserie express offerte',
        'Réservations exclusives restaurant',
        'Accès salon VIP aéroport',
        'Check-in/out flexible',
      ],
      badge: 'Suite Luxe',
      image: 'https://images.unsplash.com/photo-1590490360182-c33d57733427?w=900&q=85',
    },
    'suite-royale': {
      nom:         'Suite Royale',
      description: 'Le summum absolu de l\'art de vivre parisien. Notre joyau au dernier étage s\'étend sur 220m² avec sa piscine privée chauffée, sa terrasse à 180°, son chef personnel Michelin et son équipe de service exclusive. Une expérience unique au monde.',
      superficie:  '220 m²',
      etage:       'Dernier étage (penthouse)',
      vue:         'Paris à 360° & Tour Eiffel',
      capacite:    4,
      prix:        1200,
      equipements: [
        { icone: '🏊', label: 'Piscine privée chauffée' },
        { icone: '🌿', label: 'Terrasse 180° panoramique' },
        { icone: '👨‍🍳', label: 'Chef étoilé personnel' },
        { icone: '👔', label: 'Équipe de service dédiée' },
        { icone: '🚗', label: 'Rolls-Royce avec chauffeur' },
        { icone: '💆', label: 'Spa privatif complet' },
        { icone: '🎻', label: 'Quatuor à cordes sur demande' },
        { icone: '✈️', label: 'Hélipad & jet privé' },
      ],
      avantages: [
        'Chef étoilé Michelin à demeure',
        'Accès VIP tous les restaurants',
        'Suite parentale + 2 chambres',
        'Sécurité privée incluse',
        'Conciergerie ultra-luxe 24h',
        'Collection de vins & spiritueux',
        'Événement privé offert (4h)',
        'Séjour illimité au spa',
      ],
      badge: 'Suite Royale',
      image: 'https://images.unsplash.com/photo-1564501049412-61c2a3083791?w=900&q=85',
    },
  };

  // ── Création de la modal dans le DOM ──────────────────────
  function creerModal() {
    const modal = document.createElement('div');
    modal.id = 'modal-chambre';
    modal.className = 'modal-chambre-overlay';
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-label', 'Détail chambre');
    modal.innerHTML = `
      <div class="modal-chambre-conteneur" id="modal-chambre-box">

        <!-- Colonne image -->
        <div class="modal-chambre-image-col">
          <img id="modal-img" src="" alt="" class="modal-chambre-img" loading="lazy">
          <div class="modal-chambre-badge-over" id="modal-badge"></div>
          <div class="modal-chambre-prix-over">
            <span id="modal-prix-val"></span>
            <small>€ / nuit</small>
          </div>
        </div>

        <!-- Colonne contenu -->
        <div class="modal-chambre-contenu">

          <!-- Bouton fermer -->
          <button class="modal-chambre-close" id="modal-close" aria-label="Fermer">
            <span>✕</span>
          </button>

          <!-- En-tête -->
          <div class="modal-chambre-header">
            <p class="titre-deco">✦ Hébergement</p>
            <div class="sep" style="margin:0.6rem 0 1rem"></div>
            <h2 class="modal-chambre-titre" id="modal-nom"></h2>
            <div class="modal-chambre-meta" id="modal-meta"></div>
          </div>

          <!-- Description -->
          <p class="modal-chambre-desc" id="modal-desc"></p>

          <!-- Équipements -->
          <div class="modal-chambre-section">
            <h4 class="modal-section-titre">Équipements & Services</h4>
            <div class="modal-equipements-grille" id="modal-equip"></div>
          </div>

          <!-- Avantages inclus -->
          <div class="modal-chambre-section">
            <h4 class="modal-section-titre">Inclus dans votre séjour</h4>
            <ul class="modal-avantages-liste" id="modal-avantages"></ul>
          </div>

          <!-- CTA -->
          <div class="modal-chambre-cta">
            <div class="modal-prix-affiche">
              <span class="modal-prix-num" id="modal-prix-cta"></span>
              <span class="modal-prix-unit">€ / nuit</span>
            </div>
            <a href="#" id="modal-btn-reserver" class="btn btn-or btn-pulse">
              <span>Réserver cette chambre</span>
            </a>
          </div>

        </div><!-- /contenu -->
      </div><!-- /conteneur -->
    `;
    document.body.appendChild(modal);
    return modal;
  }

  // ── Remplissage de la modal ───────────────────────────────
  function ouvrirModal(type) {
    const data = CHAMBRES_DATA[type];
    if (!data) return;

    const modal = document.getElementById('modal-chambre') || creerModal();

    // Image
    document.getElementById('modal-img').src = data.image;
    document.getElementById('modal-img').alt = data.nom;

    // Badge & prix
    document.getElementById('modal-badge').textContent       = data.badge;
    document.getElementById('modal-prix-val').textContent    = data.prix.toLocaleString('fr-FR');
    document.getElementById('modal-prix-cta').textContent    = data.prix.toLocaleString('fr-FR');

    // Textes
    document.getElementById('modal-nom').textContent  = data.nom;
    document.getElementById('modal-desc').textContent = data.description;

    // Meta (superficie / étage / vue / capacité)
    document.getElementById('modal-meta').innerHTML = `
      <span class="modal-meta-item"><em>📐</em> ${data.superficie}</span>
      <span class="modal-meta-sep">·</span>
      <span class="modal-meta-item"><em>🏢</em> ${data.etage}</span>
      <span class="modal-meta-sep">·</span>
      <span class="modal-meta-item"><em>🌅</em> ${data.vue}</span>
      <span class="modal-meta-sep">·</span>
      <span class="modal-meta-item"><em>👤</em> ${data.capacite} pers. max</span>
    `;

    // Équipements
    const equipEl = document.getElementById('modal-equip');
    equipEl.innerHTML = data.equipements.map(e => `
      <div class="modal-equip-item">
        <span class="modal-equip-icone">${e.icone}</span>
        <span class="modal-equip-label">${e.label}</span>
      </div>
    `).join('');

    // Avantages
    const avantEl = document.getElementById('modal-avantages');
    avantEl.innerHTML = data.avantages.map(a => `
      <li class="modal-avantage-item">
        <span class="modal-avantage-check">✦</span>
        ${a}
      </li>
    `).join('');

    // Lien réserver
    document.getElementById('modal-btn-reserver').href =
      `reservations/reservation.html?type=${type}`;

    // Afficher la modal avec animation
    modal.classList.add('open');
    document.body.classList.add('modal-ouverte');

    // Animation staggerée des items
    setTimeout(() => {
      document.querySelectorAll('.modal-equip-item').forEach((el, i) => {
        el.style.animationDelay = `${i * 0.05}s`;
        el.classList.add('anim-stagger');
      });
      document.querySelectorAll('.modal-avantage-item').forEach((el, i) => {
        el.style.animationDelay = `${i * 0.06}s`;
        el.classList.add('anim-stagger');
      });
    }, 150);
  }

  // ── Fermeture de la modal ──────────────────────────────────
  function fermerModal() {
    const modal = document.getElementById('modal-chambre');
    if (!modal) return;
    modal.classList.remove('open');
    modal.classList.add('closing');
    document.body.classList.remove('modal-ouverte');
    setTimeout(() => {
      modal.classList.remove('closing');
      // Réinitialiser les animations staggerées
      document.querySelectorAll('.modal-equip-item, .modal-avantage-item').forEach(el => {
        el.classList.remove('anim-stagger');
        el.style.animationDelay = '';
      });
    }, 420);
  }

  // ── Initialisation ─────────────────────────────────────────
  function init() {
    // Créer la modal en avance
    if (!document.getElementById('modal-chambre')) creerModal();

    // Rendre chaque card cliquable (zone hors bouton "Réserver")
    document.querySelectorAll('.chambre-card').forEach(card => {
      card.style.cursor = 'pointer';

      card.addEventListener('click', function (e) {
        // Ignorer le clic si c'est sur le bouton "Réserver"
        if (e.target.closest('.btn')) return;
        const type = this.dataset.type;
        if (type) ouvrirModal(type);
      });

      // Indicateur visuel : survol
      card.addEventListener('mouseenter', function () {
        if (!this.querySelector('.card-detail-hint')) {
          const hint = document.createElement('div');
          hint.className = 'card-detail-hint';
          hint.textContent = 'Voir les détails';
          this.querySelector('.chambre-img').appendChild(hint);
        }
      });
    });

    // Fermeture via bouton ✕
    document.addEventListener('click', e => {
      if (e.target.closest('#modal-close')) fermerModal();
    });

    // Fermeture en cliquant sur l'overlay (hors conteneur)
    document.addEventListener('click', e => {
      if (e.target.id === 'modal-chambre') fermerModal();
    });

    // Fermeture via touche Échap
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') fermerModal();
    });
  }

  // Lancer après le chargement du DOM
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();