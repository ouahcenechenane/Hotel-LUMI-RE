<?php
session_start();
require_once __DIR__ . '/../includes/functions.php';
requireAnyAuth();
requirePermission('voir_salles');
$pdo = getDB();
$page_active = 'res_salles';
$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'statut' && $id) {
        $s = $_POST['statut'] ?? '';
        if (in_array($s, ['en_attente','confirmee','annulee'])) {
            $pdo->prepare("UPDATE event_reservations SET statut=:s WHERE id=:id")->execute([':s'=>$s,':id'=>$id]);
            $msg = "Statut mis à jour.";
        }
    }
    if ($action === 'modifier' && $id) {
        $pdo->prepare("UPDATE event_reservations SET nom_client=:nom, email_client=:email, telephone=:tel, nb_invites=:inv, statut=:s, budget=:b WHERE id=:id")
            ->execute([':nom'=>sanitize($_POST['nom_client']??''),':email'=>sanitize($_POST['email_client']??''),':tel'=>sanitize($_POST['telephone']??''),':inv'=>(int)($_POST['nb_invites']??0),':s'=>$_POST['statut']??'en_attente',':b'=>(float)($_POST['budget']??0),':id'=>$id]);
        $msg = "Réservation modifiée.";
    }
    if ($action === 'supprimer' && $id) {
        $pdo->prepare("DELETE FROM event_reservations WHERE id=:id")->execute([':id'=>$id]);
        $msg = "Réservation supprimée.";
    }
    header('Location: reservations_salles.php?msg='.urlencode($msg)); exit;
}
if (!empty($_GET['msg'])) $msg = $_GET['msg'];

$filtre = $_GET['statut'] ?? 'tous';
$sql = "SELECT * FROM event_reservations";
$params = [];
if ($filtre !== 'tous') { $sql .= " WHERE statut=:s"; $params[':s']=$filtre; }
$sql .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$reservations = $stmt->fetchAll();

$counts = ['tous'=>0,'en_attente'=>0,'confirmee'=>0,'annulee'=>0];
foreach(['en_attente','confirmee','annulee'] as $s) $counts[$s] = (int)$pdo->query("SELECT COUNT(*) FROM event_reservations WHERE statut='$s'")->fetchColumn();
$counts['tous'] = array_sum(array_slice($counts,1));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Réservations Salles — Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;1,400&family=Montserrat:wght@300;400;500&family=Cinzel:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../hotel-html/css/style.css">
  <link rel="stylesheet" href="css/admin.css">
</head>
<body>
<div class="admin-layout">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <main class="admin-contenu">
    <h1 class="admin-titre">🎉 Réservations Salles des Fêtes</h1>
    <div class="admin-breadcrumb"><a href="dashboard.php">Dashboard</a> / Salles des Fêtes</div>
    <?php if($msg): ?><div class="alerte alerte-ok">✓ <?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <div class="mini-stat-row">
      <?php foreach(['tous'=>['Total','var(--or)'],'en_attente'=>['En attente','#FF9800'],'confirmee'=>['Confirmées','#4CAF50'],'annulee'=>['Annulées','#E53935']] as $k=>[$l,$c]): ?>
      <div class="mini-stat-chip"><strong style="color:<?= $c ?>"><?= $counts[$k] ?></strong> <?= $l ?></div>
      <?php endforeach; ?>
    </div>

    <div class="filtres-bar">
      <?php foreach(['tous'=>'Toutes','en_attente'=>'En attente','confirmee'=>'Confirmées','annulee'=>'Annulées'] as $k=>$l): ?>
      <a href="?statut=<?= $k ?>" class="filtre-btn <?= $filtre===$k?'actif':'' ?>"><?= $l ?> (<?= $counts[$k] ?>)</a>
      <?php endforeach; ?>
      <input type="text" id="searchInput" class="search-input" placeholder="🔍 Rechercher..." oninput="document.querySelectorAll('#mainTable tbody tr').forEach(tr=>tr.style.display=tr.textContent.toLowerCase().includes(this.value.toLowerCase())?'':'none')">
    </div>

    <div class="panel" style="padding:0">
      <div class="tableau-wrap">
        <table class="tableau" id="mainTable">
          <thead><tr><th>Référence</th><th>Client</th><th>Email</th><th>Tél.</th><th>Type Événement</th><th>Date</th><th>Invités</th><th>Salle</th><th>Budget</th><th>Statut</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach($reservations as $r): ?>
            <tr>
              <td style="font-family:'Cinzel',serif;font-size:0.66rem;color:var(--or)"><?= htmlspecialchars($r['reference']) ?></td>
              <td style="font-weight:500"><?= htmlspecialchars($r['nom_client']) ?></td>
              <td style="font-size:0.78rem;color:var(--gris)"><?= htmlspecialchars($r['email_client']) ?></td>
              <td style="font-size:0.78rem;color:var(--gris)"><?= htmlspecialchars($r['telephone']??'—') ?></td>
              <td><?= htmlspecialchars($r['type_event']) ?></td>
              <td><?= date('d/m/Y',strtotime($r['date_event'])) ?></td>
              <td style="text-align:center"><?= (int)$r['nb_invites'] ?></td>
              <td style="color:var(--gris)"><?= htmlspecialchars($r['salle']??'—') ?></td>
              <td style="color:var(--or)"><?= $r['budget'] ? number_format($r['budget'],0,',',' ').' €' : '—' ?></td>
              <td><span class="badge-statut badge-<?= $r['statut'] ?>"><?= str_replace('_',' ',$r['statut']) ?></span></td>
              <td>
                <div style="display:flex;gap:0.3rem">
                  <button class="btn-icon btn-success" onclick="ouvrirEdit(<?= htmlspecialchars(json_encode($r)) ?>)" title="Modifier">✏️</button>
                  <button class="btn-icon btn-danger"  onclick="confirmerSupp(<?= $r['id'] ?>,'<?= htmlspecialchars(addslashes($r['nom_client'])) ?>')" title="Supprimer">🗑️</button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($reservations)): ?><tr><td colspan="11" style="text-align:center;padding:2.5rem;color:var(--gris)">Aucune réservation.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<div class="modal-overlay" id="m-edit">
  <div class="modal-box">
    <button class="modal-close" onclick="document.querySelectorAll('.modal-overlay').forEach(m=>m.classList.remove('open'))">✕</button>
    <div class="modal-titre">Modifier la Réservation Salle</div>
    <form method="POST">
      <input type="hidden" name="action" value="modifier">
      <input type="hidden" name="id" id="edit-id">
      <div class="form-grille-2">
        <div class="form-groupe"><label>Nom client</label><input type="text" name="nom_client" id="edit-nom" required></div>
        <div class="form-groupe"><label>Email</label><input type="email" name="email_client" id="edit-email" required></div>
        <div class="form-groupe"><label>Téléphone</label><input type="text" name="telephone" id="edit-tel"></div>
        <div class="form-groupe"><label>Nb invités</label><input type="number" name="nb_invites" id="edit-inv" min="0"></div>
        <div class="form-groupe"><label>Budget (€)</label><input type="number" name="budget" id="edit-budget" min="0" step="100"></div>
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
    <div class="modal-titre" style="color:#E53935">⚠️ Confirmer la suppression</div>
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
function ouvrirEdit(r) {
  document.getElementById('edit-id').value=r.id; document.getElementById('edit-nom').value=r.nom_client;
  document.getElementById('edit-email').value=r.email_client; document.getElementById('edit-tel').value=r.telephone||'';
  document.getElementById('edit-inv').value=r.nb_invites; document.getElementById('edit-budget').value=r.budget||0;
  document.getElementById('edit-statut').value=r.statut; document.getElementById('m-edit').classList.add('open');
}
function confirmerSupp(id,nom) {
  document.getElementById('supp-id').value=id; document.getElementById('supp-nom').textContent=nom;
  document.getElementById('m-supp').classList.add('open');
}
</script>
</body>
</html>
