// Import Chart.js
import { Chart } from 'chart.js/auto';
import { startStimulusApp } from '@symfony/stimulus-bundle';

// Expose Chart globally
window.Chart = Chart;

// Start the Stimulus application
startStimulusApp();

// Export Chart
export { Chart }; 