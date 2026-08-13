import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    safelist: [
        'bg-orange-50', 'text-orange-600',
        'bg-blue-50', 'text-blue-600',
        'bg-amber-50', 'text-amber-600',
        'bg-red-50', 'text-red-600',
        'bg-slate-50', 'text-slate-600',
        'bg-purple-50', 'text-purple-600',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
