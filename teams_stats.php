<?php
require_once 'db.php';
include 'header.php';

// --- MODIFIKASI #1: FILTER BERDASARKAN NAMA TIM, BUKAN ID ---
$filterTeamSeasons = isset($_GET['seasons']) && is_array($_GET['seasons']) ? array_map('intval', $_GET['seasons']) : [];
$filterTeamNames = isset($_GET['teams']) && is_array($_GET['teams']) ? array_map(fn($name) => trim(filter_var($name, FILTER_SANITIZE_STRING)), $_GET['teams']) : [];
$filterTeamNames = array_filter($filterTeamNames); // Hapus string kosong
sort($filterTeamSeasons);

// --- PENGAMBILAN DATA UNIK UNTUK DROPDOWN (Musim & Tim) ---
$seasonsPipeline = [['$unwind' => '$teams'], ['$unwind' => '$teams.team_season_details'], ['$match' => ['teams.team_season_details.year' => ['$ne' => null, '$exists' => true]]], ['$group' => ['_id' => '$teams.team_season_details.year']], ['$sort' => ['_id' => -1]]];
$seasonsResult = $coaches_collection->aggregate($seasonsPipeline)->toArray();
$availableSeasons = array_map(fn($s) => $s['_id'], $seasonsResult);
$max_start_year_allowed = 2023; // Sesuaikan jika perlu
$availableSeasons = array_filter($availableSeasons, fn($year) => $year <= $max_start_year_allowed);
rsort($availableSeasons);

// --- BANGUN PIPELINE AGREGRASI UTAMA ---
$mainPipeline = [];
$mainPipeline[] = ['$unwind' => '$teams'];
$mainPipeline[] = ['$match' => ['teams.team_season_details' => ['$ne' => null, '$exists' => true]]];
$mainPipeline[] = ['$replaceRoot' => ['newRoot' => '$teams.team_season_details']];

$matchStage = [];
if (!empty($filterTeamSeasons)) {
    $matchStage['year'] = ['$in' => $filterTeamSeasons];
}

// --- MODIFIKASI #2: QUERY DENGAN NAMA TIM (REGEX) ---
if (!empty($filterTeamNames)) {
    $orConditions = [];
    foreach ($filterTeamNames as $name) {
        $orConditions[] = ['name' => new MongoDB\BSON\Regex(preg_quote($name, '/'), 'i')];
    }
    $matchStage['$or'] = $orConditions;
}

if (!empty($matchStage)) {
    $mainPipeline[] = ['$match' => $matchStage];
}

// --- MODIFIKASI #3: LOGIKA PAGINASI ---
// Jalankan pipeline sekali untuk menghitung total entri
$countPipeline = array_merge($mainPipeline, [['$count' => 'total']]);
$countResult = $coaches_collection->aggregate($countPipeline)->toArray();
$totalTeamEntries = $countResult[0]['total'] ?? 0;

$validLimits = [10, 20, 50, 100];
$itemsPerPage = isset($_GET['limit']) && in_array((int)$_GET['limit'], $validLimits) ? (int)$_GET['limit'] : 20;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$totalPages = $totalTeamEntries > 0 ? ceil($totalTeamEntries / $itemsPerPage) : 1;
$currentPage = max(1, min($currentPage, $totalPages > 0 ? $totalPages : 1));
$offset = ($currentPage - 1) * $itemsPerPage;

// Tambahkan skip dan limit ke pipeline utama untuk mengambil data halaman saat ini
$mainPipeline[] = ['$sort' => ['year' => -1, 'won' => -1, 'lost' => 1]];
$mainPipeline[] = ['$skip' => $offset];
$mainPipeline[] = ['$limit' => $itemsPerPage];
$teamsData = $coaches_collection->aggregate($mainPipeline)->toArray();


function calculateWinPercentage($won, $lost, $games = null)
{
    $won = (int)($won ?? 0);
    $lost = (int)($lost ?? 0);
    $games = (int)($games ?? 0);
    $totalGamesPlayed = ($games > 0) ? $games : ($won + $lost);
    return $totalGamesPlayed > 0 ? round(($won / $totalGamesPlayed) * 100, 1) : 0;
}

// Persiapan data untuk Chart (tidak berubah)
$performanceTrendData = [];
if (!empty($filterTeamNames) && count($filterTeamSeasons) > 1) {
    // Untuk chart, kita perlu semua data, bukan hanya yang dipaginasi. Kita jalankan query lagi tanpa skip/limit.
    $chartPipeline = $mainPipeline;
    array_splice($chartPipeline, -2, 2); // Hapus $skip dan $limit
    $allFilteredTeams = $coaches_collection->aggregate($chartPipeline)->toArray();

    foreach ($allFilteredTeams as $team) {
        $teamName = $team['name'] ?? $team['tmID'];
        if (!isset($performanceTrendData[$teamName])) {
            $performanceTrendData[$teamName] = [];
        }
        $performanceTrendData[$teamName][] = ['year' => (int)$team['year'], 'win_pct' => calculateWinPercentage($team['won'] ?? 0, $team['lost'] ?? 0, $team['games'] ?? 0)];
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Performance Dashboard - NBA Universe</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Teko:wght@500;600;700&family=Rajdhani:wght@600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #0A0A14;
            color: #E0E0E0;
        }

        .font-teko {
            font-family: 'Teko', sans-serif;
        }

        .font-rajdhani {
            font-family: 'Rajdhani', sans-serif;
        }

        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #111827;
        }

        ::-webkit-scrollbar-thumb {
            background: #374151;
            border-radius: 10px;
        }

        .content-container {
            background-color: rgba(23, 23, 38, 0.7);
            border: 1px solid rgba(55, 65, 81, 0.4);
            border-radius: 0.75rem;
            padding: 1.5rem;
            backdrop-filter: blur(8px);
        }

        .btn-primary {
            background-color: #1D4ED8;
            color: white;
            transition: all 0.2s;
            font-weight: 600;
            padding: 0.625rem 1.25rem;
            border-radius: 0.375rem;
        }

        .btn-primary:hover {
            background-color: #2563EB;
            transform: translateY(-1px);
        }

        .filter-input-base {
            background-color: rgba(17, 24, 39, 0.8);
            border: 1px solid #374151;
            color: #E0E0E0;
            border-radius: 0.375rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .filter-input-base:focus-within,
        .filter-input-base:focus {
            border-color: #3B82F6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
            outline: none;
        }

        .team-tags-container {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.5rem;
            min-height: 42px;
            cursor: text;
        }

        .team-tag {
            display: inline-flex;
            align-items: center;
            background-color: #2563EB;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .remove-tag-btn {
            margin-left: 0.5rem;
            background: none;
            border: none;
            color: #bfdbfe;
            cursor: pointer;
            opacity: 0.7;
        }

        .remove-tag-btn:hover {
            opacity: 1;
        }

        #newTeamName {
            flex-grow: 1;
            background: transparent;
            border: none;
            outline: none;
            box-shadow: none;
            padding: 0.25rem;
            min-width: 120px;
        }

        .pagination a {
            padding: 0.5rem 0.875rem;
            border: 1px solid #374151;
            background-color: rgba(17, 24, 39, 0.5);
            color: #D1D5DB;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.15s;
            border-radius: 0.375rem;
        }

        .pagination a:hover {
            background-color: #374151;
        }

        .pagination a.active {
            background-color: #1D4ED8;
            color: white;
            border-color: #1D4ED8;
            font-weight: 600;
        }

        .pagination a.disabled {
            color: #6b7280;
            cursor: not-allowed;
        }

        .badge {
            display: inline-block;
            padding: 0.125rem 0.625rem;
            font-size: 0.7rem;
            font-weight: 600;
            line-height: 1.2;
            border-radius: 9999px;
        }

        .badge-gray {
            background-color: #374151;
            color: #D1D5DB;
        }

        .badge-yellow {
            background-color: #CA8A04;
            color: #FEF9C3;
        }

        .badge-green {
            background-color: #166534;
            color: #DCFCE7;
        }

        .badge-blue {
            background-color: #1E40AF;
            color: #DBEAFE;
        }

        .badge-red {
            background-color: #991B1B;
            color: #FEE2E2;
        }
    </style>
</head>

<body class="antialiased">
    <div class="max-w-screen-2xl mx-auto p-4 md:p-6 lg:p-8">
        <header class="mb-8 text-center md:text-left">
            <h1 class="text-4xl md:text-5xl font-bold text-gray-100 font-teko tracking-wider uppercase">Team Performance Dashboard</h1>
            <p class="text-md text-gray-400 mt-1">Analyze team statistics across different seasons.</p>
        </header>

        <div class="content-container mb-8 sticky top-4 z-20 !p-4">
            <form method="GET" id="teamFilterForm" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <div>
                    <label for="season-btn" class="block text-sm font-medium text-gray-300 mb-1.5">Select Season(s)</label>
                    <div class="relative" id="season-dropdown-container">
                        <button type="button" id="season-btn" class="filter-input-base w-full text-sm py-2.5 px-3 text-left flex justify-between items-center">
                            <span class="truncate">Select seasons...</span>
                            <i class="fas fa-chevron-down fa-xs text-gray-400 transition-transform duration-200"></i>
                        </button>
                        <div id="season-checklist" class="absolute hidden w-full mt-1 bg-gray-800 border border-gray-700 rounded-md shadow-lg z-30 max-h-60 overflow-y-auto">
                            <?php foreach ($availableSeasons as $season): ?>
                                <label class="flex items-center w-full px-3 py-2 text-sm text-gray-200 hover:bg-gray-700 cursor-pointer">
                                    <input type="checkbox" name="seasons[]" value="<?= htmlspecialchars($season) ?>" class="h-4 w-4 rounded border-gray-500 bg-gray-700 text-blue-500 focus:ring-blue-500/50 mr-3 season-checkbox" <?= in_array($season, $filterTeamSeasons) ? 'checked' : '' ?>>
                                    <?= htmlspecialchars($season) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div>
                    <label for="newTeamName" class="block text-sm font-medium text-gray-300 mb-1.5">Select Team(s)</label>
                    <div id="team-tags-input-container" class="filter-input-base team-tags-container">
                        <div id="hidden-team-inputs"></div>
                        <input type="text" id="newTeamName" class="text-sm" placeholder="Type team name & press Enter...">
                    </div>
                </div>
                <button type="submit" class="btn-primary w-full h-[42px] flex items-center justify-center uppercase tracking-wider text-sm">
                    <i class="fas fa-filter mr-2 fa-sm"></i> Apply
                </button>
            </form>
        </div>

        <?php
        $allTeamsForKPI = $coaches_collection->aggregate(array_slice($mainPipeline, 0, count($mainPipeline) - 2))->toArray(); // Ambil semua data sesuai filter sebelum paginasi
        $totalGames = 0;
        $totalWins = 0;
        $bestPerf = ['name' => '-', 'win_pct' => 0, 'year' => '-'];
        foreach ($allTeamsForKPI as $team) {
            $wins = (int)($team['won'] ?? 0);
            $losses = (int)($team['lost'] ?? 0);
            $totalWins += $wins;
            $totalGames += $wins + $losses;
            $winPct = calculateWinPercentage($wins, $losses);
            if ($winPct > $bestPerf['win_pct']) {
                $bestPerf = ['name' => $team['name'] ?? $team['tmID'], 'win_pct' => $winPct, 'year' => $team['year'] ?? '-'];
            }
        }
        $aggWinPct = $totalGames > 0 ? round(($totalWins / $totalGames) * 100, 1) : 0;
        $summaryCards = [
            ['label' => 'Total Team Entries', 'value' => number_format($totalTeamEntries), 'icon' => 'fa-solid fa-users', 'color' => 'text-blue-300', 'bg' => 'bg-blue-900/50'],
            ['label' => 'Aggregate Win %', 'value' => $aggWinPct . '%', 'icon' => 'fa-solid fa-percent', 'color' => 'text-green-300', 'bg' => 'bg-green-900/50'],
            ['label' => 'Peak Performance', 'value' => htmlspecialchars($bestPerf['name']), 'sub_value' => $bestPerf['win_pct'] . '% in ' . $bestPerf['year'], 'icon' => 'fa-solid fa-trophy', 'color' => 'text-amber-300', 'bg' => 'bg-amber-900/50'],
            ['label' => 'Aggregate Games', 'value' => number_format($totalGames), 'icon' => 'fa-solid fa-basketball', 'color' => 'text-purple-300', 'bg' => 'bg-purple-900/50'],
        ];
        ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
            <?php foreach ($summaryCards as $card): ?>
                <div class="content-container !p-4 flex items-start space-x-4">
                    <div class="flex-shrink-0 w-12 h-12 rounded-full flex items-center justify-center <?= htmlspecialchars($card['bg']) ?>">
                        <i class="<?= htmlspecialchars($card['icon']) ?> fa-fw text-xl <?= htmlspecialchars($card['color']) ?>"></i>
                    </div>
                    <div class="flex-grow">
                        <p class="text-xs text-gray-400 font-medium uppercase tracking-wider"><?= htmlspecialchars($card['label']) ?></p>
                        <p class="text-2xl font-bold <?= htmlspecialchars($card['color']) ?> mt-0.5 font-teko tracking-wider"><?= $card['value'] ?></p>
                        <?php if (isset($card['sub_value'])): ?><p class="text-xs text-gray-400"><?= $card['sub_value'] ?></p><?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="content-container !p-0 overflow-hidden mb-8">
            <div class="px-5 py-4 border-b border-gray-700 flex flex-col sm:flex-row justify-between items-center gap-y-3">
                <h2 class="text-lg font-semibold text-gray-200 font-rajdhani uppercase">Team Performance Details</h2>
                <div class="flex items-center gap-x-4">
                    <?php if ($totalTeamEntries > 0): $startEntry = $offset + 1;
                        $endEntry = min($offset + $itemsPerPage, $totalTeamEntries); ?>
                        <span class="text-xs font-medium text-gray-400">Showing <?= number_format($startEntry) ?>-<?= number_format($endEntry) ?> of <?= number_format($totalTeamEntries) ?> entries</span>
                    <?php endif; ?>
                    <div class="flex items-center space-x-2">
                        <label for="itemsPerPageSelect" class="text-xs font-medium text-gray-400">Show:</label>
                        <select id="itemsPerPageSelect" name="limit" class="filter-input-base text-xs py-1 pl-2 pr-7">
                            <?php foreach ($validLimits as $limitOption): ?>
                                <option value="<?= $limitOption ?>" <?= ($itemsPerPage == $limitOption) ? 'selected' : '' ?>><?= $limitOption ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full table-fixed text-sm">
                    <thead class="sticky top-0 bg-gray-900/70 backdrop-blur-sm z-10">
                        <tr class="text-left text-xs text-gray-400 uppercase tracking-wider">
                            <th class="px-4 py-3 font-semibold w-[8%]">Season</th>
                            <th class="px-4 py-3 font-semibold w-[22%]">Team</th>
                            <th class="px-4 py-3 font-semibold w-[6%] text-center">W</th>
                            <th class="px-4 py-3 font-semibold w-[6%] text-center">L</th>
                            <th class="px-4 py-3 font-semibold w-[8%] text-center">Win %</th>
                            <th class="px-4 py-3 font-semibold w-[10%] text-center">Home (W-L)</th>
                            <th class="px-4 py-3 font-semibold w-[10%] text-center">Away (W-L)</th>
                            <th class="px-4 py-3 font-semibold w-[8%] text-center">Rank</th>
                            <th class="px-4 py-3 font-semibold w-[10%] text-center">Playoff</th>
                            <th class="px-4 py-3 font-semibold w-[12%] text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                        <?php if (count($teamsData) > 0): ?>
                            <?php foreach ($teamsData as $team): $playoffStatus = $team['playoff'] ?? 'NC';
                                $playoffBadge = match (substr($playoffStatus, 0, 1)) {
                                    'W' => 'badge-green',
                                    'C' => 'badge-yellow',
                                    default => 'badge-gray',
                                }; ?>
                                <tr class="hover:bg-gray-800/60 transition-colors duration-100 group">
                                    <td class="px-4 py-2.5 text-gray-300 whitespace-nowrap"><?= htmlspecialchars($team['year'] ?? '-') ?> </td>
                                    <td class="px-4 py-2.5 font-medium text-gray-200 whitespace-nowrap truncate group-hover:text-blue-400"><?= htmlspecialchars($team['name'] ?? $team['tmID'] ?? 'N/A') ?></td>
                                    <td class="px-4 py-2.5 text-green-400 font-semibold text-center whitespace-nowrap"><?= htmlspecialchars($team['won'] ?? '0') ?></td>
                                    <td class="px-4 py-2.5 text-red-400 font-semibold text-center whitespace-nowrap"><?= htmlspecialchars($team['lost'] ?? '0') ?></td>
                                    <td class="px-4 py-2.5 text-blue-400 font-bold text-center whitespace-nowrap"><?= calculateWinPercentage($team['won'] ?? 0, $team['lost'] ?? 0) ?>%</td>
                                    <td class="px-4 py-2.5 text-gray-300 text-center whitespace-nowrap"><?= htmlspecialchars($team['homeWon'] ?? '0') ?>-<?= htmlspecialchars($team['homeLost'] ?? '0') ?></td>
                                    <td class="px-4 py-2.5 text-gray-300 text-center whitespace-nowrap"><?= htmlspecialchars($team['awayWon'] ?? '0') ?>-<?= htmlspecialchars($team['awayLost'] ?? '0') ?></td>
                                    <td class="px-4 py-2.5 text-gray-300 text-center whitespace-nowrap"><?= htmlspecialchars($team['confRank'] ?? '0') ?> <?php if (($team['confID'] ?? '') === 'WC') echo '<span class="badge badge-blue">WC</span>';
                                                                                                                                                            elseif (($team['confID'] ?? '') === 'EC') echo '<span class="badge badge-red">EC</span>'; ?></td>
                                    <td class="px-4 py-2.5 text-center whitespace-nowrap"><span class="badge <?= $playoffBadge ?>"><?= htmlspecialchars($playoffStatus) ?></span></td>
                                    <td class="px-4 py-2.5 text-center whitespace-nowrap"><a href="#" class="text-blue-400 hover:text-blue-300 text-xs font-semibold hover:underline">Detail <i class="fas fa-arrow-right fa-xs ml-0.5"></i></a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="text-center p-10 text-gray-500">
                                    <div class="flex flex-col items-center"><i class="fas fa-ghost fa-3x text-gray-700 mb-4"></i>
                                        <p class="font-semibold text-gray-300">No Team Data Found</p>
                                        <p class="text-sm">Try adjusting your filters.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="pagination px-5 py-4 border-t border-gray-700 flex flex-col sm:flex-row justify-between items-center space-y-3 sm:space-y-0">
                    <div class="text-xs text-gray-400">Page <?= $currentPage ?> of <?= $totalPages ?></div>
                    <div class="flex items-center space-x-1.5"><?php $queryParams = $_GET;
                                                                unset($queryParams['page']);
                                                                $baseUrl = '?' . http_build_query($queryParams) . '&'; ?><a href="<?= ($currentPage > 1) ? $baseUrl . 'page=' . ($currentPage - 1) : '#' ?>" class="<?= ($currentPage <= 1) ? 'disabled' : '' ?>"><i class="fas fa-chevron-left fa-xs mr-1"></i> Prev</a><?php $numPageLinks = 5;
                                                                                                                                                                                                                                                                                                                            $startPage = max(1, $currentPage - floor($numPageLinks / 2));
                                                                                                                                                                                                                                                                                                                            $endPage = min($totalPages, $startPage + $numPageLinks - 1);
                                                                                                                                                                                                                                                                                                                            if ($endPage - $startPage + 1 < $numPageLinks) {
                                                                                                                                                                                                                                                                                                                                $startPage = max(1, $endPage - $numPageLinks + 1);
                                                                                                                                                                                                                                                                                                                            }
                                                                                                                                                                                                                                                                                                                            if ($startPage > 1) {
                                                                                                                                                                                                                                                                                                                                echo '<a href="' . $baseUrl . 'page=1">1</a>';
                                                                                                                                                                                                                                                                                                                                if ($startPage > 2) echo '<span class="px-2 py-1.5 text-gray-500 text-xs">...</span>';
                                                                                                                                                                                                                                                                                                                            }
                                                                                                                                                                                                                                                                                                                            for ($i = $startPage; $i <= $endPage; $i++): ?><a href="<?= $baseUrl . 'page=' . $i ?>" class="<?= ($i == $currentPage) ? 'active' : '' ?>"><?= $i ?></a><?php endfor;
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            if ($endPage < $totalPages) {
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                if ($endPage < $totalPages - 1) echo '<span class="px-2 py-1.5 text-gray-500 text-xs">...</span>';
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                echo '<a href="' . $baseUrl . 'page=' . $totalPages . '">' . $totalPages . '</a>';
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            } ?><a href="<?= ($currentPage < $totalPages) ? $baseUrl . 'page=' . ($currentPage + 1) : '#' ?>" class="<?= ($currentPage >= $totalPages) ? 'disabled' : '' ?>">Next <i class="fas fa-chevron-right fa-xs ml-1"></i></a></div>
                </div>
            <?php endif; ?>
        </div>

        <!-- BAGIAN CHART DENGAN PERBAIKAN FINAL -->
        <section class="mt-10 mb-10">
            <div class="px-1 py-4 mb-3">
                <h2 class="text-2xl font-semibold text-gray-200 text-center font-teko uppercase tracking-wider">Visual Insights</h2>
            </div>
            <div class="grid grid-cols-1 gap-6">
                <?php if (!empty($performanceTrendData)): ?>
                    <!-- Grafik Garis (Multi-musim) -->
                    <div class="content-container">
                        <h3 class="text-base font-semibold mb-4 text-gray-300 font-rajdhani">Team Performance Trend (Win %)</h3>
                        <div class="relative h-[450px]">
                            <canvas id="teamPerformanceTrendChart"></canvas>
                        </div>
                    </div>
                <?php elseif (!empty($teamsData) && count($filterTeamSeasons) == 1): ?>
                    <!-- Grafik Batang (Satu musim) -->
                    <div class="content-container">
                        <h3 class="text-base font-semibold mb-4 text-gray-300 font-rajdhani">Top Team Win % (Season <?= htmlspecialchars($filterTeamSeasons[0]) ?>)</h3>
                        <div class="relative h-[450px]">
                            <canvas id="teamWinPercentageChart"></canvas>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Pesan Panduan -->
                    <div class="text-center my-10 p-6 bg-blue-900/20 border border-blue-500/30 rounded-lg"><i class="fas fa-chart-line fa-2x text-blue-400 mb-3"></i>
                        <p class="text-base font-semibold text-blue-200">View Performance Charts</p>
                        <p class="text-sm text-blue-300/80 mt-1">• Select a <b>single season</b> to see top performing teams.<br>• Select <b>multiple seasons and at least one team</b> to compare performance trends.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <footer class="text-center mt-12 py-6 border-t border-gray-800">
            <p class="text-xs text-gray-500">© <?= date("Y") ?> NBA Universe Dashboard. All Rights Reserved.</p>
        </footer>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // ... (Kode JavaScript lengkap dari respons sebelumnya) ...
            // 1. Dropdown Checklist Musim (dengan teks dinamis)
            const seasonBtn = document.getElementById('season-btn');
            if (seasonBtn) {
                const seasonChecklist = document.getElementById('season-checklist');
                const seasonBtnSpan = seasonBtn.querySelector('span');
                const seasonCheckboxes = document.querySelectorAll('.season-checkbox');

                function updateSeasonButtonText() {
                    const checked = Array.from(seasonCheckboxes).filter(cb => cb.checked).map(cb => cb.value);
                    if (checked.length === 0) {
                        seasonBtnSpan.textContent = 'Select seasons...';
                    } else if (checked.length <= 3) {
                        seasonBtnSpan.textContent = checked.sort((a, b) => b - a).join(', ');
                    } else {
                        seasonBtnSpan.textContent = `${checked.length} Seasons Selected`;
                    }
                }
                updateSeasonButtonText();
                seasonBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    seasonChecklist.classList.toggle('hidden');
                });
                document.addEventListener('click', () => seasonChecklist.classList.add('hidden'));
                seasonChecklist.addEventListener('click', (e) => e.stopPropagation());
                seasonCheckboxes.forEach(cb => cb.addEventListener('change', updateSeasonButtonText));
            }

            // 2. Input Tag Tim
            const initialTeams = <?= json_encode($filterTeamNames); ?>;
            const tagsContainer = document.getElementById('team-tags-input-container');
            if (tagsContainer) {
                const newTeamInput = document.getElementById('newTeamName');
                const hiddenInputsContainer = document.getElementById('hidden-team-inputs');
                let currentTeams = initialTeams;

                function renderTeamTags() {
                    tagsContainer.querySelectorAll('.team-tag').forEach(tag => tag.remove());
                    hiddenInputsContainer.innerHTML = '';
                    currentTeams.forEach((name, index) => {
                        const tag = document.createElement('div');
                        tag.className = 'team-tag';
                        tag.innerHTML = `<span>${name}</span><button type="button" class="remove-tag-btn" data-index="${index}">×</button>`;
                        tagsContainer.insertBefore(tag, newTeamInput);
                        const hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = 'teams[]';
                        hiddenInput.value = name;
                        hiddenInputsContainer.appendChild(hiddenInput);
                    });
                }
                newTeamInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        const newName = this.value.trim();
                        if (newName && !currentTeams.some(t => t.toLowerCase() === newName.toLowerCase())) {
                            currentTeams.push(newName);
                            this.value = '';
                            renderTeamTags();
                        }
                    }
                });
                tagsContainer.addEventListener('click', function(e) {
                    if (e.target.classList.contains('remove-tag-btn')) {
                        currentTeams.splice(parseInt(e.target.dataset.index, 10), 1);
                        renderTeamTags();
                    } else {
                        newTeamInput.focus();
                    }
                });
                renderTeamTags();
            }

            // 3. Dropdown "Items per Page"
            const itemsPerPageSelect = document.getElementById('itemsPerPageSelect');
            if (itemsPerPageSelect) {
                itemsPerPageSelect.addEventListener('change', function() {
                    const url = new URL(window.location);
                    url.searchParams.set('limit', this.value);
                    url.searchParams.set('page', '1'); // Selalu kembali ke halaman 1 saat mengubah limit
                    window.location.href = url.toString();
                });
            }

            // --- KODE LAMA UNTUK CHART (TIDAK BERUBAH) ---
            const teamWinPctCanvas = document.getElementById('teamWinPercentageChart');
            if (teamWinPctCanvas) {
                // Kita perlu data dari filter, bukan hanya data paginasi.
                const chartFilterPipeline = <?= json_encode(array_slice($mainPipeline, 0, count($mainPipeline) - 2)); ?>;
                const rawTeamsDataForChart = <?= json_encode($allTeamsForKPI); ?>; // Gunakan data yg sudah di-fetch
                const sortedTeams = [...rawTeamsDataForChart].map(t => ({
                    ...t,
                    win_pct: (t.won + t.lost > 0 ? parseFloat(((t.won / (t.won + t.lost)) * 100).toFixed(1)) : 0)
                })).sort((a, b) => b.win_pct - a.win_pct).slice(0, 20);
                new Chart(teamWinPctCanvas.getContext("2d"), {
                    type: "bar",
                    data: {
                        labels: sortedTeams.map(t => t.name || t.tmID),
                        datasets: [{
                            label: "Win %",
                            data: sortedTeams.map(t => t.win_pct),
                            backgroundColor: sortedTeams.map(t => t.win_pct >= 50 ? "rgba(34, 197, 94, 0.7)" : "rgba(239, 68, 68, 0.7)"),
                            borderWidth: 1,
                            borderRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: "y",
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            x: {
                                max: 100,
                                ticks: {
                                    callback: val => val + "%"
                                }
                            }
                        }
                    }
                });
            }
            const performanceTrendCanvas = document.getElementById('teamPerformanceTrendChart');
            if (performanceTrendCanvas) {
                const trendData = <?= json_encode($performanceTrendData); ?>;
                const trendSeasons = <?= json_encode($filterTeamSeasons); ?>;
                const chartColors = ['#3B82F6', '#22C55E', '#F97316', '#A855F7', '#EF4444', '#FBBF24', '#14B8A6', '#EC4899'];
                const datasets = Object.keys(trendData).map((teamName, index) => {
                    const dataPoints = trendSeasons.map(season => {
                        const record = trendData[teamName].find(r => r.year === season);
                        return record ? record.win_pct : null;
                    });
                    return {
                        label: teamName,
                        data: dataPoints,
                        borderColor: chartColors[index % chartColors.length],
                        fill: false,
                        tension: 0.1,
                        pointRadius: 4,
                        pointHoverRadius: 7
                    };
                });
                new Chart(performanceTrendCanvas.getContext("2d"), {
                    type: 'line',
                    data: {
                        labels: trendSeasons,
                        datasets: datasets
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: {
                                    color: '#E0E0E0'
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 100,
                                ticks: {
                                    color: "#9CA3AF",
                                    callback: val => val + "%"
                                }
                            },
                            x: {
                                ticks: {
                                    color: "#9CA3AF"
                                }
                            }
                        }
                    }
                });
            }
        });
    </script>
</body>

</html>