<?php
require_once 'db.php';
include 'header.php'; // Asumsi header.php tidak mengeluarkan output HTML sebelum <!DOCTYPE html>

// --- BAGIAN AWAL PHP (Filter, data unik dropdown) ---
$filterSeason = $_GET['season'] ?? '';
$filterTeam = $_GET['team'] ?? '';
$filterPos = $_GET['pos'] ?? '';
$filterPlayerName = $_GET['playerName'] ?? '';

$seasons = $playersTeams->distinct('year');
rsort($seasons);

$teamIDs = $playersTeams->distinct('tmID');
$teamDisplayNames = [];
if (!empty($teamIDs)) {
    if (is_array($teamIDs)) {
        $teamDetails = $teams->find(['tmID' => ['$in' => $teamIDs]], ['projection' => ['tmID' => 1, 'name' => 1, 'year' => 1]]);
        $latestTeamNameMap = [];
        foreach ($teamDetails as $td) {
            if (!isset($latestTeamNameMap[$td['tmID']]) || $td['year'] > $latestTeamNameMap[$td['tmID']]['year']) {
                $latestTeamNameMap[$td['tmID']] = ['name' => $td['name'], 'year' => $td['year']];
            }
        }
        foreach ($teamIDs as $id) {
            $teamDisplayNames[$id] = $latestTeamNameMap[$id]['name'] ?? $id;
        }
        asort($teamDisplayNames);
    } else {
        $teamIDs = [];
    }
}

$positions = $players->distinct('pos');
$positions = array_filter($positions, fn($value) => !is_null($value) && $value !== '');
sort($positions);

// Bangun filter query MongoDB
$query = [];
if ($filterSeason) $query['year'] = (int)$filterSeason;
if ($filterTeam) $query['tmID'] = $filterTeam;

$playerIDsForFilter = null;
if ($filterPos || $filterPlayerName) {
    $playerFilterQuery = [];
    if ($filterPos) $playerFilterQuery['pos'] = $filterPos;
    if ($filterPlayerName) {
        $playerFilterQuery['$or'] = [
            ['firstName' => new MongoDB\BSON\Regex($filterPlayerName, 'i')],
            ['lastName' => new MongoDB\BSON\Regex($filterPlayerName, 'i')]
        ];
    }
    $matchingPlayers = $players->find($playerFilterQuery, ['projection' => ['playerID' => 1]]);
    $playerIDsForFilter = [];
    foreach ($matchingPlayers as $p) {
        if (isset($p['playerID']) && is_string($p['playerID'])) {
            $playerIDsForFilter[] = $p['playerID'];
        }
    }
    if (empty($playerIDsForFilter) && ($filterPos || $filterPlayerName)) { // Hanya set __NO_MATCH__ jika filter ini aktif dan tidak ada hasil
        $query['playerID'] = ['$in' => ['__NO_MATCH__']];
    } elseif (!empty($playerIDsForFilter)) {
        $query['playerID'] = ['$in' => array_values(array_unique($playerIDsForFilter))];
    }
}

// --- KALKULASI KPI UNTUK SEMUA DATA YANG DIFILTER (SEBELUM PAGINASI) ---
$totalPointsAllFiltered = $totalAssistsAllFiltered = $totalReboundsAllFiltered = 0;
$totalStealsAllFiltered = $totalBlocksAllFiltered = $totalTurnoversAllFiltered = 0;
$totalGamesPlayedAllFiltered = 0;
$totalPlayerSeasonEntries = $playersTeams->countDocuments($query);

if ($totalPlayerSeasonEntries > 0) {
    $limitForKpiCalc = 5000;
    $kpiQueryOptions = ['limit' => $limitForKpiCalc];
    // Jika $totalPlayerSeasonEntries > $limitForKpiCalc, mungkin lebih baik pakai agregasi
    // Tapi untuk sekarang, kita pakai find dengan limit untuk KPI
    $allFilteredDataCursor = $playersTeams->find($query, $kpiQueryOptions);

    foreach ($allFilteredDataCursor as $row) {
        $totalPointsAllFiltered += $row['points'] ?? 0;
        $totalAssistsAllFiltered += $row['assists'] ?? 0;
        $totalReboundsAllFiltered += $row['rebounds'] ?? 0;
        $totalGamesPlayedAllFiltered += $row['GP'] ?? 0;
        $totalStealsAllFiltered += $row['steals'] ?? 0;
        $totalBlocksAllFiltered += $row['blocks'] ?? 0;
        $totalTurnoversAllFiltered += $row['turnovers'] ?? 0;
    }
}

$avgPoints = $totalGamesPlayedAllFiltered > 0 ? round($totalPointsAllFiltered / $totalGamesPlayedAllFiltered, 1) : 0;
$avgAssists = $totalGamesPlayedAllFiltered > 0 ? round($totalAssistsAllFiltered / $totalGamesPlayedAllFiltered, 1) : 0;
$avgRebounds = $totalGamesPlayedAllFiltered > 0 ? round($totalReboundsAllFiltered / $totalGamesPlayedAllFiltered, 1) : 0;
$avgSteals = $totalGamesPlayedAllFiltered > 0 ? round($totalStealsAllFiltered / $totalGamesPlayedAllFiltered, 1) : 0;
$avgBlocks = $totalGamesPlayedAllFiltered > 0 ? round($totalBlocksAllFiltered / $totalGamesPlayedAllFiltered, 1) : 0;
$avgTurnovers = $totalGamesPlayedAllFiltered > 0 ? round($totalTurnoversAllFiltered / $totalGamesPlayedAllFiltered, 1) : 0;
// $avgGamePlayedPerEntry akan dihitung berdasarkan data yang diambil untuk KPI.
// Jika $limitForKpiCalc lebih kecil dari $totalPlayerSeasonEntries, ini bukan rata-rata dari semua entri.
$actualEntriesForKpiCalc = ($totalPlayerSeasonEntries > $limitForKpiCalc) ? $limitForKpiCalc : $totalPlayerSeasonEntries;
$avgGamePlayedPerEntry = $actualEntriesForKpiCalc > 0 ? round($totalGamesPlayedAllFiltered / $actualEntriesForKpiCalc, 1) : 0;


// --- PAGINATION LOGIC ---
$itemsPerPage = 25;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($currentPage < 1) $currentPage = 1;

$totalPages = $totalPlayerSeasonEntries > 0 ? ceil($totalPlayerSeasonEntries / $itemsPerPage) : 1;
if ($currentPage > $totalPages) $currentPage = $totalPages;

$offset = ($currentPage - 1) * $itemsPerPage;

$optionsForPage = [
    'limit' => $itemsPerPage,
    'skip' => $offset,
    'sort' => ['year' => -1, 'points' => -1]
];
$cursor = $playersTeams->find($query, $optionsForPage);

// --- AMBIL DATA UNTUK TABEL DAN CHART (DATA HALAMAN SAAT INI) ---
$data = []; // Ini akan berisi data untuk halaman saat ini, termasuk semua field yang dibutuhkan chart
$playerIDsFromCursor = [];
foreach ($cursor as $item) {
    // Pastikan semua field yang dibutuhkan ada di sini
    $dataRow = [
        'playerID' => $item['playerID'] ?? null,
        'year' => $item['year'] ?? null,
        'tmID' => $item['tmID'] ?? null,
        'GP' => $item['GP'] ?? 0,
        'points' => $item['points'] ?? 0,
        'assists' => $item['assists'] ?? 0,
        'rebounds' => $item['rebounds'] ?? 0,
        'steals' => $item['steals'] ?? 0,
        'blocks' => $item['blocks'] ?? 0,
        'turnovers' => $item['turnovers'] ?? 0,
        'fgMade' => $item['fgMade'] ?? 0,
        'fgAttempted' => $item['fgAttempted'] ?? 0,
        // tambahkan field lain dari players_teams jika perlu
    ];
    $data[] = $dataRow;
    if (isset($item['playerID']) && is_string($item['playerID'])) {
        $playerIDsFromCursor[] = $item['playerID'];
    }
}
$playerIDsFromCursor = array_values(array_unique(array_filter($playerIDsFromCursor, 'is_string')));

$playersDetailsMap = [];
if (!empty($playerIDsFromCursor)) {
    $playersInfoCursor = $players->find(
        ['playerID' => ['$in' => $playerIDsFromCursor]],
        ['projection' => ['playerID' => 1, 'firstName' => 1, 'lastName' => 1, 'pos' => 1]]
    );
    foreach ($playersInfoCursor as $pInfo) {
        $playersDetailsMap[$pInfo['playerID']] = $pInfo;
    }
}

$processedData = [];
foreach ($data as $item) { // Loop melalui $data yang sudah berisi semua field numerik
    $playerDetail = isset($item['playerID']) && isset($playersDetailsMap[$item['playerID']]) ? $playersDetailsMap[$item['playerID']] : null;
    $tempItem = $item; // $item sudah berisi semua field yang dibutuhkan chart (points, assists, dll.)
    $tempItem['playerName'] = trim(($playerDetail['firstName'] ?? '') . ' ' . ($playerDetail['lastName'] ?? ($item['playerID'] ?? 'Unknown')));
    $tempItem['pos'] = $playerDetail['pos'] ?? '-'; // Posisi diambil dari $playersDetailsMap
    $processedData[] = $tempItem;
}
$data = $processedData; // $data sekarang siap untuk tabel dan chart (chartDataSource)
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Player Performance Dashboard - NBA Stats</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #e0e7ff;
        }

        .stat-card-enhanced {
            background-color: white;
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
            padding: 1.25rem;
        }

        .stat-card-enhanced:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.07), 0 4px 6px -4px rgba(0, 0, 0, 0.07);
        }

        .table-container {
            background-color: white;
            border-radius: 0.75rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .table-scroll {
            max-height: 60vh;
            overflow-y: auto;
        }

        /* Adjusted for better view with pagination */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: #edf2f7;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: #a0aec0;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #718096;
        }

        .chart-card {
            background-color: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        select,
        input[type="text"] {
            border-color: #d1d5db;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }

        select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
            padding-right: 2.5rem;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
        }

        input:focus,
        select:focus {
            outline: 2px solid transparent;
            outline-offset: 2px;
            border-color: #4f46e5;
            /* Indigo-600 */
            box-shadow: 0 0 0 3px rgba(165, 180, 252, 0.4);
        }

        .btn-primary {
            background-color: #4f46e5;
            color: white;
            font-weight: 600;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            transition: background-color 0.15s ease-in-out;
        }

        .btn-primary:hover {
            background-color: #4338ca;
        }

        .btn-primary:focus {
            outline: 2px solid transparent;
            outline-offset: 2px;
            box-shadow: 0 0 0 3px rgba(165, 180, 252, 0.4);
        }

        .pagination-link {
            padding: 0.375rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            background-color: white;
            color: #374151;
            font-size: 0.875rem;
            font-weight: 500;
            transition: background-color 0.15s ease-in-out, color 0.15s ease-in-out, border-color 0.15s ease-in-out;
        }

        .pagination-link:hover {
            background-color: #f3f4f6;
            border-color: #9ca3af;
        }

        .pagination-link.active {
            background-color: #4f46e5;
            color: white;
            border-color: #4f46e5;
            font-weight: 600;
        }

        .pagination-link.disabled {
            color: #9ca3af;
            cursor: not-allowed;
            background-color: #f9fafb;
            border-color: #e5e7eb;
        }

        .pagination-link.disabled:hover {
            background-color: #f9fafb;
            border-color: #e5e7eb;
        }
    </style>
</head>

<body class="text-gray-800 antialiased">

    <div class="max-w-screen-2xl mx-auto p-4 md:p-6 lg:p-8">
        <header class="mb-6 md:mb-8">
            <h1 class="text-2xl md:text-3xl font-bold text-slate-800">Player Performance Dashboard</h1>
            <p class="text-sm text-slate-500 mt-1">Dive into NBA player statistics with interactive filters and visualizations.</p>
        </header>

        <div class="bg-white p-5 md:p-6 rounded-xl shadow-lg mb-8 sticky top-4 z-20 border border-gray-300">
            <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-x-4 gap-y-4 items-end">
                <div>
                    <label for="season" class="block text-xs font-medium text-gray-600 mb-1.5">Season</label>
                    <select name="season" id="season" class="w-full border border-gray-400 rounded-md text-sm py-2 px-3 focus:ring-indigo-500 focus:border-indigo-500 appearance-none">
                        <option value="">All Seasons</option>
                        <?php foreach ($seasons as $season): ?>
                            <option value="<?= htmlspecialchars($season) ?>" <?= ($filterSeason == $season) ? 'selected' : '' ?>><?= htmlspecialchars($season) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="team" class="block text-xs font-medium text-gray-600 mb-1.5">Team</label>
                    <select name="team" id="team" class="w-full border border-gray-400 rounded-md text-sm py-2 px-3 focus:ring-indigo-500 focus:border-indigo-500 appearance-none">
                        <option value="">All Teams</option>
                        <?php foreach ($teamDisplayNames as $tmID => $name): ?>
                            <option value="<?= htmlspecialchars($tmID) ?>" <?= ($filterTeam == $tmID) ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="pos" class="block text-xs font-medium text-gray-600 mb-1.5">Position</label>
                    <select name="pos" id="pos" class="w-full border border-gray-400 rounded-md text-sm py-2 px-3 focus:ring-indigo-500 focus:border-indigo-500 appearance-none">
                        <option value="">All Positions</option>
                        <?php foreach ($positions as $pos): ?>
                            <option value="<?= htmlspecialchars($pos) ?>" <?= ($filterPos == $pos) ? 'selected' : '' ?>><?= htmlspecialchars($pos) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="playerName" class="block text-xs font-medium text-gray-600 mb-1.5">Player Name</label>
                    <input type="text" name="playerName" id="playerName" class="w-full border border-gray-400 rounded-md text-sm py-2 px-3 focus:ring-indigo-500 focus:border-indigo-500" placeholder="e.g., LeBron James" value="<?= htmlspecialchars($filterPlayerName) ?>">
                </div>
                <button type="submit" class="btn-primary w-full flex items-center justify-center">
                    <i class="fas fa-sliders-h mr-2 fa-sm"></i> Apply Filters
                </button>
            </form>
        </div>

        <?php
        $statCardsData = [
            ['label' => 'Avg Points/Game', 'value' => $avgPoints, 'color' => 'text-blue-600', 'icon' => 'fa-solid fa-basketball', 'bg' => 'bg-blue-50'],
            ['label' => 'Avg Assists/Game', 'value' => $avgAssists, 'color' => 'text-green-600', 'icon' => 'fa-solid fa-hands-helping', 'bg' => 'bg-green-50'],
            ['label' => 'Avg Rebounds/Game', 'value' => $avgRebounds, 'color' => 'text-amber-600', 'icon' => 'fa-solid fa-people-carry-box', 'bg' => 'bg-amber-50'],
            ['label' => 'Avg Steals/Game', 'value' => $avgSteals, 'color' => 'text-purple-600', 'icon' => 'fa-solid fa-user-secret', 'bg' => 'bg-purple-50'],
            ['label' => 'Avg Blocks/Game', 'value' => $avgBlocks, 'color' => 'text-orange-600', 'icon' => 'fa-solid fa-shield-halved', 'bg' => 'bg-orange-50'],
            ['label' => 'Avg Turnovers/Game', 'value' => $avgTurnovers, 'color' => 'text-red-600', 'icon' => 'fa-solid fa-recycle', 'bg' => 'bg-red-50'],
            ['label' => 'Avg GP/Entry', 'value' => $avgGamePlayedPerEntry, 'color' => 'text-teal-600', 'icon' => 'fa-solid fa-calendar-check', 'bg' => 'bg-teal-50'],
            ['label' => 'Filtered Entries', 'value' => $totalPlayerSeasonEntries, 'color' => 'text-indigo-600', 'icon' => 'fa-solid fa-list-ol', 'bg' => 'bg-indigo-50'],
        ];
        ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5 mb-8">
            <?php foreach ($statCardsData as $card): ?>
                <div class="stat-card-enhanced flex items-center space-x-4">
                    <div class="p-3.5 rounded-full <?= $card['bg'] ?> <?= $card['color'] ?>">
                        <i class="<?= $card['icon'] ?> fa-fw text-xl"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-medium uppercase tracking-wider"><?= $card['label'] ?></p>
                        <p class="text-2xl font-semibold <?= $card['color'] ?> mt-0.5"><?= $card['value'] ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="table-container p-0 mb-8">
            <div class="px-5 py-4 border-b border-gray-200 flex justify-between items-center">
                <h2 class="text-lg font-semibold text-slate-700">Player Statistics Breakdown</h2>
                <?php if ($totalPlayerSeasonEntries > 0):
                    $startEntry = ($currentPage - 1) * $itemsPerPage + 1;
                    $endEntry = min($currentPage * $itemsPerPage, $totalPlayerSeasonEntries);
                ?>
                    <span class="text-xs font-medium text-gray-500">
                        Showing <?= $startEntry ?>-<?= $endEntry ?> of <?= $totalPlayerSeasonEntries ?> entries
                    </span>
                <?php else: ?>
                    <span class="text-xs font-medium text-gray-500">No entries found</span>
                <?php endif; ?>
            </div>
            <div class="overflow-x-auto table-scroll">
                <table class="min-w-full table-auto text-sm">
                    <thead class="sticky top-0 bg-slate-100 z-10">
                        <tr class="text-left text-xs text-slate-500 uppercase tracking-wider">
                            <th class="px-4 py-3 font-semibold">Player</th>
                            <th class="px-4 py-3 font-semibold">Team</th>
                            <th class="px-4 py-3 font-semibold">Pos</th>
                            <th class="px-4 py-3 font-semibold">Season</th>
                            <th class="px-4 py-3 font-semibold text-right">GP</th>
                            <th class="px-4 py-3 font-semibold text-right">Pts</th>
                            <th class="px-4 py-3 font-semibold text-right">Ast</th>
                            <th class="px-4 py-3 font-semibold text-right">Reb</th>
                            <th class="px-4 py-3 font-semibold text-right">Stl</th>
                            <th class="px-4 py-3 font-semibold text-right">Blk</th>
                            <th class="px-4 py-3 font-semibold text-right">TO</th>
                            <th class="px-4 py-3 font-semibold text-right">FG%</th>
                            <th class="px-4 py-3 font-semibold text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (count($data) > 0): ?>
                            <?php foreach ($data as $idx => $row):
                                $fgPercent = 0;
                                if (isset($row['fgAttempted']) && $row['fgAttempted'] > 0 && isset($row['fgMade'])) {
                                    $fgPercent = round(($row['fgMade'] / $row['fgAttempted']) * 100, 1);
                                }
                                $rowClass = $idx % 2 == 0 ? 'bg-white' : 'bg-slate-50/70';
                                $teamNameForDisplay = $teamDisplayNames[$row['tmID']] ?? $row['tmID'];
                            ?>
                                <tr class="<?= $rowClass ?> hover:bg-indigo-50/40 transition-colors duration-100">
                                    <td class="px-4 py-2.5 font-medium text-slate-700 whitespace-nowrap"><?= htmlspecialchars($row['playerName']) ?></td>
                                    <td class="px-4 py-2.5 text-slate-600 whitespace-nowrap"><?= htmlspecialchars($teamNameForDisplay) ?></td>
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
                                        <a href="player_season_detail.php?playerID=<?= urlencode($row['playerID'] ?? '') ?>&year=<?= $row['year'] ?? '' ?>"
                                            class="text-indigo-600 hover:text-indigo-800 text-xs font-semibold hover:underline py-1 px-1.5 rounded-md transition-colors">
                                            Details <i class="fas fa-angle-right fa-xs ml-0.5"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="13" class="text-center p-10 text-gray-500">
                                    <div class="flex flex-col items-center">
                                        <i class="fas fa-basketball-ball fa-3x text-slate-300 mb-4 opacity-50"></i> <!-- Changed icon -->
                                        <p class="font-semibold text-slate-600">No Player Data Found</p>
                                        <p class="text-sm">Try adjusting your filters or searching for a different player.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="px-5 py-4 border-t border-gray-200 flex flex-col sm:flex-row justify-between items-center space-y-3 sm:space-y-0">
                    <div class="text-xs text-slate-500">
                        Page <?= $currentPage ?> of <?= $totalPages ?>
                    </div>
                    <div class="flex items-center space-x-1.5">
                        <?php
                        $queryParams = $_GET;
                        unset($queryParams['page']);
                        $baseUrl = 'player_stats.php?' . http_build_query($queryParams);
                        $baseUrl .= (empty($queryParams) ? '' : '&'); // Ensure correct query string format
                        ?>
                        <a href="<?= ($currentPage > 1) ? $baseUrl . 'page=' . ($currentPage - 1) : '#' ?>"
                            class="pagination-link <?= ($currentPage <= 1) ? 'disabled' : '' ?>">
                            <i class="fas fa-chevron-left fa-xs mr-1"></i> Prev
                        </a>
                        <?php
                        $numPageLinksToShow = 5;
                        $startPage = max(1, $currentPage - floor($numPageLinksToShow / 2));
                        $endPage = min($totalPages, $startPage + $numPageLinksToShow - 1);
                        if ($endPage - $startPage + 1 < $numPageLinksToShow && $startPage > 1) {
                            $startPage = max(1, $endPage - $numPageLinksToShow + 1);
                        }
                        if ($startPage > 1) {
                            echo '<a href="' . $baseUrl . 'page=1" class="pagination-link">1</a>';
                            if ($startPage > 2) echo '<span class="px-2 py-1.5 text-slate-500">...</span>';
                        }
                        for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <a href="<?= $baseUrl . 'page=' . $i ?>" class="pagination-link <?= ($i == $currentPage) ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor;
                        if ($endPage < $totalPages):
                            if ($endPage < $totalPages - 1) echo '<span class="px-2 py-1.5 text-slate-500">...</span>';
                            echo '<a href="' . $baseUrl . 'page=' . $totalPages . '" class="pagination-link">' . $totalPages . '</a>';
                        endif; ?>
                        <a href="<?= ($currentPage < $totalPages) ? $baseUrl . 'page=' . ($currentPage + 1) : '#' ?>"
                            class="pagination-link <?= ($currentPage >= $totalPages) ? 'disabled' : '' ?>">
                            Next <i class="fas fa-chevron-right fa-xs ml-1"></i>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php if (count($data) > 0): // Charts hanya ditampilkan jika ada data di halaman saat ini 
        ?>
            <section class="mt-10">
                <div class="px-1 py-4 mb-2">
                    <h2 class="text-xl font-semibold text-slate-700 text-center">Visual Insights</h2>
                    <p class="text-sm text-slate-500 text-center mt-1.5">Key statistics visualized for up to 15 player-seasons on this page.</p>
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="chart-card min-h-[380px] md:min-h-[420px] flex flex-col">
                        <h3 class="text-base font-semibold mb-4 text-slate-600">Player Core Stats Comparison</h3>
                        <div class="flex-grow relative"><canvas id="barChartPlayers"></canvas></div>
                    </div>
                    <div class="chart-card min-h-[380px] md:min-h-[420px] flex flex-col">
                        <h3 class="text-base font-semibold mb-4 text-slate-600">Performance Radar (First Player in List)</h3>
                        <div class="flex-grow relative"><canvas id="radarChartPlayer"></canvas></div>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <footer class="text-center mt-12 py-6 border-t border-gray-200">
            <p class="text-xs text-slate-500">© <?= date("Y") ?> NBA Stats Dashboard. All data is for demonstrational purposes.</p>
        </footer>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const chartDataSource = <?= json_encode($data); ?>;

            if (chartDataSource && chartDataSource.length > 0) {
                const chartData = chartDataSource.slice(0, 15);

                // Bar Chart
                const playerNamesBar = chartData.map(d => (d.playerName || 'N/A') + ' (' + (d.year || 'N/A') + ')');
                const pointsDataBar = chartData.map(d => d.points || 0);
                const assistsDataBar = chartData.map(d => d.assists || 0);
                const reboundsDataBar = chartData.map(d => d.rebounds || 0);

                const ctxBar = document.getElementById('barChartPlayers')?.getContext('2d');
                if (ctxBar) {
                    new Chart(ctxBar, {
                        type: 'bar',
                        data: {
                            labels: playerNamesBar,
                            datasets: [{
                                    label: 'Points',
                                    data: pointsDataBar,
                                    backgroundColor: 'rgba(79, 70, 229, 0.75)',
                                    borderColor: 'rgba(79, 70, 229, 1)',
                                    borderWidth: 1,
                                    borderRadius: {
                                        topLeft: 5,
                                        topRight: 5
                                    },
                                    barPercentage: 0.7,
                                    categoryPercentage: 0.8
                                },
                                {
                                    label: 'Assists',
                                    data: assistsDataBar,
                                    backgroundColor: 'rgba(5, 150, 105, 0.75)',
                                    borderColor: 'rgba(5, 150, 105, 1)',
                                    borderWidth: 1,
                                    borderRadius: {
                                        topLeft: 5,
                                        topRight: 5
                                    },
                                    barPercentage: 0.7,
                                    categoryPercentage: 0.8
                                },
                                {
                                    label: 'Rebounds',
                                    data: reboundsDataBar,
                                    backgroundColor: 'rgba(234, 179, 8, 0.75)',
                                    borderColor: 'rgba(234, 179, 8, 1)',
                                    borderWidth: 1,
                                    borderRadius: {
                                        topLeft: 5,
                                        topRight: 5
                                    },
                                    barPercentage: 0.7,
                                    categoryPercentage: 0.8
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    align: 'center',
                                    labels: {
                                        usePointStyle: true,
                                        boxWidth: 10,
                                        padding: 20,
                                        font: {
                                            size: 11
                                        }
                                    }
                                },
                                tooltip: {
                                    mode: 'index',
                                    intersect: false,
                                    backgroundColor: '#fff',
                                    titleColor: '#1e293b',
                                    bodyColor: '#475569',
                                    borderColor: '#e2e8f0',
                                    borderWidth: 1,
                                    padding: 10,
                                    cornerRadius: 6,
                                    usePointStyle: true,
                                    callbacks: {
                                        label: function(context) {
                                            let label = context.dataset.label || '';
                                            if (label) {
                                                label += ': ';
                                            }
                                            if (context.parsed.y !== null) {
                                                label += context.parsed.y;
                                            }
                                            return label;
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    grid: {
                                        color: '#f1f5f9',
                                        drawBorder: false
                                    },
                                    ticks: {
                                        color: '#64748b',
                                        font: {
                                            size: 10
                                        }
                                    }
                                },
                                x: {
                                    grid: {
                                        display: false
                                    },
                                    ticks: {
                                        color: '#64748b',
                                        font: {
                                            size: 10
                                        },
                                        autoSkipPadding: 10,
                                        maxRotation: 70,
                                        minRotation: 70
                                    }
                                } // Added rotation for long labels
                            },
                            interaction: {
                                mode: 'index',
                                intersect: false,
                            }
                        }
                    });
                } else {
                    const barChartContainer = document.getElementById('barChartPlayers')?.parentNode;
                    if (barChartContainer) barChartContainer.innerHTML = '<div class="flex flex-col items-center justify-center h-full text-slate-500"><i class="fas fa-chart-bar fa-2x mb-3 text-slate-300"></i><p class="text-sm">Bar Chart placeholder: Canvas not found.</p></div>';
                }

                // Radar Chart
                const radarCtx = document.getElementById('radarChartPlayer')?.getContext('2d');
                if (radarCtx && chartData.length > 0) {
                    const firstPlayerData = chartData[0];
                    const radarDataValues = [
                        firstPlayerData.points || 0, firstPlayerData.assists || 0, firstPlayerData.rebounds || 0,
                        firstPlayerData.steals || 0, firstPlayerData.blocks || 0, firstPlayerData.turnovers || 0
                    ];

                    new Chart(radarCtx, {
                        type: 'radar',
                        data: {
                            labels: ['Points', 'Assists', 'Rebounds', 'Steals', 'Blocks', 'Turnovers'],
                            datasets: [{
                                label: (firstPlayerData.playerName || 'N/A') + ' (' + (firstPlayerData.year || 'N/A') + ')',
                                data: radarDataValues,
                                fill: true,
                                backgroundColor: 'rgba(79, 70, 229, 0.25)',
                                borderColor: 'rgba(79, 70, 229, 0.8)',
                                pointBackgroundColor: 'rgba(79, 70, 229, 1)',
                                pointBorderColor: '#fff',
                                pointHoverBackgroundColor: '#fff',
                                pointHoverBorderColor: 'rgba(79, 70, 229, 1)',
                                borderWidth: 1.5,
                                pointRadius: 3,
                                pointHoverRadius: 5
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'bottom',
                                    align: 'center',
                                    labels: {
                                        usePointStyle: true,
                                        boxWidth: 10,
                                        padding: 15,
                                        font: {
                                            size: 11
                                        }
                                    }
                                },
                                tooltip: {
                                    backgroundColor: '#fff',
                                    titleColor: '#1e293b',
                                    bodyColor: '#475569',
                                    borderColor: '#e2e8f0',
                                    borderWidth: 1,
                                    padding: 10,
                                    cornerRadius: 6,
                                    usePointStyle: true
                                }
                            },
                            scales: {
                                r: {
                                    beginAtZero: true,
                                    angleLines: {
                                        color: '#e2e8f0'
                                    },
                                    grid: {
                                        color: '#f1f5f9'
                                    },
                                    pointLabels: {
                                        font: {
                                            size: 11,
                                            weight: '500'
                                        },
                                        color: '#475569'
                                    },
                                    ticks: {
                                        backdropColor: 'rgba(255,255,255, 0.85)',
                                        color: '#64748b',
                                        font: {
                                            size: 9
                                        },
                                        stepSize: determineRadarStepSizeHelper(firstPlayerData),
                                        maxTicksLimit: 6
                                    }
                                }
                            },
                            elements: {
                                line: {
                                    borderWidth: 2
                                }
                            }
                        }
                    });
                } else {
                    const radarChartContainer = document.getElementById('radarChartPlayer')?.parentNode;
                    if (radarChartContainer) radarChartContainer.innerHTML = '<div class="flex flex-col items-center justify-center h-full text-slate-500"><i class="fas fa-compass-drafting fa-2x mb-3 text-slate-300"></i><p class="text-sm">Radar Chart placeholder: No data or canvas not found.</p></div>';
                }
            } else {
                const barChartContainer = document.getElementById('barChartPlayers')?.parentNode;
                if (barChartContainer) barChartContainer.innerHTML = '<div class="flex flex-col items-center justify-center h-full text-slate-500"><i class="fas fa-chart-bar fa-2x mb-3 text-slate-300"></i><p class="text-sm">No data available for charts on this page.</p></div>';

                const radarChartContainer = document.getElementById('radarChartPlayer')?.parentNode;
                if (radarChartContainer) radarChartContainer.innerHTML = '<div class="flex flex-col items-center justify-center h-full text-slate-500"><i class="fas fa-compass-drafting fa-2x mb-3 text-slate-300"></i><p class="text-sm">No data available for charts on this page.</p></div>';
            }

            function determineRadarStepSizeHelper(playerData) {
                if (!playerData) return 5;
                const stats = [
                    playerData.points || 0, playerData.assists || 0, playerData.rebounds || 0,
                    playerData.steals || 0, playerData.blocks || 0, playerData.turnovers || 0
                ];
                const maxStat = Math.max(...stats.filter(s => typeof s === 'number' && !isNaN(s)));
                if (maxStat === 0) return 1;
                if (maxStat <= 5) return 1;
                if (maxStat <= 10) return 2;
                if (maxStat <= 20) return 4;
                if (maxStat <= 30) return 5;
                if (maxStat <= 50) return 10;
                if (maxStat <= 100) return 20;
                if (maxStat <= 200) return 40;
                if (maxStat <= 500) return 100;
                return Math.max(1, Math.ceil(maxStat / 50)); // Adjusted denominator for very large values
            }
        });
    </script>
</body>

</html>