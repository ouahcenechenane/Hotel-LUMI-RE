<?php
session_start();
require_once __DIR__ . '/../includes/functions.php';
requireAnyAuth();
requirePermission('voir_restaurant');
$pdo = getDB();
$page_active = 'res_restaurants';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'modifier' && $id) {
        $pdo->prepare("UPDATE restaurant_reservations SET nom_client=:nom, email_client=:email, telephone=:tel, nb_couverts=:nb, menu=:menu, statut=:s WHERE id=:id")
            ->execute([':nom'=>sanitize($_POST['nom_client']??''),':email'=>sanitize($_POST['email_client']??''),':tel'=>sanitize($_POST['telephone']??''),':nb'=>(int)($_POST['nb_couverts']??2),':menu'=>$_POST['menu']??'carte',':s'=>$_POST['statut']??'en_attente',':id'=>$id]);
        $msg = "Réservation modifiée.";
    }
    if ($action === 'supprimer' && $id) {
        $pdo->prepare("DELETE FROM restaurant_reservations WHERE id=:id")->execute([':id'=>$id]);
        $msg = "Réservation supprimée.";
    }
    header('Location: reservations_restaurants.php?msg='.urlencode($msg)); exit;
}
if (!empty($_GET['msg'])) $msg = $_GET['msg'];

$filtre = $_GET['statut'] ?? 'tous';
$sql = "SELECT * FROM restaurant_reservations";
$params = [];
if ($filtre !== 'tous') { $sql .= " WHERE statut=:s"; $params[':s']=$filtre; }
$sql .= " ORDER BY date_res DESC, heure_res ASC";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$reservations = $stmt->fetchAll();

$counts = ['tous'=>0,'en_attente'=>0,'confirmee'=>0,'annulee'=>0];
foreach(['en_attente','confirmee','annulee'] as $s) $counts[$s] = (int)$pdo->query("SELECT COUNT(*) FROM restaurant_reservations WHERE statut='$s'")->fetchColumn();
$counts['tous'] = array_sum(array_slice($counts,1));
$auj = (int)$pdo->query("SELECT COUNT(*) FROM restaurant_reservations WHERE date_res=CURDATE()")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Réservations Restaurant — Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;1,400&family=Montserrat:wght@300;400;500&family=Cinzel:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../hotel-html/css/style.css">
  <link rel="stylesheet" href="css/admin.css">
</head>
<body>
<div class="admin-layout">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <main class="admin-contenu">
    <h1 class="admin-titre">🍽️ Réservations Restaurant</h1>
    <div class="admin-breadcrumb"><a href="dashboard.php">Dashboard</a> / Restaurant</div>
    <?php if($msg): ?><div class="alerte alerte-ok">✓ <?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <div class="mini-stat-row">
      <div class="mini-stat-chip"><strong style="color:var(--or)"><?= $counts['tous'] ?></strong> Total</div>
      <div class="mini-stat-chip"><strong style="color:#FF9800"><?= $counts['en_attente'] ?></strong> En attente</div>
      <div class="mini-stat-chip"><strong style="color:#4CAF50"><?= $counts['confirmee'] ?></strong> Confirmées</div>
      <div class="mini-stat-chip"><strong style="color:#BF360C"><?= $auj ?></strong> Aujourd'hui</div>
    </div>

    <div class="filtres-bar">
      <?php foreach(['tous'=>'Toutes','en_attente'=>'En attente','confirmee'=>'Confirmées','annulee'=>'Annulées'] as $k=>$l): ?>
      <a href="?statut=<?= $k ?>" class="filtre-btn <?= $filtre===$k?'actif':'' ?>"><?= $l ?> (<?= $counts[$k] ?>)</a>
      <?php endforeach; ?>
      <input type="text" class="search-input" placeholder="🔍 Rechercher..." oninput="document.querySelectorAll('#mainTable tbody tr').forEach(tr=>tr.style.display=tr.textContent.toLowerCase().includes(this.value.toLowerCase())?'':'none')">
    </div>

    <div class="panel" style="padding:0">
      <div class="tableau-wrap">
        <table class="tableau" id="mainTable">
          <thead><tr><th>Référence</th><th>Client</th><th>Email</th><th>Tél.</th><th>Date</th><th>Heure</th><th>Couverts</th><th>Menu</th><th>Statut</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach($reservations as $r):
              $isToday = $r['date_res'] === date('Y-m-d');
            ?>
            <tr <?= $isToday ? 'style="background:rgba(191,54,12,0.04)"' : '' ?>>
              <td style="font-family:'Cinzel',serif;font-size:0.66rem;color:var(--or)"><?= htmlspecialchars($r['reference']) ?></td>
              <td style="font-weight:500"><?= htmlspecialchars($r['nom_client']) ?><?= $isToday ? ' <span style="font-size:0.6rem;color:#FF7043;margin-left:4px">● Aujourd\'hui</span>' : '' ?></td>
              <td style="font-size:0.78rem;color:var(--gris)"><?= htmlspecialchars($r['email_client']) ?></td>
              <td style="font-size:0.78rem;color:var(--gris)"><?= htmlspecialchars($r['telephone']??'—') ?></td>
              <td><?= date('d/m/Y',strtotime($r['date_res'])) ?></td>
              <td><?= substr($r['heure_res'],0,5) ?></td>
              <td style="text-align:center"><?= (int)$r['nb_couverts'] ?></td>
              <td>
                <?php $mc=['decouverte'=>['bg'=>'rgba(76,175,80,0.12)','c'=>'#4CAF50'],'prestige'=>['bg'=>'rgba(201,168,76,0.12)','c'=>'#C9A84C'],'carte'=>['bg'=>'rgba(41,182,246,0.12)','c'=>'#29B6F6']]; $m=$mc[$r['menu']]??$mc['carte']; ?>
                <span style="background:<?= $m['bg'] ?>;color:<?= $m['c'] ?>;padding:0.18rem 0.65rem;font-size:0.62rem;border-radius:10px"><?= ucfirst($r['menu']) ?></span>
              </td>
              <td><span class="badge-statut badge-<?= $r['statut'] ?>"><?= str_replace('_',' ',$r['statut']) ?></span></td>
              <td>
                <div style="display:flex;gap:0.3rem">
                  <button class="btn-icon btn-success" onclick="ouvrirEdit(<?= htmlspecialchars(json_encode($r)) ?>)">✏️</button>
                  <button class="btn-icon btn-danger" onclick="confirmerSupp(<?= $r['id'] ?>,'<?= htmlspecialchars(addslashes($r['nom_client'])) ?>')">🗑️</button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($reservations)): ?><tr><td colspan="10" style="text-align:center;padding:2.5rem;color:var(--gris)">Aucune réservation restaurant.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<div class="modal-overlay" id="m-edit">
  <div class="modal-box">
    <button class="modal-close" onclick="document.querySelectorAll('.modal-overlay').forEach(m=>m.classList.remove('open'))">✕</button>
    <div class="modal-titre">Modifier la Réservation Restaurant</div>
    <form method="POST">
      <input type="hidden" name="action" value="modifier">
      <input type="hidden" name="id" id="edit-id">
      <div class="form-grille-2">
        <div class="form-groupe"><label>Nom client</label><input type="text" name="nom_client" id="edit-nom" required></div>
        <div class="form-groupe"><label>Email</label><input type="email" name="email_client" id="edit-email" required></div>
        <div class="form-groupe"><label>Téléphone</label><input type="text" name="telephone" id="edit-tel"></div>
        <div class="form-groupe"><label>Nb couverts</label><input type="number" name="nb_couverts" id="edit-nb" min="1"></div>
        <div class="form-groupe"><label>Menu</label>
          <select name="menu" id="edit-menu">
            <option value="carte">À la carte</option>
            <option value="decouverte">Découverte</option>
            <option value="prestige">Prestige</option>
          </select>
        </div>
        <div class="form-groupe"><label>Statut</label>
          <select name="statut" id="edit-statut">
            <option value="en_attente">En attente</option>
            <option value="confirmee">Confirmée</option>
            <option value="annulee">Annulée</option>
          </select>
        </div>
      </div>
      <button type="submit" class="btn btn-or" style="width:100%;justify-content:center;margin-top:0.5rem"><span>💾 Enregistrer</span></button>
    </form>
  </div>
</div>
<div class="modal-overlay" id="m-supp">
  <div class="modal-box" style="max-width:380px">
    <button class="modal-close" onclick="document.querySelectorAll('.modal-overlay').forEach(m=>m.classList.remove('open'))">✕</button>
    <div class="modal-titre" style="color:#E53935">⚠️ Supprimer ?</div>
    <p style="color:var(--gris);margin-bottom:1.5rem">Supprimer la réservation de <strong id="supp-nom" style="color:var(--ivoire)"></strong> ?</p>
    <form method="POST" style="display:flex;gap:0.8rem">
      <input type="hidden" name="action" value="supprimer">
      <input type="hidden" name="id" id="supp-id">
      <button type="button" class="btn btn-contour" style="flex:1;justify-content:center" onclick="document.querySelectorAll('.modal-overlay').forEach(m=>m.classList.remove('open'))"><span>Annuler</span></button>
      <button type="submit" class="btn btn-danger" style="flex:1;justify-content:center"><span>🗑️ Supprimer</span></button>
    </form>
  </div>
</div>

<script>
document.querySelectorAll('.modal-overlay').forEach(m=>m.addEventListener('click',e=>{if(e.target===m)m.classList.remove('open');}));
function ouvrirEdit(r){document.getElementById('edit-id').value=r.id;document.getElementById('edit-nom').value=r.nom_client;document.getElementById('edit-email').value=r.email_client;document.getElementById('edit-tel').value=r.telephone||'';document.getElementById('edit-nb').value=r.nb_couverts;document.getElementById('edit-menu').value=r.menu;document.getElementById('edit-statut').value=r.statut;document.getElementById('m-edit').classList.add('open');}
function confirmerSupp(id,nom){document.getElementById('supp-id').value=id;document.getElementById('supp-nom').textContent=nom;document.getElementById('m-supp').classList.add('open');}
</script>
</body>
</html>
