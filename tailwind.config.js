import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{vue,js,ts}',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"Poppins"', ...defaultTheme.fontFamily.sans],
                display: ['"Poppins"', ...defaultTheme.fontFamily.sans],
                heading: ['"Poppins"', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Design tokens SignTech — #34B182, #C29B40, #5FAB6F
                primary: {
                    50: '#eefaf4',
                    100: '#d5f3e5',
                    200: '#ade6cc',
                    300: '#7cd3ae',
                    400: '#52c296',
                    DEFAULT: '#34B182',
                    dark: '#279069',
                    darker: '#1e7253',
                },
                secondary: {
                    50: '#fdf9ef',
                    100: '#f9f0d9',
                    200: '#f1deaf',
                    300: '#e4c87c',
                    400: '#d4b256',
                    DEFAULT: '#C29B40',
                    dark: '#a58230',
                    darker: '#836724',
                },
                accent: {
                    50: '#f1f9f3',
                    100: '#ddf2e3',
                    200: '#bde4c7',
                    300: '#97d2a3',
                    400: '#73be82',
                    DEFAULT: '#5FAB6F',
                    dark: '#4d915b',
                    darker: '#3d7348',
                },
                brand: {
                    mint: '#34B182',
                    gold: '#C29B40',
                    green: '#5FAB6F',
                },
                surface: {
                    DEFAULT: '#f6f9f7',
                    border: '#e2ede6',
                    card: '#ffffff',
                    muted: '#fbfdfc',
                },
                ink: {
                    DEFAULT: '#14221d',
                    muted: '#52685d',
                    soft: '#859c90',
                },
            },
            borderRadius: {
                card: '1.25rem',
            },
            boxShadow: {
                card: '0 1px 3px rgba(20, 34, 29, 0.04), 0 6px 20px -6px rgba(20, 34, 29, 0.07)',
                lift: '0 3px 6px rgba(20, 34, 29, 0.04), 0 14px 34px -8px rgba(20, 34, 29, 0.12)',
                glow: '0 8px 24px -6px rgba(52, 177, 130, 0.38)',
                'glow-gold': '0 8px 24px -6px rgba(194, 155, 64, 0.38)',
            },
        },
    },

    plugins: [forms],
};
