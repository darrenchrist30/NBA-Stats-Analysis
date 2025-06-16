<?php
require_once 'db.php';
include 'header.php';

// --- MODIFIKASI #1: LOGIKA PHP BARU UNTUK FILTER TIM & MUSIM ---
$filterTeamSeasons = isset($_GET['team_seasons']) && is_array($_GET['team_seasons']) ? array_map('intval', $_GET['team_seasons']) : [];
$filterTeamIDs = isset($_GET['teams']) && is_array($_GET['teams']) ? array_map('strval', $_GET['teams']) : [];

// --- PENGAMBILAN DATA UNIK UNTUK DROPDOWN (Musim & Tim) ---
$seasonsPipeline = [['$unwind' => '$teams'], ['$unwind' => '$teams.team_season_details'], ['$match' => ['teams.team_season_details.year' => ['$ne' => null, '$exists' => true]]], ['$group' => ['_id' => '$teams.team_season_details.year']], ['$sort' => ['_id' => -1]]];
$seasonsResult = $coaches_collection->aggregate($seasonsPipeline)->toArray();
$availableSeasons = array_map(fn($s) => $s['_id'], $seasonsResult);
$max_start_year_allowed = 2010;
$availableSeasons = array_filter($availableSeasons, fn($year) => $year <= $max_start_year_allowed);
rsort($availableSeasons);

// Ambil semua tim yang unik
$teamsPipeline = [
    ['$unwind' => '$teams'],
    ['$unwind' => '$teams.team_season_details'],
    ['$group' => ['_id' => ['tmID' => '$teams.team_season_details.tmID', 'name' => '$teams.team_season_details.name']]],
    ['$sort' => ['_id.name' => 1]]
];
$teamsResult = $coaches_collection->aggregate($teamsPipeline)->toArray();
$availableTeams = array_map(fn($t) => ['id' => $t['_id']['tmID'], 'name' => $t['_id']['name']], $teamsResult);


// --- BANGUN PIPELINE AGREGRASI UTAMA ---
$mainPipeline = [];
$mainPipeline[] = ['$unwind' => '$teams'];
$mainPipeline[] = ['$match' => ['teams.team_season_details' => ['$ne' => null, '$exists' => true]]];
$mainPipeline[] = ['$replaceRoot' => ['newRoot' => '$teams.team_season_details']];

$matchStage = [];
if (!empty($filterTeamSeasons)) {
    $validFilterSeasons = array_filter($filterTeamSeasons, fn($year) => $year <= $max_start_year_allowed);
    if (!empty($validFilterSeasons)) {
        $matchStage['year'] = ['$in' => $validFilterSeasons];
    }
}

// --- MODIFIKASI #2: TAMBAHKAN FILTER TIM KE QUERY ---
if (!empty($filterTeamIDs)) {
    $matchStage['tmID'] = ['$in' => $filterTeamIDs];
}

if (!empty($matchStage)) {
    $mainPipeline[] = ['$match' => $matchStage];
}


$mainPipeline[] = ['$group' => ['_id' => ['year' => '$year', 'tmID' => '$tmID'], 'doc' => ['$first' => '$$ROOT']]];
$mainPipeline[] = ['$replaceRoot' => ['newRoot' => '$doc']];
$mainPipeline[] = ['$sort' => ['year' => -1, 'won' => -1, 'lost' => 1]];

$teamsData = $coaches_collection->aggregate($mainPipeline)->toArray();

function calculateWinPercentage($won, $lost, $games = null)
{ /* ... fungsi tidak berubah ... */
    $won = (int)($won ?? 0);
    $lost = (int)($lost ?? 0);
    $games = (int)($games ?? 0);
    $totalGamesPlayed = ($games > 0) ? $games : ($won + $lost);
    return $totalGamesPlayed > 0 ? round(($won / $totalGamesPlayed) * 100, 1) : 0;
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
        /* ... CSS tidak berubah ... */
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
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #111827;
        }

        ::-webkit-scrollbar-thumb {
            background: #374151;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #4b5563;
        }

        .content-container {
            background-color: rgba(23, 23, 38, 0.7);
            border: 1px solid rgba(55, 65, 81, 0.4);
            border-radius: 0.75rem;
            padding: 1.5rem;
            backdrop-filter: blur(8px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }

        .btn-primary {
            background-color: #1D4ED8;
            color: white;
            transition: all 0.2s;
        }

        .btn-primary:hover {
            background-color: #1E40AF;
            transform: translateY(-2px);
        }

        .dropdown-checklist {
            position: relative;
        }

        .dropdown-btn {
            background-color: rgba(17, 24, 39, 0.8);
            border: 1px solid #374151;
            color: #E0E0E0;
            border-radius: 0.375rem;
            padding: 0.625rem 1rem;
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
        }

        .dropdown-btn:focus,
        .dropdown-btn.open {
            outline: 2px solid transparent;
            outline-offset: 2px;
            border-color: #3B82F6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
        }

        .dropdown-btn .arrow {
            transition: transform 0.2s ease-in-out;
        }

        .dropdown-btn.open .arrow {
            transform: rotate(180deg);
        }

        .dropdown-checklist-items {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            margin-top: 0.25rem;
            background-color: #1f2937;
            border: 1px solid #374151;
            border-radius: 0.375rem;
            z-index: 10;
            max-height: 200px;
            overflow-y: auto;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.06);
        }

        .dropdown-checklist-items label {
            display: flex;
            align-items: center;
            padding: 0.5rem 1rem;
            cursor: pointer;
        }

        .dropdown-checklist-items label:hover {
            background-color: #374151;
        }

        .dropdown-checklist-items input[type="checkbox"] {
            margin-right: 0.75rem;
            accent-color: #3B82F6;
            background-color: #4B5563;
            border-radius: 0.25rem;
        }

        .selected-text {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            flex-grow: 1;
            padding-right: 0.5rem;
        }

        .placeholder-text {
            color: #9CA3AF;
        }

        .badge {
            display: inline-block;
            padding: 0.125rem 0.625rem;
            font-size: 0.7rem;
            font-weight: 600;
            line-height: 1.2;
            border-radius: 9999px;
            text-transform: capitalize;
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
            <h1 class="text-3xl md:text-4xl font-bold text-gray-100 font-teko tracking-wide uppercase">Team Performance Dashboard</h1>
            <p class="text-md text-gray-400 mt-1.5">Analyze team statistics across different seasons.</p>
        </header>

        <!-- Filter Section -->
        <div class="content-container mb-8 sticky top-4 z-20">
            <!-- MODIFIKASI #3: TAMBAHKAN DROPDOWN TIM BARU KE FORM -->
            <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4 md:items-end">
                <div class="w-full">
                    <label for="team_season_btn" class="block text-sm font-medium text-gray-400 mb-1.5">Select Season(s)</label>
                    <div class="dropdown-checklist" id="season_dropdown_container">
                        <button type="button" id="season_btn" class="dropdown-btn">
                            <span class="selected-text text-sm">Select seasons...</span>
                            <i class="fas fa-chevron-down fa-xs arrow"></i>
                        </button>
                        <div class="dropdown-checklist-items hidden">
                            <?php foreach ($availableSeasons as $startYear): ?>
                                <label>
                                    <input type="checkbox" name="team_seasons[]" value="<?= $startYear ?>" ... > 
                                    Season <?= htmlspecialchars($startYear) ?> 
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="w-full">
                    <label for="team_id_btn" class="block text-sm font-medium text-gray-400 mb-1.5">Select Team(s)</label>
                    <div class="dropdown-checklist" id="team_dropdown_container">
                        <button type="button" id="team_btn" class="dropdown-btn">
                            <span class="selected-text text-sm">Select teams...</span>
                            <i class="fas fa-chevron-down fa-xs arrow"></i>
                        </button>
                        <div class="dropdown-checklist-items hidden">
                            <?php foreach ($availableTeams as $team): ?>
                                <label><input type="checkbox" name="teams[]" value="<?= htmlspecialchars($team['id']) ?>" <?= in_array($team['id'], $filterTeamIDs) ? 'checked' : '' ?>> <?= htmlspecialchars($team['name']) ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="w-full md:w-auto">
                    <button type="submit" class="btn-primary w-full h-[42px] px-6 text-sm font-semibold uppercase tracking-wider rounded-md">
                        <i class="fas fa-filter mr-2 fa-sm"></i> Apply
                    </button>
                </div>
            </form>
        </div>

        <!-- Stat Summary Cards (tidak berubah) -->
        <?php
        $totalTeamsDisplayed = count($teamsData);
        $totalGamesPlayedOverall = 0;
        $totalWinsOverall = 0;
        $bestPerformingTeam = ['name' => '-', 'win_pct' => 0, 'year_display' => '-'];
        if ($totalTeamsDisplayed > 0) {
            foreach ($teamsData as $team) {
                $wins = (int)($team['won'] ?? 0);
                $totalGamesPlayedOverall += (int)($team['games'] ?? ($wins + (int)($team['lost'] ?? 0)));
                $totalWinsOverall += $wins;
                $winPct = calculateWinPercentage($wins, $team['lost'] ?? 0, $team['games'] ?? 0);
                if ($winPct > $bestPerformingTeam['win_pct']) {
                    $bestPerformingTeam = ['name' => $team['name'] ?? $team['tmID'] ?? '-', 'win_pct' => $winPct, 'year_display' => isset($team['year']) ? (int)$team['year'] + 1 : '-'];
                }
            }
        }
        $overallAverageWinPct = $totalGamesPlayedOverall > 0 ? round(($totalWinsOverall / $totalGamesPlayedOverall) * 100, 1) : 0;
        $summaryCards = [
            ['label' => 'Total Team Entries', 'value' => $totalTeamsDisplayed, 'icon' => 'fa-solid fa-users', 'color' => 'text-blue-400', 'bg' => 'bg-blue-900/50'],
            ['label' => 'Aggregate Win %', 'value' => $overallAverageWinPct . '%', 'icon' => 'fa-solid fa-percent', 'color' => 'text-green-400', 'bg' => 'bg-green-900/50'],
            ['label' => 'Peak Performance', 'value' => htmlspecialchars($bestPerformingTeam['name']), 'sub_value' => $bestPerformingTeam['win_pct'] . '% in ' . $bestPerformingTeam['year_display'], 'icon' => 'fa-solid fa-trophy', 'color' => 'text-amber-400', 'bg' => 'bg-amber-900/50'],
            ['label' => 'Aggregate Games', 'value' => number_format($totalGamesPlayedOverall), 'icon' => 'fa-solid fa-basketball', 'color' => 'text-purple-400', 'bg' => 'bg-purple-900/50'],
        ];
        ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
            <?php foreach ($summaryCards as $card): ?>
                <div class="content-container !p-4 flex items-start space-x-4 transition-all duration-200 hover:border-blue-500/60 hover:-translate-y-1">
                    <div class="flex-shrink-0 p-3.5 rounded-full <?= htmlspecialchars($card['bg']) ?> <?= htmlspecialchars($card['color']) ?>">
                        <i class="<?= htmlspecialchars($card['icon']) ?> fa-fw text-xl"></i>
                    </div>
                    <div class="flex-grow">
                        <p class="text-xs text-gray-400 font-medium uppercase tracking-wider"><?= htmlspecialchars($card['label']) ?></p>
                        <p class="text-2xl font-semibold <?= htmlspecialchars($card['color']) ?> mt-0.5 font-teko tracking-wider"><?= $card['value'] ?></p>
                        <?php if (isset($card['sub_value'])): ?><p class="text-xs text-gray-400"><?= $card['sub_value'] ?></p><?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Data Table Section (tidak berubah) -->
        <div class="content-container !p-0 overflow-hidden mb-10">
            <div class="px-5 py-4 border-b border-gray-700 flex justify-between items-center">
                <h2 class="text-lg font-semibold text-gray-200 font-rajdhani uppercase">Team Performance Details</h2>
                <?php if ($totalTeamsDisplayed > 0): ?><span class="text-xs font-medium text-gray-400">Showing <?= $totalTeamsDisplayed ?> entries</span><?php endif; ?>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full table-fixed text-sm">
                    <thead class="sticky top-0 bg-gray-900/70 backdrop-blur-sm z-10">
                        <tr class="text-left text-xs text-gray-400 uppercase tracking-wider">
                            <th class="px-4 py-3 font-semibold w-[8%] text-center">Season</th>
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
                            <?php foreach ($teamsData as $team):
                                $winPct = calculateWinPercentage($team['won'] ?? 0, $team['lost'] ?? 0, $team['games'] ?? 0);
                                $playoffStatus = $team['playoff'] ?? '-';
                                $playoffBadge = 'badge-gray';
                                if (stripos($playoffStatus, 'won') !== false || stripos($playoffStatus, 'champion') !== false) {
                                    $playoffBadge = 'badge-green';
                                } elseif (stripos($playoffStatus, 'lost') !== false || stripos($playoffStatus, 'finals') !== false || stripos($playoffStatus, 'semifinals') !== false || stripos($playoffStatus, 'round') !== false) {
                                    $playoffBadge = 'badge-yellow';
                                }
                            ?>
                                <tr class="hover:bg-gray-800/60 transition-colors duration-100 group">
                                    <td class="px-4 py-2.5 text-gray-300 whitespace-nowrap text-center"><?= htmlspecialchars($team['year'] ?? '-') ?> </td>
                                    <td class="px-4 py-2.5 font-medium text-gray-200 whitespace-nowrap truncate group-hover:text-blue-400"><a href="team_detail.php?year=<?= htmlspecialchars($team['year'] ?? '') ?>&tmID=<?= urlencode($team['tmID'] ?? '') ?>" class="hover:underline"><?= htmlspecialchars($team['name'] ?? $team['tmID'] ?? 'N/A') ?></a></td>
                                    <td class="px-4 py-2.5 text-green-400 font-semibold text-center whitespace-nowrap"><?= htmlspecialchars($team['won'] ?? '0') ?></td>
                                    <td class="px-4 py-2.5 text-red-400 font-semibold text-center whitespace-nowrap"><?= htmlspecialchars($team['lost'] ?? '0') ?></td>
                                    <td class="px-4 py-2.5 text-blue-400 font-bold text-center whitespace-nowrap"><?= $winPct ?>%</td>
                                    <td class="px-4 py-2.5 text-gray-300 text-center whitespace-nowrap"><?= htmlspecialchars($team['homeWon'] ?? '0') ?>-<?= htmlspecialchars($team['homeLost'] ?? '0') ?></td>
                                    <td class="px-4 py-2.5 text-gray-300 text-center whitespace-nowrap"><?= htmlspecialchars($team['awayWon'] ?? '0') ?>-<?= htmlspecialchars($team['awayLost'] ?? '0') ?></td>
                                    <td class="px-4 py-2.5 text-gray-300 text-center whitespace-nowrap"><?= htmlspecialchars($team['confRank'] ?? '-') ?> <?php $confID = strtoupper($team['confID'] ?? '');
                                                                                                                                                        if ($confID === 'WC') echo '<span class="badge badge-blue">WC</span>';
                                                                                                                                                        elseif ($confID === 'EC') echo '<span class="badge badge-red">EC</span>'; ?></td>
                                    <td class="px-4 py-2.5 text-center whitespace-nowrap"><span class="badge <?= $playoffBadge ?>"><?= htmlspecialchars($playoffStatus) ?></span></td>
                                    <td class="px-4 py-2.5 text-center whitespace-nowrap"><a href="team_detail.php?year=<?= htmlspecialchars($team['year'] ?? '') ?>&tmID=<?= urlencode($team['tmID'] ?? '') ?>" class="text-blue-400 hover:text-blue-300 text-xs font-semibold hover:underline">Detail <i class="fas fa-arrow-right fa-xs ml-0.5"></i></a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="text-center p-10 text-gray-500">
                                    <div class="flex flex-col items-center"><i class="fas fa-ghost fa-3x text-gray-700 mb-4"></i>
                                        <p class="font-semibold text-gray-300 text-base">No Team Data Found</p>
                                        <p class="text-sm">Please adjust the filters.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Chart Section (tidak berubah) -->
        <section class="mt-10 mb-10">
            <div class="px-1 py-4 mb-3">
                <h2 class="text-2xl font-semibold text-gray-200 text-center font-teko uppercase tracking-wider">Visual Insights</h2>
            </div>
            <div class="grid grid-cols-1 gap-6"><?php if (count($teamsData) > 0 && !empty($filterTeamSeasons) && count($filterTeamSeasons) == 1): ?><div class="content-container min-h-[400px] md:min-h-[450px]">
                        <h3 class="text-base font-semibold mb-4 text-gray-300 font-rajdhani">Top 20 Team Win % (Season <?= htmlspecialchars($filterTeamSeasons[0]) ?>)</h3><canvas id="teamWinPercentageChart"></canvas>
                    </div><?php elseif (count($teamsData) > 0 && (empty($filterTeamSeasons) || count($filterTeamSeasons) > 1)): ?><div class="text-center my-10 p-4 bg-blue-900/30 border border-blue-500/30 rounded-md">
                        <p class="text-sm text-blue-300">Please select a single season from the filter to display performance charts.</p>
                    </div><?php endif; ?></div>
        </section>
        <footer class="text-center mt-12 py-6 border-t border-gray-800">
            <p class="text-xs text-gray-500">© <?= date("Y") ?> NBA Universe Dashboard. All Rights Reserved.</p>
        </footer>
    </div>

    <script>
        // --- MODIFIKASI #4: JAVASCRIPT BARU UNTUK DUA DROPDOWN CHECKLIST ---
        document.addEventListener('DOMContentLoaded', function() {
            // Fungsi helper generik untuk mengelola dropdown checklist
            function setupDropdownChecklist(containerId, placeholder) {
                const container = document.getElementById(containerId);
                if (!container) return;

                const btn = container.querySelector('.dropdown-btn');
                const checklist = container.querySelector('.dropdown-checklist-items');
                const textElement = btn.querySelector('.selected-text');

                function updateButtonText() {
                    const checkboxes = checklist.querySelectorAll('input[type="checkbox"]:checked');
                    const placeholderClass = 'placeholder-text';
                    if (checkboxes.length === 0) {
                        textElement.textContent = placeholder;
                        textElement.classList.add(placeholderClass);
                    } else if (checkboxes.length === 1) {
                        textElement.textContent = checkboxes[0].parentNode.textContent.trim();
                        textElement.classList.remove(placeholderClass);
                    } else {
                        textElement.textContent = `${checkboxes.length} items selected`;
                        textElement.classList.remove(placeholderClass);
                    }
                }

                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    // Tutup dropdown lain jika ada
                    document.querySelectorAll('.dropdown-checklist-items').forEach(item => {
                        if (item !== checklist) item.classList.add('hidden');
                    });
                    document.querySelectorAll('.dropdown-btn').forEach(b => {
                        if (b !== btn) b.classList.remove('open');
                    });

                    checklist.classList.toggle('hidden');
                    btn.classList.toggle('open');
                });

                checklist.querySelectorAll('input').forEach(cb => cb.addEventListener('change', updateButtonText));
                updateButtonText(); // Panggil saat load untuk set teks awal
            }

            // Inisialisasi kedua dropdown
            setupDropdownChecklist('season_dropdown_container', 'Select seasons...');
            setupDropdownChecklist('team_dropdown_container', 'Select teams...');

            // Event listener global untuk menutup dropdown saat klik di luar
            window.addEventListener('click', () => {
                document.querySelectorAll('.dropdown-checklist-items').forEach(item => item.classList.add('hidden'));
                document.querySelectorAll('.dropdown-btn').forEach(b => b.classList.remove('open'));
            });
            document.querySelectorAll('.dropdown-checklist').forEach(dd => dd.addEventListener('click', e => e.stopPropagation()));

            // --- Sisa JavaScript untuk Chart (tidak berubah) ---
            const rawTeamsData = <?= json_encode($teamsData); ?>;
            const filterSeasonsPHP = <?= json_encode($filterTeamSeasons); ?>;
            const teamWinPctCanvas = document.getElementById('teamWinPercentageChart');
            if (teamWinPctCanvas && rawTeamsData.length > 0 && filterSeasonsPHP.length === 1) {
                /* ... kode chart ... */
                const currentSeasonData = rawTeamsData.filter(team => team.year === filterSeasonsPHP[0]);
                const calculateWinPctJS = (w, l, g) => {
                    const total = (g > 0 ? g : (w + l));
                    return total > 0 ? parseFloat(((w / total) * 100).toFixed(1)) : 0
                };
                const sortedTeams = [...currentSeasonData].map(t => ({
                    ...t,
                    win_pct: calculateWinPctJS(t.won, t.lost, t.games)
                })).sort((a, b) => b.win_pct - a.win_pct).slice(0, 20);
                new Chart(teamWinPctCanvas.getContext("2d"), {
                    type: "bar",
                    data: {
                        labels: sortedTeams.map(t => t.name || t.tmID),
                        datasets: [{
                            label: "Win %",
                            data: sortedTeams.map(t => t.win_pct),
                            backgroundColor: sortedTeams.map(t => t.win_pct >= 50 ? "rgba(34, 197, 94, 0.7)" : "rgba(239, 68, 68, 0.7)"),
                            borderColor: sortedTeams.map(t => t.win_pct >= 50 ? "rgba(74, 222, 128, 1)" : "rgba(248, 113, 113, 1)"),
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
                            },
                            tooltip: {
                                backgroundColor: "#1F2937",
                                titleColor: "#F9FAFB",
                                bodyColor: "#D1D5DB",
                                callbacks: {
                                    label: ctx => ` Win %: ${ctx.parsed.x}%`
                                }
                            }
                        },
                        scales: {
                            y: {
                                grid: {
                                    color: "rgba(55, 65, 81, 0.3)"
                                },
                                ticks: {
                                    color: "#9CA3AF",
                                    font: {
                                        family: "'Montserrat', sans-serif"
                                    }
                                }
                            },
                            x: {
                                max: 100,
                                grid: {
                                    color: "rgba(55, 65, 81, 0.5)"
                                },
                                ticks: {
                                    color: "#9CA3AF",
                                    font: {
                                        family: "'Montserrat', sans-serif"
                                    },
                                    callback: val => val + "%"
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