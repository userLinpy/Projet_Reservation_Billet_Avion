<?php
require_once __DIR__.'/db.php';
requireLogin();                // redirect if not logged in

$userId = getLoggedInUserId();

// HANDLE CANCELLATION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    if (!check_csrf($_POST['csrf'] ?? '')) {
        set_flash('Invalid CSRF token.', 'error');
    } else {
        $resId = intval($_POST['reservation_id'] ?? 0);
        if ($resId > 0) {
            $del = $conn->prepare(
                "DELETE FROM reservation WHERE id_reservation = ? AND id_passager = ?"
            );
            $del->bind_param('ii', $resId, $userId);
            $del->execute();
            if ($del->affected_rows) {
                set_flash('Reservation cancelled.', 'success');
            } else {
                set_flash('Reservation not found.', 'info');
            }
        } else {
            set_flash('Invalid reservation ID.', 'error');
        }
    }
    header('Location: myspace.php');
    exit;
}

// PAST FLIGHTS
$stmtPast = $conn->prepare(
    "SELECT r.id_reservation, v.id_Vol, v.ville_depart, v.ville_arrivee,
            v.date_depart, v.date_arrivee,
            a.immatriculation, a.modele, r.classe, r.baggage
     FROM reservation r
     JOIN vol v      ON r.id_Vol = v.id_Vol
     JOIN avion a    ON v.immatriculation = a.immatriculation
     WHERE r.id_passager = ?
       AND v.date_arrivee < NOW()
     ORDER BY v.date_depart DESC"
);
$stmtPast->bind_param('i', $userId);
$stmtPast->execute();
$pastFlights = $stmtPast->get_result();

// UPCOMING FLIGHTS
$stmtFuture = $conn->prepare(
    "SELECT r.id_reservation, v.id_Vol, v.ville_depart, v.ville_arrivee,
            v.date_depart, v.date_arrivee,
            a.immatriculation, a.modele, r.classe, r.baggage
     FROM reservation r
     JOIN vol v      ON r.id_Vol = v.id_Vol
     JOIN avion a    ON v.immatriculation = a.immatriculation
     WHERE r.id_passager = ?
       AND v.date_depart >= NOW()
     ORDER BY v.date_depart ASC"
);
$stmtFuture->bind_param('i', $userId);
$stmtFuture->execute();
$futureFlights = $stmtFuture->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Space – MIASHS Airlines</title>
    <link rel="stylesheet" href="css_folder/style.css">
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

    <!-- PAST FLIGHTS -->
    <section class="flights" id="old_flights">
        <div class="flights-container">
            <h2>Old flights</h2>
            <table class="table">
                <tr>
                    <th>Start</th><th>End</th><th>Start date</th>
                    <th>End date</th><th>Plane</th><th>Model</th>
                    <th>Class</th><th>Luggage</th>
                </tr>
                <?php if ($pastFlights->num_rows): ?>
                    <?php while ($row = $pastFlights->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['ville_depart']) ?></td>
                            <td><?= htmlspecialchars($row['ville_arrivee']) ?></td>
                            <td><?= htmlspecialchars($row['date_depart']) ?></td>
                            <td><?= htmlspecialchars($row['date_arrivee']) ?></td>
                            <td><?= htmlspecialchars($row['immatriculation']) ?></td>
                            <td><?= htmlspecialchars($row['modele']) ?></td>
                            <td><?= htmlspecialchars($row['classe']) ?></td>
                            <td><?= htmlspecialchars($row['baggage']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6">You have no past flights.</td></tr>
                <?php endif; ?>
            </table>
        </div>
    </section>

    <!-- UPCOMING FLIGHTS -->
    <section class="flights" id="next_flights">
        <div class="flights-container">
            <h2>Your next flights</h2>
            <table class="table">
                <tr>
                    <th>Start</th><th>End</th><th>Start date</th>
                    <th>End date</th><th>Plane</th><th>Model</th>
                    <th>Class</th><th>Luggage</th><th>Cancel</th>
                </tr>
                <?php if ($futureFlights->num_rows): ?>
                    <?php while ($row = $futureFlights->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['ville_depart']) ?></td>
                            <td><?= htmlspecialchars($row['ville_arrivee']) ?></td>
                            <td><?= htmlspecialchars($row['date_depart']) ?></td>
                            <td><?= htmlspecialchars($row['date_arrivee']) ?></td>
                            <td><?= htmlspecialchars($row['immatriculation']) ?></td>
                            <td><?= htmlspecialchars($row['modele']) ?></td>
                            <td><?= htmlspecialchars($row['classe']) ?></td>
                            <td><?= htmlspecialchars($row['baggage']) ?></td>
                            <td>
                                <form method="POST" action="myspace.php" style="display:inline;">
                                    <input type="hidden" name="action" value="cancel">
                                    <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">
                                    <input type="hidden" name="reservation_id"
                                           value="<?= htmlspecialchars($row['id_reservation']) ?>">
                                    <input type="submit" value="Cancel" class="button">
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7">You have no upcoming flights.</td></tr>
                <?php endif; ?>
            </table>
        </div>
    </section>
</main>
</body>
</html>
