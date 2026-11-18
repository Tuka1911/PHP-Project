<?php
declare(strict_types=1);
require_once __DIR__ . '/i18n.php';

date_default_timezone_set('Asia/Almaty');
ini_set('session.use_strict_mode', '1');
$sessionDirectory = dirname(__DIR__) . '/.runtime/sessions';
if (!is_dir($sessionDirectory) && !mkdir($sessionDirectory, 0700, true) && !is_dir($sessionDirectory)) { throw new RuntimeException('Cannot create session directory.'); }
session_save_path($sessionDirectory);
session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite' => 'Lax', 'path' => '/']);
if (!session_start()) { throw new RuntimeException('Cannot start session.'); }
header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");
header('Cache-Control: no-store');
if (($_COOKIE['jinal_lang'] ?? null) !== active_lang()) {
    setcookie('jinal_lang',active_lang(),['expires'=>time()+31536000,'path'=>'/','httponly'=>true,'samesite'=>'Lax','secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
}
if (PHP_SAPI !== 'cli') { ob_start('translate_html'); }

function db_config(): array
{
    $file = dirname(__DIR__) . '/config.local.php';
    $local = is_file($file) ? require $file : [];
    if (!is_array($local)) { throw new RuntimeException('config.local.php must return an array.'); }
    $config = array_replace(['host'=>'127.0.0.1','port'=>'3306','name'=>'jinal','user'=>'root','password'=>''], $local);
    foreach (['host'=>'DB_HOST','port'=>'DB_PORT','name'=>'DB_NAME','user'=>'DB_USER','password'=>'DB_PASS'] as $key=>$variable) {
        $value = getenv($variable);
        if ($value !== false) { $config[$key]=$value; }
    }
    if (!preg_match('/\A[a-zA-Z0-9_]{1,64}\z/',$config['name']) || !ctype_digit((string)$config['port'])) { throw new RuntimeException('Invalid database configuration.'); }
    return $config;
}

function db(): PDO
{
    static $pdo;
    if (!$pdo) {
        $c = db_config();
        $pdo = new PDO("mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset=utf8mb4", $c['user'], $c['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
        $pdo->exec("SET time_zone = '+05:00'");
    }
    return $pdo;
}

function query(string $sql, array $params = []): PDOStatement
{
    $statement = db()->prepare($sql);
    $statement->execute($params);
    return $statement;
}

function e(mixed $value): string { return htmlspecialchars(is_scalar($value) ? (string)$value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $page = 'home', array $params = []): string { return 'index.php?' . http_build_query(['page' => $page] + $params + ['lang'=>active_lang()]); }
function redirect(string $page, array $params = []): never { header('Location: ' . url($page, $params), true, 303); exit; }
function flash(string $message, string $type = 'success'): void { $_SESSION['flash'] = compact('message', 'type'); }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">'; }
function money(mixed $price): string { return (float) $price <= 0 ? 'Бесплатно' : number_format((float) $price, 0, ',', ' ') . ' ₸'; }
function date_label(string $date): string
{
    $months = match(active_lang()) {
        'en'=>[1=>'Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
        'kk'=>[1=>'қаң','ақп','нау','сәу','мам','мау','шіл','там','қыр','қаз','қар','жел'],
        default=>[1=>'янв','фев','мар','апр','май','июн','июл','авг','сен','окт','ноя','дек']
    };
    $t = strtotime($date);
    return date('j', $t) . ' ' . $months[(int) date('n', $t)] . ', ' . date('H:i', $t);
}
function poster(array $event): string
{
    $slug = $event['poster'] ?? 'meetup';
    return 'assets/' . (in_array($slug, ['workshop','music','sport','art','tech','meetup'], true) ? $slug : 'meetup') . '.svg';
}
function current_user(): ?array
{
    return empty($_SESSION['user_id']) ? null : (query('SELECT id, name, email, role, bio, created_at FROM users WHERE id = ?', [$_SESSION['user_id']])->fetch() ?: null);
}
function require_user(): array
{
    $user = current_user();
    if (!$user) { flash('Войдите, чтобы продолжить.', 'info'); redirect('login'); }
    return $user;
}
function can_manage(array $event, ?array $user): bool { return $user && ((int) $user['id'] === (int) $event['organizer_id'] || $user['role'] === 'admin'); }
function find_event(int $id): ?array
{
    return query('SELECT e.*, c.name AS category_name, c.slug AS category_slug, u.name AS organizer_name, (SELECT COUNT(*) FROM bookings b WHERE b.event_id=e.id) AS registered_count FROM events e JOIN categories c ON c.id=e.category_id JOIN users u ON u.id=e.organizer_id WHERE e.id=?', [$id])->fetch() ?: null;
}
function input(string $name): string { return trim(is_string($_POST[$name] ?? null) ? $_POST[$name] : ''); }
function get_value(string $name, string $default = ''): string { return is_string($_GET[$name] ?? null) ? trim($_GET[$name]) : $default; }
function positive_id(mixed $id): int { return max(0, (int) (is_scalar($id) ? $id : 0)); }
function abort_page(int $status, string $message): never
{
    http_response_code($status);
    $page = 'error'; $title = 'Ошибка ' . $status; $user = current_user(); $flash = null;
    require dirname(__DIR__) . '/templates/header.php';
    require dirname(__DIR__) . '/templates/error.php';
    require dirname(__DIR__) . '/templates/footer.php';
    exit;
}
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
