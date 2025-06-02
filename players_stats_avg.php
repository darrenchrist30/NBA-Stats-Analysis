<?php
require_once 'db.php';

// Fungsi untuk mendapatkan informasi konferensi tim
function getTeamConference($teamId, $year, $playerAllStar) {
    $conference = 'Unknown';

    // Coba cari di data all-star
    $allStarGame = $playerAllStar->findOne(['season_id' => (int)$year, 'conference' => ['$in' => ['East', 'West']]]);
    if ($allStarGame) {
        $conference = $allStarGame['conference'];
    }

    // Jika tidak ditemukan di all-star, gunakan logika default
    if ($conference === 'Unknown') {
        $eastTeams = ['ATL', 'BOS', 'CLE', 'CHI', 'DET', 'IND', 'MIA', 'MIL', 'NJN', 'NYK', 'ORL', 'PHI', 'TOR', 'WAS'];
        $westTeams = ['DAL', 'DEN', 'HOU', 'LAC', 'LAL', 'MEM', 'MIN', 'NOP', 'OKC', 'PHO', 'POR', 'SAC', 'SAS', 'UTA', 'GSW'];

        if (in_array($teamId, $eastTeams)) {
            $conference = 'East';
        } elseif (in_array($teamId, $westTeams)) {
            $conference = 'West';
        }
    }

    return $conference;
}

// Ambil filter dari request GET
$filterSeason = $_GET['season'] ?? '';
$filterTeam = $_GET['team'] ?? '';
$filterPos = $_GET['pos'] ?? '';
$filterPlayerName = $_GET['playerName'] ?? '';

// Ambil data unik untuk filter dropdown
$seasons = $playersTeams->distinct('year');
sort($seasons);

$teams = $playersTeams->distinct('tmID');
sort($teams);

$positions = $players->distinct('pos');
sort($positions);

// Bangun filter query MongoDB
$query = [];
if ($filterSeason) $query['year'] = (int)$filterSeason;
if ($filterTeam) $query['tmID'] = $filterTeam;

// Ambil data performa dari players_teams
$options = []; // Hapus limit
$cursor = $playersTeams->find($query, $options);

$data = [];
foreach ($cursor as $item) {
    $playerData = $players->findOne(['playerID' => $item['playerID']]);
    $playerName = getPlayerName($item['playerID'], $players);
    $passesFilters = true;

    if ($filterPos && isset($playerData['pos']) && $playerData['pos'] != $filterPos) {
        $passesFilters = false;
    }

    if ($filterPlayerName && stripos($playerName, $filterPlayerName) === false) {
        $passesFilters = false;
    }

    if ($passesFilters) {
        $data[] = $item;
    }
}

// Hitung rata-rata statistik per pemain per musim
$playerSeasonStats = [];
foreach ($data as $row) {
    $playerID = $row['playerID'];
    $year = $row['year'];
    if (!isset($playerSeasonStats[$playerID])) {
        $playerSeasonStats[$playerID] = [];
    }
    if (!isset($playerSeasonStats[$playerID][$year])) {
        $playerSeasonStats[$playerID][$year] = [
            'totalPoints' => 0,
            'totalAssists' => 0,
            'totalRebounds' => 0,
            'totalGames' => 0,
            'teamId' => $row['tmID'], // Simpan teamId
            'year' => $year // Simpan year
        ];
    }
    $playerSeasonStats[$playerID][$year]['totalPoints'] += $row['points'] ?? 0;
    $playerSeasonStats[$playerID][$year]['totalAssists'] += $row['assists'] ?? 0;
    $playerSeasonStats[$playerID][$year]['totalRebounds'] += $row['rebounds'] ?? 0;
    $playerSeasonStats[$playerID][$year]['totalGames'] += $row['GP'] ?? 0;
}

// Hitung rata-rata per game
$avgPlayerSeasonStats = [];
foreach ($playerSeasonStats as $playerID => $seasons) {
    foreach ($seasons as $year => $totals) {
        $avgPlayerSeasonStats[] = [
            'playerID' => $playerID,
            'year' => $year,
            'teamId' => $totals['teamId'],
            'avgPoints' => $totals['totalGames'] > 0 ? round($totals['totalPoints'] / $totals['totalGames'], 2) : 0,
            'avgAssists' => $totals['totalGames'] > 0 ? round($totals['totalAssists'] / $totals['totalGames'], 2) : 0,
            'avgRebounds' => $totals['totalGames'] > 0 ? round($totals['totalRebounds'] / $totals['totalGames'], 2) : 0,
        ];
    }
}

// Fungsi bantu cari nama pemain lengkap dari playerID
function getPlayerName($playerID, $players) {
    $p = $players->findOne(['playerID' => $playerID]);
    if ($p) {
        return ($p['firstName'] ?? '') . ' ' . ($p['lastName'] ?? '');
    }
    return $playerID;
}

// Fungsi untuk mengurutkan data berdasarkan rata-rata poin
function sortByAvgPoints($a, $b) {
    return $b['avgPoints'] - $a['avgPoints'];
}

// Urutkan data
usort($avgPlayerSeasonStats, 'sortByAvgPoints');

// Pagination
$perPage = 10;
$page = $_GET['page'] ?? 1;
$startIndex = ($page - 1) * $perPage;
$endIndex = $startIndex + $perPage;
$totalPages = ceil(count($avgPlayerSeasonStats) / $perPage);

$pagedStats = array_slice($avgPlayerSeasonStats, $startIndex, $perPage);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Rata-Rata Statistik Pemain NBA per Musim</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet"/>
</head>
<body class="bg-gray-100 p-6">
    <h1 class="text-3xl font-bold mb-6">Rata-Rata Statistik Pemain NBA per Musim</h1>

    <form method="GET" class="mb-6 flex gap-4 items-end flex-wrap">
        <div>
            <label for="season" class="block font-semibold mb-1">Musim</label>
            <select name="season" id="season" class="border rounded px-3 py-1">
                <option value="">Semua</option>
                <?php foreach ($seasons as $season): ?>
                    <option value="<?= $season ?>" <?= ($filterSeason == $season) ? 'selected' : '' ?>><?= $season ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="team" class="block font-semibold mb-1">Tim</label>
            <select name="team" id="team" class="border rounded px-3 py-1">
                <option value="">Semua</option>
                <?php foreach ($teams as $team): ?>
                    <option value="<?= htmlspecialchars($team) ?>" <?= ($filterTeam == $team) ? 'selected' : '' ?>><?= htmlspecialchars($team) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="pos" class="block font-semibold mb-1">Posisi</label>
            <select name="pos" id="pos" class="border rounded px-3 py-1">
                <option value="">Semua</option>
                <?php foreach ($positions as $pos): ?>
                    <option value="<?= htmlspecialchars($pos) ?>" <?= ($filterPos == $pos) ? 'selected' : '' ?>><?= htmlspecialchars($pos) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="playerName" class="block font-semibold mb-1">Nama Pemain</label>
            <input type="text" name="playerName" id="playerName" class="border rounded px-3 py-1" placeholder="Cari nama pemain" value="<?= htmlspecialchars($filterPlayerName) ?>">
        </div>
        <button type="submit" class="bg-blue-600 text-white rounded px-4 py-2 hover:bg-blue-700">Filter</button>
    </form>

    <div class="overflow-x-auto bg-white p-4 rounded shadow">
        <table class="min-w-full table-auto border-collapse border border-gray-300">
            <thead>
                <tr class="bg-blue-100 text-left">
                    <th class="border border-gray-300 px-3 py-2">Nama Pemain</th>
                    <th class="border border-gray-300 px-3 py-2">Musim</th>
                    <th class="border border-gray-300 px-3 py-2">Tim</th>
                    <th class="border border-gray-300 px-3 py-2">Konferensi</th>
                    <th class="border border-gray-300 px-3 py-2">Rata-Rata Poin per Game</th>
                    <th class="border border-gray-300 px-3 py-2">Rata-Rata Assist per Game</th>
                    <th class="border border-gray-300 px-3 py-2">Rata-Rata Rebound per Game</th>
                    <th class="border border-gray-300 px-3 py-2">Detail</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($pagedStats) > 0): ?>
                    <?php foreach ($pagedStats as $stat):
                        $playerName = getPlayerName($stat['playerID'], $players);
                        global $player_allstar; // Pastikan $player_allstar tersedia
                        $teamConference = getTeamConference($stat['teamId'], $stat['year'], $player_allstar);
                    ?>
                    <tr class="border border-gray-300 hover:bg-gray-50">
                        <td class="border px-3 py-1"><?= htmlspecialchars($playerName) ?></td>
                        <td class="border px-3 py-1"><?= htmlspecialchars($stat['year']) ?></td>
                        <td class="border px-3 py-1"><?= htmlspecialchars($stat['teamId']) ?></td>
                        <td class="border px-3 py-1"><?= htmlspecialchars($teamConference) ?></td>
                        <td class="border px-3 py-1"><?= $stat['avgPoints'] ?></td>
                        <td class="border px-3 py-1"><?= $stat['avgAssists'] ?></td>
                        <td class="border px-3 py-1"><?= $stat['avgRebounds'] ?></td>
                        <td class="border px-3 py-1">
                            <a href="player_detail.php?playerID=<?= $stat['playerID'] ?>&season=<?= $stat['year'] ?>" class="text-blue-500 hover:text-blue-700">
                                <i class="fa-solid fa-circle-info"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="text-center p-4">Data tidak ditemukan.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="flex justify-center mt-4">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?><?= $filterSeason ? "&season=$filterSeason" : "" ?><?= $filterTeam ? "&team=$filterTeam" : "" ?><?= $filterPos ? "&pos=$filterPos" : "" ?><?= $filterPlayerName ? "&playerName=$filterPlayerName" : "" ?>" class="px-4 py-2 mx-1 bg-gray-200 rounded hover:bg-gray-300">Sebelumnya</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?= $i ?><?= $filterSeason ? "&season=$filterSeason" : "" ?><?= $filterTeam ? "&team=$filterTeam" : "" ?><?= $filterPos ? "&pos=$filterPos" : "" ?><?= $filterPlayerName ? "&playerName=$filterPlayerName" : "" ?>" class="px-4 py-2 mx-1 <?= $page == $i ? 'bg-blue-500 text-white' : 'bg-gray-200 hover:bg-gray-300' ?> rounded"><?= $i ?></a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?><?= $filterSeason ? "&season=$filterSeason" : "" ?><?= $filterTeam ? "&team=$filterTeam" : "" ?><?= $filterPos ? "&pos=$filterPos" : "" ?><?= $filterPlayerName ? "&playerName=$filterPlayerName" : "" ?>" class="px-4 py-2 mx-1 bg-gray-200 rounded hover:bg-gray-300">Selanjutnya</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</body>
</html>