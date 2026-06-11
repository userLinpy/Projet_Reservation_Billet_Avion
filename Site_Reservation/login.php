<?php
declare(strict_types=1);               // keep strict typing – we now obey it

require_once __DIR__ . '/db.php';      // $conn, csrf helpers, flash helpers, etc.


if (isLoggedIn()) {
    header('Location: home.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['form_type'])) {

    /* ---- CSRF protection ------------------------------------------------- */
    if (!check_csrf($_POST['csrf'] ?? '')) {
        set_flash('Invalid CSRF token.', 'error');
        header('Location: login.php');
        exit;
    }

    $type = $_POST['form_type'];   // 'login' or 'register'

    /* LOGIN */
    if ($type === 'login') {

        $email = trim((string)($_POST['mail'] ?? ''));
        $pass  = (string)($_POST['pass'] ?? '');

        if ($email === '' || $pass === '') {
            set_flash('Both email and password are required.', 'error');
            header('Location: login.php');
            exit;
        }
        $stmt = $conn->prepare(
            "SELECT id_passager, Mdp FROM passager WHERE mail = ?"
        );
        $stmt->bind_param('s', $email);          // $email is a string
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 1) {
            $stmt->bind_result($id, $hash);
            $stmt->fetch();

            /* make sure $hash is a string (never NULL) -------- */
            $hash = $hash ?? '';                  // coalesce null → empty string

            /* verify the password */
            if (password_verify($pass, $hash)) {
                // success → log the user in
                $_SESSION['passager_id'] = $id;
                $_SESSION['email']       = $email;
                header('Location: home.php');
                exit;
            }

            // wrong password
            set_flash('Incorrect password','error');
        } else {
            // email not found
            set_flash('No account found with that email.', 'error');
        }

        header('Location: login.php');
        exit;
    }

    /* REGISTRATION */
    if ($type === 'register') {

        /* read inputs, guarantee strings */
        $email     = trim((string)($_POST['mail'] ?? ''));
        $name      = trim((string)($_POST['nom'] ?? ''));
        $firstname = trim((string)($_POST['prenom'] ?? ''));
        $pass      = (string)($_POST['pass'] ?? '');

        /* basic validation */
        if ($email === '' || $name === '' || $firstname === '' || $pass === '') {
            set_flash('All fields are required.', 'error');
            header('Location: login.php');
            exit;
        }
        if (strlen($pass) < 5) {
            set_flash('Password must be at least 5 characters.', 'error');
            header('Location: login.php');
            exit;
        }

        /* duplicate‑email check */
        $dup = $conn->prepare("SELECT id_passager FROM passager WHERE mail = ?");
        $dup->bind_param('s', $email);
        $dup->execute();
        $dup->store_result();

        if ($dup->num_rows > 0) {
            set_flash('An account already exists for that email.', 'error');
            header('Location: login.php');
            exit;
        }

        /* insert the new user */
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $ins  = $conn->prepare(
            "INSERT INTO passager (Mail, Nom, Prenom, Mdp) VALUES (?,?,?,?)"
        );
        $ins->bind_param('ssss', $email, $name, $firstname, $hash);
        $ins->execute();

        /* auto‑login after successful registration */
        $_SESSION['passager_id'] = $ins->insert_id;
        $_SESSION['email']       = $email;

        header('Location: home.php');
        exit;
    }

    /* unknown form_type */
    set_flash('Unknown form submitted.', 'error');
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login – MIASHS Airlines</title>
    <link rel="stylesheet" href="css_folder/loginstyle.css">
</head>
<body>
<header class="header">
    <div class="title"><h1><span>MIASHS</span> Airlines</h1></div>
</header>

<main>
    <?= display_flash(); ?>

    <section class="form">
        <div class="log">

            <!-- LOGIN FORM -->
            <div class="login">
                <h2>Login</h2>
                <form method="POST" action="login.php">
                    <input type="hidden" name="form_type" value="login">
                    <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">
                    <div class="champs">
                        <label for="mail">Email</label>
                        <input type="email" name="mail" id="mail"
                               placeholder="Ex: exemple@gmail.com" required>
                        <label for="pass">Password</label>
                        <input type="password" name="pass" id="pass"
                               placeholder="min 5 char" minlength="5" required>
                    </div>
                    <div class="submit">
                        <input type="submit" value="Login" class="button">
                    </div>
                </form>
            </div>

            <!-- REGISTER FORM -->
            <div class="register">
                <h2>Register</h2>
                <form method="POST" action="login.php">
                    <input type="hidden" name="form_type" value="register">
                    <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">
                    <div class="champs">
                        <label for="mail">Email</label>
                        <input type="email" name="mail" id="mail"
                               placeholder="Ex: exemple@gmail.com" required>
                        <label for="nom">Name</label>
                        <input type="text" name="nom" placeholder="Ex: Lin" required>
                        <label for="prenom">First Name</label>
                        <input type="text" name="prenom"
                               placeholder="Ex: Lucien" required>
                        <label for="pass">Password</label>
                        <input type="password" name="pass" id="pass"
                               placeholder="min 5 char" minlength="5" required>
                    </div>
                    <div class="submit">
                        <input type="submit" value="Register" class="button">
                    </div>
                </form>
            </div>

        </div>
    </section>
</main>
</body>
</html>
