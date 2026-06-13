import { Controller } from '@hotwired/stimulus';
import Chart from 'chart.js/auto';

export default class extends Controller {
    static targets = ['canvas'];
    static values = {
        config: Object
    };

    connect() {
        console.log('Chart controller connected');
        
        if (!this.hasCanvasTarget) {
            console.error('No canvas target found for chart controller');
            return;
        }
        
        // Wait a moment for the DOM to be ready
        setTimeout(() => this.initializeChart(), 100);
    }
    
    disconnect() {
        if (this.chart) {
            this.chart.destroy();
        }
    }
    
    initializeChart() {
        if (this.chart) {
            this.chart.destroy();
        }
        
        try {
            const ctx = this.canvasTarget.getContext('2d');
            let config = null;
            
            // Try to get config from the value
            if (this.hasConfigValue) {
                config = this.configValue;
                console.log('Using config from value', config);
            } else {
                // Try to get config from data attribute
                try {
                    config = JSON.parse(this.canvasTarget.dataset.config || '{}');
                    console.log('Using config from data attribute', config);
                } catch (e) {
                    console.error('Error parsing chart config:', e);
                }
            }
            
            if (!config || !config.type) {
                console.error('Invalid chart configuration');
                return;
            }
            
            // Create chart
            this.chart = new Chart(ctx, config);
            console.log('Chart initialized successfully');
        } catch (e) {
            console.error('Error initializing chart:', e);
        }
    }
} 