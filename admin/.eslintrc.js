module.exports = {
  extends: [require.resolve('@umijs/fabric/dist/eslint')],
  globals: {
    ANT_DESIGN_PRO_ONLY_DO_NOT_USE_IN_YOUR_PRODUCTION: true,
    page: true,
    REACT_APP_ENV: true,
  },
  parser: '@typescript-eslint/parser',
  rules: {
    'react-hooks/exhaustive-deps': 'off',
    // H5P/Lumi is GPL: it runs only in the separate api/h5p service, framed via iframe
    // styled-components was removed: style with CSS Modules / less and CSS variables
    'no-restricted-imports': [
      'error',
      {
        paths: [
          {
            name: 'styled-components',
            message: 'styled-components was removed; use a CSS Module (*.module.css) or less.',
          },
        ],
        patterns: [
          {
            group: ['@lumieducation/*', 'h5p-*', '@escolalms/h5p-react'],
            message: 'GPL H5P code must stay in api/h5p; use H5PFrame / H5PEditorFrame instead.',
          },
          {
            group: ['styled-components/*', 'babel-plugin-styled-components'],
            message: 'styled-components was removed; use a CSS Module (*.module.css) or less.',
          },
        ],
      },
    ],
  },
};
