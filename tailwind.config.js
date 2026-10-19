import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

// Theme tokens live in resources/css/app.css (:root = light, .dark = dark).
// gray / white / indigo are remapped to them so every Jetstream screen follows the theme,
// and "indigo" becomes the MetMemo green accent.
const token = (name) => `rgb(var(--${name}) / <alpha-value>)`;
const scale = (prefix, steps) => Object.fromEntries(steps.map((step) => [step, token(`${prefix}-${step}`)]));

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                mono: ['"JetBrains Mono"', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                white: token('surface'),
                gray: scale('gray', [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950]),
                indigo: {
                    50: token('accent-soft'),
                    100: token('accent-soft'),
                    200: token('accent-soft'),
                    300: token('accent'),
                    400: token('accent'),
                    500: token('accent'),
                    600: token('accent-text'),
                    700: token('accent-strong'),
                    800: token('accent-strong'),
                    900: token('accent-strong'),
                },
                accent: {
                    DEFAULT: token('accent'),
                    fg: token('accent-fg'),
                },
                page: token('page'),
            },
        },
    },

    plugins: [forms, typography],
};
