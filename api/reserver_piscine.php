<?php
// api/reserver_piscine.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, 'Méthode non autorisée.');

$data      = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$nom       = sanitize($data['nom'] ?? '');
$email     = sanitize($data['email'] ?? '');
$tel       = sanitize($data['telephone'] ?? '');
$piscine_id= (int)($data['piscine_id'] ?? 1);
$date      = sanitize($data['date_res'] ?? '');
$hdebut    = sanitize($data['heure_debut'] ?? '');
$hfin      = sanitize($data['heure_fin'] ?? '');
$personnes = (int)($data['nb_personnes'] ?? 2);
$msg       = sanitize($data['message'] ?? '');

if (!$nom || !$email || !$date || !$hdebut || !$hfin) jsonResponse(false, 'Champs obligatoires manquants.');
if (!validEmail($email)) jsonResponse(false, 'Email invalide.');
if ($hdebut >= $hfin) jsonResponse(false, 'L\'heure de fin doit être après l\'heure de début.');

$pdo = getDB();

// Vérifier disponibilité (pas de chevauchement)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM piscine_reservations WHERE piscine_id=:pid AND date_res=:date AND statut != 'annulee' AND NOT (heure_fin <= :debut OR heure_debut >= :fin)");
$stmt->execute([':pid'=>$piscine_id, ':date'=>$date, ':debut'=>$hdebut, ':fin'=>$hfin]);
if ($stmt->fetchColumn() > 0) jsonResponse(false, 'La piscine est déjà réservée à ce créneau. Choisissez un autre horaire.');

// Calcul tarif
$piscine = $pdo->prepare("SELECT tarif_heure FROM piscines WHERE id=:id");
$piscine->execute([':id'=>$piscine_id]);
$p = $piscine->fetch();
$tarif_heure = $p ? (float)$p['tarif_heure'] : 60.0;
$heures = (strtotime($hfin) - strtotime($hdebut)) / 3600;
$tarif_total = round($heures * $tarif_heure, 2);

$ref = genererReference('PISC');

$stmt = $pdo->prepare("INSERT INTO piscine_reservations (reference, piscine_id, nom_client, email_client, telephone, date_res, heure_debut, heure_fin, nb_personnes, tarif_total, message) VALUES (:ref, :pid, :nom, :email, :tel, :date, :hdebut, :hfin, :pers, :tarif, :msg)");
$stmt->execute([':ref'=>$ref, ':pid'=>$piscine_id, ':nom'=>$nom, ':email'=>$email, ':tel'=>$tel, ':date'=>$date, ':hdebut'=>$hdebut, ':hfin'=>$hfin, ':pers'=>$personnes, ':tarif'=>$tarif_total, ':msg'=>$msg]);

// Créer notification
try {
    $pdo->prepare("INSERT INTO notifications (type, titre, message) VALUES ('reservation_piscine', 'Nouvelle réservation piscine', :m)")
        ->execute([':m' => "$nom a réservé une piscine le ".date('d/m/Y',strtotime($date))." de $hdebut à $hfin."]);
} catch(Exception $e){}

jsonResponse(true, 'Réservation de piscine confirmée.', ['reference'=>$ref,'tarif_total'=>$tarif_total,'heures'=>$heures]);
