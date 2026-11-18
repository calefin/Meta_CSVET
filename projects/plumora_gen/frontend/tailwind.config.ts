import type { Config } from 'tailwindcss'

const config: Config = {
  content: ['./index.html', './src/**/*.{js,ts,jsx,tsx}'],
  theme: {
    extend: {
      colors: {
        plumora: {
          'green-deep': '#1a3d2b',
          'green-mid':  '#2d6a4f',
          gold:         '#c8922a',
          cream:        '#faf6f0',
          terracotta:   '#c45c3d',
          charcoal:     '#1e2620',
          mist:         '#e8f0eb',
        },
      },
      fontFamily: {
        display: ['"Cormorant Garamond"', 'Georgia', 'serif'],
        sans:    ['"DM Sans"', 'system-ui', 'sans-serif'],
      },
    },
  },
  plugins: [],
}

export default config
