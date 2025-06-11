<?php
include 'header.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Universal Search - NBA Universe</title>
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

        #searchInput {
            background-color: rgba(31, 41, 55, 0.5);
            border: 1px solid #374151;
            transition: all 0.2s ease-in-out;
        }

        #searchInput:focus {
            background-color: rgba(17, 24, 39, 0.8);
            border-color: #3B82F6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
            outline: none;
        }

        #resultList {
            background-color: #1F2937;
            border: 1px solid #374151;
        }

        .result-item {
            border-bottom: 1px solid #374151;
            transition: background-color 0.15s ease-in-out;
        }

        .result-item:hover {
            background-color: #374151;
        }

        .result-item:last-child {
            border-bottom: none;
        }

        .info-card {
            background: linear-gradient(145deg, rgba(31, 41, 55, 0.7), rgba(17, 24, 39, 0.7));
            border: 1px solid #374151;
            backdrop-filter: blur(10px);
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body class="antialiased">

    <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-16 md:py-24">
        <div class="text-center mb-12">
            <h1 class="text-5xl md:text-6xl font-bold text-gray-100 font-teko tracking-wider uppercase">UNIVERSAL SEARCH</h1>
            <p class="text-lg text-gray-400 mt-2">Find any player or team in the NBA Universe.</p>
        </div>

        <!-- Search Input and Dropdown List Container -->
        <div class="max-w-2xl mx-auto relative">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <i class="fas fa-search text-gray-500"></i>
                </div>
                <input type="text" id="searchInput" placeholder="Search for 'LeBron James', 'Lakers'..." class="w-full p-4 pl-12 text-lg rounded-full text-gray-200" autocomplete="off" />
            </div>

            <div id="resultContainer" class="mt-2 absolute w-full z-10 hidden">
                <ul id="resultList" class="rounded-xl shadow-2xl max-h-80 overflow-auto">
                    <!-- Hasil pencarian akan ditampilkan di sini -->
                </ul>
            </div>
        </div>

        <!-- Info Card Container -->
        <div id="infoCardContainer" class="max-w-4xl mx-auto mt-12">
            <!-- Info Card akan dirender di sini -->
        </div>
    </div>

    <script>
        const searchInput = document.getElementById('searchInput');
        const resultList = document.getElementById('resultList');
        const resultContainer = document.getElementById('resultContainer');
        const infoCardContainer = document.getElementById('infoCardContainer');
        let debounceTimer;

        searchInput.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(async () => {
                const q = searchInput.value.trim();

                if (q.length < 3) {
                    resultList.innerHTML = '';
                    resultContainer.classList.add('hidden');
                    infoCardContainer.innerHTML = ''; // Kosongkan card juga
                    return;
                }

                // Tampilkan loading di dropdown, dan kosongkan card
                resultContainer.classList.remove('hidden');
                resultList.innerHTML = '<li class="p-4 text-gray-400 animate-pulse">Searching...</li>';
                infoCardContainer.innerHTML = '';

                try {
                    const res = await fetch(`api_search.php?q=${encodeURIComponent(q)}`);
                    const data = await res.json();

                    if (data.result_type === 'card') {
                        renderInfoCard(data.content);
                        resultContainer.classList.add('hidden'); // Sembunyikan dropdown
                    } else if (data.result_type === 'list') {
                        renderResultList(data.content);
                    } else {
                        resultList.innerHTML = '<li class="p-4 text-gray-500">No results found.</li>';
                    }

                } catch (error) {
                    console.error('Search failed:', error);
                    resultList.innerHTML = '<li class="p-4 text-red-400">Error fetching results.</li>';
                }

            }, 350); // Debounce untuk 350ms
        });

        // Sembunyikan dropdown saat klik di luar
        document.addEventListener('click', (e) => {
            if (!searchInput.contains(e.target) && !resultContainer.contains(e.target)) {
                resultContainer.classList.add('hidden');
            }
        });

        function renderResultList(data) {
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

        function renderInfoCard(cardData) {
            let detailsHtml = '';
            let extraSectionHtml = '';

            if (cardData.type === 'player') {
                for (const [key, value] of Object.entries(cardData.biodata)) {
                    if (value && value.trim() !== 'N/A' && value.trim() !== ',') {
                        detailsHtml += `<div class="flex justify-between py-2.5 border-b border-gray-700/50"><dt class="text-sm font-medium text-gray-400">${key}</dt><dd class="text-sm text-gray-200 text-right">${value}</dd></div>`;
                    }
                }
                if (cardData.achievements && cardData.achievements.length > 0) {
                    extraSectionHtml = `
                        <h3 class="text-lg font-bold font-teko tracking-wide mt-6 mb-3 text-blue-300">Notable Awards</h3>
                        <ul class="list-disc list-inside space-y-1.5 text-gray-300 text-sm">
                            ${cardData.achievements.map(ach => `<li>${ach}</li>`).join('')}
                        </ul>`;
                }
            } else if (cardData.type === 'team') {
                for (const [key, value] of Object.entries(cardData.details)) {
                    if (value) {
                        detailsHtml += `<div class="flex justify-between py-2.5 border-b border-gray-700/50"><dt class="text-sm font-medium text-gray-400">${key}</dt><dd class="text-sm text-gray-200 font-semibold text-right">${value}</dd></div>`;
                    }
                }
            }

            const cardHtml = `
                <div class="info-card rounded-2xl p-6 md:p-8 shadow-2xl flex flex-col md:flex-row gap-8">
                    <div class="md:w-1/3 flex-shrink-0 text-center">
                        <img src="${cardData.imageUrl}" alt="${cardData.name}" class="rounded-lg w-full max-w-[250px] mx-auto object-cover bg-gray-800 aspect-[4/3]" onerror="this.style.display='none'">
                        <a href="${cardData.url}" class="inline-block mt-4 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-5 rounded-full text-sm transition-colors">
                            View Full Profile <i class="fas fa-arrow-right fa-xs ml-1"></i>
                        </a>
                    </div>
                    <div class="md:w-2/3">
                        <h2 class="text-4xl font-bold font-teko tracking-wider uppercase">${cardData.name}</h2>
                        <p class="mt-2 text-gray-300 leading-relaxed">${cardData.description}</p>
                        
                        <div class="mt-6">
                            <dl>${detailsHtml}</dl>
                        </div>
                        
                        ${extraSectionHtml}
                    </div>
                </div>
            `;
            infoCardContainer.innerHTML = cardHtml;
        }
    </script>
</body>

</html>