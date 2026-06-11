<?php
require_once __DIR__.'/db.php';

// ACCESS CONTROL
if (!isAdmin()) {
    set_flash('Access denied – admins only.', 'error');
    header('Location: home.php');
    exit;
}

// POST HANDLERS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {

    if (!check_csrf($_POST['csrf'] ?? '')) {
        set_flash('Invalid CSRF token.', 'error');
        header('Location: admin.php');
        exit;
    }

    $action = $_POST['action'];

    // ADD FLIGHT
    if ($action === 'add_flight') {
        $startCity = trim($_POST['StartCity'] ?? '');
        $endCity   = trim($_POST['EndCity'] ?? '');
        $plane     = trim($_POST['plane'] ?? '');
        $startDt   = date('Y-m-d H:i:s', strtotime($_POST['StartDate'] ?? ''));
        $endDt     = date('Y-m-d H:i:s', strtotime($_POST['EndDate'] ?? ''));

        if ($startCity && $endCity && $plane && $startDt && $endDt) {
            $stmt = $conn->prepare(
                "INSERT INTO vol (ville_depart, ville_arrivee, date_depart, date_arrivee, immatriculation)
                 VALUES (?,?,?,?,?)"
            );
            $stmt->bind_param('sssss', $startCity, $endCity, $startDt, $endDt, $plane);
            try {
                $stmt->execute();
                set_flash('Flight added successfully!', 'success');
            } catch (mysqli_sql_exception $e) {
                set_flash('Could not add flight: '.$e->getMessage(), 'error');
            }
        } else {
            set_flash('All fields are required.', 'error');
        }
        header('Location: admin.php#add-flight');
        exit;
    }

    // ADD PLANE
    if ($action === 'add_plane') {
        $imma     = trim($_POST['imma'] ?? '');
        $model    = trim($_POST['model'] ?? '');
        $capacity = intval($_POST['capacity'] ?? 0);

        if ($imma && $model && $capacity > 0) {
            $stmt = $conn->prepare(
                "INSERT INTO avion (immatriculation, modele, capacite) VALUES (?,?,?)"
            );
            $stmt->bind_param('ssi', $imma, $model, $capacity);
            try {
                $stmt->execute();
                set_flash('Plane added successfully!', 'success');
            } catch (mysqli_sql_exception $e) {
                set_flash('Could not add plane: '.$e->getMessage(), 'error');
            }
        } else {
            set_flash('All fields are required and capacity must be > 0.', 'error');
        }
        header('Location: admin.php#add-plane');
        exit;
    }

    // DELETE FLIGHT
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'del_flight') {
        $flightId = intval($_POST['flight_id'] ?? 0);
        if ($flightId <= 0) {
            set_flash('Please select a flight to delete.', 'error');
            header('Location: admin.php#del-flight');
            exit;
        }

        $stmt = $conn->prepare("DELETE FROM vol WHERE ID_Vol = ?");
        $stmt->bind_param('i', $flightId);

        try {
            $stmt->execute();
            // Success
            set_flash('Flight deleted successfully.', 'success');
        } catch (mysqli_sql_exception $e) {
            // 1451 = foreign‑key RESTRICT
            if ((int)$e->getCode() === 1451) {
                set_flash(
                    'Cannot delete this flight because there are reservations for it.',
                    'error'
                );
            } else {
                // any other unexpected DB error
                set_flash('Error deleting flight: ' . friendly_sql_error($e), 'error');
            }
        }

        header('Location: admin.php#del-flight');
        exit;
    }

    // DELETE PLANE
    if ($action === 'del_plane') {
        $imma = trim($_POST['plane_id'] ?? '');
        if ($imma) {
            $stmt = $conn->prepare("DELETE FROM avion WHERE immatriculation = ?");
            $stmt->bind_param('s', $imma);
            try {
                $stmt->execute();
                $msg = $stmt->affected_rows ? 'Plane deleted.' : 'Plane not found.';
                set_flash($msg, $stmt->affected_rows ? 'success' : 'info');
            } catch (mysqli_sql_exception $e) {
                set_flash('Error deleting plane: '.$e->getMessage(), 'error');
            }
        } else {
            set_flash('Please select a plane to delete.', 'error');
        }
        header('Location: admin.php#del-plane');
        exit;
    }

    set_flash('Unknown action.', 'error');
    header('Location: admin.php');
    exit;
}

// DATA FOR DISPLAY
// flights (table + dropdown)
$flightsResult = $conn->query(
    "SELECT id_Vol, ville_depart, ville_arrivee, date_depart, date_arrivee, immatriculation
     FROM vol ORDER BY id_Vol ASC"
);

// planes (table + dropdown)
$planesResult  = $conn->query(
    "SELECT immatriculation, modele, capacite FROM avion ORDER BY modele ASC"
);

// plane options for the Add‑Flight form
$planeOptions = $conn->query(
    "SELECT immatriculation FROM avion ORDER BY modele ASC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Board – MIASHS Airlines</title>
    <link rel="stylesheet" href="css_folder/adminstyle.css">
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

    <!-- ADD FLIGHT -->
    <section class="panel" id="add-flight">
        <div class="title"><h2>ADD FLIGHT</h2></div>
        <div class="container">
            <form method="POST" action="admin.php" class="form">
                <input type="hidden" name="action" value="add_flight">
                <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">

                <div class="set"><label>Starting City</label>
                    <input type="text" name="StartCity" required></div>

                <div class="set"><label>End City</label>
                    <input type="text" name="EndCity" required></div>

                <div class="set"><label>Flight Start Date</label>
                    <input type="datetime-local" name="StartDate" required></div>

                <div class="set"><label>Flight Arrival Date</label>
                    <input type="datetime-local" name="EndDate" required></div>

                <div class="set"><label>Plane</label>
                    <select name="plane" required>
                        <?php
                        if ($planeOptions->num_rows) {
                            while ($p = $planeOptions->fetch_assoc()) {
                                echo '<option value="'.htmlspecialchars($p['immatriculation']).'">'
                                   .'Plane '.$p['immatriculation'].'</option>';
                            }
                        } else {
                            echo '<option disabled>No planes available</option>';
                        }
                        ?>
                    </select>
                </div>

                <input type="submit" value="Add Flight">
            </form>
        </div>
    </section>

    <!-- DELETE FLIGHT -->
    <section class="panel" id="del-flight">
        <div class="title"><h2>DELETE FLIGHT</h2></div>
        <div class="container">
            <table class="table">
                <tr>
                    <th>Start</th><th>End</th><th>Start date</th><th>End date</th><th>Plane</th>
                </tr>
                <?php
                while ($f = $flightsResult->fetch_assoc()) {
                    echo '<tr>';
                    echo '<td>'.htmlspecialchars($f['ville_depart']).'</td>';
                    echo '<td>'.htmlspecialchars($f['ville_arrivee']).'</td>';
                    echo '<td>'.htmlspecialchars($f['date_depart']).'</td>';
                    echo '<td>'.htmlspecialchars($f['date_arrivee']).'</td>';
                    echo '<td>'.htmlspecialchars($f['immatriculation']).'</td>';
                    echo '</tr>';
                }
                ?>
            </table>

            <form method="POST" action="admin.php#del-flight">
                <input type="hidden" name="action" value="del_flight">
                <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">
                <label for="flight_id">Select flight to delete:</label>
                <select name="flight_id" id="flight_id" required>
                    <?php
                    $flightsResult->data_seek(0); // rewind for dropdown
                    while ($f = $flightsResult->fetch_assoc()) {
                        $label = sprintf(
                            "ID %d – %s → %s (%s)",
                            $f['id_Vol'],
                            $f['ville_depart'],
                            $f['ville_arrivee'],
                            $f['date_depart']
                        );
                        echo '<option value="'.intval($f['id_Vol']).'">'.htmlspecialchars($label).'</option>';
                    }
                    ?>
                </select>
                <input type="submit" value="Delete selected flight">
            </form>
        </div>
    </section>

    <!-- ADD PLANE -->
    <section class="panel" id="add-plane">
        <div class="title"><h2>ADD PLANE</h2></div>
        <div class="container">
            <form method="POST" action="admin.php">
                <input type="hidden" name="action" value="add_plane">
                <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">

                <div class="set"><label>Immatriculation</label>
                    <input type="text" name="imma" required></div>

                <div class="set"><label>Model</label>
                    <input type="text" name="model" required></div>

                <div class="set"><label>Capacity</label>
                    <input type="number" name="capacity" min="1" required></div>

                <input type="submit" value="Add Plane">
            </form>
        </div>
    </section>

    <!-- DELETE PLANE -->
    <section class="panel" id="del-plane">
        <div class="title"><h2>DELETE PLANE</h2></div>
        <div class="container">
            <table class="table">
                <tr><th>Immatriculation</th><th>Model</th><th>Capacity</th></tr>
                <?php
                while ($p = $planesResult->fetch_assoc()) {
                    echo '<tr>';
                    echo '<td>'.htmlspecialchars($p['immatriculation']).'</td>';
                    echo '<td>'.htmlspecialchars($p['modele']).'</td>';
                    echo '<td>'.htmlspecialchars($p['capacite']).'</td>';
                    echo '</tr>';
                }
                ?>
            </table>

            <form method="POST" action="admin.php#del-plane">
                <input type="hidden" name="action" value="del_plane">
                <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">
                <label for="plane_id">Select plane to delete:</label>
                <select name="plane_id" id="plane_id" required>
                    <?php
                    $planesResult->data_seek(0);
                    while ($p = $planesResult->fetch_assoc()) {
                        $label = $p['immatriculation'].' – '.$p['modele'];
                        echo '<option value="'.htmlspecialchars($p['immatriculation']).'">'
                           .htmlspecialchars($label).'</option>';
                    }
                    ?>
                </select>
                <input type="submit" value="Delete selected plane">
            </form>
        </div>
    </section>
</main>
</body>
</html>
