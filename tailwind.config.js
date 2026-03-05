/** @type {import('tailwindcss').Config} */
export default {
    // Scan only the package's own files — never the host app.
    // This ensures the compiled CSS is self-contained and portable.
    content: [
        './resources/views/**/*.blade.php',
        './src/**/*.php',
    ],

    theme: {
        extend: {},
    },

    plugins: [],
};
