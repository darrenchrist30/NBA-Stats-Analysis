    <?php
    require_once 'db.php';
    include 'header.php';

    // --- LOGIKA PHP ANDA (Bagian Awal) ---
    $filterSeasons = isset($_GET['seasons']) && is_array($_GET['seasons']) ? array_map('intval', $_GET['seasons']) : [];
    $filterPlayerNames = isset($_GET['players']) && is_array($_GET['players']) ? array_map(fn($name) => trim(filter_var($name, FILTER_SANITIZE_STRING)), $_GET['players']) : [];
    $filterPlayerNames = array_filter($filterPlayerNames);
    $filterTeam = $_GET['team'] ?? '';
    $filterPos = $_GET['pos'] ?? '';

    // --- PENGAMBILAN DATA UNIK (TIDAK BERUBAH) ---
    $seasonsPipeline = [['$unwind' => '$career_teams'], ['$match' => ['career_teams.year' => ['$ne' => null, '$exists' => true]]], ['$group' => ['_id' => '$career_teams.year']], ['$sort' => ['_id' => -1]]];
    $seasonsResult = $players_collection->aggregate($seasonsPipeline)->toArray();
    $seasons = array_map(fn($s) => $s['_id'], $seasonsResult);

    $teamsPipeline = [['$unwind' => '$career_teams'], ['$match' => ['career_teams.tmID' => ['$ne' => null, '$exists' => true]]], ['$group' => ['_id' => '$career_teams.tmID']], ['$sort' => ['_id' => 1]]];
    $teamIDsResult = $players_collection->aggregate($teamsPipeline)->toArray();
    $teamIDs = array_map(fn($t) => $t['_id'], $teamIDsResult);
    $teamDisplayNames = [];
    if (!empty($teamIDs)) {
        if (isset($teams) && $teams instanceof MongoDB\Collection) {
            $teamDetailsCursor = $teams->find(['tmID' => ['$in' => $teamIDs]], ['projection' => ['tmID' => 1, 'name' => 1, 'year' => 1]]);
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
            foreach ($teamIDs as $id) {
                if (is_string($id)) {
                    $teamDisplayNames[$id] = $id;
                }
            }
        }
        asort($teamDisplayNames);
    }
    $positions = $players_collection->distinct('pos');
    $positions = array_filter($positions, fn($value) => !is_null($value) && $value !== '');
    sort($positions);

    // --- PIPELINE AGREGRASI (TIDAK BERUBAH) ---
    $aggregationPipeline = [];
    $aggregationPipeline[] = ['$addFields' => ['fullName' => ['$concat' => ['$firstName', ' ', '$lastName']]]];
    $playerMatchStage = [];
    if ($filterPos) {
        $playerMatchStage['pos'] = $filterPos;
    }
    if (!empty($filterPlayerNames)) {
        $orConditions = [];
        foreach ($filterPlayerNames as $name) {
            $regexPattern = str_replace(' ', '.*', preg_quote($name, '/'));
            $orConditions[] = ['$or' => [['firstName' => new MongoDB\BSON\Regex($regexPattern, 'i')], ['lastName' => new MongoDB\BSON\Regex($regexPattern, 'i')], ['fullName' => new MongoDB\BSON\Regex($regexPattern, 'i')], ['useFirst' => new MongoDB\BSON\Regex($regexPattern, 'i')]]];
        }
        $playerMatchStage['$or'] = $orConditions;
    }
    if (!empty($playerMatchStage)) {
        $aggregationPipeline[] = ['$match' => $playerMatchStage];
    }
    $aggregationPipeline[] = ['$unwind' => '$career_teams'];
    $seasonTeamMatchStage = [];
    if (!empty($filterSeasons)) {
        $seasonTeamMatchStage['career_teams.year'] = ['$in' => $filterSeasons];
    }
    if ($filterTeam) {
        $seasonTeamMatchStage['career_teams.tmID'] = $filterTeam;
    }
    if (!empty($seasonTeamMatchStage)) {
        $aggregationPipeline[] = ['$match' => $seasonTeamMatchStage];
    }

    // --- LOGIKA KPI (TIDAK BERUBAH) ---
    $kpiPipeline = array_merge($aggregationPipeline, [['$group' => ['_id' => null, 'totalPoints' => ['$sum' => '$career_teams.points'], 'totalAssists' => ['$sum' => '$career_teams.assists'], 'totalRebounds' => ['$sum' => '$career_teams.rebounds'], 'totalSteals' => ['$sum' => '$career_teams.steals'], 'totalBlocks' => ['$sum' => '$career_teams.blocks'], 'totalTurnovers' => ['$sum' => '$career_teams.turnovers'], 'totalGP' => ['$sum' => '$career_teams.GP'], 'countEntries' => ['$sum' => 1]]]]);
    $kpiResult = $players_collection->aggregate($kpiPipeline)->toArray();
    $kpiData = $kpiResult[0] ?? null;

    $totalPlayerSeasonEntries = $kpiData['countEntries'] ?? 0;
    $totalGamesPlayedAllFiltered = $kpiData['totalGP'] ?? 0;
    $avgPoints = $totalGamesPlayedAllFiltered > 0 ? round(($kpiData['totalPoints'] ?? 0) / $totalGamesPlayedAllFiltered, 1) : 0;
    $avgAssists = $totalGamesPlayedAllFiltered > 0 ? round(($kpiData['totalAssists'] ?? 0) / $totalGamesPlayedAllFiltered, 1) : 0;
    $avgRebounds = $totalGamesPlayedAllFiltered > 0 ? round(($kpiData['totalRebounds'] ?? 0) / $totalGamesPlayedAllFiltered, 1) : 0;
    $avgSteals = $totalGamesPlayedAllFiltered > 0 ? round(($kpiData['totalSteals'] ?? 0) / $totalGamesPlayedAllFiltered, 1) : 0;
    $avgBlocks = $totalGamesPlayedAllFiltered > 0 ? round(($kpiData['totalBlocks'] ?? 0) / $totalGamesPlayedAllFiltered, 1) : 0;
    $avgTurnovers = $totalGamesPlayedAllFiltered > 0 ? round(($kpiData['totalTurnovers'] ?? 0) / $totalGamesPlayedAllFiltered, 1) : 0;
    $avgGamePlayedPerEntry = $totalPlayerSeasonEntries > 0 ? round($totalGamesPlayedAllFiltered / $totalPlayerSeasonEntries, 1) : 0;

    // --- PENAMBAHAN: PAGINASI DINAMIS ---
    $validLimits = [25, 50, 75, 100];
    $itemsPerPage = isset($_GET['limit']) && in_array((int)$_GET['limit'], $validLimits) ? (int)$_GET['limit'] : 25;
    $currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $totalPages = $totalPlayerSeasonEntries > 0 ? ceil($totalPlayerSeasonEntries / $itemsPerPage) : 1;
    $currentPage = max(1, min($currentPage, $totalPages > 0 ? $totalPages : 1));
    $offset = ($currentPage - 1) * $itemsPerPage;

    // --- PENAMBAHAN: Pengambilan data utama dengan limit dan skip dinamis ---
    $dataPipeline = array_merge($aggregationPipeline, [['$sort' => ['career_teams.year' => -1, 'career_teams.points' => -1]], ['$skip' => $offset], ['$limit' => $itemsPerPage]]);
    $cursor = $players_collection->aggregate($dataPipeline);
    $data = [];
    foreach ($cursor as $doc) {
        $playerSeasonData = $doc['career_teams'];
        $data[] = ['playerID' => $doc['playerID'] ?? null, 'playerName' => trim(($doc['useFirst'] ?? ($doc['firstName'] ?? '')) . ' ' . ($doc['lastName'] ?? ($doc['playerID'] ?? 'Unknown'))), 'pos' => $doc['pos'] ?? '-', 'year' => $playerSeasonData['year'] ?? null, 'tmID' => $playerSeasonData['tmID'] ?? null, 'GP' => $playerSeasonData['GP'] ?? 0, 'points' => $playerSeasonData['points'] ?? 0, 'assists' => $playerSeasonData['assists'] ?? 0, 'rebounds' => $playerSeasonData['rebounds'] ?? 0, 'steals' => $playerSeasonData['steals'] ?? 0, 'blocks' => $playerSeasonData['blocks'] ?? 0, 'turnovers' => $playerSeasonData['turnovers'] ?? 0, 'fgMade' => $playerSeasonData['fgMade'] ?? 0, 'fgAttempted' => $playerSeasonData['fgAttempted'] ?? 0,];
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8" />
        <title>Player Stats Dashboard - NBA Universe</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
                height: 8px;
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
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            }

            select,
            input[type="text"] {
                background-color: rgba(17, 24, 39, 0.8);
                border: 1px solid #374151;
                color: #E0E0E0;
                border-radius: 0.375rem;
            }

            select:focus,
            input:focus {
                outline: 2px solid transparent;
                outline-offset: 2px;
                border-color: #3B82F6;
                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
            }

            select {
                background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%239CA3AF' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
                background-position: right 0.5rem center;
                background-repeat: no-repeat;
                background-size: 1.5em 1.5em;
                -webkit-appearance: none;
                -moz-appearance: none;
                appearance: none;
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
                background-color: #1E40AF;
                transform: translateY(-2px);
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
                border-color: #4b5563;
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
                background-color: #1f2937;
                border-color: #374151;
            }

            .player-tags-container {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 0.5rem;
                padding: 0.25rem 0.5rem;
                border: 1px solid #374151;
                border-radius: 0.375rem;
                background-color: rgba(17, 24, 39, 0.8);
                min-height: 42px;
                cursor: text;
                transition: border-color 0.2s, box-shadow 0.2s;
            }

            .player-tags-container:focus-within {
                border-color: #3B82F6;
                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
            }

            .player-tag {
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

            #newPlayerName {
                flex-grow: 1;
                background: transparent;
                border: none;
                outline: none;
                box-shadow: none;
                padding: 0.25rem;
                min-width: 120px;
            }

            .player-radar-checkbox {
                cursor: pointer;
                width: 1.15rem;
                height: 1.15rem;
                accent-color: #3B82F6;
            }

            /* PENAMBAHAN: CSS untuk Tooltip Info */
            .info-tooltip-container {
                position: relative;
                display: inline-block;
            }

            .info-tooltip-container .info-tooltip-text {
                visibility: hidden;
                width: 220px;
                background-color: #1f2937;
                color: #d1d5db;
                text-align: center;
                border-radius: 6px;
                padding: 8px;
                font-size: 0.75rem;
                position: absolute;
                z-index: 1;
                bottom: 125%;
                left: 50%;
                margin-left: -110px;
                opacity: 0;
                transition: opacity 0.3s;
                border: 1px solid #374151;
            }

            .info-tooltip-container:hover .info-tooltip-text {
                visibility: visible;
                opacity: 1;
            }
        </style>
    </head>

    <body class="antialiased">
        <div class="max-w-screen-2xl mx-auto p-4 md:p-6 lg:p-8">
            <header class="mb-6 md:mb-8 text-center md:text-left">
                <h1 class="text-3xl md:text-4xl font-bold text-gray-100 font-teko tracking-wide uppercase">Player Performance Dashboard</h1>
                <p class="text-sm text-gray-400 mt-1">Explore detailed statistics and trends for NBA players across seasons.</p>
            </header>

            <div class="content-container mb-8 sticky top-4 z-20">
                <form method="GET" id="playerFilterForm" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-x-4 gap-y-4 items-end">
                    <div>
                        <label for="season-btn" class="block text-xs font-medium text-gray-400 mb-1.5">Season(s)</label>
                        <div class="relative" id="season-dropdown-container">
                            <button type="button" id="season-btn" class="w-full text-sm py-2.5 px-3 text-left flex justify-between items-center bg-gray-800/80 border border-gray-700 rounded-md"> <span class="truncate">Select Seasons</span> <i class="fas fa-chevron-down fa-xs text-gray-400 transition-transform duration-200"></i> </button>
                            <div id="season-checklist" class="absolute hidden w-full mt-1 bg-gray-800 border border-gray-700 rounded-md shadow-lg z-30 max-h-60 overflow-y-auto">
                                <?php foreach ($seasons as $season): ?> <label class="flex items-center w-full px-3 py-2 text-sm text-gray-200 hover:bg-gray-700 cursor-pointer"> <input type="checkbox" name="seasons[]" value="<?= htmlspecialchars($season) ?>" class="h-4 w-4 rounded border-gray-500 bg-gray-700 text-blue-500 focus:ring-blue-500/50 mr-3 season-checkbox" <?= in_array($season, $filterSeasons) ? 'checked' : '' ?>> <?= htmlspecialchars($season) ?> </label> <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div> <label for="team" class="block text-xs font-medium text-gray-400 mb-1.5">Team</label> <select name="team" id="team" class="w-full text-sm py-2.5 px-3">
                            <option value="">All Teams</option> <?php foreach ($teamDisplayNames as $tmID => $name): ?><option value="<?= htmlspecialchars($tmID) ?>" <?= ($filterTeam == $tmID) ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option><?php endforeach; ?>
                        </select> </div>
                    <div> <label for="pos" class="block text-xs font-medium text-gray-400 mb-1.5">Position</label> <select name="pos" id="pos" class="w-full text-sm py-2.5 px-3">
                            <option value="">All Positions</option> <?php foreach ($positions as $pos): ?><option value="<?= htmlspecialchars($pos) ?>" <?= ($filterPos == $pos) ? 'selected' : '' ?>><?= htmlspecialchars($pos) ?></option><?php endforeach; ?>
                        </select> </div>
                    <div>
                        <label for="newPlayerName" class="block text-xs font-medium text-gray-400 mb-1.5">Player Name(s)</label>
                        <div id="player-tags-input-container" class="player-tags-container">
                            <div id="hidden-player-inputs"></div>
                            <input type="text" id="newPlayerName" class="text-sm" placeholder="Type name & press Enter...">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-full flex items-center justify-center h-[42px] uppercase tracking-wider text-sm"> <i class="fas fa-filter mr-2 fa-sm"></i> Apply </button>
                </form>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5 mb-8"><?php $statCardsData = [['label' => 'Avg Points', 'value' => $avgPoints, 'color' => 'text-blue-400', 'icon' => 'fa-solid fa-basketball', 'bg' => 'bg-blue-900/50'], ['label' => 'Avg Assists', 'value' => $avgAssists, 'color' => 'text-green-400', 'icon' => 'fa-solid fa-hands-helping', 'bg' => 'bg-green-900/50'], ['label' => 'Avg Rebounds', 'value' => $avgRebounds, 'color' => 'text-yellow-400', 'icon' => 'fa-solid fa-people-carry-box', 'bg' => 'bg-yellow-900/50'], ['label' => 'Avg Steals', 'value' => $avgSteals, 'color' => 'text-purple-400', 'icon' => 'fa-solid fa-user-secret', 'bg' => 'bg-purple-900/50'], ['label' => 'Avg Blocks', 'value' => $avgBlocks, 'color' => 'text-orange-400', 'icon' => 'fa-solid fa-shield-halved', 'bg' => 'bg-orange-900/50'], ['label' => 'Avg Turnovers', 'value' => $avgTurnovers, 'color' => 'text-red-400', 'icon' => 'fa-solid fa-recycle', 'bg' => 'bg-red-900/50'], ['label' => 'Avg GP / Entry', 'value' => $avgGamePlayedPerEntry, 'color' => 'text-teal-400', 'icon' => 'fa-solid fa-calendar-check', 'bg' => 'bg-teal-900/50'], ['label' => 'Total Entries', 'value' => number_format($totalPlayerSeasonEntries), 'color' => 'text-indigo-400', 'icon' => 'fa-solid fa-list-ol', 'bg' => 'bg-indigo-900/50'],];
                                                                                                    foreach ($statCardsData as $card): ?>
                    <div class="content-container !p-4 flex items-center space-x-4 transition-all duration-200 hover:border-blue-500/60 hover:-translate-y-1">
                        <div class="p-3 rounded-full <?= $card['bg'] ?> <?= $card['color'] ?>"><i class="<?= $card['icon'] ?> fa-fw text-xl"></i></div>
                        <div>
                            <p class="text-xs text-gray-400 font-medium uppercase tracking-wider"><?= $card['label'] ?></p>
                            <p class="text-2xl font-semibold <?= $card['color'] ?> mt-0.5 font-teko tracking-wider"><?= $card['value'] ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="content-container !p-0 overflow-hidden mb-8">
                <div class="px-5 py-4 border-b border-gray-700 flex flex-col sm:flex-row justify-between items-center gap-y-3">
                    <h2 class="text-lg font-semibold text-gray-200 font-rajdhani uppercase">Player Statistics</h2>
                    <div class="flex items-center gap-x-4">
                        <?php if ($totalPlayerSeasonEntries > 0):
                            $startEntry = ($currentPage - 1) * $itemsPerPage + 1;
                            $endEntry = min($currentPage * $itemsPerPage, $totalPlayerSeasonEntries); ?>
                            <span class="text-xs font-medium text-gray-400"> Showing <?= number_format($startEntry) ?>-<?= number_format($endEntry) ?> of <?= number_format($totalPlayerSeasonEntries) ?> entries </span>
                        <?php endif; ?>
                        <div class="flex items-center space-x-2">
                            <label for="itemsPerPageSelect" class="text-xs font-medium text-gray-400">Show:</label>
                            <select id="itemsPerPageSelect" name="limit" class="text-xs py-1 pl-2 pr-7 rounded border-gray-600 bg-gray-800 text-gray-200 focus:border-blue-500 focus:ring-blue-500/50">
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
                                <th class="px-4 py-3 font-semibold w-[4%] text-center"><i class="fas fa-bullseye" title="Select for Radar Chart"></i></th>
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
                                <th class="px-4 py-3 font-semibold w-[10%] text-center">Details</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-800">
                            <?php if (count($data) > 0): foreach ($data as $index => $row): ?>
                                    <?php $fgPercent = ($row['fgAttempted'] > 0) ? round(($row['fgMade'] / $row['fgAttempted']) * 100, 1) : 0;
                                    $teamNameForDisplay = $teamDisplayNames[$row['tmID']] ?? $row['tmID']; ?>
                                    <tr class="hover:bg-gray-800/60 transition-colors duration-100 group">
                                        <td class="px-4 py-2.5 text-center"><input type="checkbox" class="player-radar-checkbox" data-player-index="<?= $index ?>"></td>
                                        <td class="px-4 py-2.5 font-medium text-gray-200 whitespace-nowrap truncate group-hover:text-blue-400"><?= htmlspecialchars($row['playerName']) ?></td>
                                        <td class="px-4 py-2.5 text-gray-300 whitespace-nowrap truncate"><?= htmlspecialchars($teamNameForDisplay) ?></td>
                                        <td class="px-4 py-2.5 text-gray-300 whitespace-nowrap text-center"><?= htmlspecialchars($row['pos']) ?></td>
                                        <td class="px-4 py-2.5 text-gray-300 whitespace-nowrap text-center"><?= htmlspecialchars($row['year']) ?></td>
                                        <td class="px-4 py-2.5 text-gray-300 text-right whitespace-nowrap"><?= htmlspecialchars($row['GP']) ?></td>
                                        <td class="px-4 py-2.5 text-gray-200 text-right whitespace-nowrap font-semibold"><?= htmlspecialchars($row['points']) ?></td>
                                        <td class="px-4 py-2.5 text-gray-300 text-right whitespace-nowrap"><?= htmlspecialchars($row['assists']) ?></td>
                                        <td class="px-4 py-2.5 text-gray-300 text-right whitespace-nowrap"><?= htmlspecialchars($row['rebounds']) ?></td>
                                        <td class="px-4 py-2.5 text-gray-300 text-right whitespace-nowrap"><?= htmlspecialchars($row['steals']) ?></td>
                                        <td class="px-4 py-2.5 text-gray-300 text-right whitespace-nowrap"><?= htmlspecialchars($row['blocks']) ?></td>
                                        <td class="px-4 py-2.5 text-gray-300 text-right whitespace-nowrap"><?= htmlspecialchars($row['turnovers']) ?></td>
                                        <td class="px-4 py-2.5 text-gray-300 text-right whitespace-nowrap"><?= $fgPercent ?>%</td>
                                        <td class="px-4 py-2.5 text-center whitespace-nowrap"><a href="player_season_detail.php?playerID=<?= urlencode($row['playerID']) ?>&year=<?= $row['year'] ?>&team=<?= urlencode($row['tmID']) ?>" class="text-blue-400 hover:text-blue-300 text-xs font-semibold hover:underline transition-colors"> View <i class="fas fa-arrow-right fa-xs ml-0.5"></i></a></td>
                                    </tr>
                                <?php endforeach;
                            else: ?>
                                <tr>
                                    <td colspan="14" class="text-center p-10 text-gray-500">
                                        <div class="flex flex-col items-center"><i class="fas fa-ghost fa-3x text-gray-700 mb-4"></i>
                                            <p class="font-semibold text-gray-300 text-base">No Player Data Found</p>
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
                        <div class="flex items-center space-x-1.5">
                            <?php $queryParams = $_GET;
                            unset($queryParams['page']);
                            $baseUrl = '?' . http_build_query($queryParams) . (empty($queryParams) ? '' : '&'); ?>
                            <a href="<?= ($currentPage > 1) ? $baseUrl . 'page=' . ($currentPage - 1) : '#' ?>" class="<?= ($currentPage <= 1) ? 'disabled' : '' ?>"><i class="fas fa-chevron-left fa-xs mr-1"></i> Prev</a>
                            <?php
                            $numPageLinksToShow = 5;
                            $startPage = max(1, $currentPage - floor($numPageLinksToShow / 2));
                            $endPage = min($totalPages, $startPage + $numPageLinksToShow - 1);
                            if ($endPage - $startPage + 1 < $numPageLinksToShow && $startPage > 1) $startPage = max(1, $endPage - $numPageLinksToShow + 1);
                            if ($startPage > 1) {
                                echo '<a href="' . $baseUrl . 'page=1">1</a>';
                                if ($startPage > 2) echo '<span class="px-2 py-1.5 text-gray-500 text-xs">...</span>';
                            }
                            for ($i = $startPage; $i <= $endPage; $i++): ?><a href="<?= $baseUrl . 'page=' . $i ?>" class="<?= ($i == $currentPage) ? 'active' : '' ?>"><?= $i ?></a><?php endfor;
                                                                                                                                                                                        if ($endPage < $totalPages) {
                                                                                                                                                                                            if ($endPage < $totalPages - 1) echo '<span class="px-2 py-1.5 text-gray-500 text-xs">...</span>';
                                                                                                                                                                                            echo '<a href="' . $baseUrl . 'page=' . $totalPages . '">' . $totalPages . '</a>';
                                                                                                                                                                                        }
                                                                                                                                                                                            ?>
                            <a href="<?= ($currentPage < $totalPages) ? $baseUrl . 'page=' . ($currentPage + 1) : '#' ?>" class="<?= ($currentPage >= $totalPages) ? 'disabled' : '' ?>">Next <i class="fas fa-chevron-right fa-xs ml-1"></i></a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <section class="mt-10">
                <div class="px-1 py-4 mb-4">
                    <h2 class="text-2xl font-semibold text-gray-200 text-center font-teko uppercase tracking-wider">Visual Insights</h2>
                    <p class="text-sm text-gray-400 text-center mt-1">Compare player stats visually. Use the checkboxes in the table to select players for the radar chart.</p>
                </div>
                <div id="charts-grid-container" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <?php if (count($data) > 0): ?>
                        <div class="content-container min-h-[400px] md:min-h-[450px] flex flex-col">
                            <div class="flex flex-col sm:flex-row justify-between items-center mb-4 gap-2">
                                <h3 class="text-base font-semibold text-gray-300 font-rajdhani flex-grow">Player Core Stats Comparison</h3>
                                <div class="flex items-center space-x-2"><label for="barChartSort" class="text-xs text-gray-400">Sort by:</label><select id="barChartSort" class="text-xs py-1 pl-2 pr-8 rounded border-gray-600 bg-gray-800 text-gray-200 focus:border-blue-500 focus:ring-blue-500">
                                        <option value="default">Default Order</option>
                                        <option value="points">Points (High to Low)</option>
                                        <option value="assists">Assists (High to Low)</option>
                                        <option value="rebounds">Rebounds (High to Low)</option>
                                    </select></div>
                            </div>
                            <div class="flex-grow relative"><canvas id="barChartPlayers"></canvas></div>
                        </div>
                        <div class="content-container min-h-[400px] md:min-h-[450px] flex flex-col">
                            <!-- PENAMBAHAN: Judul Radar Chart dengan ikon info -->
                            <div class="flex items-center gap-2 mb-4 text-center sm:text-left">
                                <h3 class="text-base font-semibold text-gray-300 font-rajdhani">Player Skill Radar</h3>
                                <div class="info-tooltip-container">
                                    <i class="fas fa-info-circle text-gray-400 hover:text-gray-200 cursor-pointer"></i>
                                    <span class="info-tooltip-text">The Dominance Score (%) is the average of a player's normalized stats, showing their overall performance balance.</span>
                                </div>
                            </div>
                            <div class="flex-grow relative">
                                <canvas id="radarChart"></canvas>
                                <div id="radar-placeholder" class="absolute inset-0 flex flex-col items-center justify-center text-center text-gray-500 p-4"><i class="fas fa-bullseye fa-3x text-gray-600 mb-4"></i>
                                    <p class="font-semibold text-gray-400">Select 2 to 5 Players</p>
                                    <p class="text-xs">Use the checkboxes in the table above to compare.</p>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="lg:col-span-2 content-container min-h-[400px]">
                            <div class="flex flex-col items-center justify-center h-full text-gray-500 p-6 text-center"><i class="fas fa-chart-pie fa-3x text-gray-700 mb-4"></i>
                                <p class="text-sm font-medium">No data to display charts.</p>
                                <p class="text-xs">Adjust the filters to see visual insights.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <footer class="text-center mt-16 py-8 border-t border-gray-800">
                <p class="text-xs text-gray-500">© <?= date("Y") ?> NBA Universe Dashboard. All Rights Reserved.</p>
            </footer>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // --- SETUP GLOBAL & FUNGSI CHART ---
                const chartDataSource = <?= json_encode($data); ?>;
                let barChartInstance = null;
                let radarChartInstance = null;
                const selectedPlayerIndices = new Set();
                const RADAR_COLORS = [{
                        fill: 'rgba(59, 130, 246, 0.4)',
                        stroke: 'rgba(96, 165, 250, 1)'
                    }, {
                        fill: 'rgba(239, 68, 68, 0.4)',
                        stroke: 'rgba(248, 113, 113, 1)'
                    },
                    {
                        fill: 'rgba(16, 185, 129, 0.4)',
                        stroke: 'rgba(52, 211, 153, 1)'
                    }, {
                        fill: 'rgba(245, 158, 11, 0.4)',
                        stroke: 'rgba(251, 191, 36, 1)'
                    },
                    {
                        fill: 'rgba(168, 85, 247, 0.4)',
                        stroke: 'rgba(192, 132, 252, 1)'
                    },
                ];

                // --- FUNGIONALITAS UI INTERAKTIF (SEASONS & ITEMS PER PAGE) ---

                // 1. Dropdown Checklist Musim (dengan teks dinamis)
                const seasonDropdownContainer = document.getElementById('season-dropdown-container');
                const seasonBtn = document.getElementById('season-btn');
                const seasonChecklist = document.getElementById('season-checklist');
                const seasonBtnSpan = seasonBtn.querySelector('span');
                const seasonCheckboxes = document.querySelectorAll('.season-checkbox');

                function updateSeasonButtonText() {
                    const checkedSeasons = Array.from(seasonCheckboxes)
                        .filter(cb => cb.checked)
                        .map(cb => cb.value);

                    if (checkedSeasons.length === 0) {
                        seasonBtnSpan.textContent = 'Select Seasons';
                    } else if (checkedSeasons.length <= 3) {
                        seasonBtnSpan.textContent = checkedSeasons.sort((a, b) => b - a).join(', ');
                    } else {
                        seasonBtnSpan.textContent = `${checkedSeasons.length} Seasons Selected`;
                    }
                }

                if (seasonBtn && seasonChecklist) {
                    updateSeasonButtonText(); // Atur teks awal saat halaman dimuat
                    seasonBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        seasonChecklist.classList.toggle('hidden');
                        seasonBtn.querySelector('i').classList.toggle('rotate-180');
                    });
                    document.addEventListener('click', (e) => {
                        if (!seasonDropdownContainer.contains(e.target)) {
                            seasonChecklist.classList.add('hidden');
                            seasonBtn.querySelector('i').classList.remove('rotate-180');
                        }
                    });
                    seasonCheckboxes.forEach(cb => {
                        cb.addEventListener('change', updateSeasonButtonText);
                    });
                }

                // 2. Dropdown "Items per Page"
                const itemsPerPageSelect = document.getElementById('itemsPerPageSelect');
                if (itemsPerPageSelect) {
                    itemsPerPageSelect.addEventListener('change', function() {
                        const newLimit = this.value;
                        const currentUrl = new URL(window.location);
                        currentUrl.searchParams.set('limit', newLimit);
                        currentUrl.searchParams.set('page', '1'); // Kembali ke halaman 1
                        window.location.href = currentUrl.toString();
                    });
                }

                // --- KODE LAMA ANDA UNTUK FITUR LAINNYA (TIDAK DIUBAH) ---

                // Input Tag Pemain
                const initialPlayers = <?= json_encode($filterPlayerNames ?? []); ?>;
                const playerTagsContainer = document.getElementById('player-tags-input-container');
                if (playerTagsContainer) {
                    const newPlayerInput = document.getElementById('newPlayerName');
                    const hiddenInputsContainer = document.getElementById('hidden-player-inputs');
                    let currentPlayers = initialPlayers;

                    function renderPlayerTags() {
                        playerTagsContainer.querySelectorAll('.player-tag').forEach(tag => tag.remove());
                        hiddenInputsContainer.innerHTML = '';
                        currentPlayers.forEach((name, index) => {
                            const tag = document.createElement('div');
                            tag.className = 'player-tag';
                            tag.innerHTML = `<span>${name}</span><button type="button" class="remove-tag-btn" data-index="${index}">×</button>`;
                            playerTagsContainer.insertBefore(tag, newPlayerInput);
                            const hiddenInput = document.createElement('input');
                            hiddenInput.type = 'hidden';
                            hiddenInput.name = 'players[]';
                            hiddenInput.value = name;
                            hiddenInputsContainer.appendChild(hiddenInput);
                        });
                    }
                    newPlayerInput.addEventListener('keydown', function(event) {
                        if (event.key === 'Enter') {
                            event.preventDefault();
                            const newName = this.value.trim();
                            if (newName && !currentPlayers.some(p => p.toLowerCase() === newName.toLowerCase())) {
                                currentPlayers.push(newName);
                                this.value = '';
                                renderPlayerTags();
                            }
                        }
                    });
                    playerTagsContainer.addEventListener('click', function(event) {
                        if (event.target.classList.contains('remove-tag-btn')) {
                            const indexToRemove = parseInt(event.target.dataset.index, 10);
                            currentPlayers.splice(indexToRemove, 1);
                            renderPlayerTags();
                        } else if (event.target === playerTagsContainer) {
                            newPlayerInput.focus();
                        }
                    });
                    renderPlayerTags();
                }

                // Logika Bar Chart
                const barChartSortSelect = document.getElementById('barChartSort');

                function updateBarChart(sortBy = 'default') {
                    if (!document.getElementById('barChartPlayers')) return;
                    const chartData = [...chartDataSource].slice(0, 15);
                    if (sortBy !== 'default') {
                        chartData.sort((a, b) => (b[sortBy] || 0) - (a[sortBy] || 0));
                    }
                    const labels = chartData.map(p => `${p.playerName} (${p.pos || 'N/A'})`);
                    const chartConfigData = {
                        labels: labels,
                        datasets: [{
                                label: 'Points',
                                data: chartData.map(p => p.points),
                                backgroundColor: 'rgba(59, 130, 246, 0.7)',
                                borderColor: 'rgba(59, 130, 246, 1)',
                                borderWidth: 1
                            },
                            {
                                label: 'Assists',
                                data: chartData.map(p => p.assists),
                                backgroundColor: 'rgba(16, 185, 129, 0.7)',
                                borderColor: 'rgba(16, 185, 129, 1)',
                                borderWidth: 1
                            },
                            {
                                label: 'Rebounds',
                                data: chartData.map(p => p.rebounds),
                                backgroundColor: 'rgba(245, 158, 11, 0.7)',
                                borderColor: 'rgba(245, 158, 11, 1)',
                                borderWidth: 1
                            }
                        ]
                    };
                    if (barChartInstance) {
                        barChartInstance.data = chartConfigData;
                        barChartInstance.update();
                    } else {
                        const ctx = document.getElementById('barChartPlayers')?.getContext('2d');
                        if (!ctx) return;
                        barChartInstance = new Chart(ctx, {
                            type: 'bar',
                            data: chartConfigData,
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        grid: {
                                            color: 'rgba(255, 255, 255, 0.1)'
                                        },
                                        ticks: {
                                            color: '#9CA3AF'
                                        }
                                    },
                                    x: {
                                        grid: {
                                            display: false
                                        },
                                        ticks: {
                                            color: '#9CA3AF'
                                        }
                                    }
                                },
                                plugins: {
                                    legend: {
                                        position: 'top',
                                        labels: {
                                            color: '#E0E0E0',
                                            usePointStyle: true,
                                            boxWidth: 8
                                        }
                                    },
                                    tooltip: {
                                        backgroundColor: '#1F2937',
                                        titleColor: '#E5E7EB',
                                        bodyColor: '#D1D5DB',
                                        boxPadding: 4,
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
                                }
                            }
                        });
                    }
                }
                if (barChartSortSelect) {
                    barChartSortSelect.addEventListener('change', function() {
                        updateBarChart(this.value);
                    });
                }

                // --- LOGIKA RADAR CHART DENGAN SKOR DOMINASI ---
                const radarPlaceholder = document.getElementById('radar-placeholder');
                const STAT_KEYS = ['points', 'rebounds', 'assists', 'steals', 'blocks'];
                const STAT_LABELS = ['Scoring', 'Rebounding', 'Playmaking', 'Steals', 'Defense'];

                function updateRadarChart() {
                    if (!document.getElementById('radarChart')) return;
                    const selectedPlayers = Array.from(selectedPlayerIndices).map(index => chartDataSource[index]);

                    if (selectedPlayers.length < 2) {
                        if (radarPlaceholder) radarPlaceholder.style.display = 'flex';
                        if (radarChartInstance) {
                            radarChartInstance.destroy();
                            radarChartInstance = null;
                        }
                        return;
                    }
                    if (radarPlaceholder) radarPlaceholder.style.display = 'none';

                    // Hitung stats per game & skor dominasi
                    let playersWithScores = selectedPlayers.map(p => {
                        const perGameStats = {};
                        STAT_KEYS.forEach(key => {
                            perGameStats[key] = p.GP > 0 ? parseFloat((p[key] / p.GP).toFixed(1)) : 0;
                        });
                        return {
                            ...p,
                            perGameStats
                        };
                    });

                    const maxStats = {};
                    STAT_KEYS.forEach(key => {
                        maxStats[key] = Math.max(...playersWithScores.map(p => p.perGameStats[key]), 0.1);
                    });

                    playersWithScores = playersWithScores.map(p => {
                        const normalizedData = STAT_KEYS.map(key => (p.perGameStats[key] / maxStats[key]) * 100);
                        const dominanceScore = normalizedData.reduce((a, b) => a + b, 0) / normalizedData.length;
                        return {
                            ...p,
                            normalizedData,
                            dominanceScore
                        };
                    });

                    // Urutkan pemain berdasarkan skor dominasi (tertinggi ke terendah)
                    playersWithScores.sort((a, b) => b.dominanceScore - a.dominanceScore);

                    const datasets = playersWithScores.map((player, index) => {
                        const color = RADAR_COLORS[index % RADAR_COLORS.length];
                        return {
                            label: `${player.playerName} (${player.year}) - ${player.dominanceScore.toFixed(0)}%`,
                            data: player.normalizedData,
                            backgroundColor: color.fill,
                            borderColor: color.stroke,
                            pointBackgroundColor: color.stroke,
                            pointBorderColor: '#fff',
                            pointHoverBackgroundColor: '#fff',
                            pointHoverBorderColor: color.stroke,
                            borderWidth: 2.5
                        };
                    });

                    if (radarChartInstance) {
                        radarChartInstance.data.datasets = datasets;
                        radarChartInstance.update();
                    } else {
                        const ctx = document.getElementById('radarChart')?.getContext('2d');
                        if (!ctx) return;
                        radarChartInstance = new Chart(ctx, {
                            type: 'radar',
                            data: {
                                labels: STAT_LABELS,
                                datasets: datasets
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                elements: {
                                    line: {
                                        tension: 0.1,
                                        fill: true
                                    }
                                },
                                scales: {
                                    r: {
                                        angleLines: {
                                            color: 'rgba(255, 255, 255, 0.15)'
                                        },
                                        grid: {
                                            color: 'rgba(255, 255, 255, 0.15)'
                                        },
                                        pointLabels: {
                                            color: '#E0E0E0',
                                            font: {
                                                size: 13,
                                                family: "'Rajdhani', sans-serif",
                                                weight: '700'
                                            }
                                        },
                                        ticks: {
                                            display: false,
                                            stepSize: 25
                                        },
                                        suggestedMin: 0,
                                        suggestedMax: 100
                                    }
                                },
                                plugins: {
                                    legend: {
                                        position: 'bottom',
                                        labels: {
                                            color: '#E0E0E0',
                                            usePointStyle: true,
                                            boxWidth: 8,
                                            padding: 20,
                                            font: {
                                                size: 12
                                            }
                                        }
                                    },
                                    tooltip: {
                                        backgroundColor: '#111827',
                                        borderColor: 'rgba(255,255,255,0.2)',
                                        borderWidth: 1,
                                        titleColor: '#E5E7EB',
                                        bodyColor: '#D1D5DB',
                                        boxPadding: 8,
                                        callbacks: {
                                            label: function(context) {
                                                const player = playersWithScores[context.datasetIndex];
                                                const statKey = STAT_KEYS[context.dataIndex];
                                                const originalValue = player.perGameStats[statKey];
                                                return `${player.playerName}: ${originalValue.toFixed(1)} ${statKey.toUpperCase()} per game`;
                                            }
                                        }
                                    }
                                }
                            }
                        });
                    }
                }

                document.querySelectorAll('.player-radar-checkbox').forEach(checkbox => {
                    checkbox.addEventListener('change', function() {
                        const playerIndex = parseInt(this.dataset.playerIndex, 10);
                        if (this.checked) {
                            if (selectedPlayerIndices.size < 5) {
                                selectedPlayerIndices.add(playerIndex);
                            } else {
                                this.checked = false;
                                alert('You can compare a maximum of 5 players at a time.');
                                return;
                            }
                        } else {
                            selectedPlayerIndices.delete(playerIndex);
                        }
                        updateRadarChart();
                    });
                });

                // Panggilan Inisialisasi Saat Halaman Dimuat
                if (chartDataSource && chartDataSource.length > 0) {
                    updateBarChart();
                    updateRadarChart();
                }
            });
        </script>
    </body>

    </html>