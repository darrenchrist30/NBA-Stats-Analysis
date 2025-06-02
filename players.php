<?php
require_once 'autoload.php';

$client = new MongoDB\Client('mongodb://mongo:mongo@localhost:27017/');
$collection = $client->nba_projek->players;

$cursor = $collection->find();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Daftar Pemain NBA</title>
    <style>
        table { border-collapse: collapse; width: 100%; font-size: 14px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background-color: #f0f0f0; }
    </style>
</head>
<body>
    <h1>Daftar Pemain NBA</h1>
    <table>
        <thead>
            <tr>
                <th>Player ID</th>
                <th>Use First</th>
                <th>First Name</th>
                <th>Middle Name</th>
                <th>Last Name</th>
                <th>Name Given</th>
                <th>Full Given Name</th>
                <th>Name Suffix</th>
                <th>Name Nick</th>
                <th>Posisi</th>
                <th>Musim Pertama</th>
                <th>Musim Terakhir</th>
                <th>Tinggi</th>
                <th>Berat</th>
                <th>Kampus</th>
                <th>Kampus Lain</th>
                <th>Tanggal Lahir</th>
                <th>Kota Lahir</th>
                <th>Provinsi Lahir</th>
                <th>Negara Lahir</th>
                <th>SMA</th>
                <th>Kota SMA</th>
                <th>Provinsi SMA</th>
                <th>Negara SMA</th>
                <th>Tanggal Wafat</th>
                <th>Ras</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($cursor as $player): ?>
            <tr>
                <td><?= htmlspecialchars($player['playerID'] ?? '-') ?></td>
                <td><?= htmlspecialchars($player['useFirst'] ?? '-') ?></td>
                <td><?= htmlspecialchars($player['firstName'] ?? '-') ?></td>
                <td><?= htmlspecialchars($player['middleName'] ?? '-') ?></td>
                <td><?= htmlspecialchars($player['lastName'] ?? '-') ?></td>
                <td><?= htmlspecialchars($player['nameGiven'] ?? '-') ?></td>
                <td><?= htmlspecialchars($player['fullGivenName'] ?? '-') ?></td>
                <td><?= htmlspecialchars($player['nameSuffix'] ?? '-') ?></td>
                <td><?= htmlspecialchars($player['nameNick'] ?? '-') ?></td>
                <td><?= htmlspecialchars($player['pos'] ?? '-') ?></td>
                <td><?= htmlspecialchars($player['firstseason'] ?? '-') ?></td>
                <td><?= htmlspecialchars($player['lastseason'] ?? '-') ?></td>
                <td><?= htmlspecialchars($player['height'] ?? '-') ?></td>
                <td><?= htmlspecialchars($player['weight'] ?? '-') ?></td>
                <td><?= htmlspecialchars($player['college'] ?? '-') ?></td>
                <td><?= htmlspecialchars($player['collegeOther'] ?? '-') ?></td>
                <td><?= htmlspecialchars($player['birthDate'] ?? '-') ?></td>
                <td><?= htmlspecialchars($player['birthCity'] ?? '-') ?></td>
                <td><?= htmlspecialchars($player['birthState'] ?? '-') ?></td>
                <td><?= htmlspecialchars($player['birthCountry'] ?? '-') ?></td>
                <td><?= htmlspecialchars($player['highSchool'] ?? '-') ?></td>
                <td><?= htmlspecialchars($player['hsCity'] ?? '-') ?></td>
                <td><?= htmlspecialchars($player['hsState'] ?? '-') ?></td>
                <td><?= htmlspecialchars($player['hsCountry'] ?? '-') ?></td>
                <td><?= htmlspecialchars($player['deathDate'] ?? '-') ?></td>
                <td><?= htmlspecialchars($player['race'] ?? '-') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
