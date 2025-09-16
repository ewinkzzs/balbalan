<?php
// Koneksi database
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

function generateTable($conn, $table, $label, $posisiFilter = null, $clubFilter = null) {
    echo "<h2>Top 20 Pemain berdasarkan $label</h2>";

    // Query dasar
    $sql = "SELECT player.nama_pemain, player.club, player.posisi, gw, $table AS value 
            FROM $table 
            JOIN player ON $table.nama_pemain = player.nama_pemain";

    // Tambahkan filter posisi & club jika ada
    $conditions = [];
    if ($posisiFilter) {
        $conditions[] = "player.posisi = '".$conn->real_escape_string($posisiFilter)."'";
    }
    if ($clubFilter) {
        $conditions[] = "player.club = '".$conn->real_escape_string($clubFilter)."'";
    }

    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }

    $sql .= " ORDER BY player.nama_pemain, gw";
    $result = $conn->query($sql);

    // Susun data ke array
    $data = [];
    $gameweeks = [];

    while ($row = $result->fetch_assoc()) {
        $nama   = $row['nama_pemain'];
        $club   = $row['club'];
        $posisi = $row['posisi'];
        $gw     = $row['gw'];
        $value  = $row['value'];

        $data[$nama]['club'] = $club;
        $data[$nama]['posisi'] = $posisi;
        $data[$nama]['gw'][$gw] = $value;

        $gameweeks[$gw] = true;
    }

    if (empty($data)) {
        echo "<p><i>Tidak ada data untuk filter ini.</i></p>";
        return;
    }

    // Urutkan GW
    $gameweeks = array_keys($gameweeks);
    sort($gameweeks);

    // Hitung total per pemain
    $totals = [];
    foreach ($data as $nama => $info) {
        $totals[$nama] = array_sum($info['gw']);
    }

    // Urutkan berdasarkan total (descending)
    arsort($totals);

    // Ambil hanya 20 pemain teratas
    $totals = array_slice($totals, 0, 20, true);

    // Tampilkan tabel
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>No</th><th>Nama Pemain</th>";
    foreach ($gameweeks as $gw) {
        echo "<th>GW$gw</th>";
    }
    echo "<th>Total</th></tr>";

    $no = 1;
    foreach ($totals as $nama => $total) {
        $club   = $data[$nama]['club'];
        $posisi = $data[$nama]['posisi'];

        echo "<tr>";
        echo "<td>{$no}</td>";
        echo "<td>{$nama} - <small>{$club} - {$posisi}</small></td>";

        foreach ($gameweeks as $gw) {
            $val = isset($data[$nama]['gw'][$gw]) ? $data[$nama]['gw'][$gw] : 0;
            if($label == 'DCP' && $posisi == 'DEF' && $val >= 10)
                echo "<td style='background-color:yellow'>{$val}</td>";
            else if($label == 'DCP' && ($posisi == 'MID' || $posisi == 'FWD') && $val >= 12)
                echo "<td style='background-color:yellow'>{$val}</td>";
            else echo "<td>{$val}</td>";
        }

        echo "<td>{$total}</td>";
        echo "</tr>";
        $no++;
    }

    echo "</table><br>";
}

// ==== FORM FILTER POSISI + CLUB ====
$clubs = [
    "Arsenal","Aston Villa","Bournemouth","Brentford","Brighton","Burnley",
    "Chelsea","Crystal Palace","Everton","Fulham","Leeds","Liverpool",
    "Man City","Man Utd","Newcastle","Nottingham Forest","Spurs","Sunderland",
    "West Ham","Wolves"
];
?>
<form method="GET" style="margin-bottom:20px;">
    <label for="posisi">Filter Posisi:</label>
    <select name="posisi" id="posisi">
        <option value="">Semua</option>
        <option value="GKP" <?= $posisiFilter=='GKP'?'selected':'' ?>>GKP</option>
        <option value="DEF" <?= $posisiFilter=='DEF'?'selected':'' ?>>DEF</option>
        <option value="MID" <?= $posisiFilter=='MID'?'selected':'' ?>>MID</option>
        <option value="FWD" <?= $posisiFilter=='FWD'?'selected':'' ?>>FWD</option>
    </select>

    <label for="club">Filter Club:</label>
    <select name="club" id="club">
        <option value="">Semua</option>
        <?php foreach ($clubs as $club): ?>
            <option value="<?= $club ?>" <?= $clubFilter==$club?'selected':'' ?>>
                <?= $club ?>
            </option>
        <?php endforeach; ?>
    </select>

    <button type="submit">Filter</button>
</form>

<?php
// ==== TAMPILKAN TABEL ====
generateTable($conn, "bps", "BPS", $posisiFilter, $clubFilter);
generateTable($conn, "dcp", "DCP", $posisiFilter, $clubFilter);
