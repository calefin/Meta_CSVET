import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './app/Livewire/**/*.php',
        './app/View/**/*.php',
    ],
    theme: {
        screens: {
            xs: '360px',
            md: '768px',
            lg: '1024px',
            xl: '1440px',
        },
        extend: {
            colors: {
                'ifa-green': '#2D6A4F',
                'orisa-earth': '#7B4F2E',
                'sacred-sand': '#FAF7F0',
                'orun-blue': '#4A7DAA',
                ebony: '#1A1A1A',
                danger: '#C0392B',
                success: '#27AE60',
            },
            fontFamily: {
                display: ['"Playfair Display"', 'serif'],
                sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                mono: ['"JetBrains Mono"', 'ui-monospace', 'SFMono-Regular', 'monospace'],
            },
        },
    },
    plugins: [typography],
};
