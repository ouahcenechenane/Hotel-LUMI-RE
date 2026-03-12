<?php
session_start();
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();
$pdo = getDB();
$page_active = 'clients';
$msg = '';

// Auto-create piscines table
try { $pdo->exec("CREATE TABLE IF NOT EXISTS piscine_reservations (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, reference VARCHAR(20) NOT NULL UNIQUE, piscine_id INT UNSIGNED, nom_client VARCHAR(200) NOT NULL, email_client VARCHAR(191) NOT NULL, telephone VARCHAR(20), date_res DATE NOT NULL, heure_debut TIME NOT NULL, heure_fin TIME NOT NULL, nb_personnes TINYINT NOT NULL DEFAULT 2, tarif_total DECIMAL(10,2) DEFAULT 0, statut ENUM('en_attente','confirmee','annulee') DEFAULT 'en_attente', message TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB"); } catch(Exception $e){}

// ── Récupérer tous les clients (email unique, toutes tables) ──
$clients = $pdo->query("
    SELECT email_client AS email, nom_client AS nom, telephone,
           MIN(created_at) AS premier_contact, COUNT(*) AS nb_res,
           'chambre' AS source
    FROM reservations GROUP BY email_client
    UNION ALL
    SELECT email_client, nom_client, telephone, MIN(created_at), COUNT(*), 'evenement'
    FROM event_reservations GROUP BY email_client
    UNION ALL
    SELECT email_client, nom_client, telephone, MIN(created_at), COUNT(*), 'piscine'
    FROM piscine_reservations GROUP BY email_client
    UNION ALL
    SELECT email_client, nom_client, telephone, MIN(created_at), COUNT(*), 'restaurant'
    FROM restaurant_reservations GROUP BY email_client
")->fetchAll();

// Fusionner par email
$merged = [];
foreach($clients as $c) {
    $email = $c['email'];
    if (!isset($merged[$email])) {
        $merged[$email] = ['email'=>$email,'nom'=>$c['nom'],'telephone'=>$c['telephone'],'premier_contact'=>$c['premier_contact'],'services'=>[],'nb_total'=>0];
    }
    $merged[$email]['services'][] = $c['source'];
    $merged[$email]['nb_total']  += $c['nb_res'];
    if ($c['premier_contact'] < $merged[$email]['premier_contact']) $merged[$email]['premier_contact'] = $c['premier_contact'];
}
$clients = array_values($merged);
usort($clients, fn($a,$b) => $b['nb_total'] - $a['nb_total']);

// Recherche
$search = trim($_GET['q'] ?? '');
if ($search) {
    $clients = array_filter($clients, fn($c) => str_contains(strtolower($c['nom'].$c['email']), strtolower($search)));
}

// Détail client
$detail_email = $_GET['detail'] ?? '';
$detail = null; $detail_res = [];
if ($detail_email) {
    foreach($merged as $c) { if ($c['email'] === $detail_email) { $detail = $c; break; } }
    if ($detail) {
        // Toutes ses réservations
        $stmt = $pdo->prepare("SELECT reference, nom_client, date_arrivee AS date_debut, date_depart AS date_fin, prix_total, statut, 'Chambre' AS service FROM reservations WHERE email_client=:e ORDER BY created_at DESC");
        $stmt->execute([':e'=>$detail_email]); $detail_res = array_merge($detail_res, $stmt->fetchAll());
        $stmt = $pdo->prepare("SELECT reference, nom_client, date_event AS date_debut, NULL AS date_fin, budget AS prix_total, statut, type_event AS service FROM event_reservations WHERE email_client=:e ORDER BY created_at DESC");
        $stmt->execute([':e'=>$detail_email]); $detail_res = array_merge($detail_res, $stmt->fetchAll());
        $stmt = $pdo->prepare("SELECT reference, nom_client, date_res AS date_debut, NULL AS date_fin, tarif_total AS prix_total, statut, 'Piscine' AS service FROM piscine_reservations WHERE email_client=:e ORDER BY created_at DESC");
        $stmt->execute([':e'=>$detail_email]); $detail_res = array_merge($detail_res, $stmt->fetchAll());
        $stmt = $pdo->prepare("SELECT reference, nom_client, date_res AS date_debut, NULL AS date_fin, NULL AS prix_total, statut, CONCAT('Restaurant · ',menu) AS service FROM restaurant_reservations WHERE email_client=:e ORDER BY created_at DESC");
        $stmt->execute([':e'=>$detail_email]); $detail_res = array_merge($detail_res, $stmt->fetchAll());
        usort($detail_res, fn($a,$b) => strtotime($b['date_debut']) - strtotime($a['date_debut']));
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Clients — Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;1,400&family=Montserrat:wght@300;400;500&family=Cinzel:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../hotel-html/css/style.css">
  <link rel="stylesheet" href="css/admin.css">
</head>
<body>
<div class="admin-layout">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <main class="admin-contenu">
    <h1 class="admin-titre">👥 Gestion des Clients</h1>
    <div class="admin-breadcrumb"><a href="dashboard.php">Dashboard</a> / Clients</div>

    <div class="mini-stat-row">
      <div class="mini-stat-chip"><strong style="color:var(--or)"><?= count($merged) ?></strong> Clients uniques</div>
      <div class="mini-stat-chip"><strong style="color:#4CAF50"><?= array_sum(array_column(array_values($merged),'nb_total')) ?></strong> Réservations totales</div>
    </div>

    <?php if ($detail): ?>
    <!-- ── DÉTAIL CLIENT ── -->
    <div class="panel" style="border-color:rgba(201,168,76,0.2)">
      <div class="panel-header">
        <h2 class="panel-title">Fiche Client</h2>
        <a href="clients.php" class="btn btn-sm btn-contour"><span>← Retour à la liste</span></a>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem">
        <div>
          <div style="font-family:var(--ff-titre);font-size:1.8rem;color:var(--ivoire);margin-bottom:0.3rem"><?= htmlspecialchars($detail['nom']) ?></div>
          <div style="color:var(--or);font-size:0.85rem;margin-bottom:0.2rem">✉️ <?= htmlspecialchars($detail['email']) ?></div>
          <div style="color:var(--gris);font-size:0.82rem">📞 <?= htmlspecialchars($detail['telephone']??'—') ?></div>
        </div>
        <div>
          <div class="mini-stat-chip" style="margin-bottom:0.5rem;display:inline-flex"><strong style="color:var(--or)"><?= $detail['nb_total'] ?></strong>&nbsp;réservation(s)</div><br>
          <div style="font-size:0.75rem;color:var(--gris)">Client depuis le <?= date('d/m/Y',strtotime($detail['premier_contact'])) ?></div>
          <div style="margin-top:0.5rem">
            <?php $svc_icons=['chambre'=>'🛏️','evenement'=>'🎉','piscine'=>'🏊','restaurant'=>'🍽️']; ?>
            <?php foreach(array_unique($detail['services']) as $s): ?>
            <span style="font-size:0.85rem;margin-right:4px"><?= $svc_icons[$s]??'•' ?></span>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <h3 style="font-family:var(--ff-titre);font-size:1.2rem;color:var(--or);margin-bottom:1rem">Historique des réservations</h3>
      <div class="tableau-wrap">
        <table class="tableau">
          <thead><tr><th>Référence</th><th>Service</th><th>Date</th><th>Montant</th><th>Statut</th></tr></thead>
          <tbody>
            <?php foreach($detail_res as $r): ?>
            <tr>
              <td style="font-family:'Cinzel',serif;font-size:0.66rem;color:var(--or)"><?= htmlspecialchars($r['reference']) ?></td>
              <td><?= htmlspecialchars($r['service']) ?></td>
              <td><?= date('d/m/Y',strtotime($r['date_debut'])) ?></td>
              <td style="color:var(--or)"><?= $r['prix_total'] ? number_format($r['prix_total'],0,',',' ').' €' : '—' ?></td>
              <td><span class="badge-statut badge-<?= $r['statut'] ?>"><?= str_replace('_',' ',$r['statut']) ?></span></td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($detail_res)): ?><tr><td colspan="5" style="text-align:center;padding:2rem;color:var(--gris)">Aucune réservation.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php else: ?>
    <!-- ── LISTE CLIENTS ── -->
    <div class="panel">
      <div class="panel-header">
        <h2 class="panel-title">Tous les Clients</h2>
        <form method="GET" style="display:flex;gap:0.6rem">
          <input type="text" name="q" class="search-input" placeholder="🔍 Nom ou email..." value="<?= htmlspecialchars($search) ?>" style="min-width:250px">
          <button type="submit" class="btn btn-or btn-sm"><span>Rechercher</span></button>
          <?php if($search): ?><a href="clients.php" class="btn btn-contour btn-sm"><span>✕</span></a><?php endif; ?>
        </form>
      </div>
      <div class="tableau-wrap">
        <table class="tableau">
          <thead><tr><th>Nom</th><th>Email</th><th>Téléphone</th><th>Services</th><th>Nb rés.</th><th>Client depuis</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach($clients as $c):
              $svc_icons=['chambre'=>'🛏️','evenement'=>'🎉','piscine'=>'🏊','restaurant'=>'🍽️'];
            ?>
            <tr>
              <td style="font-weight:500"><?= htmlspecialchars($c['nom']) ?></td>
              <td style="color:var(--or);font-size:0.82rem"><?= htmlspecialchars($c['email']) ?></td>
              <td style="color:var(--gris);font-size:0.8rem"><?= htmlspecialchars($c['telephone']??'—') ?></td>
              <td><?php foreach(array_unique($c['services']) as $s) echo '<span style="font-size:1rem;margin-right:3px">'.$svc_icons[$s].'</span>'; ?></td>
              <td style="text-align:center"><strong style="color:var(--or)"><?= $c['nb_total'] ?></strong></td>
              <td style="color:var(--gris);font-size:0.8rem"><?= date('d/m/Y',strtotime($c['premier_contact'])) ?></td>
              <td>
                <a href="clients.php?detail=<?= urlencode($c['email']) ?>" class="btn-icon btn-success" title="Voir détails">👁️</a>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($clients)): ?><tr><td colspan="7" style="text-align:center;padding:2.5rem;color:var(--gris)">Aucun client trouvé.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </main>
</div>
</body>
</html>
