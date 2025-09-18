<?php
// === Koneksi DB ===
$host = "localhost";
$user = "root";
$pass = "";
$db   = "bola";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Ambil filter dari GET request
$posisiFilter = isset($_GET['posisi']) && $_GET['posisi'] != '' ? $_GET['posisi'] : null;
$clubFilter   = isset($_GET['club'])   && $_GET['club']   != '' ? $_GET['club']   : null;

// ==== fungsi ambil jadwal (fixtures) ====
function getFixtures($conn, $clubShort, $startGw, $endGw) {
    $fixtures = [];
    $sql = "SELECT gw, opponent_id, home_away, difficulty
            FROM fixtures 
            WHERE fixtures.club_id = ? AND gw BETWEEN ? AND ?
            ORDER BY gw";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return $fixtures;
    }
    $stmt->bind_param("sii", $clubShort, $startGw, $endGw);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $fixtures[$row['gw']] = [
            "opp" => $row['opponent_id'], // langsung pakai short_name
            "ha"  => $row['home_away'],
            "dif" => (int)$row['difficulty']
        ];
    }
    $stmt->close();
    return $fixtures;
}

// ==== fungsi generate tabel ====
function generateTable($conn, $table, $label, $posisiFilter = null, $clubFilter = null) {
    echo "<h2>Top 20 Pemain berdasarkan $label</h2>";

    // Query ambil data statistik
    $sql = "SELECT player.id AS player_id, player.nama_pemain, club.club, player.posisi, player.club_id,
                   gw, {$table} AS value 
            FROM {$table} 
            JOIN player ON {$table}.nama_pemain = player.nama_pemain
            JOIN club ON player.club_id = club.short_name";

    $conditions = [];
    if ($posisiFilter) {
        $conditions[] = "player.posisi = '".$conn->real_escape_string($posisiFilter)."'";
    }
    if ($clubFilter) {
        $conditions[] = "club.club = '".$conn->real_escape_string($clubFilter)."'";
    }

    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }

    $sql .= " ORDER BY player.nama_pemain, gw";
    $result = $conn->query($sql);

    if (!$result) {
        echo "<p>Error query: " . htmlspecialchars($conn->error) . "</p>";
        return;
    }

    // susun data
    $data = [];
    $gameweeks = [];
    while ($row = $result->fetch_assoc()) {
        $nama   = $row['nama_pemain'];
        $club   = $row['club'];
        $posisi = $row['posisi'];
        $clubShort = isset($row['club_id']) ? $row['club_id'] : null; // sudah short_name
        $gw     = (int)$row['gw'];
        $value  = is_numeric($row['value']) ? (int)$row['value'] : 0;

        $data[$nama]['club']   = $club;
        $data[$nama]['posisi'] = $posisi;
        $data[$nama]['clubId'] = $clubShort;
        $data[$nama]['gw'][$gw] = $value;

        $gameweeks[$gw] = true;
    }

    if (empty($data)) {
        echo "<p><i>Tidak ada data untuk filter ini.</i></p>";
        return;
    }

    // urutkan gw
    $gameweeks = array_keys($gameweeks);
    sort($gameweeks);
    $lastGw = max($gameweeks);

    // hitung total
    $totals = [];
    foreach ($data as $nama => $info) {
        $totals[$nama] = array_sum($info['gw']);
    }
    arsort($totals);
    $totals = array_slice($totals, 0, 20, true);

    // tampilkan tabel
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>No</th><th>Pemain</th>";
    foreach ($gameweeks as $gw) {
        echo "<th>GW" . htmlspecialchars($gw) . "</th>";
    }
    echo "<th>Total</th>";

    // Tambah kolom fixture mulai dari GW(last+1) sampai GW24
    for ($i = $lastGw+1; $i <= 24; $i++) {
        echo "<th>GW" . $i . "</th>";
    }
    echo "</tr>";

    // mapping difficulty => warna
    $colorMap = [
        1 => "lightgreen",   // hijau
        2 => "lightgray",    // abu-abu
        3 => "red",          // merah
        4 => "maroon"        // maron
    ];

    $no = 1;
    foreach ($totals as $nama => $total) {
        $club   = $data[$nama]['club'];
        $posisi = $data[$nama]['posisi'];
        $clubShort = $data[$nama]['clubId'];

        echo "<tr>";
        echo "<td>" . $no . "</td>";
        echo "<td>" . htmlspecialchars($nama) . " <small>(" . htmlspecialchars($club) . " - " . htmlspecialchars($posisi) . ")</small></td>";

        foreach ($gameweeks as $gw) {
            $val = isset($data[$nama]['gw'][$gw]) ? $data[$nama]['gw'][$gw] : 0;
            echo "<td>" . htmlspecialchars($val) . "</td>";
        }
        echo "<td>" . htmlspecialchars($total) . "</td>";

        // ambil fixture mulai dari GW(lastGw+1) sampai GW24
        $fixtures = getFixtures($conn, $clubShort, $lastGw+1, 24);
        for ($i = $lastGw+1; $i <= 24; $i++) {
            if (isset($fixtures[$i])) {
                $f = $fixtures[$i];
                $dif = isset($f['dif']) ? (int)$f['dif'] : 2;
                $color = isset($colorMap[$dif]) ? $colorMap[$dif] : "white";
                $opp = htmlspecialchars($f['opp']);
                $ha = htmlspecialchars($f['ha']);
                echo "<td style='background:{$color}; text-align:center;'>{$opp}({$ha})</td>";
            } else {
                echo "<td>-</td>";
            }
        }

        echo "</tr>";
        $no++;
    }
    echo "</table><br>";
}

// === FORM ===

$clubs = [
    "Arsenal","Aston Villa","Bournemouth","Brentford","Brighton","Burnley",
    "Chelsea","Crystal Palace","Everton","Fulham","Leeds","Liverpool",
    "Man City","Man Utd","Newcastle","Nottingham Forest","Spurs","Sunderland",
    "West Ham","Wolves"
];
?>
<form method="GET">
    Posisi:
    <select name="posisi">
        <option value="">Semua</option>
        <option value="GKP" <?php echo ($posisiFilter=='GKP'?'selected':''); ?>>GKP</option>
        <option value="DEF" <?php echo ($posisiFilter=='DEF'?'selected':''); ?>>DEF</option>
        <option value="MID" <?php echo ($posisiFilter=='MID'?'selected':''); ?>>MID</option>
        <option value="FWD" <?php echo ($posisiFilter=='FWD'?'selected':''); ?>>FWD</option>
    </select>
    Club:
    <select name="club" id="club">
        <option value="">Semua</option>
        <?php foreach ($clubs as $club): ?>
            <option value="<?= $club ?>" <?= $clubFilter==$club?'selected':'' ?>>
                <?= $club ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button type="submit">Filter</button>
    <button type="button" onclick="window.location='bola.php'">Reset</button>
</form>
<?php

generateTable($conn, "bps", "BPS", $posisiFilter, $clubFilter);
generateTable($conn, "dcp", "DCP", $posisiFilter, $clubFilter);

$conn->close();
?>
