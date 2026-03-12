<?php
// api/reserver_chambre.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Méthode non autorisée.');
}

$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

// Validation
$nom       = sanitize($data['nom'] ?? '');
$email     = sanitize($data['email'] ?? '');
$tel       = sanitize($data['telephone'] ?? '');
$typeSlug  = sanitize($data['type'] ?? '');
$arrivee   = sanitize($data['date_arrivee'] ?? '');
$depart    = sanitize($data['date_depart'] ?? '');
$personnes = (int)($data['nb_personnes'] ?? 1);
$demandes  = sanitize($data['demandes_speciales'] ?? '');

if (!$nom || !$email || !$typeSlug || !$arrivee || !$depart) {
    jsonResponse(false, 'Tous les champs obligatoires doivent être remplis.');
}
if (!validEmail($email)) {
    jsonResponse(false, 'Adresse email invalide.');
}
if (strtotime($arrivee) >= strtotime($depart)) {
    jsonResponse(false, 'Dates invalides.');
}

$pdo = getDB();

// Récupérer le type
$stmt = $pdo->prepare("SELECT * FROM room_types WHERE slug = :slug");
$stmt->execute([':slug' => $typeSlug]);
$type = $stmt->fetch();
if (!$type) jsonResponse(false, 'Type de chambre inconnu.');

// Trouver une chambre dispo
$stmt = $pdo->prepare("
    SELECT r.id FROM rooms r
    WHERE r.room_type_id = :type_id AND r.statut = 'disponible'
      AND r.id NOT IN (
          SELECT res.room_id FROM reservations res
          WHERE res.statut NOT IN ('annulee')
            AND NOT (res.date_depart <= :arrivee OR res.date_arrivee >= :depart)
      )
    LIMIT 1
");
$stmt->execute([':type_id' => $type['id'], ':arrivee' => $arrivee, ':depart' => $depart]);
$room = $stmt->fetch();

if (!$room) jsonResponse(false, 'Aucune chambre disponible pour ces dates. Veuillez choisir d\'autres dates.');

$nuits     = nbNuits($arrivee, $depart);
$prixTotal = $nuits * $type['prix_nuit'];
$reference = genererReference('CH');

// Enregistrer
$stmt = $pdo->prepare("
    INSERT INTO reservations
        (reference, room_id, nom_client, email_client, telephone, date_arrivee, date_depart, nb_personnes, prix_total, demandes_spec)
    VALUES
        (:ref, :room_id, :nom, :email, :tel, :arrivee, :depart, :pers, :prix, :dem)
");
$stmt->execute([
    ':ref'     => $reference,
    ':room_id' => $room['id'],
    ':nom'     => $nom,
    ':email'   => $email,
    ':tel'     => $tel,
    ':arrivee' => $arrivee,
    ':depart'  => $depart,
    ':pers'    => $personnes,
    ':prix'    => $prixTotal,
    ':dem'     => $demandes,
]);

jsonResponse(true, 'Réservation confirmée avec succès.', [
    'reference'  => $reference,
    'prix_total' => $prixTotal,
    'nuits'      => $nuits,
]);
