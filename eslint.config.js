import pluginVue from 'eslint-plugin-vue';
import { defineConfigWithVueTs, vueTsConfigs } from '@vue/eslint-config-typescript';
import prettier from '@vue/eslint-config-prettier/skip-formatting';

export default defineConfigWithVueTs(
    pluginVue.configs['flat/recommended'],
    vueTsConfigs.recommended,
    prettier,
    {
        rules: {
            'vue/multi-word-component-names': 'off',
        },
    },
);
