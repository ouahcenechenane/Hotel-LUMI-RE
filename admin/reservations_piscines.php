<?php
session_start();
require_once __DIR__ . '/../includes/functions.php';
requireAnyAuth();
requirePermission('voir_piscine');
$pdo = getDB();
$page_active = 'res_piscines';
$msg = ''; $err = '';

// Auto-créer table si nécessaire
try { $pdo->exec("CREATE TABLE IF NOT EXISTS piscine_reservations (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, reference VARCHAR(20) NOT NULL UNIQUE, piscine_id INT UNSIGNED, nom_client VARCHAR(200) NOT NULL, email_client VARCHAR(191) NOT NULL, telephone VARCHAR(20), date_res DATE NOT NULL, heure_debut TIME NOT NULL, heure_fin TIME NOT NULL, nb_personnes TINYINT NOT NULL DEFAULT 2, tarif_total DECIMAL(10,2) DEFAULT 0, statut ENUM('en_attente','confirmee','annulee') DEFAULT 'en_attente', message TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB"); } catch(Exception $e){}
try { $pdo->exec("CREATE TABLE IF NOT EXISTS piscines (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, nom VARCHAR(100) NOT NULL, description TEXT, capacite SMALLINT NOT NULL DEFAULT 20, tarif_heure DECIMAL(10,2) NOT NULL DEFAULT 50.00, statut ENUM('disponible','maintenance','fermee') DEFAULT 'disponible', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB"); } catch(Exception $e){}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'statut' && $id) {
        $s = $_POST['statut'] ?? '';
        if (in_array($s, ['en_attente','confirmee','annulee'])) {
            $pdo->prepare("UPDATE piscine_reservations SET statut=:s WHERE id=:id")->execute([':s'=>$s,':id'=>$id]);
            $msg = "Statut mis à jour.";
        }
    }
    if ($action === 'modifier' && $id) {
        $pdo->prepare("UPDATE piscine_reservations SET nom_client=:nom, email_client=:email, telephone=:tel, nb_personnes=:pers, statut=:s WHERE id=:id")
            ->execute([':nom'=>sanitize($_POST['nom_client']??''),':email'=>sanitize($_POST['email_client']??''),':tel'=>sanitize($_POST['telephone']??''),':pers'=>(int)($_POST['nb_personnes']??1),':s'=>$_POST['statut']??'en_attente',':id'=>$id]);
        $msg = "Réservation modifiée.";
    }
    if ($action === 'supprimer' && $id) {
        $pdo->prepare("DELETE FROM piscine_reservations WHERE id=:id")->execute([':id'=>$id]);
        $msg = "Réservation supprimée.";
    }
    header('Location: reservations_piscines.php?msg='.urlencode($msg)); exit;
}
if (!empty($_GET['msg'])) $msg = $_GET['msg'];

$filtre = $_GET['statut'] ?? 'tous';
$sql = "SELECT pr.*, p.nom AS piscine_nom FROM piscine_reservations pr LEFT JOIN piscines p ON pr.piscine_id=p.id";
$params = [];
if ($filtre !== 'tous') { $sql .= " WHERE pr.statut=:s"; $params[':s']=$filtre; }
$sql .= " ORDER BY pr.created_at DESC";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$reservations = $stmt->fetchAll();

$counts = ['tous'=>0,'en_attente'=>0,'confirmee'=>0,'annulee'=>0];
foreach(['en_attente','confirmee','annulee'] as $s) $counts[$s] = (int)$pdo->query("SELECT COUNT(*) FROM piscine_reservations WHERE statut='$s'")->fetchColumn();
$counts['tous'] = array_sum(array_slice($counts,1));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Réservations Piscines — Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;1,400&family=Montserrat:wght@300;400;500&family=Cinzel:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../hotel-html/css/style.css">
  <link rel="stylesheet" href="css/admin.css">
</head>
<body>
<div class="admin-layout">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <main class="admin-contenu">
    <h1 class="admin-titre">🏊 Réservations Piscines</h1>
    <div class="admin-breadcrumb"><a href="dashboard.php">Dashboard</a> / Piscines</div>
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
      <input type="text" class="search-input" placeholder="🔍 Rechercher..." oninput="document.querySelectorAll('#mainTable tbody tr').forEach(tr=>tr.style.display=tr.textContent.toLowerCase().includes(this.value.toLowerCase())?'':'none')">
    </div>

    <div class="panel" style="padding:0">
      <div class="tableau-wrap">
        <table class="tableau" id="mainTable">
          <thead><tr><th>Référence</th><th>Client</th><th>Email</th><th>Tél.</th><th>Piscine</th><th>Date</th><th>Heure début</th><th>Heure fin</th><th>Pers.</th><th>Tarif</th><th>Statut</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach($reservations as $r): ?>
            <tr>
              <td style="font-family:'Cinzel',serif;font-size:0.66rem;color:var(--or)"><?= htmlspecialchars($r['reference']) ?></td>
              <td style="font-weight:500"><?= htmlspecialchars($r['nom_client']) ?></td>
              <td style="font-size:0.78rem;color:var(--gris)"><?= htmlspecialchars($r['email_client']) ?></td>
              <td style="font-size:0.78rem;color:var(--gris)"><?= htmlspecialchars($r['telephone']??'—') ?></td>
              <td><?= htmlspecialchars($r['piscine_nom']??'—') ?></td>
              <td><?= date('d/m/Y',strtotime($r['date_res'])) ?></td>
              <td><?= $r['heure_debut'] ?></td>
              <td><?= $r['heure_fin'] ?></td>
              <td style="text-align:center"><?= (int)$r['nb_personnes'] ?></td>
              <td style="color:var(--or)"><?= $r['tarif_total'] ? number_format($r['tarif_total'],0,',',' ').' €' : '—' ?></td>
              <td><span class="badge-statut badge-<?= $r['statut'] ?>"><?= str_replace('_',' ',$r['statut']) ?></span></td>
              <td>
                <div style="display:flex;gap:0.3rem">
                  <button class="btn-icon btn-success" onclick="ouvrirEdit(<?= htmlspecialchars(json_encode($r)) ?>)">✏️</button>
                  <button class="btn-icon btn-danger"  onclick="confirmerSupp(<?= $r['id'] ?>,'<?= htmlspecialchars(addslashes($r['nom_client'])) ?>')">🗑️</button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($reservations)): ?><tr><td colspan="12" style="text-align:center;padding:2.5rem;color:var(--gris)">Aucune réservation de piscine.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<div class="modal-overlay" id="m-edit">
  <div class="modal-box">
    <button class="modal-close" onclick="document.querySelectorAll('.modal-overlay').forEach(m=>m.classList.remove('open'))">✕</button>
    <div class="modal-titre">Modifier la Réservation Piscine</div>
    <form method="POST">
      <input type="hidden" name="action" value="modifier">
      <input type="hidden" name="id" id="edit-id">
      <div class="form-grille-2">
        <div class="form-groupe"><label>Nom client</label><input type="text" name="nom_client" id="edit-nom" required></div>
        <div class="form-groupe"><label>Email</label><input type="email" name="email_client" id="edit-email" required></div>
        <div class="form-groupe"><label>Téléphone</label><input type="text" name="telephone" id="edit-tel"></div>
        <div class="form-groupe"><label>Nb personnes</label><input type="number" name="nb_personnes" id="edit-pers" min="1"></div>
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
function ouvrirEdit(r){document.getElementById('edit-id').value=r.id;document.getElementById('edit-nom').value=r.nom_client;document.getElementById('edit-email').value=r.email_client;document.getElementById('edit-tel').value=r.telephone||'';document.getElementById('edit-pers').value=r.nb_personnes;document.getElementById('edit-statut').value=r.statut;document.getElementById('m-edit').classList.add('open');}
function confirmerSupp(id,nom){document.getElementById('supp-id').value=id;document.getElementById('supp-nom').textContent=nom;document.getElementById('m-supp').classList.add('open');}
</script>
</body>
</html>
