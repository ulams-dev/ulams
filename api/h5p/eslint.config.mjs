import js from '@eslint/js';
import tseslint from 'typescript-eslint';

export default tseslint.config(
    { ignores: ['dist/**', 'node_modules/**', 'h5p/**'] },
    js.configs.recommended,
    ...tseslint.configs.recommended,
    {
        rules: {
            '@typescript-eslint/no-explicit-any': 'off',
            '@typescript-eslint/no-unused-vars': [
                'error',
                { argsIgnorePattern: '^_', varsIgnorePattern: '^_' }
            ]
        }
    },
    {
        // browser code of the embed pages; TypeScript (DOM lib) checks globals
        files: ['embed-client/**/*.ts'],
        rules: { 'no-undef': 'off' }
    },
    {
        files: ['scripts/**/*.mjs'],
        languageOptions: { globals: { console: 'readonly', process: 'readonly' } }
    }
);
