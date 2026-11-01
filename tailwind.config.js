import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './app/Livewire/**/*.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Tajawal', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                night: {
                    DEFAULT: '#120d2b',
                    800: '#1b1540',
                    700: '#251d57',
                    600: '#30266e',
                },
                paper: '#f7f2e7',
                card: '#fff3dc',
                lantern: {
                    DEFAULT: '#ffaa33',
                    light: '#ffc76b',
                    dark: '#e08a12',
                },
                teamA: {
                    DEFAULT: '#22d3ee',
                    dark: '#0e7490',
                },
                teamB: {
                    DEFAULT: '#fb7185',
                    dark: '#be123c',
                },
            },
            keyframes: {
                pop: {
                    '0%': { transform: 'scale(.85)', opacity: '0' },
                    '100%': { transform: 'scale(1)', opacity: '1' },
                },
                glow: {
                    '0%, 100%': { boxShadow: '0 0 0 0 rgba(255,170,51,.0)' },
                    '50%': { boxShadow: '0 0 28px 4px rgba(255,170,51,.35)' },
                },
            },
            animation: {
                pop: 'pop .25s ease-out',
                glow: 'glow 2s ease-in-out infinite',
            },
        },
    },
    plugins: [],
};
