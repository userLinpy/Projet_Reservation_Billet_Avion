<?php
declare(strict_types=1);

/* --------------------------------------------------------------
   1️⃣  Load the same session helpers that the rest of the app uses
   -------------------------------------------------------------- */
require_once __DIR__ . '/db.php';      // ← starts the session for us

/* --------------------------------------------------------------
   2️⃣  (Optional) protect the request with a CSRF token
   -------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // The logout form sends a POST + hidden csrf field.
    if (!check_csrf($_POST['csrf'] ?? '')) {
        // If the token is wrong we simply ignore the request.
        set_flash('Invalid logout request.', 'error');
        header('Location: login.php');
        exit;
    }
}

/* --------------------------------------------------------------
   3️⃣  Remove *all* session data
   -------------------------------------------------------------- */
$_SESSION = [];

/* --------------------------------------------------------------
   4️⃣  Delete the session cookie (if cookies are used)
   -------------------------------------------------------------- */
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,                 // expire in the past
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

/* --------------------------------------------------------------
   5️⃣  Finally destroy the server‑side session data
   -------------------------------------------------------------- */
session_destroy();

/* --------------------------------------------------------------
   6️⃣  Give the user a friendly message and send him to the login page
   -------------------------------------------------------------- */
set_flash('You have been logged out.', 'info');
header('Location: login.php');
exit;
?>
