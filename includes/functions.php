<?php
// ============================================================
//  includes/functions.php — Fonctions utilitaires
// ============================================================

require_once __DIR__ . '/../config/database.php';

function genererReference(string $prefix = 'RES'): string {
    return strtoupper($prefix) . '-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}
function sanitize(string $val): string {
    return htmlspecialchars(strip_tags(trim($val)), ENT_QUOTES, 'UTF-8');
}
function validEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}
function nbNuits(string $arrivee, string $depart): int {
    $d1 = new DateTime($arrivee); $d2 = new DateTime($depart);
    return max(0, (int)$d1->diff($d2)->days);
}
function jsonResponse(bool $success, string $message, array $data = []): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data)); exit;
}
function chambreDisponible(PDO $pdo, int $roomId, string $arrivee, string $depart): bool {
    $sql = "SELECT COUNT(*) FROM reservations WHERE room_id=:r AND statut NOT IN ('annulee') AND NOT (date_depart<=:a OR date_arrivee>=:d)";
    $stmt = $pdo->prepare($sql); $stmt->execute([':r'=>$roomId,':a'=>$arrivee,':d'=>$depart]);
    return (int)$stmt->fetchColumn() === 0;
}

// ── AUTH ─────────────────────────────────────────────────────
function requireAnyAuth(): void {
    if (session_status()===PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['admin_id']) && empty($_SESSION['employe_id'])) {
        header('Location: /hotel-luxe/admin/login.php'); exit;
    }
}
function requireAdmin(): void {
    if (session_status()===PHP_SESSION_NONE) session_start();
    if (!empty($_SESSION['employe_id'])) { header('Location: /hotel-luxe/admin/dashboard.php'); exit; }
    if (empty($_SESSION['admin_id'])) { header('Location: /hotel-luxe/admin/login.php'); exit; }
}
function requireSuperAdmin(): void {
    if (session_status()===PHP_SESSION_NONE) session_start();
    requireAdmin();
    if (($_SESSION['admin_role']??'') !== 'superadmin') { header('Location: /hotel-luxe/admin/dashboard.php'); exit; }
}

// ── PERMISSIONS ───────────────────────────────────────────────
function permissionsParDefaut(string $service): array {
    $defaults = [
        'reception'  => ['voir_chambres','approuver_chambres','creer_chambres','voir_restaurant','approuver_restaurant','creer_restaurant','voir_piscine','approuver_piscine','creer_piscine','voir_spa','approuver_spa','creer_spa','voir_salles','approuver_salles','voir_clients','voir_calendrier'],
        'restaurant' => ['voir_restaurant','approuver_restaurant','creer_restaurant'],
        'chambres'   => ['voir_chambres','approuver_chambres','creer_chambres','gerer_chambres','voir_calendrier'],
        'spa'        => ['voir_spa','approuver_spa','creer_spa'],
        'piscine'    => ['voir_piscine','approuver_piscine','creer_piscine'],
    ];
    return $defaults[$service] ?? [];
}
function libelleService(string $service): string {
    return ['reception'=>'Réception','restaurant'=>'Restaurant','chambres'=>'Chambres','spa'=>'Spa','piscine'=>'Piscine'][$service] ?? ucfirst($service);
}
function emojiService(string $service): string {
    return ['reception'=>'🏨','restaurant'=>'🍽️','chambres'=>'🛏️','spa'=>'🧖','piscine'=>'🏊'][$service] ?? '⚙️';
}
function hasPermission(string $permission): bool {
    if (!empty($_SESSION['admin_id'])) return true;
    return in_array($permission, $_SESSION['employe_permissions'] ?? [], true);
}
function requirePermission(string $permission): void {
    requireAnyAuth();
    if (!hasPermission($permission)) {
        $_SESSION['flash_error'] = "Accès refusé : vous n'avez pas la permission requise.";
        header('Location: /hotel-luxe/admin/dashboard.php'); exit;
    }
}
function isAdmin(): bool { return !empty($_SESSION['admin_id']); }
function isSuperAdmin(): bool { return !empty($_SESSION['admin_id']) && ($_SESSION['admin_role']??'')==='superadmin'; }
function isEmployee(): bool { return !empty($_SESSION['employe_id']); }
function getNomConnecte(): string { return $_SESSION['admin_nom'] ?? $_SESSION['employe_nom'] ?? 'Utilisateur'; }
function getRoleConnecte(): string {
    if (!empty($_SESSION['admin_role'])) {
        return ['superadmin'=>'Super Administrateur','manager'=>'Manager','receptionniste'=>'Réceptionniste'][$_SESSION['admin_role']] ?? $_SESSION['admin_role'];
    }
    return !empty($_SESSION['employe_service']) ? libelleService($_SESSION['employe_service']) : '';
}

// ── LOGS ─────────────────────────────────────────────────────
function logActivity(PDO $pdo, string $action, string $details = ''): void {
    try {
        $type = isEmployee() ? 'employe' : 'admin';
        $id = $_SESSION['employe_id'] ?? $_SESSION['admin_id'] ?? 0;
        $pdo->prepare("INSERT INTO activity_logs (account_type,account_id,nom,action,details,ip) VALUES (?,?,?,?,?,?)")
            ->execute([$type,$id,getNomConnecte(),$action,$details,$_SERVER['REMOTE_ADDR']??'']);
    } catch (Exception $e) {}
}
