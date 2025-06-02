<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Search Pemain NBA</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">

<h1 class="text-3xl font-bold mb-6">Cari Pemain NBA</h1>

<div class="max-w-xl mx-auto">
    <input
        type="text"
        id="searchInput"
        placeholder="Ketik nama pemain..."
        class="w-full p-3 rounded border border-gray-300"
        autocomplete="off"
    />
    <ul id="resultList" class="mt-4 bg-white rounded shadow max-h-64 overflow-auto"></ul>
</div>

<script>
const searchInput = document.getElementById('searchInput');
const resultList = document.getElementById('resultList');

searchInput.addEventListener('input', async () => {
    const q = searchInput.value.trim();
    if (q.length < 2) {
        resultList.innerHTML = '';
        return;
    }

    const res = await fetch(`search.php?q=${encodeURIComponent(q)}`);
    const data = await res.json();

    if (data.length === 0) {
        resultList.innerHTML = '<li class="p-2 text-gray-500">Tidak ada hasil</li>';
        return;
    }

    resultList.innerHTML = data.map(p => `
        <li class="border-b border-gray-200 p-2 hover:bg-gray-100 cursor-pointer">
            <strong>${p.name}</strong> — Posisi: ${p.pos} — Team: ${p.team}
        </li>
    `).join('');
});
</script>

</body>
</html>
