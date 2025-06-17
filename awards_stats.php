<?php
// Menggunakan kedua koneksi database
require_once 'db.php'; // Koneksi MongoDB
require_once 'db_sql.php'; // Koneksi SQL (PDO)
include 'header.php';

// --- PENGAMBILAN DATA UNTUK FILTER ---
$allYears = $pdo->query("SELECT DISTINCT year FROM awards ORDER BY year DESC")->fetchAll(PDO::FETCH_COLUMN);
$allAwardNames = $pdo->query("SELECT DISTINCT award_name FROM awards ORDER BY award_name ASC")->fetchAll(PDO::FETCH_COLUMN);

// --- LOGIKA FILTER DENGAN MULTI-RECIPIENT ---
$filterYears = isset($_GET['years']) && is_array($_GET['years']) ? array_map('intval', $_GET['years']) : [];
$filterAward = $_GET['award_name'] ?? '';
$filterType = $_GET['type'] ?? '';
// Menerima array recipients dari input tag
$filterRecipients = isset($_GET['recipients']) && is_array($_GET['recipients']) ? array_map('trim', $_GET['recipients']) : [];
$filterRecipients = array_filter($filterRecipients); // Hapus string kosong

// --- MEMBANGUN QUERY SQL DINAMIS ---
$whereClauses = [];
$params = [];

if (!empty($filterYears)) {
    $placeholders = implode(',', array_fill(0, count($filterYears), '?'));
    $whereClauses[] = "year IN ($placeholders)";
    $params = array_merge($params, $filterYears);
}
if ($filterAward) {
    $whereClauses[] = "award_name = ?";
    $params[] = $filterAward;
}
if ($filterType) {
    $whereClauses[] = "recipient_type = ?";
    $params[] = $filterType;
}
if (!empty($filterRecipients)) {
    $recipientPlaceholders = implode(',', array_fill(0, count($filterRecipients), '?'));
    $whereClauses[] = "recipient_id IN ($recipientPlaceholders)";
    $params = array_merge($params, $filterRecipients);
}

$baseSql = "FROM awards";
if (!empty($whereClauses)) {
    $baseSql .= " WHERE " . implode(' AND ', $whereClauses);
}

// --- PAGINATION ---
$countStmt = $pdo->prepare("SELECT COUNT(*) " . $baseSql);
$countStmt->execute($params);
$totalEntries = $countStmt->fetchColumn();

$itemsPerPage = 25;
$totalPages = $totalEntries > 0 ? ceil($totalEntries / $itemsPerPage) : 1;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$currentPage = max(1, min($currentPage, $totalPages > 0 ? $totalPages : 1));
$offset = ($currentPage - 1) * $itemsPerPage;

// --- MENGAMBIL DATA UTAMA ---
$dataSql = "SELECT * " . $baseSql . " ORDER BY year DESC, award_name ASC LIMIT ? OFFSET ?";
$dataParams = array_merge($params, [$itemsPerPage, $offset]);
$dataStmt = $pdo->prepare($dataSql);
for ($i = 1; $i <= count($params); $i++) {
    $dataStmt->bindValue($i, $params[$i - 1]);
}
$dataStmt->bindValue(count($params) + 1, $itemsPerPage, PDO::PARAM_INT);
$dataStmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$dataStmt->execute();
$awardsData = $dataStmt->fetchAll(PDO::FETCH_ASSOC);


// --- MENGAMBIL INFORMASI TAMBAHAN (NAMA & TOTAL AWARDS) ---
$nameMap = [];
$totalAwardsMap = [];

if (!empty($awardsData)) {
    $playerIds = [];
    $coachIds = [];
    $allRecipientIds = array_unique(array_column($awardsData, 'recipient_id'));

    foreach ($awardsData as $award) {
        if ($award['recipient_type'] === 'player') {
            $playerIds[] = $award['recipient_id'];
        } elseif ($award['recipient_type'] === 'coach') {
            $coachIds[] = $award['recipient_id'];
        }
    }

    if (!empty($playerIds)) {
        $playerIdsForQuery = array_values(array_unique($playerIds));
        $playerCursor = $players_collection->find(['playerID' => ['$in' => $playerIdsForQuery]]);
        foreach ($playerCursor as $player) {
            $nameMap[$player['playerID']] = trim(($player['firstName'] ?? '') . ' ' . ($player['lastName'] ?? ''));
        }
    }

    if (!empty($coachIds) && isset($coaches_collection)) {
        $coachIdsForQuery = array_values(array_unique($coachIds));
        $coachCursor = $coaches_collection->find(['coachID' => ['$in' => $coachIdsForQuery]]);
        foreach ($coachCursor as $coach) {
            $nameMap[$coach['coachID']] = trim(($coach['firstName'] ?? '') . ' ' . ($coach['lastName'] ?? ''));
        }
    }

    // Query untuk menghitung total awards
    if (!empty($allRecipientIds)) {
        $placeholders = implode(',', array_fill(0, count($allRecipientIds), '?'));
        $totalAwardsSql = "SELECT recipient_id, COUNT(*) as total FROM awards WHERE recipient_id IN ($placeholders) GROUP BY recipient_id";
        $totalStmt = $pdo->prepare($totalAwardsSql);

        // --- FIX: Gunakan array_values() untuk memastikan kunci array sekuensial ---
        $totalStmt->execute(array_values($allRecipientIds));

        $totals = $totalStmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $totalAwardsMap = $totals;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>NBA Awards Dashboard - NBA Universe</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
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
            transition: all 0.2s;
        }

        select:focus,
        input:focus,
        .recipient-tags-container:focus-within {
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

        #year-checklist::-webkit-scrollbar {
            width: 6px;
        }

        #year-checklist::-webkit-scrollbar-track {
            background: #1f2937;
        }

        #year-checklist::-webkit-scrollbar-thumb {
            background: #4b5563;
            border-radius: 3px;
        }

        .recipient-tags-container {
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
        }

        .recipient-tag {
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

        #newRecipientId {
            flex-grow: 1;
            background: transparent;
            border: none;
            outline: none;
            box-shadow: none;
            padding: 0.25rem;
            min-width: 120px;
        }
    </style>
</head>

<body class="antialiased">
    <div class="max-w-screen-2xl mx-auto p-4 md:p-6 lg:p-8">
        <header class="mb-6 md:mb-8 text-center md:text-left">
            <h1 class="text-3xl md:text-4xl font-bold text-gray-100 font-teko tracking-wide uppercase">NBA Awards Dashboard</h1>
            <p class="text-sm text-gray-400 mt-1">Explore historical NBA awards for players and coaches.</p>
        </header>

        <div class="content-container mb-8 sticky top-4 z-20">
            <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-x-4 gap-y-4 items-end">
                <div><label for="year-btn" class="block text-xs font-medium text-gray-400 mb-1.5">Year(s)</label>
                    <div class="relative" id="year-dropdown-container"><button type="button" id="year-btn" class="w-full text-sm py-2.5 px-3 text-left flex justify-between items-center bg-gray-800/80 border border-gray-700 rounded-md"><span class="truncate">Select Years</span> <i class="fas fa-chevron-down fa-xs text-gray-400 transition-transform duration-200"></i></button>
                        <div id="year-checklist" class="absolute hidden w-full mt-1 bg-gray-800 border border-gray-700 rounded-md shadow-lg z-30 max-h-60 overflow-y-auto"><?php foreach ($allYears as $year): ?><label class="flex items-center w-full px-3 py-2 text-sm text-gray-200 hover:bg-gray-700 cursor-pointer"><input type="checkbox" name="years[]" value="<?= htmlspecialchars($year) ?>" class="h-4 w-4 rounded border-gray-500 bg-gray-700 text-blue-500 focus:ring-blue-500/50 mr-3" <?= in_array($year, $filterYears) ? 'checked' : '' ?>><?= htmlspecialchars($year) ?></label><?php endforeach; ?></div>
                    </div>
                </div>
                <div><label for="award_name" class="block text-xs font-medium text-gray-400 mb-1.5">Award Name</label><select name="award_name" id="award_name" class="w-full text-sm py-2.5 px-3">
                        <option value="">All Awards</option><?php foreach ($allAwardNames as $awardName): ?><option value="<?= htmlspecialchars($awardName) ?>" <?= ($filterAward == $awardName) ? 'selected' : '' ?>><?= htmlspecialchars($awardName) ?></option><?php endforeach; ?>
                    </select></div>
                <div><label for="type" class="block text-xs font-medium text-gray-400 mb-1.5">Type</label><select name="type" id="type" class="w-full text-sm py-2.5 px-3">
                        <option value="">All Types</option>
                        <option value="player" <?= ($filterType == 'player') ? 'selected' : '' ?>>Player</option>
                        <option value="coach" <?= ($filterType == 'coach') ? 'selected' : '' ?>>Coach</option>
                    </select></div>

                <div>
                    <label for="newRecipientId" class="block text-xs font-medium text-gray-400 mb-1.5">Recipient ID(s)</label>
                    <div id="recipient-tags-container" class="recipient-tags-container">
                        <div id="hidden-recipient-inputs"></div>
                        <input type="text" id="newRecipientId" class="text-sm" placeholder="e.g., jordami01 & press Enter...">
                    </div>
                </div>

                <button type="submit" class="btn-primary w-full flex items-center justify-center h-[42px] uppercase tracking-wider text-sm font-semibold rounded-md"><i class="fas fa-filter mr-2 fa-sm"></i> Apply</button>
            </form>
        </div>

        <div class="content-container !p-0 overflow-hidden mb-8">
            <div class="px-5 py-4 border-b border-gray-700 flex flex-col sm:flex-row justify-between items-center space-y-2 sm:space-y-0">
                <h2 class="text-lg font-semibold text-gray-200 font-rajdhani uppercase">Award Winners</h2><span class="text-xs font-medium text-gray-400">Showing <?= count($awardsData) ?> of <?= number_format($totalEntries) ?> entries</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-900/70">
                        <tr class="text-left text-xs text-gray-400 uppercase tracking-wider">
                            <th class="px-4 py-3 font-semibold">Year</th>
                            <th class="px-4 py-3 font-semibold">Award</th>
                            <th class="px-4 py-3 font-semibold">Recipient Name</th>
                            <th class="px-4 py-3 font-semibold">Recipient ID</th>
                            <th class="px-4 py-3 font-semibold text-center">Total Awards</th>
                            <th class="px-4 py-3 font-semibold text-center">Type</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                        <?php if (count($awardsData) > 0): ?>
                            <?php foreach ($awardsData as $row): ?>
                                <tr class="hover:bg-gray-800/60 transition-colors duration-100">
                                    <td class="px-4 py-3 text-gray-300"><?= htmlspecialchars($row['year']) ?></td>
                                    <td class="px-4 py-3 font-medium text-gray-200"><?= htmlspecialchars($row['award_name']) ?></td>
                                    <td class="px-4 py-3 text-blue-400 font-semibold"><?= htmlspecialchars($nameMap[$row['recipient_id']] ?? 'N/A') ?></td>
                                    <td class="px-4 py-3 text-gray-400"><?= htmlspecialchars($row['recipient_id']) ?></td>
                                    <td class="px-4 py-3 text-center text-gray-200 font-semibold"><?= htmlspecialchars($totalAwardsMap[$row['recipient_id']] ?? 0) ?></td>
                                    <td class="px-4 py-3 text-center"><?php if ($row['recipient_type'] == 'player'): ?><span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-900/80 text-blue-300">Player</span><?php else: ?><span class="px-2 py-1 text-xs font-medium rounded-full bg-purple-900/80 text-purple-300">Coach</span><?php endif; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center p-10 text-gray-500">
                                    <div class="flex flex-col items-center"><i class="fas fa-trophy fa-3x text-gray-700 mb-4"></i>
                                        <p class="font-semibold text-gray-300 text-base">No Awards Found</p>
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
                                                                $baseUrl = '?' . http_build_query($queryParams) . (empty($queryParams) ? '' : '&'); ?><a href="<?= ($currentPage > 1) ? $baseUrl . 'page=' . ($currentPage - 1) : '#' ?>" class="<?= ($currentPage <= 1) ? 'disabled' : '' ?>"><i class="fas fa-chevron-left fa-xs mr-1"></i> Prev</a><?php $numPageLinksToShow = 5;
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
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        } ?><a href="<?= ($currentPage < $totalPages) ? $baseUrl . 'page=' . ($currentPage + 1) : '#' ?>" class="<?= ($currentPage >= $totalPages) ? 'disabled' : '' ?>">Next <i class="fas fa-chevron-right fa-xs ml-1"></i></a></div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const yearDropdownContainer = document.getElementById('year-dropdown-container');
            const yearBtn = document.getElementById('year-btn');
            if (yearBtn) {
                const yearChecklist = document.getElementById('year-checklist');
                const yearBtnSpan = yearBtn.querySelector('span');
                const yearCheckboxes = document.querySelectorAll('#year-checklist input[type="checkbox"]');

                function updateYearButtonText() {
                    const checkedYears = Array.from(yearCheckboxes).filter(cb => cb.checked).map(cb => cb.value);
                    if (checkedYears.length === 0) {
                        yearBtnSpan.textContent = 'Select Years';
                    } else if (checkedYears.length <= 3) {
                        yearBtnSpan.textContent = checkedYears.sort((a, b) => b - a).join(', ');
                    } else {
                        yearBtnSpan.textContent = `${checkedYears.length} Years Selected`;
                    }
                }
                updateYearButtonText();
                yearBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    yearChecklist.classList.toggle('hidden');
                    yearBtn.querySelector('i').classList.toggle('rotate-180');
                });
                document.addEventListener('click', function(e) {
                    if (yearDropdownContainer && !yearDropdownContainer.contains(e.target)) {
                        yearChecklist.classList.add('hidden');
                        yearBtn.querySelector('i').classList.remove('rotate-180');
                    }
                });
                yearCheckboxes.forEach(cb => cb.addEventListener('change', updateYearButtonText));
            }

            const initialRecipients = <?= json_encode($filterRecipients); ?>;
            const tagsContainer = document.getElementById('recipient-tags-container');
            if (tagsContainer) {
                const newInput = document.getElementById('newRecipientId');
                const hiddenInputsContainer = document.getElementById('hidden-recipient-inputs');
                let currentRecipients = initialRecipients;

                function renderRecipientTags() {
                    tagsContainer.querySelectorAll('.recipient-tag').forEach(tag => tag.remove());
                    hiddenInputsContainer.innerHTML = '';
                    currentRecipients.forEach((id, index) => {
                        const tag = document.createElement('div');
                        tag.className = 'recipient-tag';
                        tag.innerHTML = `<span>${id}</span><button type="button" class="remove-tag-btn" data-index="${index}">×</button>`;
                        tagsContainer.insertBefore(tag, newInput);
                        const hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = 'recipients[]';
                        hiddenInput.value = id;
                        hiddenInputsContainer.appendChild(hiddenInput);
                    });
                }
                newInput.addEventListener('keydown', function(event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        const newId = this.value.trim();
                        if (newId && !currentRecipients.includes(newId)) {
                            currentRecipients.push(newId);
                            this.value = '';
                            renderRecipientTags();
                        }
                    }
                });
                tagsContainer.addEventListener('click', function(event) {
                    if (event.target.classList.contains('remove-tag-btn')) {
                        const indexToRemove = parseInt(event.target.dataset.index, 10);
                        currentRecipients.splice(indexToRemove, 1);
                        renderRecipientTags();
                    } else if (event.target === tagsContainer) {
                        newInput.focus();
                    }
                });
                renderRecipientTags();
            }
        });
    </script>
</body>

</html>