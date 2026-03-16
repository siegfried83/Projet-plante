/* ============================================
   JAVASCRIPT BAC PAGE - Toggles, Tabs, Charts
   ============================================ */

// Fonction pour afficher/masquer le formulaire d'ajout de plante
function togglePlantSection() {
    const section = document.getElementById('plantFormSection');
    const btn = document.getElementById('togglePlantForm');
    const text = document.getElementById('toggleText');
    
    section.classList.toggle('visible');
    btn.classList.toggle('active');
    
    if (section.classList.contains('visible')) {
        text.textContent = 'Masquer le formulaire';
        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } else {
        text.textContent = 'Ajouter / Gérer une plante';
    }
}

function showTab(id, btn) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    btn.classList.add('active');
}

// Configuration commune des graphiques
const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false }
    },
    scales: {
        x: {
            grid: { color: 'rgba(0,0,0,0.05)' },
            ticks: { font: { size: 10 }, maxRotation: 45 }
        },
        y: {
            grid: { color: 'rgba(0,0,0,0.05)' },
            ticks: { font: { size: 11 } }
        }
    },
    elements: {
        line: { tension: 0.3, borderWidth: 2 },
        point: { radius: 3, hoverRadius: 5 }
    }
};

// Initialisation des graphiques au chargement du DOM
document.addEventListener('DOMContentLoaded', function() {
    // Utiliser la variable globale historyData définie dans bac.php
    if (typeof historyData === 'undefined' || !historyData.timestamps || historyData.timestamps.length === 0) {
        return;
    }
    
    if (historyData.timestamps && historyData.timestamps.length > 0) {
        // Graphique Température
        if (document.getElementById('tempChart')) {
            new Chart(document.getElementById('tempChart'), {
                type: 'line',
                data: {
                    labels: historyData.timestamps,
                    datasets: [{
                        label: 'Température (°C)',
                        data: historyData.temp,
                        borderColor: '#e74c3c',
                        backgroundColor: 'rgba(231, 76, 60, 0.1)',
                        fill: true
                    }]
                },
                options: {
                    ...chartOptions,
                    scales: {
                        ...chartOptions.scales,
                        y: { ...chartOptions.scales.y, suggestedMin: 15, suggestedMax: 35 }
                    }
                }
            });
        }
        
        // Graphique Humidité
        if (document.getElementById('humidityChart')) {
            new Chart(document.getElementById('humidityChart'), {
                type: 'line',
                data: {
                    labels: historyData.timestamps,
                    datasets: [{
                        label: 'Humidité (%)',
                        data: historyData.humidity,
                        borderColor: '#3498db',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        fill: true
                    }]
                },
                options: {
                    ...chartOptions,
                    scales: {
                        ...chartOptions.scales,
                        y: { ...chartOptions.scales.y, suggestedMin: 0, suggestedMax: 100 }
                    }
                }
            });
        }
        
        // Graphique pH
        if (document.getElementById('phChart')) {
            new Chart(document.getElementById('phChart'), {
                type: 'line',
                data: {
                    labels: historyData.timestamps,
                    datasets: [{
                        label: 'pH',
                        data: historyData.ph,
                        borderColor: '#9b59b6',
                        backgroundColor: 'rgba(155, 89, 182, 0.1)',
                        fill: true
                    }]
                },
                options: {
                    ...chartOptions,
                    scales: {
                        ...chartOptions.scales,
                        y: { ...chartOptions.scales.y, suggestedMin: 4, suggestedMax: 9 }
                    }
                }
            });
        }
    }
});
