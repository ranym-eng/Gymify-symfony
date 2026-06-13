// Debug script for Chart.js issues
console.log('debug-chart.js loaded');

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM Content Loaded');
    console.log('Chart global object exists:', typeof Chart !== 'undefined');
    
    if (typeof Chart === 'undefined') {
        console.error('Chart.js not loaded! Attempting to load manually...');
        
        // Load Chart.js from CDN
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
        script.onload = function() {
            console.log('Chart.js loaded from CDN successfully');
            debugChartStatus();
        };
        script.onerror = function() {
            console.error('Failed to load Chart.js from CDN');
            // Try local script
            const localScript = document.createElement('script');
            localScript.src = '/js/plugin/chart.js/chart.min.js';
            localScript.onload = function() {
                console.log('Chart.js loaded from local path successfully');
                debugChartStatus();
            };
            localScript.onerror = function() {
                console.error('Failed to load Chart.js from local path');
            };
            document.head.appendChild(localScript);
        };
        document.head.appendChild(script);
    } else {
        debugChartStatus();
    }
    
    function debugChartStatus() {
        console.log('Chart.js version:', Chart.version);
        console.log('Available chart types:', Object.keys(Chart.registry.controllers).join(', '));
        
        // Check for canvas elements
        const canvasElements = document.querySelectorAll('canvas');
        console.log(`Found ${canvasElements.length} canvas elements:`, canvasElements);
        
        // Try to create a simple chart
        if (canvasElements.length > 0) {
            try {
                const ctx = canvasElements[0].getContext('2d');
                const testChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: ['Red', 'Blue', 'Yellow', 'Green', 'Purple', 'Orange'],
                        datasets: [{
                            label: 'Test Dataset',
                            data: [12, 19, 3, 5, 2, 3],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
                console.log('Test chart created successfully:', testChart);
            } catch (e) {
                console.error('Error creating test chart:', e);
            }
        }
    }
}); 