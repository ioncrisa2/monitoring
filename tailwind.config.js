import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                indigo: {
                    50: '#F2EFFF',
                    100: '#E8E3FF',
                    200: '#D6CEFF',
                    300: '#BEB0FE',
                    400: '#A38FFD',
                    500: '#886EFE',
                    600: '#6D4AFF',
                    700: '#5D3DE8',
                    800: '#4320DD',
                    900: '#4025B7',
                    950: '#2E1B82',
                },
            },
        },
    },

    plugins: [forms],
};
