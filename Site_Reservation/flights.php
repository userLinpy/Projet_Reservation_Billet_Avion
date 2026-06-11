<?php
require_once __DIR__.'/db.php';

$flightId = intval($_GET['id'] ?? 0);
if ($flightId <= 0) {
    set_flash('Invalid flight ID.', 'error');
    header('Location: home.php');
    exit;
}

// FETCH FLIGHT INFO
$flightStmt = $conn->prepare(
    "SELECT v.id_Vol, v.ville_depart, v.ville_arrivee,
            v.date_depart, v.date_arrivee,
            a.immatriculation, a.modele, a.capacite
     FROM vol v
     JOIN avion a ON v.immatriculation = a.immatriculation
     WHERE v.id_Vol = ?"
);
$flightStmt->bind_param('i', $flightId);
$flightStmt->execute();
$flightRes = $flightStmt->get_result();

if ($flightRes->num_rows === 0) {
    set_flash('Flight not found.', 'error');
    header('Location: home.php');
    exit;
}
$flight = $flightRes->fetch_assoc();

// HANDLE BUY / CANCEL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    if (!check_csrf($_POST['csrf'] ?? '')) {
        set_flash('Invalid CSRF token.', 'error');
        header("Location: flights.php?id=$flightId");
        exit;
    }

    if (!isLoggedIn()) {
        set_flash('You must be logged in to book a ticket.', 'error');
        header('Location: login.php');
        exit;
    }

    $userId = getLoggedInUserId();

    if ($_POST['action'] === 'buy') {
        // Check plane capacity
        $cntStmt = $conn->prepare(
            "SELECT COUNT(*) FROM reservation WHERE id_vol = ?"
        );
        $cntStmt->bind_param('i', $flightId);
        $cntStmt->execute();
        $cntStmt->bind_result($existingReservations);
        $cntStmt->fetch();
        $cntStmt->close();

        if ($existingReservations >= $flight['capacite']) {
            set_flash('Cannot book this flight – the plane is fully booked.', 'error');
            header("Location: flights.php?id=$flightId");
            exit;
        }


        // Validate class & luggage
        $classe   = trim($_POST['classe']   ?? '');
        $baggage  = intval($_POST['baggage'] ?? -1);   // will be -1 if omitted

        $allowedClasses = ['Economy','Business','First'];
        if (!in_array($classe, $allowedClasses, true)) {
            set_flash('Invalid class selected.', 'error');
            header("Location: flights.php?id=$flightId");
            exit;
        }
        if ($baggage < 0 || $baggage > 3) {
            set_flash('Luggage must be between 0 and 3 pieces.', 'error');
            header("Location: flights.php?id=$flightId");
            exit;
        }
        
        // Insert reservation (with class & baggage)
        $ins = $conn->prepare(
            // reservation_date gets the current date automatically
            "INSERT INTO reservation (classe, baggage, id_passager, id_vol)
             VALUES (?, ?, ?, ?)"
        );
        $ins->bind_param('siii', $classe, $baggage, $userId, $flightId);
        try {
            $ins->execute();
            set_flash('Ticket booked! See it in My Space.', 'success');
        } catch (mysqli_sql_exception $e) {
            // Duplicate reservation – treat as info
            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                set_flash('You already have a reservation for this flight.', 'info');
            } else {
                set_flash('Error booking ticket: '.$e->getMessage(), 'error');
            }
        }

        header("Location: flights.php?id=$flightId");
        exit;

    }

    if ($_POST['action'] === 'cancel') {
        $del = $conn->prepare(
            "DELETE FROM reservation WHERE id_passager = ? AND id_Vol = ?"
        );
        $del->bind_param('ii', $userId, $flightId);
        $del->execute();
        $msg = $del->affected_rows ? 'Reservation cancelled.' : 'No reservation to cancel.';
        set_flash($msg, $del->affected_rows ? 'success' : 'info');
        header("Location: flights.php?id=$flightId");
        exit;
    }
}

// IS USER ALREADY BOOKED?
$alreadyBooked = false;
if (isLoggedIn()) {
    $chk = $conn->prepare(
        "SELECT id_reservation FROM reservation WHERE id_passager = ? AND id_Vol = ?"
    );
    $uid = getLoggedInUserId();
    $chk->bind_param('ii', $uid, $flightId);
    $chk->execute();
    $chkRes = $chk->get_result();
    $alreadyBooked = $chkRes->num_rows > 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Flight #<?= htmlspecialchars($flight['id_Vol']); ?> – MIASHS Airlines</title>
    <link rel="stylesheet" href="css_folder/flightstyle.css">
</head>
<body>
<header class="header">
    <div class="title"><h1><span>MIASHS</span> Airlines</h1></div>
    <div class="nav-bar">
        <a href="home.php">Home</a>
        <a href="myspace.php">My Space</a>
        <?php
            if (isAdmin()) {
                echo '<a href="admin.php">Admin Board</a>';
            }
        ?>
        <a href="logout.php">Log out</a>
    </div>
</header>

<main>
    <?= display_flash(); ?>

    <section class="infos" id="infos">
        <h1 class="Info-title">Flight Info</h1>
        <h3>From: <?= htmlspecialchars($flight['ville_depart']) ?></h3>
        <h3>To:   <?= htmlspecialchars($flight['ville_arrivee']) ?></h3>
        <h3>Departure: <?= htmlspecialchars($flight['date_depart']) ?></h3>
        <h3>Arrival:   <?= htmlspecialchars($flight['date_arrivee']) ?></h3>
        <h3>Plane (Immatriculation): <?= htmlspecialchars($flight['immatriculation']) ?></h3>
        <h3>Model: <?= htmlspecialchars($flight['modele']) ?></h3>
        <h3>Capacity: <?= htmlspecialchars($flight['capacite']) ?></h3>
    </section>

    <section class="changes" id="changes">
        <?php if (isLoggedIn()): ?>
            <?php if ($alreadyBooked): ?>
                <p>You already have a reservation for this flight.</p>
                <form method="POST" action="flights.php?id=<?= $flightId ?>">
                    <input type="hidden" name="action" value="cancel">
                    <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">
                    <input type="submit" value="Cancel reservation" class="button">
                </form>
            <?php else: ?>
                <form method="POST" action="flights.php?id=<?= $flightId ?>">
                    <input type="hidden" name="action" value="buy">
                    <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">

                    <div class="form-group">
                        <label for="classe" class="form-label">Class</label>
                        <select name="classe" id="classe" required>
                            <option value="Economy">Economy</option>
                            <option value="Business">Business</option>
                            <option value="First">First</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="baggage" class="form-label">Luggage (0‑3 pieces)</label>
                        <input type="number" name="baggage" id="baggage"
                               min="0" max="3" value="0" required>
                    </div>

                    <input type="submit" value="Buy ticket" class="button">
                </form>
            <?php endif; ?>
        <?php else: ?>
            <p>Please <a href="login.php">log in</a> to purchase a ticket.</p>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
