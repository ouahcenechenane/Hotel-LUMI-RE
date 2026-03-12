<?php
session_start();
require_once __DIR__ . '/../includes/functions.php';
requireAnyAuth();
$pdo = getDB();
$page_active = 'dashboard';

// Stats selon permissions
$stats = [];
try {
    if(hasPermission('voir_chambres'))    $stats['chambres_attente']   = (int)$pdo->query("SELECT COUNT(*) FROM reservations WHERE statut='en_attente'")->fetchColumn();
    if(hasPermission('voir_restaurant'))  $stats['resto_attente']      = (int)$pdo->query("SELECT COUNT(*) FROM restaurant_reservations WHERE statut='en_attente'")->fetchColumn();
    if(hasPermission('voir_piscine'))     $stats['piscine_attente']    = (int)$pdo->query("SELECT COUNT(*) FROM piscine_reservations WHERE statut='en_attente'")->fetchColumn();
    if(hasPermission('voir_salles'))      $stats['salles_attente']     = (int)$pdo->query("SELECT COUNT(*) FROM event_reservations WHERE statut='en_attente'")->fetchColumn();
    try { if(hasPermission('voir_spa'))   $stats['spa_attente']        = (int)$pdo->query("SELECT COUNT(*) FROM spa_reservations WHERE statut='en_attente'")->fetchColumn(); } catch(Exception $e) {}
    if(isAdmin()) {
        $stats['total_clients'] = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $stats['comptes_employes'] = (int)$pdo->query("SELECT COUNT(*) FROM employee_accounts WHERE actif=1")->fetchColumn();
        $stats['chambres_dispo'] = (int)$pdo->query("SELECT COUNT(*) FROM rooms WHERE statut='disponible'")->fetchColumn();
    }
} catch(Exception $e) {}

// Dernières réservations accessibles
$dernieres = [];
try {
    if(hasPermission('voir_chambres')) {
        $rows = $pdo->query("SELECT reference,nom_client,statut,created_at,'chambre' as type FROM reservations ORDER BY created_at DESC LIMIT 5")->fetchAll();
        $dernieres = array_merge($dernieres, $rows);
    }
    if(hasPermission('voir_restaurant')) {
        $rows = $pdo->query("SELECT reference,nom_client,statut,created_at,'restaurant' as type FROM restaurant_reservations ORDER BY created_at DESC LIMIT 5")->fetchAll();
        $dernieres = array_merge($dernieres, $rows);
    }
    if(hasPermission('voir_piscine')) {
        $rows = $pdo->query("SELECT reference,nom_client,statut,created_at,'piscine' as type FROM piscine_reservations ORDER BY created_at DESC LIMIT 3")->fetchAll();
        $dernieres = array_merge($dernieres, $rows);
    }
    try { if(hasPermission('voir_spa')) {
        $rows = $pdo->query("SELECT reference,nom_client,statut,created_at,'spa' as type FROM spa_reservations ORDER BY created_at DESC LIMIT 3")->fetchAll();
        $dernieres = array_merge($dernieres, $rows);
    } } catch(Exception $e) {}
    usort($dernieres, fn($a,$b) => strtotime($b['created_at']) - strtotime($a['created_at']));
    $dernieres = array_slice($dernieres, 0, 8);
} catch(Exception $e) {}

$service = $_SESSION['employe_service'] ?? '';
?>
<!DOCTYPE html><html lang="fr"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Dashboard · Hôtel Lumière</title>
<link rel="stylesheet" href="../hotel-html/css/style.css">
<link rel="stylesheet" href="css/admin.css">
<style>
.dash-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:1.2rem;margin-bottom:2.5rem}
.dash-card{background:var(--noir-2);border:1px solid rgba(201,168,76,.12);padding:1.5rem;transition:.2s;text-decoration:none;display:block}
.dash-card:hover{border-color:rgba(201,168,76,.35);transform:translateY(-2px)}
.dash-card-num{font-family:'Cinzel',serif;font-size:2.2rem;color:var(--or);display:block;line-height:1}
.dash-card-lbl{font-size:.68rem;color:var(--gris);letter-spacing:.18em;text-transform:uppercase;margin-top:.5rem;display:block}
.dash-card-icon{font-size:1.5rem;margin-bottom:.6rem;display:block}
.urgence{border-color:rgba(229,57,53,.35)!important;background:rgba(229,57,53,.04)}
.urgence .dash-card-num{color:#E57373}
.welcome-banner{background:linear-gradient(135deg,rgba(201,168,76,.08),rgba(201,168,76,.02));border:1px solid rgba(201,168,76,.15);padding:2rem;margin-bottom:2rem}
.type-badge{display:inline-block;padding:.15rem .5rem;font-size:.65rem;letter-spacing:.08em;border-radius:2px}
.tb-chambre{background:rgba(156,39,176,.15);color:#CE93D8}
.tb-restaurant{background:rgba(255,152,0,.15);color:#FFB74D}
.tb-piscine{background:rgba(3,169,244,.15);color:#4FC3F7}
.tb-spa{background:rgba(0,150,136,.15);color:#4DB6AC}
.tb-salle{background:rgba(76,175,80,.15);color:#81C784}
</style>
</head><body>
<div class="admin-layout">
<?php include 'includes/sidebar.php'; ?>
<main class="admin-contenu">

  <!-- Bannière de bienvenue -->
  <div class="welcome-banner">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem">
      <div>
        <h1 style="font-family:'Cinzel',serif;color:var(--or);font-size:1.3rem;margin-bottom:.3rem">
          Bonjour, <?=htmlspecialchars(getNomConnecte())?>
        </h1>
        <p style="color:var(--gris);font-size:.82rem">
          <?php if(isEmployee()):?>
          <?=emojiService($service)?> Service <strong style="color:var(--gris-clair)"><?=libelleService($service)?></strong> · <?=date('l d F Y')?>
          <?php else:?>
          <?=getRoleConnecte()?> · <?=date('l d F Y')?>
          <?php endif;?>
        </p>
      </div>
      <div style="font-size:.75rem;color:var(--gris);text-align:right">
        <?php $total_attente = array_sum(array_filter($stats, fn($k)=>str_ends_with($k,'_attente'), ARRAY_FILTER_USE_KEY)); ?>
        <?php if($total_attente > 0):?>
        <span style="color:#FFB74D;font-size:1.1rem">⚠️</span>
        <strong style="color:#FFB74D"><?=$total_attente?></strong> réservation<?=$total_attente>1?'s':''?> en attente
        <?php else:?>
        <span style="color:#81C784">✓</span> Tout est à jour
        <?php endif;?>
      </div>
    </div>
  </div>

  <!-- Cartes de stats -->
  <div class="dash-grid">
    <?php if(!empty($stats['chambres_attente'])):?>
    <a href="reservations_chambres.php" class="dash-card <?=$stats['chambres_attente']>0?'urgence':''?>">
      <span class="dash-card-icon">🛏️</span>
      <span class="dash-card-num"><?=$stats['chambres_attente']?></span>
      <span class="dash-card-lbl">Chambres en attente</span>
    </a>
    <?php endif;?>
    <?php if(!empty($stats['resto_attente'])):?>
    <a href="reservations_restaurants.php" class="dash-card <?=$stats['resto_attente']>0?'urgence':''?>">
      <span class="dash-card-icon">🍽️</span>
      <span class="dash-card-num"><?=$stats['resto_attente']?></span>
      <span class="dash-card-lbl">Restaurant en attente</span>
    </a>
    <?php endif;?>
    <?php if(!empty($stats['piscine_attente'])):?>
    <a href="reservations_piscines.php" class="dash-card <?=$stats['piscine_attente']>0?'urgence':''?>">
      <span class="dash-card-icon">🏊</span>
      <span class="dash-card-num"><?=$stats['piscine_attente']?></span>
      <span class="dash-card-lbl">Piscine en attente</span>
    </a>
    <?php endif;?>
    <?php if(!empty($stats['spa_attente'])):?>
    <a href="reservations_spa.php" class="dash-card <?=$stats['spa_attente']>0?'urgence':''?>">
      <span class="dash-card-icon">🧖</span>
      <span class="dash-card-num"><?=$stats['spa_attente']?></span>
      <span class="dash-card-lbl">Spa en attente</span>
    </a>
    <?php endif;?>
    <?php if(!empty($stats['salles_attente'])):?>
    <a href="reservations_salles.php" class="dash-card <?=$stats['salles_attente']>0?'urgence':''?>">
      <span class="dash-card-icon">🎉</span>
      <span class="dash-card-num"><?=$stats['salles_attente']?></span>
      <span class="dash-card-lbl">Salles en attente</span>
    </a>
    <?php endif;?>
    <?php if(isset($stats['total_clients'])):?>
    <a href="clients.php" class="dash-card">
      <span class="dash-card-icon">👥</span>
      <span class="dash-card-num"><?=$stats['total_clients']?></span>
      <span class="dash-card-lbl">Clients inscrits</span>
    </a>
    <?php endif;?>
    <?php if(isset($stats['chambres_dispo'])):?>
    <a href="chambres.php" class="dash-card">
      <span class="dash-card-icon">🏨</span>
      <span class="dash-card-num" style="color:#81C784"><?=$stats['chambres_dispo']?></span>
      <span class="dash-card-lbl">Chambres disponibles</span>
    </a>
    <?php endif;?>
    <?php if(isset($stats['comptes_employes']) && isSuperAdmin()):?>
    <a href="gestion_comptes.php" class="dash-card">
      <span class="dash-card-icon">🔐</span>
      <span class="dash-card-num"><?=$stats['comptes_employes']?></span>
      <span class="dash-card-lbl">Comptes employés actifs</span>
    </a>
    <?php endif;?>
  </div>

  <!-- Activité récente -->
  <?php if(!empty($dernieres)):?>
  <div style="background:var(--noir-2);border:1px solid rgba(201,168,76,.1);padding:1.8rem">
    <h2 style="font-family:'Cinzel',serif;color:var(--or);font-size:.9rem;letter-spacing:.12em;text-transform:uppercase;margin-bottom:1.2rem">Activité récente</h2>
    <div style="overflow-x:auto">
    <table class="tableau">
      <thead><tr><th>Référence</th><th>Client</th><th>Type</th><th>Statut</th><th>Date</th></tr></thead>
      <tbody>
        <?php foreach($dernieres as $r):?>
        <tr>
          <td style="font-family:'Cinzel',serif;font-size:.72rem;color:var(--or)"><?=htmlspecialchars($r['reference'])?></td>
          <td><?=htmlspecialchars($r['nom_client'])?></td>
          <td><span class="type-badge tb-<?=htmlspecialchars($r['type'])?>"><?=htmlspecialchars($r['type'])?></span></td>
          <td><span class="badge-statut <?=$r['statut']==='confirmee'?'badge-confirmee':($r['statut']==='en_attente'?'badge-attente':'badge-annulee')?>"><?=htmlspecialchars($r['statut'])?></span></td>
          <td style="color:var(--gris);font-size:.8rem"><?=date('d/m/Y H:i',strtotime($r['created_at']))?></td>
        </tr>
        <?php endforeach;?>
      </tbody>
    </table>
    </div>
  </div>
  <?php endif;?>

  <!-- Accès rapides selon service -->
  <?php if(isEmployee()):?>
  <div style="margin-top:2rem;display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:1rem">
    <?php if(hasPermission('creer_chambres')):?>
    <a href="reservations_chambres.php?action=nouvelle" class="dash-card" style="text-align:center">
      <span style="font-size:2rem;display:block;margin-bottom:.5rem">➕🛏️</span>
      <span style="font-size:.75rem;color:var(--gris-clair)">Nouvelle réservation chambre</span>
    </a>
    <?php endif;?>
    <?php if(hasPermission('creer_restaurant')):?>
    <a href="reservations_restaurants.php?action=nouvelle" class="dash-card" style="text-align:center">
      <span style="font-size:2rem;display:block;margin-bottom:.5rem">➕🍽️</span>
      <span style="font-size:.75rem;color:var(--gris-clair)">Nouvelle réservation restaurant</span>
    </a>
    <?php endif;?>
    <?php if(hasPermission('creer_piscine')):?>
    <a href="reservations_piscines.php?action=nouvelle" class="dash-card" style="text-align:center">
      <span style="font-size:2rem;display:block;margin-bottom:.5rem">➕🏊</span>
      <span style="font-size:.75rem;color:var(--gris-clair)">Nouvelle réservation piscine</span>
    </a>
    <?php endif;?>
  </div>
  <?php endif;?>

</main>
</div>
</body></html>
