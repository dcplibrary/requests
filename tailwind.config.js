/** @type {import('tailwindcss').Config} */
export default {
    // Scan only the package's own files — never the host app.
    // This ensures the compiled CSS is self-contained and portable.
    content: [
        './resources/views/**/*.blade.php',
        './src/**/*.php',
    ],

    theme: {
        extend: {
            colors: {
                'dcpl-blue':   '#0075A3',
                'dcpl-orange': '#F47920',
                'dcpl-green':  '#8BC53F',
                'dcpl-purple': '#8B5CF6',
                'dcpl-gold':   '#F4B942',
                'dcpl-text':   '#4d6375',
            },
            fontFamily: {
                outfit: ['Outfit', 'sans-serif'],
            },
        },
    },

    plugins: [],
};
