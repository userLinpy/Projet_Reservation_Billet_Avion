<?php
require_once __DIR__.'/db.php';

// FETCH FILTER OPTIONS
$departCities = $conn->query(
    "SELECT DISTINCT ville_depart FROM vol ORDER BY ville_depart ASC"
);
$arrivalCities = $conn->query(
    "SELECT DISTINCT ville_arrivee FROM vol ORDER BY ville_arrivee ASC"
);

// BUILD FILTERED QUERY
$baseSql = "SELECT
                ID_Vol,
                ville_depart,
                ville_arrivee,
                date_depart,
                date_arrivee
            FROM vol";

$conds   = [];
$params  = [];
$types   = '';

if (!empty($_GET['start'])) {
    $conds[] = "ville_depart = ?";
    $params[] = trim($_GET['start']);
    $types   .= 's';
}
if (!empty($_GET['destination'])) {
    $conds[] = "ville_arrivee = ?";
    $params[] = trim($_GET['destination']);
    $types   .= 's';
}
if (!empty($_GET['date1'])) {
    $conds[] = "date_depart >= ?";
    $params[] = $_GET['date1'] . " 00:00:00";
    $types   .= 's';
}
if (!empty($_GET['date2'])) {
    $conds[] = "date_depart <= ?";
    $params[] = $_GET['date2'] . " 23:59:59";
    $types   .= 's';
}

if ($conds) {
    $sql = $baseSql . " WHERE " . implode(' AND ', $conds) . " ORDER BY date_depart ASC";
} else {
    $sql = $baseSql . " ORDER BY date_depart ASC";
}

// PREPARE + EXECUTE
$stmt = $conn->prepare($sql);
if ($params) {
    // spread the params array into bind_param()
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$flightsResult = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Home – MIASHS Airlines</title>
    <link rel="stylesheet" href="css_folder/style.css">
    <style>
        .detail-link {
            display: inline-block;
            padding: .6rem 1.2rem;
            background:#0066cc;
            color:#fff;
            border-radius:4px;
            text-decoration:none;
            font-size:1.3rem;
        }
        .detail-link:hover { background:#004e99; }
    </style>
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

    <section class="flights" id="flights">
        <div class="form-container">
            <h2>Filter</h2>
            <form method="GET" action="home.php">
                <div class="filter">
                    <h2>Location</h2>
                    <div class="labels">
                        <label for="start">From</label>
                        <label for="destination">To</label>
                    </div>
                    <div class="select">
                        <select name="start" id="start">
                            <option value="">— any —</option>
                            <?php while ($c = $departCities->fetch_assoc()): ?>
                                <?php $selected = (isset($_GET['start']) && $_GET['start']===$c['ville_depart']) ? 'selected' : ''; ?>
                                <option <?= $selected ?> value="<?= htmlspecialchars($c['ville_depart']) ?>">
                                    <?= htmlspecialchars($c['ville_depart']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>

                        <select name="destination" id="destination">
                            <option value="">— any —</option>
                            <?php while ($c = $arrivalCities->fetch_assoc()): ?>
                                <?php $selected = (isset($_GET['destination']) && $_GET['destination']===$c['ville_arrivee']) ? 'selected' : ''; ?>
                                <option <?= $selected ?> value="<?= htmlspecialchars($c['ville_arrivee']) ?>">
                                    <?= htmlspecialchars($c['ville_arrivee']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <h2>Date</h2>
                    <div class="labels">
                        <label for="date1">From</label>
                        <label for="date2">To</label>
                    </div>
                    <div class="select">
                        <input type="date" name="date1" id="date1"
                               value="<?= htmlspecialchars($_GET['date1'] ?? '') ?>">
                        <input type="date" name="date2" id="date2"
                               value="<?= htmlspecialchars($_GET['date2'] ?? '') ?>">
                    </div>
                </div>
                <div class="apply">
                    <input type="submit" value="Apply Filter">
                </div>
            </form>
        </div>

        <div class="flights-container">
            <h2>Flights</h2>
            <table class="table">
                <tr>
                    <th>Start point</th>
                    <th>End Point</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th><!-- Details column header – empty --> </th>
                </tr>

                <?php if ($flightsResult->num_rows): ?>
                    <?php while ($row = $flightsResult->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['ville_depart']) ?></td>
                            <td><?= htmlspecialchars($row['ville_arrivee']) ?></td>
                            <td><?= htmlspecialchars($row['date_depart']) ?></td>
                            <td><?= htmlspecialchars($row['date_arrivee']) ?></td>

                            <!-- -------------- Details link ----------------- -->
                            <td>
                                <?php
                                $flightId = (int)$row['ID_Vol'];
                                ?>
                                <a class="detail-link"
                                   href="flights.php?id=<?= $flightId ?>">
                                    Details
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5">No flights match the criteria.</td></tr>
                <?php endif; ?>
            </table>
        </div>
    </section>
</main>
</body>
</html>
