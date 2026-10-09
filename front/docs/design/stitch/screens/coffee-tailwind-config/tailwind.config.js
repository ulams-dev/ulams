/** @type {import('tailwindcss').Config} */
module.exports = {
  darkMode: ['class', '[data-theme="dark"]'],
  content: [
    './pages/**/*.{js,ts,jsx,tsx,mdx}',
    './components/**/*.{js,ts,jsx,tsx,mdx}',
    './app/**/*.{js,ts,jsx,tsx,mdx}',
    './src/**/*.{js,ts,jsx,tsx,mdx}',
  ],
  theme: {
    extend: {
      colors: {
        /* Foundation & Paper Canvas */
        bg: 'var(--color-bg, #f6f1e9)',
        surface: {
          DEFAULT: 'var(--color-surface, #fff8f5)',
          dim: 'var(--color-surface-dim, #efd5c6)',
          bright: 'var(--color-surface-bright, #fff8f5)',
          'container-lowest': 'var(--color-surface-container-lowest, #ffffff)',
          'container-low': 'var(--color-surface-container-low, #fff1ea)',
          container: 'var(--color-surface-container, #faebe3)',
          'container-high': 'var(--color-surface-container-high, #f4e5dd)',
          'container-highest': 'var(--color-surface-container-highest, #eedfd8)',
        },

        /* Roast Browns, Editorial Neutrals & Borders */
        text: {
          DEFAULT: 'var(--color-text, #2b1d14)',
          primary: 'var(--color-text-primary, #2b1d14)',
          secondary: 'var(--color-text-secondary, #5c4333)',
          muted: 'var(--color-text-muted, #826e63)',
          subtle: 'var(--color-text-subtle, #a89a90)',
        },
        border: {
          DEFAULT: 'var(--color-border, #d9cfc2)',
          subtle: 'var(--color-border-subtle, #e9e1d6)',
          strong: 'var(--color-border-strong, #bfaea0)',
        },

        /* Accents & Semantic Statuses */
        accent: {
          DEFAULT: 'var(--color-accent, #c2552d)',
          hover: 'var(--color-accent-hover, #a84521)',
          subtle: 'var(--color-accent-subtle, #fbece7)',
          muted: 'var(--color-accent-muted, #e5997d)',
        },
        secondary: {
          DEFAULT: 'var(--color-secondary, #7a8b6f)',
          hover: 'var(--color-secondary-hover, #65745b)',
          subtle: 'var(--color-secondary-subtle, #f1f4ee)',
        },
        success: {
          DEFAULT: 'var(--color-success, #3c7a52)',
          subtle: 'var(--color-success-subtle, #eef6f1)',
        },
        warning: {
          DEFAULT: 'var(--color-warning, #c27d1a)',
          subtle: 'var(--color-warning-subtle, #fdf6ec)',
        },
        error: {
          DEFAULT: 'var(--color-error, #b8332a)',
          subtle: 'var(--color-error-subtle, #fdf1f0)',
        },
      },

      fontFamily: {
        display: ['var(--font-display)', 'Playfair Display', 'Fraunces', 'Georgia', 'serif'],
        serif: ['var(--font-serif)', 'Playfair Display', 'Fraunces', 'Baskerville', 'serif'],
        sans: ['var(--font-sans)', 'Inter Tight', 'Inter', '-apple-system', 'BlinkMacSystemFont', 'sans-serif'],
        mono: ['var(--font-mono)', 'JetBrains Mono', 'Fira Code', 'Courier New', 'monospace'],
      },

      fontSize: {
        '2xs': ['var(--font-size-2xs, 0.6875rem)', { lineHeight: 'var(--line-height-tight, 1.15)' }],
        xs: ['var(--font-size-xs, 0.75rem)', { lineHeight: 'var(--line-height-snug, 1.3)' }],
        sm: ['var(--font-size-sm, 0.875rem)', { lineHeight: 'var(--line-height-normal, 1.5)' }],
        base: ['var(--font-size-base, 1rem)', { lineHeight: 'var(--line-height-normal, 1.5)' }],
        md: ['var(--font-size-md, 1.125rem)', { lineHeight: 'var(--line-height-relaxed, 1.7)' }],
        lg: ['var(--font-size-lg, 1.25rem)', { lineHeight: 'var(--line-height-snug, 1.3)' }],
        xl: ['var(--font-size-xl, 1.5rem)', { lineHeight: 'var(--line-height-snug, 1.3)' }],
        '2xl': ['var(--font-size-2xl, 1.875rem)', { lineHeight: 'var(--line-height-tight, 1.15)' }],
        '3xl': ['var(--font-size-3xl, 2.25rem)', { lineHeight: 'var(--line-height-tight, 1.15)' }],
        '4xl': ['var(--font-size-4xl, 3rem)', { lineHeight: 'var(--line-height-tight, 1.15)' }],
        '5xl': ['var(--font-size-5xl, 3.75rem)', { lineHeight: 'var(--line-height-tight, 1.15)' }],
      },

      spacing: {
        '3xs': 'var(--space-3xs, 0.125rem)',
        '2xs': 'var(--space-2xs, 0.25rem)',
        xs: 'var(--space-xs, 0.5rem)',
        sm: 'var(--space-sm, 0.75rem)',
        md: 'var(--space-md, 1rem)',
        lg: 'var(--space-lg, 1.5rem)',
        xl: 'var(--space-xl, 2rem)',
        '2xl': 'var(--space-2xl, 2.5rem)',
        '3xl': 'var(--space-3xl, 3rem)',
        '4xl': 'var(--space-4xl, 4rem)',
        '5xl': 'var(--space-5xl, 5rem)',
        '6xl': 'var(--space-6xl, 6rem)',
      },

      borderRadius: {
        none: 'var(--radius-none, 0px)',
        xs: 'var(--radius-xs, 2px)',
        sm: 'var(--radius-sm, 4px)',
        md: 'var(--radius-md, 6px)',
        lg: 'var(--radius-lg, 8px)',
        xl: 'var(--radius-xl, 12px)',
      },

      boxShadow: {
        sm: 'var(--shadow-sm, 0 1px 2px 0 rgba(43, 29, 20, 0.04))',
        md: 'var(--shadow-md, 0 4px 12px -2px rgba(43, 29, 20, 0.06), 0 2px 4px -1px rgba(43, 29, 20, 0.03))',
        lg: 'var(--shadow-lg, 0 12px 28px -6px rgba(43, 29, 20, 0.08), 0 4px 10px -2px rgba(43, 29, 20, 0.04))',
        letterpress: 'var(--shadow-letterpress, inset 0 1px 2px rgba(43, 29, 20, 0.08))',
      },

      lineHeight: {
        tight: 'var(--line-height-tight, 1.15)',
        snug: 'var(--line-height-snug, 1.3)',
        normal: 'var(--line-height-normal, 1.5)',
        relaxed: 'var(--line-height-relaxed, 1.7)',
        loose: 'var(--line-height-loose, 1.85)',
      },

      transitionDuration: {
        fast: 'var(--transition-fast, 150ms)',
        normal: 'var(--transition-normal, 250ms)',
        slow: 'var(--transition-slow, 350ms)',
      },
    },
  },
  plugins: [],
};
