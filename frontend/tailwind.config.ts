import type { Config } from 'tailwindcss';

/**
 * Design tokens from Design Philosophy §14–17:
 *   • Primary: Modern Blue
 *   • Secondary: Slate Gray
 *   • Success: Green, Warning: Orange, Danger: Red, Info: Light Blue
 *   • Spacing system: 8pt (8, 16, 24, 32, 48, 64)
 *   • Border radii: cards 12px, buttons 10px, inputs 10px, modals 16px
 */
const config: Config = {
  darkMode: ['class'],
  content: [
    './src/app/**/*.{ts,tsx}',
    './src/components/**/*.{ts,tsx}',
    './src/lib/**/*.{ts,tsx}',
  ],
  theme: {
    extend: {
      colors: {
        // Modern Blue (primary)
        primary: {
          50:  '#eff6ff',
          100: '#dbeafe',
          200: '#bfdbfe',
          300: '#93c5fd',
          400: '#60a5fa',
          500: '#3b82f6',
          600: '#2563eb',  // base
          700: '#1d4ed8',
          800: '#1e40af',
          900: '#1e3a8a',
          950: '#172554',
        },
        // Slate Gray (secondary, text, borders)
        secondary: {
          50:  '#f8fafc',
          100: '#f1f5f9',
          200: '#e2e8f0',
          300: '#cbd5e1',
          400: '#94a3b8',
          500: '#64748b',  // base
          600: '#475569',
          700: '#334155',
          800: '#1e293b',
          900: '#0f172a',
          950: '#020617',
        },
        success: { DEFAULT: '#16a34a', light: '#dcfce7', dark: '#15803d' },
        warning: { DEFAULT: '#ea580c', light: '#ffedd5', dark: '#c2410c' },
        danger:  { DEFAULT: '#dc2626', light: '#fee2e2', dark: '#b91c1c' },
        info:    { DEFAULT: '#0284c7', light: '#e0f2fe', dark: '#0369a1' },

        // Surface
        background: '#f8fafc',  // off-white
        surface:    '#ffffff',  // cards
        ink:        '#0f172a',  // body text
      },
      borderRadius: {
        card:   '12px',
        button: '10px',
        input:  '10px',
        modal:  '16px',
      },
      spacing: {
        // 8pt system; Tailwind already covers 2(8px), 4(16px), 6(24px),
        // 8(32px), 12(48px), 16(64px). Aliases for clarity:
        'gap-1': '8px',
        'gap-2': '16px',
        'gap-3': '24px',
        'gap-4': '32px',
        'gap-5': '48px',
        'gap-6': '64px',
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', '-apple-system', 'Segoe UI', 'Roboto', 'sans-serif'],
        mono: ['JetBrains Mono', 'Menlo', 'monospace'],
      },
      boxShadow: {
        card: '0 1px 3px 0 rgb(0 0 0 / 0.05), 0 1px 2px -1px rgb(0 0 0 / 0.05)',
        'card-hover': '0 4px 6px -1px rgb(0 0 0 / 0.08), 0 2px 4px -2px rgb(0 0 0 / 0.04)',
        modal: '0 25px 50px -12px rgb(0 0 0 / 0.25)',
      },
    },
  },
  plugins: [],
};

export default config;
