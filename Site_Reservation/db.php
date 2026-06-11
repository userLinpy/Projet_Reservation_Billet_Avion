<?php
declare(strict_types=1);

session_start();

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'gestion_avion');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // throw exceptions

// one global connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');

/*---------------------- CSRF ----------------------*/
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}
function check_csrf(string $token): bool {
    return hash_equals($_SESSION['csrf'] ?? '', $token);
}

// Flash messages
function set_flash(string $msg, string $type = 'info'): void {
    $_SESSION['flash_msg']  = $msg;
    $_SESSION['flash_type'] = $type;            // info / success / error
}
function display_flash(): string {
    if (!empty($_SESSION['flash_msg'])) {
        $html = '<p class="flash '.htmlspecialchars($_SESSION['flash_type']).'">'
              . htmlspecialchars($_SESSION['flash_msg'])
              . '</p>';
        unset($_SESSION['flash_msg'], $_SESSION['flash_type']);
        return $html;
    }
    return '';
}

// Auth helpers
function isLoggedIn(): bool {
    return isset($_SESSION['passager_id']);
}
function getLoggedInUserId(): ?int {
    return $_SESSION['passager_id'] ?? null;
}
function getLoggedInEmail(): ?string {
    return $_SESSION['email'] ?? null; 
}
function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}
function isAdmin(): bool {
    // Admin Check
    return (getLoggedInEmail() === 'admin@admin.com');
}
function friendly_sql_error(mysqli_sql_exception $e): string {
    // MySQL error numbers
    $map = [
        // 1451 = foreign‑key RESTRICT violation
        1451 => 'Cannot delete this record because other data depend on it.',
        // 1062 = duplicate entry (unique key violation)
        1062 => 'A record with the same identifier already exists.',
        // 1452 = foreign‑key INSERT violation (parent row missing)
        1452 => 'You cannot create this record because a required related record does not exist.',
    ];

    $code = $e->getCode();
    return $map[$code] ?? $e->getMessage();
}

?>