<?php
/**
 * admin/includes/sidebar.php — Sidebar dynamique (admin + employés)
 */
if (session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../includes/functions.php';

$pdo_sidebar = isset($pdo) ? $pdo : getDB();

// Compter réservations en attente (seulement les sections accessibles)
$att = ['chambres'=>0,'salles'=>0,'piscines'=>0,'restaurants'=>0,'spa'=>0];
try {
    if(hasPermission('voir_chambres'))    $att['chambres']   = (int)$pdo_sidebar->query("SELECT COUNT(*) FROM reservations WHERE statut='en_attente'")->fetchColumn();
    if(hasPermission('voir_salles'))      $att['salles']     = (int)$pdo_sidebar->query("SELECT COUNT(*) FROM event_reservations WHERE statut='en_attente'")->fetchColumn();
    if(hasPermission('voir_piscine'))     $att['piscines']   = (int)$pdo_sidebar->query("SELECT COUNT(*) FROM piscine_reservations WHERE statut='en_attente'")->fetchColumn();
    if(hasPermission('voir_restaurant'))  $att['restaurants']= (int)$pdo_sidebar->query("SELECT COUNT(*) FROM restaurant_reservations WHERE statut='en_attente'")->fetchColumn();
    if(hasPermission('voir_spa')) { try{$att['spa']=(int)$pdo_sidebar->query("SELECT COUNT(*) FROM spa_reservations WHERE statut='en_attente'")->fetchColumn();}catch(Exception $e){} }
} catch(Exception $e) {}
$att_total = array_sum($att);

$pa = $page_active ?? '';
$nomUser    = getNomConnecte();
$roleUser   = getRoleConnecte();
$initiale   = mb_strtoupper(mb_substr($nomUser, 0, 1));
$isAdm      = isAdmin();
$isSA       = isSuperAdmin();
$empService = $_SESSION['employe_service'] ?? '';
?>
<aside class="admin-sidebar">
  <div class="sidebar-header">
    <div class="sidebar-logo">LUMIÈRE<small>Administration</small></div>
  </div>
  <nav class="sidebar-nav">

    <!-- Dashboard : toujours visible -->
    <div class="sidebar-section">Principal</div>
    <a href="dashboard.php" class="<?=$pa==='dashboard'?'actif':''?>">
      <span class="nav-icon">📊</span> Dashboard
      <?php if($att_total>0):?><span class="nav-badge"><?=$att_total?></span><?php endif;?>
    </a>

    <?php if(hasPermission('voir_calendrier') || $isAdm):?>
    <a href="calendrier.php" class="<?=$pa==='calendrier'?'actif':''?>"><span class="nav-icon">📅</span> Calendrier</a>
    <?php endif;?>

    <?php if(hasPermission('voir_clients') || $isAdm):?>
    <a href="clients.php" class="<?=$pa==='clients'?'actif':''?>"><span class="nav-icon">👥</span> Clients</a>
    <?php endif;?>

    <!-- ── Réservations ── -->
    <?php
    $show_res_section = hasPermission('voir_chambres') || hasPermission('voir_restaurant') || hasPermission('voir_piscine') || hasPermission('voir_spa') || hasPermission('voir_salles') || $isAdm;
    if($show_res_section):?>
    <div class="sidebar-section">Réservations</div>
    <?php endif;?>

    <?php if(hasPermission('voir_chambres') || $isAdm):?>
    <a href="reservations_chambres.php" class="<?=$pa==='res_chambres'?'actif':''?>">
      <span class="nav-icon">🛏️</span> Chambres
      <?php if($att['chambres']>0):?><span class="nav-badge"><?=$att['chambres']?></span><?php endif;?>
    </a>
    <?php endif;?>

    <?php if(hasPermission('voir_restaurant') || $isAdm):?>
    <a href="reservations_restaurants.php" class="<?=$pa==='res_restaurants'?'actif':''?>">
      <span class="nav-icon">🍽️</span> Restaurant
      <?php if($att['restaurants']>0):?><span class="nav-badge"><?=$att['restaurants']?></span><?php endif;?>
    </a>
    <?php endif;?>

    <?php if(hasPermission('voir_piscine') || $isAdm):?>
    <a href="reservations_piscines.php" class="<?=$pa==='res_piscines'?'actif':''?>">
      <span class="nav-icon">🏊</span> Piscines
      <?php if($att['piscines']>0):?><span class="nav-badge"><?=$att['piscines']?></span><?php endif;?>
    </a>
    <?php endif;?>

    <?php if(hasPermission('voir_spa') || $isAdm):?>
    <a href="reservations_spa.php" class="<?=$pa==='res_spa'?'actif':''?>">
      <span class="nav-icon">🧖</span> Spa
      <?php if($att['spa']>0):?><span class="nav-badge"><?=$att['spa']?></span><?php endif;?>
    </a>
    <?php endif;?>

    <?php if(hasPermission('voir_salles') || $isAdm):?>
    <a href="reservations_salles.php" class="<?=$pa==='res_salles'?'actif':''?>">
      <span class="nav-icon">🎉</span> Salles & Événements
      <?php if($att['salles']>0):?><span class="nav-badge"><?=$att['salles']?></span><?php endif;?>
    </a>
    <?php endif;?>

    <!-- ── Gestion (admin seulement ou chambres) ── -->
    <?php if($isAdm || hasPermission('gerer_chambres')):?>
    <div class="sidebar-section">Gestion</div>
    <?php if($isAdm || hasPermission('gerer_chambres')):?>
    <a href="chambres.php" class="<?=$pa==='chambres'?'actif':''?>"><span class="nav-icon">🏨</span> Parc Chambres</a>
    <?php endif;?>
    <?php if($isAdm):?>
    <a href="services.php" class="<?=$pa==='services'?'actif':''?>"><span class="nav-icon">⚙️</span> Services</a>
    <a href="employes.php" class="<?=$pa==='employes'?'actif':''?>"><span class="nav-icon">👔</span> Employés</a>
    <?php endif;?>
    <?php if(hasPermission('voir_stats') || $isAdm):?>
    <a href="statistiques.php" class="<?=$pa==='statistiques'?'actif':''?>"><span class="nav-icon">📈</span> Statistiques</a>
    <?php endif;?>
    <?php endif;?>

    <!-- ── Administration (superadmin seulement) ── -->
    <?php if($isSA):?>
    <div class="sidebar-section">Administration</div>
    <a href="gestion_comptes.php" class="<?=$pa==='gestion_comptes'?'actif':''?>">
      <span class="nav-icon">🔐</span> Comptes Employés
    </a>
    <?php endif;?>

  </nav>

  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="sidebar-avatar"><?=$initiale?></div>
      <div class="sidebar-user-info">
        <div class="sidebar-user-name"><?=htmlspecialchars($nomUser)?></div>
        <div class="sidebar-user-role">
          <?php if(!empty($empService)):?>
          <span style="font-size:.65rem"><?=emojiService($empService)?> <?=htmlspecialchars($roleUser)?></span>
          <?php else:?>
          <?=htmlspecialchars($roleUser)?>
          <?php endif;?>
        </div>
      </div>
    </div>
    <a href="logout.php" class="sidebar-logout">⬚ &nbsp;Déconnexion</a>
  </div>
</aside>
