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
        'bg-amber-50', 'text-amber-600', 'bg-amber-500',
        'bg-red-50', 'text-red-600', 'bg-red-500',
        'bg-slate-50', 'text-slate-600',
        'bg-purple-50', 'text-purple-600',
        'bg-emerald-50', 'text-emerald-600',
        'text-green-600', 'bg-green-500',
        'bg-green-50', 'text-green-700',
        'bg-red-700', 'ring-red-300',
        'bg-gray-50', 'text-gray-700',
        // Dynamic per-result status coloring on the testing results page
        // (resources/views/testing/show.blade.php, `bg-{$statusColor}-*`).
        'border-green-100', 'text-green-900',
        'border-amber-100', 'text-amber-700', 'text-amber-900',
        'border-red-100', 'text-red-900',
        'border-gray-100', 'text-gray-900',
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
