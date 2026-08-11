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
            maxWidth: {
                app: '90rem',
            },
            borderRadius: {
                'ui-sm': 'var(--ui-radius-sm)',
                ui: 'var(--ui-radius-md)',
                'ui-lg': 'var(--ui-radius-lg)',
            },
            boxShadow: {
                raised: 'var(--ui-shadow-raised)',
            },
            colors: {
                canvas: 'rgb(var(--ui-page) / <alpha-value>)',
                surface: {
                    DEFAULT: 'rgb(var(--ui-surface) / <alpha-value>)',
                    subtle: 'rgb(var(--ui-surface-subtle) / <alpha-value>)',
                    muted: 'rgb(var(--ui-surface-muted) / <alpha-value>)',
                },
                line: {
                    DEFAULT: 'rgb(var(--ui-border) / <alpha-value>)',
                    strong: 'rgb(var(--ui-border-strong) / <alpha-value>)',
                },
                ink: {
                    DEFAULT: 'rgb(var(--ui-text) / <alpha-value>)',
                    secondary: 'rgb(var(--ui-text-secondary) / <alpha-value>)',
                    muted: 'rgb(var(--ui-text-muted) / <alpha-value>)',
                },
                brand: {
                    DEFAULT: 'rgb(var(--ui-brand) / <alpha-value>)',
                    hover: 'rgb(var(--ui-brand-hover) / <alpha-value>)',
                    soft: 'rgb(var(--ui-brand-soft) / <alpha-value>)',
                },
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
