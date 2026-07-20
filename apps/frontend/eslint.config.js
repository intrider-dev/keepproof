import js from '@eslint/js'
import eslintConfigPrettier from 'eslint-config-prettier'
import pluginVue from 'eslint-plugin-vue'
import globals from 'globals'
import tseslint from 'typescript-eslint'

export default tseslint.config(
  {
    ignores: ['dist/**', 'coverage/**', 'src/api/generated/**', '../../output/**'],
  },
  js.configs.recommended,
  ...tseslint.configs.recommended,
  ...pluginVue.configs['flat/recommended'],
  {
    files: ['src/**/*.{ts,vue}'],
    languageOptions: {
      globals: globals.browser,
      parserOptions: {
        parser: tseslint.parser,
      },
    },
    rules: {
      '@typescript-eslint/consistent-type-imports': [
        'error',
        { prefer: 'type-imports', fixStyle: 'inline-type-imports' },
      ],
      '@typescript-eslint/no-explicit-any': 'error',
      'vue/multi-word-component-names': 'off',
      'vue/no-v-html': 'error',
      'vue/require-default-prop': 'off',
    },
  },
  {
    files: ['*.{js,ts}', 'e2e/**/*.ts', 'scripts/**/*.mjs'],
    languageOptions: {
      globals: globals.node,
    },
  },
  eslintConfigPrettier,
)
