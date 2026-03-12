<?php
session_start();
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();
$pdo = getDB();
$page_active = 'services';
$msg = ''; $err = '';

// Auto-create table
try { $pdo->exec("CREATE TABLE IF NOT EXISTS services (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, categorie ENUM('chambre','salle','piscine','restaurant','spa','autre') NOT NULL DEFAULT 'autre', nom VARCHAR(100) NOT NULL, description TEXT, capacite SMALLINT, tarif DECIMAL(10,2), unite VARCHAR(30) DEFAULT 'unité', statut ENUM('actif','inactif') DEFAULT 'actif', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB"); } catch(Exception $e){}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'ajouter') {
        $nom  = sanitize($_POST['nom']??'');
        $desc = sanitize($_POST['description']??'');
        $cat  = $_POST['categorie']??'autre';
        $cap  = (int)($_POST['capacite']??0) ?: null;
        $tar  = (float)($_POST['tarif']??0) ?: null;
        $un   = sanitize($_POST['unite']??'unité');
        if ($nom) {
            $pdo->prepare("INSERT INTO services (categorie,nom,description,capacite,tarif,unite) VALUES (:c,:n,:d,:ca,:t,:u)")
                ->execute([':c'=>$cat,':n'=>$nom,':d'=>$desc,':ca'=>$cap,':t'=>$tar,':u'=>$un]);
            $msg = "Service ajouté avec succès.";
        } else { $err = "Le nom est obligatoire."; }
    }

    if ($action === 'modifier' && $id) {
        $pdo->prepare("UPDATE services SET categorie=:c, nom=:n, description=:d, capacite=:ca, tarif=:t, unite=:u, statut=:s WHERE id=:id")
            ->execute([':c'=>$_POST['categorie']??'autre',':n'=>sanitize($_POST['nom']??''),':d'=>sanitize($_POST['description']??''),':ca'=>(int)($_POST['capacite']??0)?:(null),':t'=>(float)($_POST['tarif']??0)?:(null),':u'=>sanitize($_POST['unite']??'unité'),':s'=>$_POST['statut']??'actif',':id'=>$id]);
        $msg = "Service modifié.";
    }

    if ($action === 'supprimer' && $id) {
        $pdo->prepare("DELETE FROM services WHERE id=:id")->execute([':id'=>$id]);
        $msg = "Service supprimé.";
    }

    header('Location: services.php?msg='.urlencode($msg).'&err='.urlencode($err)); exit;
}

if (!empty($_GET['msg'])) $msg = $_GET['msg'];
if (!empty($_GET['err'])) $err = $_GET['err'];

$services = $pdo->query("SELECT * FROM services ORDER BY categorie, nom")->fetchAll();
$categories = ['chambre'=>['label'=>'Chambres','icon'=>'🛏️'],'salle'=>['label'=>'Salles des Fêtes','icon'=>'🎉'],'piscine'=>['label'=>'Piscines','icon'=>'🏊'],'restaurant'=>['label'=>'Restaurant','icon'=>'🍽️'],'spa'=>['label'=>'Spa & Bien-être','icon'=>'💆'],'autre'=>['label'=>'Autre','icon'=>'⚙️']];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Services — Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;1,400&family=Montserrat:wght@300;400;500&family=Cinzel:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../hotel-html/css/style.css">
  <link rel="stylesheet" href="css/admin.css">
</head>
<body>
<div class="admin-layout">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <main class="admin-contenu">
    <h1 class="admin-titre">⚙️ Gestion des Services</h1>
    <div class="admin-breadcrumb"><a href="dashboard.php">Dashboard</a> / Services</div>
    <?php if($msg): ?><div class="alerte alerte-ok">✓ <?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if($err): ?><div class="alerte alerte-err">✗ <?= htmlspecialchars($err) ?></div><?php endif; ?>

    <!-- Stats par catégorie -->
    <div class="mini-stat-row">
      <?php foreach($categories as $k=>$v):
        $nb = count(array_filter($services,fn($s)=>$s['categorie']===$k));
      ?>
      <div class="mini-stat-chip"><?= $v['icon'] ?> <strong style="color:var(--or)"><?= $nb ?></strong> <?= $v['label'] ?></div>
      <?php endforeach; ?>
    </div>

    <div style="display:grid;grid-template-columns:340px 1fr;gap:1.5rem;align-items:start">

      <!-- Formulaire ajout -->
      <div class="panel" style="position:sticky;top:1rem">
        <div class="panel-header"><h2 class="panel-title">Ajouter un Service</h2></div>
        <form method="POST">
          <input type="hidden" name="action" value="ajouter">
          <div class="form-groupe"><label>Catégorie *</label>
            <select name="categorie" required>
              <?php foreach($categories as $k=>$v): ?>
              <option value="<?= $k ?>"><?= $v['icon'] ?> <?= $v['label'] ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-groupe"><label>Nom *</label><input type="text" name="nom" required placeholder="Ex: Suite Présidentielle"></div>
          <div class="form-groupe"><label>Description</label><textarea name="description" rows="3" placeholder="Description du service..."></textarea></div>
          <div class="form-grille-2">
            <div class="form-groupe"><label>Capacité</label><input type="number" name="capacite" min="0" placeholder="Nb personnes"></div>
            <div class="form-groupe"><label>Tarif (€)</label><input type="number" name="tarif" min="0" step="0.01" placeholder="0.00"></div>
          </div>
          <div class="form-groupe"><label>Unité</label>
            <select name="unite">
              <option value="nuit">Par nuit</option>
              <option value="heure">Par heure</option>
              <option value="journée">Par journée</option>
              <option value="personne">Par personne</option>
              <option value="séance">Par séance</option>
              <option value="unité">Unité</option>
            </select>
          </div>
          <button type="submit" class="btn btn-or" style="width:100%;justify-content:center"><span>➕ Ajouter</span></button>
        </form>
      </div>

      <!-- Liste des services groupés par catégorie -->
      <div>
        <?php foreach($categories as $cat_key=>$cat_info):
          $cat_services = array_filter($services, fn($s)=>$s['categorie']===$cat_key);
          if(empty($cat_services)) continue;
        ?>
        <div class="panel" style="margin-bottom:1.2rem">
          <div class="panel-header">
            <h2 class="panel-title" style="font-size:1.2rem"><?= $cat_info['icon'] ?> <?= $cat_info['label'] ?></h2>
            <span style="font-size:0.78rem;color:var(--gris)"><?= count($cat_services) ?> service(s)</span>
          </div>
          <div class="tableau-wrap">
            <table class="tableau">
              <thead><tr><th>Nom</th><th>Description</th><th>Capacité</th><th>Tarif</th><th>Unité</th><th>Statut</th><th>Actions</th></tr></thead>
              <tbody>
                <?php foreach($cat_services as $s): ?>
                <tr>
                  <td style="font-weight:500"><?= htmlspecialchars($s['nom']) ?></td>
                  <td style="color:var(--gris);font-size:0.79rem;max-width:200px"><?= htmlspecialchars(mb_strimwidth($s['description']??'',0,60,'...')) ?></td>
                  <td style="text-align:center"><?= $s['capacite'] ? (int)$s['capacite'].' pers.' : '—' ?></td>
                  <td style="color:var(--or)"><?= $s['tarif'] ? number_format($s['tarif'],0,',',' ').' €' : '—' ?></td>
                  <td style="color:var(--gris);font-size:0.8rem"><?= htmlspecialchars($s['unite']??'—') ?></td>
                  <td><span class="badge-statut badge-<?= $s['statut'] ?>"><?= $s['statut'] ?></span></td>
                  <td>
                    <div style="display:flex;gap:0.3rem">
                      <button class="btn-icon btn-success" onclick="ouvrirEdit(<?= htmlspecialchars(json_encode($s)) ?>)">✏️</button>
                      <button class="btn-icon btn-danger"  onclick="confirmerSupp(<?= $s['id'] ?>,'<?= htmlspecialchars(addslashes($s['nom'])) ?>')">🗑️</button>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if(empty($services)): ?>
        <div class="panel"><p style="color:var(--gris);text-align:center;padding:2rem">Aucun service. Ajoutez-en un à gauche.</p></div>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>

<!-- Modal Modifier -->
<div class="modal-overlay" id="m-edit">
  <div class="modal-box">
    <button class="modal-close" onclick="document.querySelectorAll('.modal-overlay').forEach(m=>m.classList.remove('open'))">✕</button>
    <div class="modal-titre">Modifier le Service</div>
    <form method="POST">
      <input type="hidden" name="action" value="modifier">
      <input type="hidden" name="id" id="edit-id">
      <div class="form-groupe"><label>Catégorie</label>
        <select name="categorie" id="edit-cat">
          <?php foreach($categories as $k=>$v): ?><option value="<?= $k ?>"><?= $v['icon'] ?> <?= $v['label'] ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-groupe"><label>Nom</label><input type="text" name="nom" id="edit-nom" required></div>
      <div class="form-groupe"><label>Description</label><textarea name="description" id="edit-desc" rows="3"></textarea></div>
      <div class="form-grille-2">
        <div class="form-groupe"><label>Capacité</label><input type="number" name="capacite" id="edit-cap" min="0"></div>
        <div class="form-groupe"><label>Tarif (€)</label><input type="number" name="tarif" id="edit-tarif" min="0" step="0.01"></div>
      </div>
      <div class="form-grille-2">
        <div class="form-groupe"><label>Unité</label>
          <select name="unite" id="edit-unite">
            <option value="nuit">Par nuit</option><option value="heure">Par heure</option><option value="journée">Par journée</option><option value="personne">Par personne</option><option value="séance">Par séance</option><option value="unité">Unité</option>
          </select>
        </div>
        <div class="form-groupe"><label>Statut</label>
          <select name="statut" id="edit-statut"><option value="actif">Actif</option><option value="inactif">Inactif</option></select>
        </div>
      </div>
      <button type="submit" class="btn btn-or" style="width:100%;justify-content:center"><span>💾 Enregistrer</span></button>
    </form>
  </div>
</div>
<div class="modal-overlay" id="m-supp">
  <div class="modal-box" style="max-width:380px">
    <button class="modal-close" onclick="document.querySelectorAll('.modal-overlay').forEach(m=>m.classList.remove('open'))">✕</button>
    <div class="modal-titre" style="color:#E53935">⚠️ Supprimer ?</div>
    <p style="color:var(--gris);margin-bottom:1.5rem">Supprimer le service <strong id="supp-nom" style="color:var(--ivoire)"></strong> ?</p>
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
function ouvrirEdit(s){
  document.getElementById('edit-id').value=s.id; document.getElementById('edit-cat').value=s.categorie;
  document.getElementById('edit-nom').value=s.nom; document.getElementById('edit-desc').value=s.description||'';
  document.getElementById('edit-cap').value=s.capacite||''; document.getElementById('edit-tarif').value=s.tarif||'';
  document.getElementById('edit-unite').value=s.unite||'unité'; document.getElementById('edit-statut').value=s.statut;
  document.getElementById('m-edit').classList.add('open');
}
function confirmerSupp(id,nom){document.getElementById('supp-id').value=id;document.getElementById('supp-nom').textContent=nom;document.getElementById('m-supp').classList.add('open');}
</script>
</body>
</html>
