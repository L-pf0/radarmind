// public/js/dashboardMatriz.js
// Gráfico de desempenho: lê os dados dos atributos data-* do canvas (vindos do Laravel).

document.addEventListener('DOMContentLoaded', function () {
    const canvas = document.getElementById('desempenhoChart');

    // Se não houver canvas (sem dados), não faz nada
    if (!canvas) return;

    // Converte os atributos JSON em arrays
    const labels = JSON.parse(canvas.dataset.labels);
    const valores = JSON.parse(canvas.dataset.values);

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Desempenho (%)',
                data: valores,
                backgroundColor: '#1e7a3e',
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, max: 100 }
            }
        }
    });
});