<?php
include 'header.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Search - NBA Universe</title>
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

        ::-webkit-scrollbar-thumb:hover {
            background: #4b5563;
        }

        /* Styling untuk input pencarian */
        #searchInput {
            background-color: rgba(31, 41, 55, 0.5);
            /* bg-gray-700/50 */
            border: 1px solid #374151;
            /* border-gray-700 */
            transition: all 0.2s ease-in-out;
        }

        #searchInput:focus {
            background-color: rgba(17, 24, 39, 0.8);
            border-color: #3B82F6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
            outline: none;
        }

        /* Styling untuk hasil pencarian */
        #resultList {
            background-color: #1F2937;
            /* bg-gray-800 */
            border: 1px solid #374151;
            /* border-gray-700 */
        }

        .result-item {
            border-bottom: 1px solid #374151;
            /* border-gray-700 */
            transition: background-color 0.15s ease-in-out;
        }

        .result-item:hover {
            background-color: #374151;
            /* bg-gray-700 */
        }

        .result-item:last-child {
            border-bottom: none;
        }
    </style>
</head>

<body class="antialiased">

    <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-16 md:py-24">
        <div class="text-center mb-12">
            <h1 class="text-5xl md:text-6xl font-bold text-gray-100 font-teko tracking-wider uppercase">UNIVERSAL SEARCH</h1>
            <p class="text-lg text-gray-400 mt-2">Find any player or team in the NBA Universe.</p>
        </div>

        <div class="max-w-2xl mx-auto relative">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <i class="fas fa-search text-gray-500"></i>
                </div>
                <input
                    type="text"
                    id="searchInput"
                    placeholder="Search for 'LeBron James', 'GSW', 'Lakers'..."
                    class="w-full p-4 pl-12 text-lg rounded-full text-gray-200"
                    autocomplete="off" />
            </div>

            <div id="resultContainer" class="mt-2 absolute w-full z-10">
                <ul id="resultList" class="rounded-xl shadow-2xl max-h-80 overflow-auto">
                    <!-- Hasil pencarian akan ditampilkan di sini oleh JavaScript -->
                </ul>
            </div>
        </div>
    </div>

    <script>
        const searchInput = document.getElementById('searchInput');
        const resultList = document.getElementById('resultList');
        const resultContainer = document.getElementById('resultContainer');
        let debounceTimer;

        searchInput.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(async () => {
                const q = searchInput.value.trim();

                if (q.length < 2) {
                    resultList.innerHTML = '';
                    resultContainer.classList.add('hidden');
                    return;
                }

                resultContainer.classList.remove('hidden');
                resultList.innerHTML = '<li class="p-4 text-gray-400 animate-pulse">Searching...</li>';

                try {
                    const res = await fetch(`api_search.php?q=${encodeURIComponent(q)}`);
                    const data = await res.json();
                    renderResults(data);
                } catch (error) {
                    console.error('Search failed:', error);
                    resultList.innerHTML = '<li class="p-4 text-red-400">Error fetching results.</li>';
                }

            }, 300); // Debounce untuk 300ms
        });

        // Sembunyikan hasil saat klik di luar
        document.addEventListener('click', (e) => {
            if (!searchInput.contains(e.target) && !resultContainer.contains(e.target)) {
                resultContainer.classList.add('hidden');
            }
        });

        function renderResults(data) {
            if (data.length === 0) {
                resultList.innerHTML = '<li class="p-4 text-gray-500">No results found.</li>';
                return;
            }

            resultList.innerHTML = data.map(item => {
                const icon = item.type === 'player' ?
                    '<i class="fas fa-user-circle text-blue-400 w-5 text-center"></i>' :
                    '<i class="fas fa-shield-alt text-green-400 w-5 text-center"></i>';

                return `
            <li class="result-item">
                <a href="${item.url}" class="flex items-center p-4">
                    <div class="mr-4">${icon}</div>
                    <div>
                        <strong class="text-gray-100">${item.name}</strong>
                        <p class="text-xs text-gray-400">${item.info}</p>
                    </div>
                    <div class="ml-auto text-gray-500"><i class="fas fa-chevron-right fa-xs"></i></div>
                </a>
            </li>
        `;
            }).join('');
        }
    </script>

</body>

</html>