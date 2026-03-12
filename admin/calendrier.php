<?php
session_start();
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();
$pdo = getDB();
$page_active = 'calendrier';

// Auto-create tables
try { $pdo->exec("CREATE TABLE IF NOT EXISTS piscine_reservations (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, reference VARCHAR(20) NOT NULL UNIQUE, piscine_id INT UNSIGNED, nom_client VARCHAR(200) NOT NULL, email_client VARCHAR(191) NOT NULL, telephone VARCHAR(20), date_res DATE NOT NULL, heure_debut TIME NOT NULL, heure_fin TIME NOT NULL, nb_personnes TINYINT NOT NULL DEFAULT 2, tarif_total DECIMAL(10,2) DEFAULT 0, statut ENUM('en_attente','confirmee','annulee') DEFAULT 'en_attente', message TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB"); } catch(Exception $e){}

// ── Charger tous les événements ────────────────────────────────
$events = [];

// Chambres
$chambres = $pdo->query("SELECT r.id, r.reference, r.nom_client, r.email_client, r.telephone, r.date_arrivee, r.date_depart, r.nb_personnes, r.prix_total, r.statut, rt.nom AS detail FROM reservations r JOIN rooms rm ON r.room_id=rm.id JOIN room_types rt ON rm.room_type_id=rt.id WHERE r.statut != 'annulee'")->fetchAll();
foreach($chambres as $r) {
    $events[] = [
        'id'          => 'ch-'.$r['id'],
        'title'       => $r['nom_client'].' · '.$r['detail'],
        'start'       => $r['date_arrivee'],
        'end'         => $r['date_depart'],
        'className'   => 'ev-chambre',
        'service'     => 'chambre',
        'extendedProps' => [
            'reference'   => $r['reference'],
            'client'      => $r['nom_client'],
            'email'       => $r['email_client'],
            'telephone'   => $r['telephone']??'—',
            'detail'      => $r['detail'],
            'date_debut'  => date('d/m/Y', strtotime($r['date_arrivee'])),
            'date_fin'    => date('d/m/Y', strtotime($r['date_depart'])),
            'personnes'   => $r['nb_personnes'],
            'montant'     => number_format($r['prix_total'],0,',',' ').' €',
            'statut'      => $r['statut'],
            'service_label'=> 'Chambre',
        ]
    ];
}

// Salles
$salles = $pdo->query("SELECT * FROM event_reservations WHERE statut != 'annulee'")->fetchAll();
foreach($salles as $r) {
    $events[] = [
        'id'         => 'ev-'.$r['id'],
        'title'      => $r['nom_client'].' · '.$r['type_event'],
        'start'      => $r['date_event'],
        'className'  => 'ev-salle',
        'service'    => 'salle',
        'extendedProps' => [
            'reference'    => $r['reference'],
            'client'       => $r['nom_client'],
            'email'        => $r['email_client'],
            'telephone'    => $r['telephone']??'—',
            'detail'       => $r['type_event'],
            'date_debut'   => date('d/m/Y', strtotime($r['date_event'])),
            'date_fin'     => '—',
            'personnes'    => $r['nb_invites'],
            'montant'      => $r['budget'] ? number_format($r['budget'],0,',',' ').' €' : '—',
            'statut'       => $r['statut'],
            'service_label'=> 'Salle des Fêtes',
        ]
    ];
}

// Piscines
try {
    $piscines = $pdo->query("SELECT pr.*, COALESCE(p.nom,'Piscine') AS piscine_nom FROM piscine_reservations pr LEFT JOIN piscines p ON pr.piscine_id=p.id WHERE pr.statut != 'annulee'")->fetchAll();
    foreach($piscines as $r) {
        $events[] = [
            'id'         => 'pi-'.$r['id'],
            'title'      => $r['nom_client'].' · '.$r['piscine_nom'],
            'start'      => $r['date_res'].'T'.$r['heure_debut'],
            'end'        => $r['date_res'].'T'.$r['heure_fin'],
            'className'  => 'ev-piscine',
            'service'    => 'piscine',
            'extendedProps' => [
                'reference'    => $r['reference'],
                'client'       => $r['nom_client'],
                'email'        => $r['email_client'],
                'telephone'    => $r['telephone']??'—',
                'detail'       => $r['piscine_nom'].' '.$r['heure_debut'].' → '.$r['heure_fin'],
                'date_debut'   => date('d/m/Y', strtotime($r['date_res'])),
                'date_fin'     => '—',
                'personnes'    => $r['nb_personnes'],
                'montant'      => $r['tarif_total'] ? number_format($r['tarif_total'],0,',',' ').' €' : '—',
                'statut'       => $r['statut'],
                'service_label'=> 'Piscine',
            ]
        ];
    }
} catch(Exception $e){}

// Restaurant
$restaurants = $pdo->query("SELECT * FROM restaurant_reservations WHERE statut != 'annulee'")->fetchAll();
foreach($restaurants as $r) {
    $events[] = [
        'id'         => 'rs-'.$r['id'],
        'title'      => $r['nom_client'].' · '.ucfirst($r['menu']),
        'start'      => $r['date_res'].'T'.$r['heure_res'],
        'className'  => 'ev-restaurant',
        'service'    => 'restaurant',
        'extendedProps' => [
            'reference'    => $r['reference'],
            'client'       => $r['nom_client'],
            'email'        => $r['email_client'],
            'telephone'    => $r['telephone']??'—',
            'detail'       => 'Menu '.ucfirst($r['menu']).' · '.$r['nb_couverts'].' couverts',
            'date_debut'   => date('d/m/Y', strtotime($r['date_res'])).' à '.substr($r['heure_res'],0,5),
            'date_fin'     => '—',
            'personnes'    => $r['nb_couverts'],
            'montant'      => '—',
            'statut'       => $r['statut'],
            'service_label'=> 'Restaurant',
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Calendrier — Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;1,400&family=Montserrat:wght@300;400;500&family=Cinzel:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../hotel-html/css/style.css">
  <link rel="stylesheet" href="css/admin.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/6.1.10/index.global.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/6.1.10/index.global.min.js"></script>
  <style>
    #calendar { min-height: 680px; }
    .detail-row { display:flex; justify-content:space-between; padding:0.5rem 0; border-bottom:1px solid rgba(255,255,255,0.05); font-size:0.85rem; }
    .detail-row:last-child { border-bottom:none; }
    .detail-label { color:var(--gris); font-size:0.72rem; letter-spacing:0.1em; }
    .detail-val { color:var(--ivoire-2); text-align:right; max-width:60%; }
  </style>
</head>
<body>
<div class="admin-layout">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <main class="admin-contenu">
    <h1 class="admin-titre">📅 Calendrier des Réservations</h1>
    <div class="admin-breadcrumb"><a href="dashboard.php">Dashboard</a> / Calendrier</div>

    <!-- Légende & Filtres -->
    <div class="panel" style="padding:1.2rem 1.8rem">
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem">
        <div class="cal-legend">
          <div class="cal-legend-item"><div class="cal-dot" style="background:#1565C0"></div> Chambres</div>
          <div class="cal-legend-item"><div class="cal-dot" style="background:#6A1B9A"></div> Salles des Fêtes</div>
          <div class="cal-legend-item"><div class="cal-dot" style="background:#00695C"></div> Piscines</div>
          <div class="cal-legend-item"><div class="cal-dot" style="background:#BF360C"></div> Restaurant</div>
        </div>
        <div style="display:flex;gap:0.5rem;flex-wrap:wrap">
          <button class="filtre-btn actif" data-filter="all" onclick="filtrerCal('all',this)">Tous</button>
          <button class="filtre-btn" data-filter="chambre"    onclick="filtrerCal('chambre',this)">🛏️ Chambres</button>
          <button class="filtre-btn" data-filter="salle"      onclick="filtrerCal('salle',this)">🎉 Salles</button>
          <button class="filtre-btn" data-filter="piscine"    onclick="filtrerCal('piscine',this)">🏊 Piscines</button>
          <button class="filtre-btn" data-filter="restaurant" onclick="filtrerCal('restaurant',this)">🍽️ Restaurant</button>
        </div>
      </div>
    </div>

    <div class="panel">
      <div id="calendar"></div>
    </div>
  </main>
</div>

<!-- Modal Détail -->
<div class="modal-overlay" id="m-detail">
  <div class="modal-box" style="max-width:480px">
    <button class="modal-close" onclick="document.getElementById('m-detail').classList.remove('open')">✕</button>
    <div style="display:flex;align-items:center;gap:0.8rem;margin-bottom:1.5rem">
      <div id="det-icon" style="font-size:2rem"></div>
      <div>
        <div class="modal-titre" style="margin-bottom:0.2rem;font-size:1.4rem" id="det-titre">—</div>
        <span id="det-badge" class="badge-statut">—</span>
      </div>
    </div>
    <div id="det-body"></div>
    <div style="margin-top:1.5rem;display:flex;gap:0.6rem">
      <a id="det-link" href="#" class="btn btn-contour btn-sm" style="flex:1;justify-content:center"><span>Voir dans la liste →</span></a>
    </div>
  </div>
</div>

<script>
const allEvents = <?= json_encode(array_values($events)) ?>;
let currentFilter = 'all';
let calendar;

const icons = {chambre:'🛏️', salle:'🎉', piscine:'🏊', restaurant:'🍽️'};
const links = {chambre:'reservations_chambres.php',salle:'reservations_salles.php',piscine:'reservations_piscines.php',restaurant:'reservations_restaurants.php'};

document.addEventListener('DOMContentLoaded', () => {
  const el = document.getElementById('calendar');
  calendar = new FullCalendar.Calendar(el, {
    initialView: 'dayGridMonth',
    locale: 'fr',
    height: 'auto',
    headerToolbar: {
      left: 'prev,next today',
      center: 'title',
      right: 'dayGridMonth,timeGridWeek,listMonth'
    },
    buttonText: { today:'Aujourd\'hui', month:'Mois', week:'Semaine', list:'Liste' },
    events: allEvents,
    eventClick: function(info) {
      const p = info.event.extendedProps;
      const svc = info.event.extendedProps.service_label || '—';
      const svcKey = info.event.classNames.find(c=>c.startsWith('ev-'))?.replace('ev-','') || 'chambre';
      document.getElementById('det-icon').textContent = icons[svcKey]||'📋';
      document.getElementById('det-titre').textContent = p.client || info.event.title;
      const badgeEl = document.getElementById('det-badge');
      badgeEl.textContent = (p.statut||'').replace('_',' ');
      badgeEl.className = 'badge-statut badge-'+(p.statut||'');
      document.getElementById('det-body').innerHTML = `
        <div class="detail-row"><span class="detail-label">RÉFÉRENCE</span><span class="detail-val" style="color:var(--or);font-family:'Cinzel',serif;font-size:0.7rem">${p.reference||'—'}</span></div>
        <div class="detail-row"><span class="detail-label">SERVICE</span><span class="detail-val">${svc}</span></div>
        <div class="detail-row"><span class="detail-label">DÉTAIL</span><span class="detail-val">${p.detail||'—'}</span></div>
        <div class="detail-row"><span class="detail-label">DATE DÉBUT</span><span class="detail-val">${p.date_debut||'—'}</span></div>
        <div class="detail-row"><span class="detail-label">DATE FIN</span><span class="detail-val">${p.date_fin||'—'}</span></div>
        <div class="detail-row"><span class="detail-label">EMAIL</span><span class="detail-val" style="font-size:0.78rem">${p.email||'—'}</span></div>
        <div class="detail-row"><span class="detail-label">TÉLÉPHONE</span><span class="detail-val">${p.telephone||'—'}</span></div>
        <div class="detail-row"><span class="detail-label">PERSONNES</span><span class="detail-val">${p.personnes||'—'}</span></div>
        <div class="detail-row"><span class="detail-label">MONTANT</span><span class="detail-val" style="color:var(--or)">${p.montant||'—'}</span></div>
      `;
      document.getElementById('det-link').href = links[svcKey]||'#';
      document.getElementById('m-detail').classList.add('open');
    },
    eventDidMount: function(info) {
      info.el.title = info.event.title;
    }
  });
  calendar.render();
});

function filtrerCal(filter, btn) {
  currentFilter = filter;
  document.querySelectorAll('.filtre-btn').forEach(b=>b.classList.remove('actif'));
  btn.classList.add('actif');
  const filtered = filter === 'all' ? allEvents : allEvents.filter(e=>e.service===filter);
  calendar.removeAllEvents();
  calendar.addEventSource(filtered);
}

document.getElementById('m-detail').addEventListener('click', e=>{
  if(e.target===document.getElementById('m-detail')) document.getElementById('m-detail').classList.remove('open');
});
</script>
</body>
</html>
