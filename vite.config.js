import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/deliverable-charts.js',
                'resources/js/activity-form.js',
                'resources/js/agreement-form.js',
                'resources/js/user-form.js',
                'resources/js/logging-field-form.js',
                'resources/js/certification-history.js',
            ],
            refresh: true,
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
