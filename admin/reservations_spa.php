<?php
session_start();
require_once __DIR__ . '/../includes/functions.php';
requireAnyAuth();
requirePermission('voir_spa');
$pdo = getDB();
$page_active = 'res_spa';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? ''; $id = (int)($_POST['id'] ?? 0);
    if ($action === 'confirmer' && $id && hasPermission('approuver_spa')) {
        $pdo->prepare("UPDATE spa_reservations SET statut='confirmee' WHERE id=?")->execute([$id]);
        logActivity($pdo, 'confirmer_spa', "Réservation SPA id=$id confirmée");
        $msg = "Réservation confirmée.";
    }
    if ($action === 'annuler' && $id && hasPermission('approuver_spa')) {
        $pdo->prepare("UPDATE spa_reservations SET statut='annulee' WHERE id=?")->execute([$id]);
        logActivity($pdo, 'annuler_spa', "Réservation SPA id=$id annulée");
        $msg = "Réservation annulée.";
    }
    if ($action === 'creer' && hasPermission('creer_spa')) {
        $ref = genererReference('SPA');
        try {
            $pdo->prepare("INSERT INTO spa_reservations (reference,nom_client,email_client,telephone,soin,date_res,heure_res,nb_personnes,tarif_total,statut,message) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$ref,sanitize($_POST['nom_client']??''),sanitize($_POST['email_client']??''),sanitize($_POST['telephone']??''),sanitize($_POST['soin']??'Soin Signature'),$_POST['date_res']??date('Y-m-d'),$_POST['heure_res']??'10:00',(int)($_POST['nb_personnes']??1),(float)($_POST['tarif_total']??180),'confirmee',sanitize($_POST['message']??'')]);
            logActivity($pdo, 'creer_spa', "Nouvelle réservation SPA $ref créée");
            $msg = "Réservation créée.";
        } catch(Exception $e) { $msg = "Erreur lors de la création."; }
    }
    header('Location: reservations_spa.php?msg='.urlencode($msg)); exit;
}
if(!empty($_GET['msg'])) $msg = $_GET['msg'];

$filtre = $_GET['statut'] ?? 'tous';
$sql = "SELECT * FROM spa_reservations"; $params = [];
if($filtre !== 'tous') { $sql .= " WHERE statut=:s"; $params[':s']=$filtre; }
$sql .= " ORDER BY date_res DESC, heure_res ASC";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$reservations = $stmt->fetchAll();
$counts = ['tous'=>0,'en_attente'=>0,'confirmee'=>0,'annulee'=>0];
foreach(['en_attente','confirmee','annulee'] as $s) { try{$counts[$s]=(int)$pdo->query("SELECT COUNT(*) FROM spa_reservations WHERE statut='$s'")->fetchColumn();}catch(Exception $e){} }
$counts['tous'] = array_sum(array_slice($counts,1));
?>
<!DOCTYPE html><html lang="fr"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Réservations Spa · Admin Lumière</title>
<link rel="stylesheet" href="../hotel-html/css/style.css">
<link rel="stylesheet" href="css/admin.css">
</head><body>
<div class="admin-layout">
<?php include 'includes/sidebar.php'; ?>
<main class="admin-contenu">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;flex-wrap:wrap;gap:1rem">
    <h1 class="admin-titre">🧖 Réservations Spa</h1>
    <?php if(hasPermission('creer_spa')):?>
    <button onclick="document.getElementById('modal-creer').style.display='flex'" class="btn btn-or"><span>➕ Nouvelle réservation</span></button>
    <?php endif;?>
  </div>

  <?php if($msg):?><div style="padding:1rem;background:rgba(76,175,80,.1);border-left:3px solid #4CAF50;color:#81C784;margin-bottom:1.5rem"><?=htmlspecialchars($msg)?></div><?php endif;?>

  <!-- Filtres -->
  <div style="display:flex;gap:.6rem;margin-bottom:1.5rem;flex-wrap:wrap">
    <?php foreach(['tous'=>'Toutes','en_attente'=>'En attente','confirmee'=>'Confirmées','annulee'=>'Annulées'] as $k=>$v):?>
    <a href="?statut=<?=$k?>" style="padding:.4rem 1rem;font-size:.78rem;text-decoration:none;border:1px solid;transition:.2s;<?=$filtre===$k?'background:rgba(201,168,76,.15);border-color:var(--or);color:var(--or)':'border-color:rgba(255,255,255,.1);color:var(--gris)'?>">
      <?=$v?> <span style="opacity:.7">(<?=$counts[$k]?>)</span>
    </a>
    <?php endforeach;?>
  </div>

  <!-- Table -->
  <div style="background:var(--noir-2);border:1px solid rgba(201,168,76,.08);padding:1.5rem;overflow-x:auto">
    <?php if(empty($reservations)):?>
    <p style="text-align:center;color:var(--gris);padding:2rem">Aucune réservation spa trouvée.</p>
    <?php else:?>
    <table class="tableau">
      <thead><tr><th>Référence</th><th>Client</th><th>Soin</th><th>Date</th><th>Heure</th><th>Personnes</th><th>Tarif</th><th>Statut</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($reservations as $r):?>
        <tr>
          <td style="font-family:'Cinzel',serif;font-size:.7rem;color:var(--or)"><?=htmlspecialchars($r['reference'])?></td>
          <td>
            <div style="font-weight:500"><?=htmlspecialchars($r['nom_client'])?></div>
            <div style="font-size:.75rem;color:var(--gris)"><?=htmlspecialchars($r['email_client'])?></div>
          </td>
          <td><?=htmlspecialchars($r['soin'])?></td>
          <td><?=date('d/m/Y',strtotime($r['date_res']))?></td>
          <td><?=substr($r['heure_res'],0,5)?></td>
          <td style="text-align:center"><?=$r['nb_personnes']?></td>
          <td style="color:var(--or)"><?=number_format($r['tarif_total'],2,',',' ')?> €</td>
          <td><span class="badge-statut <?=$r['statut']==='confirmee'?'badge-confirmee':($r['statut']==='en_attente'?'badge-attente':'badge-annulee')?>"><?=$r['statut']?></span></td>
          <td>
            <?php if(hasPermission('approuver_spa') && $r['statut']==='en_attente'):?>
            <form method="POST" style="display:inline;margin-right:.3rem">
              <input type="hidden" name="action" value="confirmer"><input type="hidden" name="id" value="<?=$r['id']?>">
              <button type="submit" class="btn" style="padding:.3rem .7rem;font-size:.7rem;border-color:rgba(76,175,80,.4);color:#81C784">✓ Confirmer</button>
            </form>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="annuler"><input type="hidden" name="id" value="<?=$r['id']?>">
              <button type="submit" class="btn" style="padding:.3rem .7rem;font-size:.7rem;border-color:rgba(229,57,53,.4);color:#E57373" onclick="return confirm('Annuler ?')">✗ Annuler</button>
            </form>
            <?php elseif($r['statut']==='en_attente'):?>
            <span style="font-size:.72rem;color:var(--gris);font-style:italic">Lecture seule</span>
            <?php else:?><span style="font-size:.72rem;color:var(--gris)">—</span><?php endif;?>
          </td>
        </tr>
        <?php endforeach;?>
      </tbody>
    </table>
    <?php endif;?>
  </div>
</main>
</div>

<?php if(hasPermission('creer_spa')):?>
<div id="modal-creer" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.8);z-index:1000;align-items:center;justify-content:center" onclick="if(event.target===this)this.style.display='none'">
<div style="background:var(--noir-2);border:1px solid rgba(201,168,76,.2);padding:2rem;width:90%;max-width:560px">
  <div style="font-family:'Cinzel',serif;color:var(--or);margin-bottom:1.5rem;padding-bottom:.8rem;border-bottom:1px solid rgba(201,168,76,.15)">Nouvelle réservation Spa</div>
  <form method="POST" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
    <input type="hidden" name="action" value="creer">
    <div class="form-groupe" style="grid-column:1/-1"><label>Nom du client *</label><input type="text" name="nom_client" required></div>
    <div class="form-groupe"><label>Email</label><input type="email" name="email_client"></div>
    <div class="form-groupe"><label>Téléphone</label><input type="text" name="telephone"></div>
    <div class="form-groupe" style="grid-column:1/-1"><label>Soin</label>
      <select name="soin"><option>Soin Signature</option><option>Massage Relaxant</option><option>Soin Visage</option><option>Hammam</option></select>
    </div>
    <div class="form-groupe"><label>Date *</label><input type="date" name="date_res" value="<?=date('Y-m-d')?>" required></div>
    <div class="form-groupe"><label>Heure *</label><input type="time" name="heure_res" value="10:00" required></div>
    <div class="form-groupe"><label>Personnes</label><input type="number" name="nb_personnes" value="1" min="1" max="4"></div>
    <div class="form-groupe"><label>Tarif (€)</label><input type="number" name="tarif_total" value="180" step="0.01"></div>
    <div class="form-groupe" style="grid-column:1/-1"><label>Note</label><textarea name="message" rows="2"></textarea></div>
    <div style="grid-column:1/-1;display:flex;gap:1rem;justify-content:flex-end">
      <button type="button" onclick="this.closest('[id]').style.display='none'" class="btn" style="color:var(--gris)">Annuler</button>
      <button type="submit" class="btn btn-or"><span>Créer</span></button>
    </div>
  </form>
</div></div>
<?php endif;?>
</body></html>
