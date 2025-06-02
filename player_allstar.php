<?php
require_once 'autoload.php';

$client = new MongoDB\Client('mongodb://mongo:mongo@localhost:27017/');
$collection = $client->nba_projek->player_allstar;

$cursor = $collection->find();
?>

<!DOCTYPE html>
<html>
<head>
    <title>All-Star Players</title>
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
    <h1>All-Star Players</h1>
    <div class="scroll-container">
    <table>
        <thead>
            <tr>
                <th>Player ID</th>
                <th>Last Name</th>
                <th>First Name</th>
                <th>Season ID</th>
                <th>Conference</th>
                <th>League ID</th>
                <th>Games Played</th>
                <th>Minutes</th>
                <th>Points</th>
                <th>O-Rebounds</th>
                <th>D-Rebounds</th>
                <th>Total Rebounds</th>
                <th>Assists</th>
                <th>Steals</th>
                <th>Blocks</th>
                <th>Turnovers</th>
                <th>Personal Fouls</th>
                <th>FG Attempted</th>
                <th>FG Made</th>
                <th>FT Attempted</th>
                <th>FT Made</th>
                <th>3PT Attempted</th>
                <th>3PT Made</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($cursor as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['playerID'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['last_name'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['first_name'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['season_id'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['conference'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['league_id'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['games_played'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['minutes'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['points'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['o_rebounds'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['d_rebounds'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['rebounds'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['assists'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['steals'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['blocks'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['turnovers'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['personal_fouls'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['fg_attempted'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['fg_made'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['ft_attempted'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['ft_made'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['three_attempted'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['three_made'] ?? '-') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</body>
</html>
