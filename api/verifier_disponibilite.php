<?php
// api/verifier_disponibilite.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Méthode non autorisée.');
}

$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$typeSlug  = sanitize($data['type'] ?? '');
$arrivee   = sanitize($data['date_arrivee'] ?? '');
$depart    = sanitize($data['date_depart'] ?? '');
$personnes = (int)($data['nb_personnes'] ?? 1);

if (!$typeSlug || !$arrivee || !$depart) {
    jsonResponse(false, 'Paramètres manquants.');
}
if (strtotime($arrivee) >= strtotime($depart)) {
    jsonResponse(false, 'La date de départ doit être postérieure à la date d\'arrivée.');
}
if (strtotime($arrivee) < strtotime('today')) {
    jsonResponse(false, 'La date d\'arrivée ne peut pas être dans le passé.');
}

$pdo = getDB();

// Récupérer le type et vérifier la capacité
$stmt = $pdo->prepare("SELECT * FROM room_types WHERE slug = :slug");
$stmt->execute([':slug' => $typeSlug]);
$type = $stmt->fetch();

if (!$type) jsonResponse(false, 'Type de chambre inconnu.');
if ($personnes > $type['capacite_max']) {
    jsonResponse(false, "Ce type de chambre accepte au maximum {$type['capacite_max']} personne(s).");
}

// Chercher une chambre disponible
$stmt = $pdo->prepare("
    SELECT r.id, r.numero FROM rooms r
    WHERE r.room_type_id = :type_id
      AND r.statut = 'disponible'
      AND r.id NOT IN (
          SELECT res.room_id FROM reservations res
          WHERE res.statut NOT IN ('annulee')
            AND NOT (res.date_depart <= :arrivee OR res.date_arrivee >= :depart)
      )
    LIMIT 1
");
$stmt->execute([':type_id' => $type['id'], ':arrivee' => $arrivee, ':depart' => $depart]);
$room = $stmt->fetch();

$nuits = nbNuits($arrivee, $depart);
$prix  = $nuits * $type['prix_nuit'];

jsonResponse(true, $room ? 'Disponible' : 'Indisponible', [
    'disponible'  => (bool)$room,
    'room_id'     => $room['id'] ?? null,
    'nuits'       => $nuits,
    'prix_nuit'   => (float)$type['prix_nuit'],
    'prix_total'  => (float)$prix,
    'type_nom'    => $type['nom'],
]);
