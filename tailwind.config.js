/** @type {import('tailwindcss').Config} */
import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

export default {
    // Tailwind only generates classes used in these files.
    // We scope to the *user archive* side here — Filament has its own Tailwind
    // build inside the filament/filament package, so we don't need to scan
    // filament views from this config.
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/Livewire/**/*.php',
        './app/View/Components/**/*.php',
    ],

    // Toggled via a .dark class on <html>. The theme switcher in the user layout
    // writes/reads localStorage.theme and toggles this class.
    darkMode: 'class',

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // SiteArchive brand palette — derived from #534AB7 (PDF brand color).
                // Hand-tuned ramp so dark surfaces (bg-brand-950, bg-brand-900)
                // match the OptiPixel reference UI.
                brand: {
                    50:  '#F1EFFB',
                    100: '#E0DCF6',
                    200: '#C2BAEE',
                    300: '#A197E3',
                    400: '#8276D4',
                    500: '#6457C4',
                    600: '#534AB7', // primary
                    700: '#443C97',
                    800: '#36307A',
                    900: '#2A265F',
                    950: '#17132F', // darkest surface
                },
                // Neutral surfaces matching the near-black panels in the reference.
                surface: {
                    50:  '#FAFAFA',
                    100: '#F4F4F5',
                    200: '#E4E4E7',
                    300: '#D4D4D8',
                    700: '#3F3F46',
                    800: '#27272A',
                    900: '#18181B',
                    950: '#09090B', // body bg in dark mode
                },
            },
        },
    },

    plugins: [forms],
};
