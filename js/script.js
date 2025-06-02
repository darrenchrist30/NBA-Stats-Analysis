// js/script.js

// Contoh: Menambahkan sedikit animasi (membutuhkan sedikit modifikasi CSS)
const tables = document.querySelectorAll('table');
tables.forEach(table => {
    table.classList.add('transition-opacity', 'duration-500');
    table.style.opacity = 1; // Pastikan awalnya terlihat

    table.addEventListener('mouseover', () => {
        table.style.boxShadow = '0 4px 8px rgba(0, 0, 0, 0.1)';
    });

    table.addEventListener('mouseout', () => {
        table.style.boxShadow = 'none';
    });
});

// ... Tambahkan interaksi lain sesuai kebutuhan ...