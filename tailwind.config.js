/** @type {import('tailwindcss').Config} */
export default {
  darkMode: 'class',
  content: ['./client/index.html', './client/src/**/*.{js,ts,tsx}'],
  theme: {
    extend: {
      colors: {
        /**
         * Admin “primary” palette — driven at runtime by CSS variables on `#sikshya-admin-root`
         * (see `client/src/lib/adminBrandTokens.ts` + `client/src/index.css` defaults).
         *
         * Tailwind v3.4 `rgb(var(--token) / <alpha-value>)` enables `bg-brand-500/40`, etc.
         */
        brand: {
          50: 'rgb(var(--sikshya-brand-50-rgb) / <alpha-value>)',
          100: 'rgb(var(--sikshya-brand-100-rgb) / <alpha-value>)',
          200: 'rgb(var(--sikshya-brand-200-rgb) / <alpha-value>)',
          300: 'rgb(var(--sikshya-brand-300-rgb) / <alpha-value>)',
          400: 'rgb(var(--sikshya-brand-400-rgb) / <alpha-value>)',
          500: 'rgb(var(--sikshya-brand-500-rgb) / <alpha-value>)',
          600: 'rgb(var(--sikshya-brand-600-rgb) / <alpha-value>)',
          700: 'rgb(var(--sikshya-brand-700-rgb) / <alpha-value>)',
          800: 'rgb(var(--sikshya-brand-800-rgb) / <alpha-value>)',
          900: 'rgb(var(--sikshya-brand-900-rgb) / <alpha-value>)',
          950: 'rgb(var(--sikshya-brand-950-rgb) / <alpha-value>)',
        },
        /**
         * Secondary "accent" palette — Sikshya logo purple. Use `bg-accent-600` etc
         * for Pro/upgrade surfaces, never `bg-indigo-*` (Tailwind default indigo
         * doesn't match the logo).
         */
        accent: {
          50: 'rgb(var(--sikshya-accent-50-rgb) / <alpha-value>)',
          100: 'rgb(var(--sikshya-accent-100-rgb) / <alpha-value>)',
          200: 'rgb(var(--sikshya-accent-200-rgb) / <alpha-value>)',
          300: 'rgb(var(--sikshya-accent-300-rgb) / <alpha-value>)',
          400: 'rgb(var(--sikshya-accent-400-rgb) / <alpha-value>)',
          500: 'rgb(var(--sikshya-accent-500-rgb) / <alpha-value>)',
          600: 'rgb(var(--sikshya-accent-600-rgb) / <alpha-value>)',
          700: 'rgb(var(--sikshya-accent-700-rgb) / <alpha-value>)',
          800: 'rgb(var(--sikshya-accent-800-rgb) / <alpha-value>)',
          900: 'rgb(var(--sikshya-accent-900-rgb) / <alpha-value>)',
          950: 'rgb(var(--sikshya-accent-950-rgb) / <alpha-value>)',
        },
        surface: {
          DEFAULT: '#ffffff',
          muted: '#f8fafc',
        },
      },
      fontFamily: {
        sans: [
          'Inter',
          'system-ui',
          '-apple-system',
          'Segoe UI',
          'Roboto',
          'Helvetica Neue',
          'Arial',
          'sans-serif',
        ],
      },
    },
  },
  plugins: [],
};
