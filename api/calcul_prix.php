<?php
// api/calcul_prix.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../includes/functions.php';

$typeSlug = sanitize($_GET['type'] ?? '');
$arrivee  = sanitize($_GET['date_arrivee'] ?? '');
$depart   = sanitize($_GET['date_depart'] ?? '');

if (!$typeSlug || !$arrivee || !$depart) jsonResponse(false, 'Paramètres manquants.');

$pdo = getDB();
$stmt = $pdo->prepare("SELECT prix_nuit, nom, capacite_max FROM room_types WHERE slug = :slug");
$stmt->execute([':slug' => $typeSlug]);
$type = $stmt->fetch();

if (!$type) jsonResponse(false, 'Type inconnu.');

$nuits = nbNuits($arrivee, $depart);
if ($nuits <= 0) jsonResponse(false, 'Dates invalides.');

$prixTotal = $nuits * $type['prix_nuit'];

// Tarif weekend +20%
$arriveeDay = date('N', strtotime($arrivee));
$supplement = ($arriveeDay >= 5) ? $prixTotal * 0.20 : 0;

jsonResponse(true, 'OK', [
    'nuits'        => $nuits,
    'prix_nuit'    => (float)$type['prix_nuit'],
    'sous_total'   => (float)$prixTotal,
    'supplement'   => round($supplement, 2),
    'prix_total'   => round($prixTotal + $supplement, 2),
    'type_nom'     => $type['nom'],
    'capacite_max' => $type['capacite_max'],
]);
