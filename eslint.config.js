import { defineConfigWithVueTs, vueTsConfigs } from '@vue/eslint-config-typescript';
import prettier from 'eslint-config-prettier';
import vue from 'eslint-plugin-vue';

/**
 * Import ORDER is deliberately not enforced here.
 *
 * `prettier-plugin-organize-imports` (see .prettierrc) already sorts and dedupes
 * imports on every format, and its ordering disagrees with `import/order`'s —
 * running both meant `npm run format` and `npm run lint` undid each other
 * forever. Prettier owns import order; ESLint owns correctness.
 */
export default defineConfigWithVueTs(
    vue.configs['flat/essential'],
    vueTsConfigs.recommended,
    {
        ignores: ['vendor', 'node_modules', 'public', 'bootstrap/ssr', 'tailwind.config.js', 'resources/js/components/ui/*'],
    },
    {
        rules: {
            'vue/multi-word-component-names': 'off',
            '@typescript-eslint/no-explicit-any': 'error',
            '@typescript-eslint/no-unused-vars': ['error', { argsIgnorePattern: '^_', varsIgnorePattern: '^_' }],
        },
    },
    prettier,
);
