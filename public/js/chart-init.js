// Initialisation des graphiques Chart.js
document.addEventListener('DOMContentLoaded', function() {
    // Forcer le rafraîchissement des graphiques
    if (typeof Chart !== 'undefined') {
        console.log('Chart.js is loaded and global object available');
        initializeCharts();
    } else {
        console.log('Chart.js not found, attempting to load it');
        
        // Try to load Chart.js from CDN or local path
        const chartScript = document.createElement('script');
        chartScript.src = "/js/plugin/chart.js/chart.min.js";
        chartScript.onload = function() {
            console.log('Chart.js loaded successfully');
            initializeCharts();
        };
        chartScript.onerror = function() {
            console.error('Failed to load Chart.js from local path, trying CDN');
            const cdnScript = document.createElement('script');
            cdnScript.src = "https://cdn.jsdelivr.net/npm/chart.js";
            cdnScript.onload = function() {
                console.log('Chart.js loaded from CDN successfully');
                initializeCharts();
            };
            document.head.appendChild(cdnScript);
        };
        document.head.appendChild(chartScript);
    }
    
    function initializeCharts() {
        console.log('Initializing charts...');
        
        // Réinitialiser les Canvas pour s'assurer qu'ils sont propres
        document.querySelectorAll('canvas').forEach(canvas => {
            if (canvas.chart) {
                console.log('Destroying existing chart instance');
                canvas.chart.destroy();
            }
        });
        
        // Attendre un court moment pour permettre au DOM de se mettre à jour
        setTimeout(function() {
            // Rafraîchir les graphiques
            if (window.renderChartjsElements) {
                console.log('Calling renderChartjsElements()');
                window.renderChartjsElements();
            } else {
                console.log('renderChartjsElements function not found, using stimulus controllers');
                
                // Attempt to initialize charts using Chart.js directly
                document.querySelectorAll('canvas.chart-js').forEach((canvas, index) => {
                    const ctx = canvas.getContext('2d');
                    let config;
                    
                    try {
                        config = JSON.parse(canvas.dataset.config || '{}');
                        console.log(`Creating chart ${index+1} with config:`, config);
                        new Chart(ctx, config);
                    } catch (e) {
                        console.error('Error creating chart:', e);
                    }
                });
            }
        }, 100);
    }
}); 