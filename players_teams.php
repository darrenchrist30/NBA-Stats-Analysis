<?php
require_once 'autoload.php';

$client = new MongoDB\Client('mongodb://mongo:mongo@localhost:27017/');
$collection = $client->nba_projek->players_teams;

$cursor = $collection->find();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Statistik Pemain per Tim</title>
    <style>
        table { border-collapse: collapse; width: 100%; font-size: 13px; }
        th, td { border: 1px solid #ccc; padding: 4px; text-align: center; }
        th { background-color: #f5f5f5; }
        body { font-family: Arial, sans-serif; }
        h1 { margin-bottom: 10px; }
        .scroll-container { overflow-x: auto; max-width: 100%; }
    </style>
</head>
<body>
    <h1>Statistik Pemain Tim NBA</h1>
    <div class="scroll-container">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Player ID</th>
                <th>Tahun</th>
                <th>Stint</th>
                <th>Tim</th>
                <th>Liga</th>
                <th>GP</th>
                <th>GS</th>
                <th>Menit</th>
                <th>Poin</th>
                <th>OREB</th>
                <th>DREB</th>
                <th>REB</th>
                <th>AST</th>
                <th>STL</th>
                <th>BLK</th>
                <th>TO</th>
                <th>PF</th>
                <th>FGA</th>
                <th>FGM</th>
                <th>FTA</th>
                <th>FTM</th>
                <th>3PA</th>
                <th>3PM</th>
                <th>Post GP</th>
                <th>Post GS</th>
                <th>Post Min</th>
                <th>Post Pts</th>
                <th>Post OREB</th>
                <th>Post DREB</th>
                <th>Post REB</th>
                <th>Post AST</th>
                <th>Post STL</th>
                <th>Post BLK</th>
                <th>Post TO</th>
                <th>Post PF</th>
                <th>Post FGA</th>
                <th>Post FGM</th>
                <th>Post FTA</th>
                <th>Post FTM</th>
                <th>Post 3PA</th>
                <th>Post 3PM</th>
                <th>Catatan</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($cursor as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['id'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['playerID'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['year'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['stint'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['tmID'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['lgID'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['GP'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['GS'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['minutes'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['points'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['oRebounds'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['dRebounds'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['rebounds'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['assists'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['steals'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['blocks'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['turnovers'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['PF'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['fgAttempted'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['fgMade'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['ftAttempted'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['ftMade'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['threeAttempted'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['threeMade'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['PostGP'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['PostGS'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['PostMinutes'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['PostPoints'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['PostoRebounds'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['PostdRebounds'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['PostRebounds'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['PostAssists'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['PostSteals'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['PostBlocks'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['PostTurnovers'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['PostPF'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['PostfgAttempted'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['PostfgMade'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['PostftAttempted'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['PostftMade'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['PostthreeAttempted'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['PostthreeMade'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['note'] ?? '-') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</body>
</html>
