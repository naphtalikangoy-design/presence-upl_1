<?php
date_default_timezone_set('Africa/Lubumbashi');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
if (!empty($_SERVER['HTTPS']) || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ini_set('session.cookie_secure', '1');
session_start();

const SETTINGS_DEFAULTS = [
    'heure_ouverture'    => '07:30',
    'heure_retard'       => '08:15',
    'heure_cloture'      => '10:00',
    'jours_ouvres'       => '1,2,3,4,5',
    'restriction_reseau' => '1',
    'nom_reseau'         => 'UPL-WiFi',
    'ips_autorisees'     => '',
];

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $url = trim((string)getenv('DATABASE_URL'));
    $u = $url !== '' ? parse_url($url) : false;
    if (!$u || empty($u['host']) || empty($u['user']) || !isset($u['pass']) || empty($u['path'])) {
        http_response_code(500);
        exit("Configuration manquante : la variable d'environnement DATABASE_URL est absente ou invalide. "
           . "Elle doit avoir la forme postgresql://utilisateur:motdepasse@hote/nombase (Internal Database URL de Render).");
    }
    parse_str($u['query'] ?? '', $q);
    $ssl = $q['sslmode'] ?? (strpos($u['host'], '.') !== false ? 'require' : 'prefer');
    $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s;sslmode=%s', $u['host'], $u['port'] ?? 5432, ltrim($u['path'], '/'), $ssl);
    $pdo = new PDO($dsn, urldecode($u['user']), urldecode($u['pass']), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS administrateurs (
        id SERIAL PRIMARY KEY, username VARCHAR(50) UNIQUE NOT NULL, password VARCHAR(255) NOT NULL);
    CREATE TABLE IF NOT EXISTS stagiaires (
        id SERIAL PRIMARY KEY, matricule VARCHAR(30) UNIQUE NOT NULL, nom VARCHAR(120) NOT NULL,
        faculte VARCHAR(120), lieu_stage VARCHAR(120), date_debut DATE DEFAULT CURRENT_DATE,
        actif BOOLEAN DEFAULT TRUE, password VARCHAR(255));
    CREATE TABLE IF NOT EXISTS presences (
        id SERIAL PRIMARY KEY, stagiaire_id INT REFERENCES stagiaires(id) ON DELETE CASCADE,
        date_presence DATE NOT NULL, statut VARCHAR(10) NOT NULL, heure TIME, ip VARCHAR(45),
        source VARCHAR(12) DEFAULT 'admin', observation VARCHAR(255),
        UNIQUE (stagiaire_id, date_presence));
    CREATE TABLE IF NOT EXISTS parametres (cle VARCHAR(50) PRIMARY KEY, valeur TEXT NOT NULL);
    ALTER TABLE stagiaires ADD COLUMN IF NOT EXISTS password VARCHAR(255);
    ALTER TABLE presences ADD COLUMN IF NOT EXISTS heure TIME;
    ALTER TABLE presences ADD COLUMN IF NOT EXISTS ip VARCHAR(45);
    ALTER TABLE presences ADD COLUMN IF NOT EXISTS source VARCHAR(12) DEFAULT 'admin';
    ");
    if ($pdo->query("SELECT COUNT(*) FROM administrateurs")->fetchColumn() == 0) {
        $pw = getenv('ADMIN_PASSWORD') ?: 'admin123';
        $pdo->prepare("INSERT INTO administrateurs (username, password) VALUES ('admin', ?)")
            ->execute([password_hash($pw, PASSWORD_DEFAULT)]);
    }
    return $pdo;
}

/* ---------- Paramètres ---------- */
function setting(string $k): string {
    static $all = null;
    if ($all === null) {
        $all = SETTINGS_DEFAULTS;
        foreach (db()->query("SELECT cle, valeur FROM parametres") as $r) $all[$r['cle']] = $r['valeur'];
    }
    return $all[$k] ?? '';
}
function save_setting(string $k, string $v): void {
    db()->prepare("INSERT INTO parametres (cle, valeur) VALUES (?,?)
        ON CONFLICT (cle) DO UPDATE SET valeur = EXCLUDED.valeur")->execute([$k, $v]);
}

/* ---------- Réseau ---------- */
function client_ip(): string {
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_TRUE_CLIENT_IP'] as $k)
        if (!empty($_SERVER[$k]) && filter_var($_SERVER[$k], FILTER_VALIDATE_IP)) return $_SERVER[$k];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $first = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        if (filter_var($first, FILTER_VALIDATE_IP)) return $first;
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}
function valid_ip_or_cidr(string $s): bool {
    if (strpos($s, '/') !== false) {
        [$ip, $b] = explode('/', $s, 2);
        if (!filter_var($ip, FILTER_VALIDATE_IP) || !ctype_digit($b)) return false;
        return (int)$b <= (strpos($ip, ':') !== false ? 128 : 32);
    }
    return (bool)filter_var($s, FILTER_VALIDATE_IP);
}
function ip_in_range(string $ip, string $range): bool {
    $a = @inet_pton($ip);
    if (strpos($range, '/') === false) return $a !== false && $a === @inet_pton($range);
    [$sub, $bits] = explode('/', $range, 2);
    $b = @inet_pton($sub);
    if ($a === false || $b === false || strlen($a) !== strlen($b)) return false;
    $bits = (int)$bits; $bytes = intdiv($bits, 8); $rem = $bits % 8;
    if ($bytes && substr($a, 0, $bytes) !== substr($b, 0, $bytes)) return false;
    if ($rem) {
        $mask = (~(0xFF >> $rem)) & 0xFF;
        if ((ord($a[$bytes]) & $mask) !== (ord($b[$bytes]) & $mask)) return false;
    }
    return true;
}
function allowed_ranges(): array {
    return array_values(array_filter(array_map('trim', preg_split('/\R/', setting('ips_autorisees')))));
}
function network_ok(?string $ip = null): bool {
    if (setting('restriction_reseau') !== '1') return true;
    $ip = $ip ?? client_ip();
    foreach (allowed_ranges() as $r) if (ip_in_range($ip, $r)) return true;
    return false;
}
function network_message(): string {
    if (!allowed_ranges()) return "Le réseau de l'établissement n'a pas encore été configuré par l'administrateur.";
    return "Accès refusé. Connectez-vous au réseau Wi-Fi de l'UPL (" . setting('nom_reseau') . ") puis réessayez.";
}

/* ---------- Sécurité ---------- */
function require_role(string $role): void {
    if (($_SESSION['role'] ?? '') !== $role) { header('Location: login.php'); exit; }
}
function csrf_field(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return '<input type="hidden" name="csrf" value="' . $_SESSION['csrf'] . '">';
}
function csrf_check(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(403); exit('Requête invalide.'); }
}
function flash(string $type, string $msg): void { $_SESSION['flash'] = [$type, $msg]; }
function gen_password(int $n = 8): string {
    $c = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789'; $s = '';
    for ($i = 0; $i < $n; $i++) $s .= $c[random_int(0, strlen($c) - 1)];
    return $s;
}
