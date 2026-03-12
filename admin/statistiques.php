<?php
session_start();
require_once __DIR__ . '/../includes/functions.php';
requireAnyAuth();
requirePermission('voir_stats');
$pdo = getDB();
$page_active = 'statistiques';

try { $pdo->exec("CREATE TABLE IF NOT EXISTS piscine_reservations (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, reference VARCHAR(20) NOT NULL UNIQUE, piscine_id INT UNSIGNED, nom_client VARCHAR(200) NOT NULL, email_client VARCHAR(191) NOT NULL, telephone VARCHAR(20), date_res DATE NOT NULL, heure_debut TIME NOT NULL, heure_fin TIME NOT NULL, nb_personnes TINYINT NOT NULL DEFAULT 2, tarif_total DECIMAL(10,2) DEFAULT 0, statut ENUM('en_attente','confirmee','annulee') DEFAULT 'en_attente', message TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB"); } catch(Exception $e){}

// CA par mois (12 derniers mois)
$ca_mois = $pdo->query("SELECT DATE_FORMAT(created_at,'%Y-%m') as mois, SUM(prix_total) as ca, COUNT(*) as nb FROM reservations WHERE statut IN ('confirmee','terminee') AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH) GROUP BY DATE_FORMAT(created_at,'%Y-%m') ORDER BY mois")->fetchAll();

// Répartition par type
$par_type = $pdo->query("SELECT rt.nom, COUNT(*) as nb, SUM(r.prix_total) as ca FROM reservations r JOIN rooms rm ON r.room_id=rm.id JOIN room_types rt ON rm.room_type_id=rt.id WHERE r.statut NOT IN ('annulee') GROUP BY rt.id ORDER BY nb DESC")->fetchAll();

// Stats restaurant
$rest_stats = $pdo->query("SELECT menu, COUNT(*) as nb FROM restaurant_reservations WHERE statut != 'annulee' GROUP BY menu")->fetchAll();
$rest_auj   = (int)$pdo->query("SELECT COUNT(*) FROM restaurant_reservations WHERE date_res=CURDATE()")->fetchColumn();

// Stats événements
$evt_stats  = $pdo->query("SELECT statut, COUNT(*) as nb FROM event_reservations GROUP BY statut")->fetchAll();

// Totaux globaux
$totals = [
    'chambres'   => (int)$pdo->query("SELECT COUNT(*) FROM reservations")->fetchColumn(),
    'salles'     => (int)$pdo->query("SELECT COUNT(*) FROM event_reservations")->fetchColumn(),
    'piscines'   => (int)$pdo->query("SELECT COUNT(*) FROM piscine_reservations")->fetchColumn(),
    'restaurant' => (int)$pdo->query("SELECT COUNT(*) FROM restaurant_reservations")->fetchColumn(),
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Statistiques — Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;1,400&family=Montserrat:wght@300;400;500&family=Cinzel:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../hotel-html/css/style.css">
  <link rel="stylesheet" href="css/admin.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
</head>
<body>
<div class="admin-layout">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <main class="admin-contenu">
    <h1 class="admin-titre">📈 Statistiques Globales</h1>
    <div class="admin-breadcrumb"><a href="dashboard.php">Dashboard</a> / Statistiques</div>

    <!-- Totaux par service -->
    <div class="stats-grille" style="grid-template-columns:repeat(4,1fr)">
      <div class="stat-carte" style="--accent:#1565C0"><div class="stat-carte-icon">🛏️</div><div class="stat-val"><?= $totals['chambres'] ?></div><div class="stat-label">Chambres</div></div>
      <div class="stat-carte" style="--accent:#6A1B9A"><div class="stat-carte-icon">🎉</div><div class="stat-val"><?= $totals['salles'] ?></div><div class="stat-label">Événements</div></div>
      <div class="stat-carte" style="--accent:#00695C"><div class="stat-carte-icon">🏊</div><div class="stat-val"><?= $totals['piscines'] ?></div><div class="stat-label">Piscines</div></div>
      <div class="stat-carte" style="--accent:#BF360C"><div class="stat-carte-icon">🍽️</div><div class="stat-val"><?= $totals['restaurant'] ?></div><div class="stat-label">Restaurant (<?= $rest_auj ?> auj.)</div></div>
    </div>

    <div class="charts-grille">
      <!-- CA Mensuel -->
      <div class="chart-panel">
        <div class="panel-header"><h2 class="panel-title" style="font-size:1.1rem">CA Mensuel — Chambres (12 mois)</h2></div>
        <canvas id="chartCA"></canvas>
      </div>
      <!-- Restaurant par menu -->
      <div class="chart-panel">
        <div class="panel-header"><h2 class="panel-title" style="font-size:1.1rem">Restaurant — Répartition Menus</h2></div>
        <canvas id="chartMenus"></canvas>
      </div>
    </div>

    <!-- Répartition par type chambre -->
    <div class="panel">
      <div class="panel-header"><h2 class="panel-title">Répartition par Type de Chambre</h2></div>
      <?php if(!empty($par_type)): ?>
      <?php $max_ca = max(array_column($par_type,'ca') ?: [1]); ?>
      <?php foreach($par_type as $t): $pct = round((float)$t['ca']/$max_ca*100); ?>
      <div style="margin-bottom:1.2rem">
        <div style="display:flex;justify-content:space-between;font-size:0.82rem;margin-bottom:0.4rem">
          <span style="color:var(--ivoire-2)"><?= htmlspecialchars($t['nom']) ?> <span style="color:var(--gris)">(<?= (int)$t['nb'] ?> rés.)</span></span>
          <span style="color:var(--or)"><?= number_format((float)$t['ca'],0,',',' ') ?> €</span>
        </div>
        <div style="height:8px;background:rgba(255,255,255,0.05);border-radius:2px;overflow:hidden">
          <div style="height:100%;width:<?= $pct ?>%;background:linear-gradient(90deg,var(--or-sombre),var(--or));border-radius:2px;transition:width 1s ease"></div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php else: ?><p style="color:var(--gris);padding:1rem">Pas de données disponibles.</p><?php endif; ?>
    </div>
  </main>
</div>
<script>
Chart.defaults.color = '#8A8A8A';
Chart.defaults.borderColor = 'rgba(201,168,76,0.08)';

const caData = <?= json_encode(array_values(array_map(fn($m)=>['mois'=>$m['mois'],'ca'=>(float)$m['ca'],'nb'=>(int)$m['nb']],$ca_mois))) ?>;
const fmtMois = d => { const [y,m] = d.split('-'); return new Date(y,m-1).toLocaleString('fr-FR',{month:'short',year:'2-digit'}); };

new Chart(document.getElementById('chartCA'), {
  type:'bar', data:{labels:caData.map(d=>fmtMois(d.mois)),datasets:[{label:'CA (€)',data:caData.map(d=>d.ca),backgroundColor:'rgba(201,168,76,0.45)',borderColor:'#C9A84C',borderWidth:1,borderRadius:2}]},
  options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{callback:v=>v.toLocaleString('fr-FR')+'€'}},x:{grid:{display:false}}}}
});

const menusData = <?= json_encode(array_values($rest_stats)) ?>;
if(menusData.length>0) {
  new Chart(document.getElementById('chartMenus'), {
    type:'pie', data:{labels:menusData.map(d=>d.menu.charAt(0).toUpperCase()+d.menu.slice(1)),datasets:[{data:menusData.map(d=>d.nb),backgroundColor:['rgba(76,175,80,0.7)','rgba(201,168,76,0.7)','rgba(41,182,246,0.7)'],borderColor:['#4CAF50','#C9A84C','#29B6F6'],borderWidth:1}]},
    options:{responsive:true,plugins:{legend:{position:'bottom',labels:{padding:12,font:{size:11}}}}}
  });
}
</script>
</body>
</html>
