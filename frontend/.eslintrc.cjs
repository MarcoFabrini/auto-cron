/* eslint-env node */
/**
 * ESLint (config legacy eslintrc — ESLint 8.57).
 * Lo script `lint` usa `--ext ts,tsx`, quindi niente flat config.
 */
module.exports = {
  root: true,
  env: { browser: true, es2022: true },
  parser: '@typescript-eslint/parser',
  parserOptions: { ecmaVersion: 'latest', sourceType: 'module' },
  plugins: ['@typescript-eslint', 'react-hooks', 'react-refresh'],
  extends: [
    'eslint:recommended',
    'plugin:@typescript-eslint/recommended',
    'plugin:react-hooks/recommended',
  ],
  settings: { react: { version: '18.3' } },
  rules: {
    // Off: i componenti shadcn/ui co-locano le varianti (es. Button + buttonVariants)
    // e il router mescola elementi/loader → falsi positivi su un hint solo-HMR.
    'react-refresh/only-export-components': 'off',
    '@typescript-eslint/no-unused-vars': ['error', { argsIgnorePattern: '^_', varsIgnorePattern: '^_' }],
    '@typescript-eslint/no-explicit-any': 'warn',
  },
  overrides: [
    {
      // Service worker: contesto WebWorker, non browser.
      files: ['src/sw.ts'],
      env: { worker: true, browser: false },
    },
    {
      // I file di test usano i globali Vitest e mock liberi.
      files: ['src/**/*.test.{ts,tsx}', 'src/test/**'],
      env: { node: true },
      rules: { '@typescript-eslint/no-explicit-any': 'off' },
    },
  ],
};
