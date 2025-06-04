<?php
require_once 'db.php'; // Menggunakan $players_collection dari db.php
include 'header.php';   // Pastikan header.php tidak mengeluarkan output sebelum <!DOCTYPE html>

// --- BAGIAN AWAL PHP (Filter, data unik dropdown) ---
$filterSeason = $_GET['season'] ?? '';
$filterTeam = $_GET['team'] ?? '';
$filterPos = $_GET['pos'] ?? '';
$filterPlayerName = $_GET['playerName'] ?? '';

// Ambil data unik untuk dropdown menggunakan Aggregation dari $players_collection
// Seasons
$seasonsPipeline = [
    ['$unwind' => '$career_teams'],
    ['$match' => ['career_teams.year' => ['$ne' => null, '$exists' => true]]], 
    ['$group' => ['_id' => '$career_teams.year']],
    ['$sort' => ['_id' => -1]]
];
$seasonsResult = $players_collection->aggregate($seasonsPipeline)->toArray();
$seasons = array_map(fn($s) => $s['_id'], $seasonsResult);

// Team IDs
$teamsPipeline = [
    ['$unwind' => '$career_teams'],
    ['$match' => ['career_teams.tmID' => ['$ne' => null, '$exists' => true]]], 
    ['$group' => ['_id' => '$career_teams.tmID']],
    ['$sort' => ['_id' => 1]]
];
$teamIDsResult = $players_collection->aggregate($teamsPipeline)->toArray();
$teamIDs = array_map(fn($t) => $t['_id'], $teamIDsResult);

$teamDisplayNames = [];
if (!empty($teamIDs)) {
    // Coba ambil nama tim dari koleksi $teams (jika ada dan didefinisikan di db.php)
    // Ganti $teams dengan nama variabel koleksi tim Anda jika berbeda.
    if (isset($teams) && $teams instanceof MongoDB\Collection) { // $teams dari db.php
        $teamDetailsCursor = $teams->find(
            ['tmID' => ['$in' => $teamIDs]],
            ['projection' => ['tmID' => 1, 'name' => 1, 'year' => 1]] 
        );
        $latestTeamNameMap = [];
        foreach ($teamDetailsCursor as $td) {
            if (!isset($latestTeamNameMap[$td['tmID']]) || (isset($td['year']) && $td['year'] > ($latestTeamNameMap[$td['tmID']]['year'] ?? 0))) {
                $latestTeamNameMap[$td['tmID']] = ['name' => $td['name'], 'year' => $td['year'] ?? 0];
            }
        }
        foreach ($teamIDs as $id) {
            if (is_string($id)) {
                 $teamDisplayNames[$id] = $latestTeamNameMap[$id]['name'] ?? $id; 
            }
        }
    } else {
        // Fallback: Jika koleksi $teams tidak ada, gunakan tmID sebagai nama
        foreach ($teamIDs as $id) {
            if (is_string($id)) {
                $teamDisplayNames[$id] = $id;
            }
        }
    }
    asort($teamDisplayNames);
}

// Positions (langsung dari koleksi utama)
$positions = $players_collection->distinct('pos');
$positions = array_filter($positions, fn($value) => !is_null($value) && $value !== '');
sort($positions);


// --- BANGUN PIPELINE AGREGRASI ---
$aggregationPipeline = [];

// Tahap $match awal untuk filter pada field level pemain
$playerMatchStage = [];
if ($filterPos) $playerMatchStage['pos'] = $filterPos;
if ($filterPlayerName) {
    $playerMatchStage['$or'] = [
        ['firstName' => new MongoDB\BSON\Regex($filterPlayerName, 'i')],
        ['lastName' => new MongoDB\BSON\Regex($filterPlayerName, 'i')],
        ['useFirst' => new MongoDB\BSON\Regex($filterPlayerName, 'i')] 
    ];
}
if (!empty($playerMatchStage)) {
    $aggregationPipeline[] = ['$match' => $playerMatchStage];
}

// $unwind career_teams
$aggregationPipeline[] = ['$unwind' => '$career_teams'];

// Tahap $match setelah unwind untuk filter pada field career_teams
$seasonTeamMatchStage = [];
if ($filterSeason) $seasonTeamMatchStage['career_teams.year'] = (int)$filterSeason;
if ($filterTeam) $seasonTeamMatchStage['career_teams.tmID'] = $filterTeam;

if (!empty($seasonTeamMatchStage)) {
    $aggregationPipeline[] = ['$match' => $seasonTeamMatchStage];
}

// --- KALKULASI KPI ---
$kpiPipeline = array_merge($aggregationPipeline, [
    [
        '$group' => [
            '_id' => null,
            'totalPoints' => ['$sum' => '$career_teams.points'],
            'totalAssists' => ['$sum' => '$career_teams.assists'],
            'totalRebounds' => ['$sum' => '$career_teams.rebounds'],
            'totalSteals' => ['$sum' => '$career_teams.steals'],
            'totalBlocks' => ['$sum' => '$career_teams.blocks'],
            'totalTurnovers' => ['$sum' => '$career_teams.turnovers'],
            'totalGP' => ['$sum' => '$career_teams.GP'],
            'countEntries' => ['$sum' => 1]
        ]
    ]
]);

$kpiResult = $players_collection->aggregate($kpiPipeline)->toArray();
$kpiData = $kpiResult[0] ?? null;

$totalPointsAllFiltered = $kpiData['totalPoints'] ?? 0;
$totalAssistsAllFiltered = $kpiData['totalAssists'] ?? 0;
$totalReboundsAllFiltered = $kpiData['totalRebounds'] ?? 0;
$totalStealsAllFiltered = $kpiData['totalSteals'] ?? 0;
$totalBlocksAllFiltered = $kpiData['totalBlocks'] ?? 0;
$totalTurnoversAllFiltered = $kpiData['totalTurnovers'] ?? 0;
$totalGamesPlayedAllFiltered = $kpiData['totalGP'] ?? 0;
$totalPlayerSeasonEntries = $kpiData['countEntries'] ?? 0;

$avgPoints = $totalGamesPlayedAllFiltered > 0 ? round($totalPointsAllFiltered / $totalGamesPlayedAllFiltered, 1) : 0;
$avgAssists = $totalGamesPlayedAllFiltered > 0 ? round($totalAssistsAllFiltered / $totalGamesPlayedAllFiltered, 1) : 0;
$avgRebounds = $totalGamesPlayedAllFiltered > 0 ? round($totalReboundsAllFiltered / $totalGamesPlayedAllFiltered, 1) : 0;
$avgSteals = $totalGamesPlayedAllFiltered > 0 ? round($totalStealsAllFiltered / $totalGamesPlayedAllFiltered, 1) : 0;
$avgBlocks = $totalGamesPlayedAllFiltered > 0 ? round($totalBlocksAllFiltered / $totalGamesPlayedAllFiltered, 1) : 0;
$avgTurnovers = $totalGamesPlayedAllFiltered > 0 ? round($totalTurnoversAllFiltered / $totalGamesPlayedAllFiltered, 1) : 0;
$avgGamePlayedPerEntry = $totalPlayerSeasonEntries > 0 ? round($totalGamesPlayedAllFiltered / $totalPlayerSeasonEntries, 1) : 0;


// --- PAGINATION LOGIC ---
$itemsPerPage = 25;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($currentPage < 1) $currentPage = 1;

$totalPages = $totalPlayerSeasonEntries > 0 ? ceil($totalPlayerSeasonEntries / $itemsPerPage) : 1;
if ($currentPage > $totalPages && $totalPages > 0) { $currentPage = $totalPages; }
elseif ($totalPages == 0 && $currentPage > 1) { $currentPage = 1; }

$offset = ($currentPage - 1) * $itemsPerPage;

// Pipeline untuk mengambil data halaman saat ini
$dataPipeline = array_merge($aggregationPipeline, [ 
    ['$sort' => ['career_teams.year' => -1, 'career_teams.points' => -1]],
    ['$skip' => $offset],
    ['$limit' => $itemsPerPage]
]);

$cursor = $players_collection->aggregate($dataPipeline);

// --- PROSES DATA UNTUK TABEL DAN CHART ---
$data = [];
foreach ($cursor as $doc) {
    $playerSeasonData = $doc['career_teams'];
    $dataRow = [
        'playerID' => $doc['playerID'] ?? null,
        'playerName' => trim(($doc['useFirst'] ?? ($doc['firstName'] ?? '')) . ' ' . ($doc['lastName'] ?? ($doc['playerID'] ?? 'Unknown'))),
        'pos' => $doc['pos'] ?? '-',
        'year' => $playerSeasonData['year'] ?? null,
        'tmID' => $playerSeasonData['tmID'] ?? null,
        'GP' => $playerSeasonData['GP'] ?? 0,
        'points' => $playerSeasonData['points'] ?? 0,
        'assists' => $playerSeasonData['assists'] ?? 0,
        'rebounds' => $playerSeasonData['rebounds'] ?? 0,
        'steals' => $playerSeasonData['steals'] ?? 0,
        'blocks' => $playerSeasonData['blocks'] ?? 0,
        'turnovers' => $playerSeasonData['turnovers'] ?? 0,
        'fgMade' => $playerSeasonData['fgMade'] ?? 0,
        'fgAttempted' => $playerSeasonData['fgAttempted'] ?? 0,
    ];
    $data[] = $dataRow;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Player Performance Dashboard - NBA Stats</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Roboto+Condensed:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #F0F4F8; }
        .font-condensed { font-family: 'Roboto Condensed', sans-serif; }
        .stat-card { background-color: white; border-radius: 0.75rem; box-shadow: 0 4px 12px rgba(0,0,0,0.06); transition: transform 0.2s, box-shadow 0.2s; padding: 1.25rem; }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 8px 16px rgba(0,0,0,0.08); }
        .table-wrapper { background-color: white; border-radius: 0.75rem; box-shadow: 0 4px 12px rgba(0,0,0,0.06); overflow: hidden;}
        .table-scroll-container { max-height: 65vh; overflow-y: auto; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #e5e7eb; border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: #9ca3af; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #6b7280; }
        .chart-container { background-color: white; border-radius: 0.75rem; padding: 1.5rem; box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
        select, input[type="text"] { border-color: #D1D5DB; box-shadow: 0 1px 2px rgba(0,0,0,0.03); }
        select { background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236B7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e"); background-position: right 0.5rem center; background-repeat: no-repeat; background-size: 1.5em 1.5em; padding-right: 2.5rem; -webkit-appearance: none; -moz-appearance: none; appearance: none; }
        input:focus, select:focus { outline: 2px solid transparent; outline-offset: 2px; border-color: #4F46E5; box-shadow: 0 0 0 3px rgba(129, 140, 248, 0.4); }
        .btn { font-weight: 600; padding: 0.625rem 1.25rem; border-radius: 0.375rem; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: all 0.2s; }
        .btn-primary { background-color: #4F46E5; color: white; }
        .btn-primary:hover { background-color: #4338CA; }
        .pagination a { padding: 0.5rem 0.875rem; border: 1px solid #D1D5DB; border-radius: 0.375rem; background-color: white; color: #374151; font-size: 0.875rem; font-weight: 500; transition: all 0.15s; }
        .pagination a:hover { background-color: #F3F4F6; border-color: #9CA3AF; }
        .pagination a.active { background-color: #4F46E5; color: white; border-color: #4F46E5; font-weight: 600; }
        .pagination a.disabled { color: #9CA3AF; cursor: not-allowed; background-color: #F9FAFB; border-color: #E5E7EB; }
    </style>
</head>
<body class="text-gray-800 antialiased">

    <div class="max-w-screen-2xl mx-auto p-4 md:p-6 lg:p-8">
        <header class="mb-6 md:mb-8 text-center md:text-left">
            <h1 class="text-3xl md:text-4xl font-bold text-slate-800 font-condensed tracking-tight">NBA Player Performance</h1>
            <p class="text-sm text-slate-500 mt-1">Explore detailed statistics and trends for NBA players.</p>
        </header>

        <!-- Filter Section -->
        <div class="bg-white p-5 md:p-6 rounded-xl shadow-xl mb-8 sticky top-4 z-20 border border-gray-200">
            <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-x-4 gap-y-4 items-end">
                <div>
                    <label for="season" class="block text-xs font-medium text-gray-600 mb-1.5">Season</label>
                    <select name="season" id="season" class="w-full border-gray-300 rounded-md text-sm py-2.5 px-3 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">All Seasons</option>
                        <?php foreach ($seasons as $season): ?>
                            <option value="<?= htmlspecialchars($season) ?>" <?= ($filterSeason == $season) ? 'selected' : '' ?>><?= htmlspecialchars($season) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="team" class="block text-xs font-medium text-gray-600 mb-1.5">Team</label>
                    <select name="team" id="team" class="w-full border-gray-300 rounded-md text-sm py-2.5 px-3 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">All Teams</option>
                        <?php foreach ($teamDisplayNames as $tmID => $name): ?>
                            <option value="<?= htmlspecialchars($tmID) ?>" <?= ($filterTeam == $tmID) ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="pos" class="block text-xs font-medium text-gray-600 mb-1.5">Position</label>
                    <select name="pos" id="pos" class="w-full border-gray-300 rounded-md text-sm py-2.5 px-3 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">All Positions</option>
                        <?php foreach ($positions as $pos): ?>
                            <option value="<?= htmlspecialchars($pos) ?>" <?= ($filterPos == $pos) ? 'selected' : '' ?>><?= htmlspecialchars($pos) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="playerName" class="block text-xs font-medium text-gray-600 mb-1.5">Player Name</label>
                    <input type="text" name="playerName" id="playerName" class="w-full border-gray-300 rounded-md text-sm py-2.5 px-3 focus:ring-indigo-500 focus:border-indigo-500" placeholder="e.g., LeBron James" value="<?= htmlspecialchars($filterPlayerName) ?>">
                </div>
                <button type="submit" class="btn btn-primary w-full flex items-center justify-center h-[42px]">
                    <i class="fas fa-filter mr-2 fa-sm"></i> Apply Filters
                </button>
            </form>
        </div>

        <!-- Stat Cards Section -->
        <?php
        $statCardsData = [
            ['label' => 'Avg Points/Game', 'value' => $avgPoints, 'color' => 'text-blue-600', 'icon' => 'fa-solid fa-basketball', 'bg' => 'bg-blue-100'],
            ['label' => 'Avg Assists/Game', 'value' => $avgAssists, 'color' => 'text-green-600', 'icon' => 'fa-solid fa-hands-helping', 'bg' => 'bg-green-100'],
            ['label' => 'Avg Rebounds/Game', 'value' => $avgRebounds, 'color' => 'text-yellow-600', 'icon' => 'fa-solid fa-people-carry-box', 'bg' => 'bg-yellow-100'],
            ['label' => 'Avg Steals/Game', 'value' => $avgSteals, 'color' => 'text-purple-600', 'icon' => 'fa-solid fa-user-secret', 'bg' => 'bg-purple-100'],
            ['label' => 'Avg Blocks/Game', 'value' => $avgBlocks, 'color' => 'text-orange-600', 'icon' => 'fa-solid fa-shield-halved', 'bg' => 'bg-orange-100'],
            ['label' => 'Avg Turnovers/Game', 'value' => $avgTurnovers, 'color' => 'text-red-600', 'icon' => 'fa-solid fa-recycle', 'bg' => 'bg-red-100'],
            ['label' => 'Avg GP / Entry', 'value' => $avgGamePlayedPerEntry, 'color' => 'text-teal-600', 'icon' => 'fa-solid fa-calendar-check', 'bg' => 'bg-teal-100'],
            ['label' => 'Total Entries', 'value' => number_format($totalPlayerSeasonEntries), 'color' => 'text-indigo-600', 'icon' => 'fa-solid fa-list-ol', 'bg' => 'bg-indigo-100'],
        ];
        ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5 mb-8">
            <?php foreach ($statCardsData as $card): ?>
                <div class="stat-card flex items-center space-x-4">
                    <div class="p-3 rounded-full <?= $card['bg'] ?> <?= $card['color'] ?>">
                        <i class="<?= $card['icon'] ?> fa-fw text-xl"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-medium uppercase tracking-wider"><?= $card['label'] ?></p>
                        <p class="text-2xl font-semibold <?= $card['color'] ?> mt-0.5 font-condensed"><?= $card['value'] ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Table Section -->
        <div class="table-wrapper mb-8">
            <div class="px-5 py-4 border-b border-gray-200 flex flex-col sm:flex-row justify-between items-center space-y-2 sm:space-y-0">
                <h2 class="text-lg font-semibold text-slate-700 font-condensed">Player Statistics</h2>
                <?php if ($totalPlayerSeasonEntries > 0):
                    $startEntry = ($currentPage - 1) * $itemsPerPage + 1;
                    $endEntry = min($currentPage * $itemsPerPage, $totalPlayerSeasonEntries);
                ?>
                    <span class="text-xs font-medium text-gray-500">
                        Showing <?= number_format($startEntry) ?>-<?= number_format($endEntry) ?> of <?= number_format($totalPlayerSeasonEntries) ?> entries
                    </span>
                <?php else: ?>
                    <span class="text-xs font-medium text-gray-500">No entries found</span>
                <?php endif; ?>
            </div>
            <div class="table-scroll-container">
                <table class="min-w-full table-fixed text-sm">
                    <thead class="sticky top-0 bg-slate-100 z-10 shadow-sm">
                        <tr class="text-left text-xs text-slate-500 uppercase tracking-wider">
                            <th class="px-4 py-3 font-semibold w-2/12">Player</th>
                            <th class="px-4 py-3 font-semibold w-2/12">Team</th>
                            <th class="px-4 py-3 font-semibold w-[6%] text-center">Pos</th>
                            <th class="px-4 py-3 font-semibold w-[7%] text-center">Season</th>
                            <th class="px-4 py-3 font-semibold w-[6%] text-right">GP</th>
                            <th class="px-4 py-3 font-semibold w-[7%] text-right">Pts</th>
                            <th class="px-4 py-3 font-semibold w-[7%] text-right">Ast</th>
                            <th class="px-4 py-3 font-semibold w-[7%] text-right">Reb</th>
                            <th class="px-4 py-3 font-semibold w-[6%] text-right">Stl</th>
                            <th class="px-4 py-3 font-semibold w-[6%] text-right">Blk</th>
                            <th class="px-4 py-3 font-semibold w-[6%] text-right">TO</th>
                            <th class="px-4 py-3 font-semibold w-[8%] text-right">FG%</th>
                            <th class="px-4 py-3 font-semibold w-[10%] text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (count($data) > 0): ?>
                            <?php foreach ($data as $idx => $row):
                                $fgPercent = (isset($row['fgAttempted']) && $row['fgAttempted'] > 0 && isset($row['fgMade'])) ? round(($row['fgMade'] / $row['fgAttempted']) * 100, 1) : 0;
                                $rowClass = $idx % 2 == 0 ? 'bg-white' : 'bg-slate-50/80';
                                $teamNameForDisplay = $teamDisplayNames[$row['tmID']] ?? $row['tmID'];
                            ?>
                                <tr class="<?= $rowClass ?> hover:bg-indigo-50/50 transition-colors duration-100 group">
                                    <td class="px-4 py-2.5 font-medium text-slate-700 whitespace-nowrap truncate group-hover:text-indigo-600"><?= htmlspecialchars($row['playerName']) ?></td>
                                    <td class="px-4 py-2.5 text-slate-600 whitespace-nowrap truncate"><?= htmlspecialchars($teamNameForDisplay) ?></td>
                                    <td class="px-4 py-2.5 text-slate-600 whitespace-nowrap text-center"><?= htmlspecialchars($row['pos']) ?></td>
                                    <td class="px-4 py-2.5 text-slate-600 whitespace-nowrap text-center"><?= htmlspecialchars($row['year']) ?></td>
                                    <td class="px-4 py-2.5 text-slate-600 text-right whitespace-nowrap"><?= htmlspecialchars($row['GP'] ?? '-') ?></td>
                                    <td class="px-4 py-2.5 text-slate-700 text-right whitespace-nowrap font-semibold"><?= htmlspecialchars($row['points'] ?? '-') ?></td>
                                    <td class="px-4 py-2.5 text-slate-600 text-right whitespace-nowrap"><?= htmlspecialchars($row['assists'] ?? '-') ?></td>
                                    <td class="px-4 py-2.5 text-slate-600 text-right whitespace-nowrap"><?= htmlspecialchars($row['rebounds'] ?? '-') ?></td>
                                    <td class="px-4 py-2.5 text-slate-600 text-right whitespace-nowrap"><?= htmlspecialchars($row['steals'] ?? '-') ?></td>
                                    <td class="px-4 py-2.5 text-slate-600 text-right whitespace-nowrap"><?= htmlspecialchars($row['blocks'] ?? '-') ?></td>
                                    <td class="px-4 py-2.5 text-slate-600 text-right whitespace-nowrap"><?= htmlspecialchars($row['turnovers'] ?? '-') ?></td>
                                    <td class="px-4 py-2.5 text-slate-600 text-right whitespace-nowrap"><?= $fgPercent ?>%</td>
                                    <td class="px-4 py-2.5 text-center whitespace-nowrap">
                                        <a href="player_season_detail.php?playerID=<?= urlencode($row['playerID'] ?? '') ?>&year=<?= $row['year'] ?? '' ?>&team=<?= urlencode($row['tmID'] ?? '') ?>"
                                            class="text-indigo-600 hover:text-indigo-800 text-xs font-semibold hover:underline py-1 px-1.5 rounded-md transition-colors">
                                            Details <i class="fas fa-arrow-right fa-xs ml-0.5"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="13" class="text-center p-10 text-gray-500">
                                    <div class="flex flex-col items-center">
                                        <i class="fas fa-ghost fa-3x text-slate-300 mb-4 opacity-70"></i>
                                        <p class="font-semibold text-slate-600 text-base">No Player Data Found</p>
                                        <p class="text-sm">Try adjusting your filters or explore other seasons.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Section -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination px-5 py-4 border-t border-gray-200 flex flex-col sm:flex-row justify-between items-center space-y-3 sm:space-y-0">
                    <div class="text-xs text-slate-500">
                        Page <?= $currentPage ?> of <?= $totalPages ?>
                    </div>
                    <div class="flex items-center space-x-1.5">
                        <?php
                        $queryParams = $_GET; unset($queryParams['page']);
                        $baseUrl = 'player_stats.php?' . http_build_query($queryParams) . (empty($queryParams) ? '' : '&');
                        ?>
                        <a href="<?= ($currentPage > 1) ? $baseUrl . 'page=' . ($currentPage - 1) : '#' ?>" class="<?= ($currentPage <= 1) ? 'disabled' : '' ?>"><i class="fas fa-chevron-left fa-xs mr-1"></i> Prev</a>
                        <?php
                        $numPageLinksToShow = 5; $startPage = max(1, $currentPage - floor($numPageLinksToShow / 2));
                        $endPage = min($totalPages, $startPage + $numPageLinksToShow - 1);
                        if ($endPage - $startPage + 1 < $numPageLinksToShow && $startPage > 1) $startPage = max(1, $endPage - $numPageLinksToShow + 1);
                        if ($startPage > 1) { echo '<a href="' . $baseUrl . 'page=1">1</a>'; if ($startPage > 2) echo '<span class="px-2 py-1.5 text-slate-500 text-xs">...</span>'; }
                        for ($i = $startPage; $i <= $endPage; $i++): ?><a href="<?= $baseUrl . 'page=' . $i ?>" class="<?= ($i == $currentPage) ? 'active' : '' ?>"><?= $i ?></a><?php endfor;
                        if ($endPage < $totalPages) { if ($endPage < $totalPages - 1) echo '<span class="px-2 py-1.5 text-slate-500 text-xs">...</span>'; echo '<a href="' . $baseUrl . 'page=' . $totalPages . '">' . $totalPages . '</a>'; } ?>
                        <a href="<?= ($currentPage < $totalPages) ? $baseUrl . 'page=' . ($currentPage + 1) : '#' ?>" class="<?= ($currentPage >= $totalPages) ? 'disabled' : '' ?>">Next <i class="fas fa-chevron-right fa-xs ml-1"></i></a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Charts Section -->
        <?php if (count($data) > 0): ?>
            <section class="mt-10">
                <div class="px-1 py-4 mb-4">
                    <h2 class="text-2xl font-semibold text-slate-700 text-center font-condensed">Visual Insights</h2>
                    <p class="text-sm text-slate-500 text-center mt-1">Statistics for up to 15 player-seasons on this page.</p>
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="chart-container min-h-[400px] md:min-h-[450px] flex flex-col">
                        <h3 class="text-base font-semibold mb-4 text-slate-600">Player Core Stats Comparison (Pts, Ast, Reb)</h3>
                        <div class="flex-grow relative"><canvas id="barChartPlayers"></canvas></div>
                    </div>
                    <div class="chart-container min-h-[400px] md:min-h-[450px] flex flex-col">
                        <h3 class="text-base font-semibold mb-4 text-slate-600">Performance Radar (First Player in Table)</h3>
                        <div class="flex-grow relative"><canvas id="radarChartPlayer"></canvas></div>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <footer class="text-center mt-16 py-8 border-t border-gray-200">
            <p class="text-xs text-slate-500">© <?= date("Y") ?> NBA Stats Dashboard. Data for illustrative purposes.</p>
        </footer>
    </div>

    <script>
    // Salin bagian JavaScript lengkap dari kode player_stats.php Anda yang sebelumnya (versi lengkap)
    // JavaScript untuk Chart.js (Sama seperti versi lengkap sebelumnya)
    // Pastikan variabel chartDataSource (= json_encode($data)) memiliki data yang benar
    document.addEventListener('DOMContentLoaded', function() {
        const chartDataSource = <?= json_encode($data); ?>;

        if (chartDataSource && chartDataSource.length > 0) {
            const chartData = chartDataSource.slice(0, 15);

            const playerNamesBar = chartData.map(d => `${d.playerName || 'N/A'} (${d.year || 'N/A'})`);
            const pointsDataBar = chartData.map(d => d.points || 0);
            const assistsDataBar = chartData.map(d => d.assists || 0);
            const reboundsDataBar = chartData.map(d => d.rebounds || 0);

            const ctxBar = document.getElementById('barChartPlayers')?.getContext('2d');
            if (ctxBar) {
                new Chart(ctxBar, { 
                    type: 'bar',
                    data: {
                        labels: playerNamesBar,
                        datasets: [
                            { label: 'Points', data: pointsDataBar, backgroundColor: 'rgba(79, 70, 229, 0.75)', borderColor: 'rgba(79, 70, 229, 1)', borderWidth: 1, borderRadius: {topLeft:4, topRight:4}, barPercentage: 0.7, categoryPercentage: 0.8 },
                            { label: 'Assists', data: assistsDataBar, backgroundColor: 'rgba(5, 150, 105, 0.75)', borderColor: 'rgba(5, 150, 105, 1)', borderWidth: 1, borderRadius: {topLeft:4, topRight:4}, barPercentage: 0.7, categoryPercentage: 0.8 },
                            { label: 'Rebounds', data: reboundsDataBar, backgroundColor: 'rgba(202, 138, 4, 0.75)', borderColor: 'rgba(202, 138, 4, 1)', borderWidth: 1, borderRadius: {topLeft:4, topRight:4}, barPercentage: 0.7, categoryPercentage: 0.8 }
                        ]
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth:8, padding:15, font:{size:10} } }, tooltip: { mode: 'index', intersect: false, backgroundColor:'#fff', titleColor:'#334155', bodyColor:'#475569', borderColor:'#e2e8f0', borderWidth:1, padding:10, cornerRadius:6, usePointStyle:true } }, scales: { y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks:{color:'#64748b', font:{size:9}} }, x: { grid: { display: false }, ticks:{color:'#64748b', font:{size:9}, autoSkipPadding:15, maxRotation:65, minRotation:65 }} }, interaction: { mode: 'index', intersect: false } }
                });
            }

            const radarCtx = document.getElementById('radarChartPlayer')?.getContext('2d');
            if (radarCtx && chartData.length > 0) {
                const firstPlayerData = chartData[0];
                const radarDataValues = [firstPlayerData.points || 0, firstPlayerData.assists || 0, firstPlayerData.rebounds || 0, firstPlayerData.steals || 0, firstPlayerData.blocks || 0, firstPlayerData.turnovers || 0];
                new Chart(radarCtx, { 
                    type: 'radar',
                    data: {
                        labels: ['Points', 'Assists', 'Rebounds', 'Steals', 'Blocks', 'TOs (Low Ideal)'],
                        datasets: [{ label: `${firstPlayerData.playerName || 'N/A'} (${firstPlayerData.year || 'N/A'})`, data: radarDataValues, fill: true, backgroundColor: 'rgba(79, 70, 229, 0.2)', borderColor: 'rgba(79, 70, 229, 0.7)', pointBackgroundColor: 'rgba(79, 70, 229, 1)', pointBorderColor: '#fff', pointHoverBackgroundColor: '#fff', pointHoverBorderColor: 'rgba(79, 70, 229, 1)', borderWidth: 1.5, pointRadius: 3 }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth:8, padding:15, font:{size:10} } }, tooltip: { backgroundColor:'#fff', titleColor:'#334155', bodyColor:'#475569', borderColor:'#e2e8f0', borderWidth:1, padding:10, cornerRadius:6, usePointStyle:true } }, scales: { r: { beginAtZero: true, angleLines: { color: '#e2e8f0' }, grid: { color: '#f1f5f9' }, pointLabels: { font:{size:10, weight:'500'}, color:'#475569' }, ticks: { backdropColor: 'rgba(255,255,255,0.9)', color:'#64748b', font:{size:8}, stepSize: determineRadarStep(firstPlayerData), maxTicksLimit: 5 } } }, elements: { line: { tension: 0.1, borderWidth: 2 } } }
                });
            }
        } else {
             ['barChartPlayers', 'radarChartPlayer'].forEach(id => {
                const container = document.getElementById(id)?.parentNode;
                if(container) container.innerHTML = `<div class="flex flex-col items-center justify-center h-full text-slate-400 p-6 text-center"><i class="fas fa-chart-pie fa-3x mb-4 text-slate-300"></i><p class="text-sm font-medium">No data to display charts.</p><p class="text-xs">Please adjust filters or check data source.</p></div>`;
            });
        }

        function determineRadarStep(playerData) {
            if (!playerData) return 10;
            const stats = [playerData.points || 0, playerData.assists || 0, playerData.rebounds || 0, playerData.steals || 0, playerData.blocks || 0];
            const maxVal = Math.max(...stats.filter(s => typeof s === 'number' && !isNaN(s) && s > 0));
            if (maxVal === 0) return 2;
            let step = Math.ceil(maxVal / 4); 
            const niceSteps = [1, 2, 5, 10, 15, 20, 25, 50, 100, 200, 500, 1000]; 
            for (let s of niceSteps) { if (step <= s) return s; }
            return Math.max(10, Math.ceil(step / 50) * 50);
        }
    });
    </script>
</body>
</html>