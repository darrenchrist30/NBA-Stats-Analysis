<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teams Stats - NBA Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;400&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Montserrat', sans-serif; }
        .stat-card {
            transition: transform 0.15s;
        }
        .stat-card:hover {
            transform: translateY(-4px) scale(1.03);
            box-shadow: 0 8px 24px 0 rgba(16,185,129,0.15);
        }
        .table-scroll {
            max-height: 420px;
            overflow-y: auto;
        }
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-thumb { background: #bbf7d0; border-radius: 4px; }

        body { font-family: 'Montserrat', sans-serif; }
        .hero-bg {
            background: linear-gradient(135deg, #e0e7ff 0%, #f0fdfa 100%);
        }
        .card {
            transition: transform 0.15s;
        }
        .card:hover {
            transform: translateY(-6px) scale(1.03);
            box-shadow: 0 8px 24px 0 rgba(59,130,246,0.15);
        }
        .pulse {
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }
    </style>
</head>
<body>

    <nav class="bg-white shadow p-4 flex justify-between items-center sticky top-0 z-50">
        <div class="flex items-center gap-2">
            <img src="image/nba.png" alt="NBA" class="h-8">
            <h1 class="text-2xl font-extrabold text-blue-700 tracking-tight drop-shadow">NBA Player Dashboard</h1>
        </div>
        <div class="space-x-4">
            <a href="index.php" class="text-gray-700 hover:text-blue-500 font-semibold">Home</a>
            <a href="player_stats.php" class="text-gray-700 hover:text-blue-500 font-semibold">Player Stats</a>
            <a href="teams_stats.php" class="text-gray-700 hover:text-blue-500 font-semibold">Team Stats</a>
            <a href="search_page.php" class="text-gray-700 hover:text-blue-500 font-semibold">Search</a>
        </div>
    </nav>
    
</body>
</html>