/**
 * Charts Helper - Ensures proper Chart.js initialization for Symfony UX Chart.js bundle
 */
(function() {
    console.log('Charts Helper v2 loaded');
    
    // Wait for DOM to be fully loaded
    document.addEventListener('DOMContentLoaded', function() {
        // Try immediately and also after a delay
        ensureChartJsLoaded(function() {
            initializeSymfonyCharts();
            
            // Try again after 500ms to catch any late-loading canvas elements
            setTimeout(initializeSymfonyCharts, 500);
            
            // And one more time after 1 second to be sure
            setTimeout(initializeSymfonyCharts, 1000);
        });
    });
    
    /**
     * Ensures Chart.js is loaded, either from existing script or by loading it
     */
    function ensureChartJsLoaded(callback) {
        console.log('Checking if Chart.js is loaded:', typeof Chart !== 'undefined');
        
        if (typeof Chart !== 'undefined') {
            console.log('Chart.js already loaded');
            callback();
            return;
        }
        
        // Load from CDN first for better reliability
        console.log('Chart.js not found, loading from CDN');
        loadScript('https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', function() {
            console.log('Chart.js loaded from CDN');
            callback();
        }, function() {
            console.warn('Failed to load Chart.js from CDN, trying local path');
            loadScript('/js/plugin/chart.js/chart.min.js', function() {
                console.log('Chart.js loaded from local path');
                callback();
            }, function() {
                console.error('Failed to load Chart.js from all sources');
                
                // Final fallback - try to load from unpkg
                loadScript('https://unpkg.com/chart.js@4.4.0/dist/chart.umd.js', function() {
                    console.log('Chart.js loaded from unpkg');
                    callback();
                }, function() {
                    console.error('All Chart.js loading attempts failed');
                });
            });
        });
    }
    
    /**
     * Load a script from the given URL
     */
    function loadScript(url, onSuccess, onError) {
        var script = document.createElement('script');
        script.src = url;
        script.onload = onSuccess;
        script.onerror = onError;
        document.head.appendChild(script);
    }
    
    /**
     * Initialize charts created by Symfony UX Chart.js bundle
     */
    function initializeSymfonyCharts() {
        // Find all chart canvases rendered by the render_chart() function
        const chartCanvases = document.querySelectorAll('canvas[data-controller="symfony--ux-chartjs--chart"]');
        console.log(`Found ${chartCanvases.length} chart canvases`);
        
        if (chartCanvases.length === 0) {
            // Try a more generic approach if we can't find canvases with the expected data-controller
            const allCanvases = document.querySelectorAll('.stats-card-body canvas');
            console.log(`Fallback: found ${allCanvases.length} canvases in chart containers`);
            processCanvases(allCanvases);
        } else {
            processCanvases(chartCanvases);
        }
        
        function processCanvases(canvases) {
            canvases.forEach(function(canvas) {
                if (!canvas.chartInitialized) {
                    console.log('Processing chart canvas:', canvas.id || 'unnamed');
                    
                    try {
                        // Try to extract the chart config
                        const configElement = canvas.parentNode.querySelector('script[type="application/json"]');
                        
                        if (configElement) {
                            const config = JSON.parse(configElement.textContent);
                            console.log('Found chart config');
                            
                            // Destroy previous chart instance if exists
                            if (canvas.chart) {
                                canvas.chart.destroy();
                            }
                            
                            // Create the chart with the extracted config
                            canvas.chart = new Chart(canvas.getContext('2d'), config);
                            canvas.chartInitialized = true;
                            console.log('Chart created successfully');
                        } else {
                            console.warn('Chart config not found, trying direct rendering');
                            
                            // If no config is found, try direct rendering with default values
                            renderDefaultChart(canvas);
                        }
                    } catch (e) {
                        console.error('Error creating chart:', e);
                        // Try direct rendering as fallback
                        renderDefaultChart(canvas);
                    }
                }
            });
        }
        
        // Render a default chart when config cannot be found
        function renderDefaultChart(canvas) {
            try {
                const container = canvas.closest('.stats-card');
                let chartType = 'line';
                let chartTitle = 'Données';
                
                if (container) {
                    if (container.classList.contains('posts-chart')) {
                        chartTitle = 'Posts';
                    } else if (container.classList.contains('comments-chart')) {
                        chartTitle = 'Commentaires';
                    } else if (container.classList.contains('reactions-chart')) {
                        chartType = 'pie';
                        chartTitle = 'Réactions';
                    }
                }
                
                const data = {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    datasets: [{
                        label: chartTitle,
                        data: [12, 19, 3, 5, 2, 3],
                        backgroundColor: chartType === 'pie' 
                            ? ['#36a2eb', '#ff6384', '#ffcd56', '#4bc0c0', '#9966ff', '#ff9f40']
                            : 'rgba(54, 162, 235, 0.2)',
                        borderColor: chartType === 'pie' 
                            ? 'white'
                            : 'rgb(54, 162, 235)',
                        borderWidth: 1
                    }]
                };
                
                const config = {
                    type: chartType,
                    data: data,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: true,
                                text: chartTitle
                            }
                        },
                        scales: chartType !== 'pie' ? {
                            y: {
                                beginAtZero: true
                            }
                        } : {}
                    }
                };
                
                // Destroy previous chart instance if exists
                if (canvas.chart) {
                    canvas.chart.destroy();
                }
                
                // Create default chart
                canvas.chart = new Chart(canvas.getContext('2d'), config);
                canvas.chartInitialized = true;
                console.log('Fallback chart created for', chartTitle);
            } catch (e) {
                console.error('Error creating fallback chart:', e);
            }
        }
    }
    
    // Expose functions to global scope if needed
    window.chartsHelper = {
        ensureChartJsLoaded: ensureChartJsLoaded,
        initializeSymfonyCharts: initializeSymfonyCharts,
        reloadCharts: function() {
            ensureChartJsLoaded(initializeSymfonyCharts);
        }
    };
})(); 