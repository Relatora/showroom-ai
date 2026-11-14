import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { execSync } from 'node:child_process';
import { defineConfig, lazyPlugins } from 'vite-plus';

/**
 * Wayfinder generates typed route helpers by running `php artisan`. Plenty of us don't have PHP
 * installed on the host because everything PHP lives in Sail, so fall back to running it
 * inside the app container when there's no local `php`.
 */
function wayfinderCommand(): string {
    try {
        execSync('php -v', { stdio: 'ignore' });

        return 'php artisan wayfinder:generate';
    } catch {
        return 'docker compose exec -T laravel.test php artisan wayfinder:generate';
    }
}

export default defineConfig({
    plugins: lazyPlugins(() => [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
            fonts: [
                // Modelled on North American highway-sign lettering. One family for the whole UI.
                bunny('Overpass', {
                    weights: [400, 500, 600, 700, 800],
                }),
                // Only for codes: VINs and stock numbers.
                bunny('JetBrains Mono', {
                    weights: [400, 500],
                }),
            ],
        }),
        inertia(),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder({
            formVariants: true,
            command: wayfinderCommand(),
        }),
    ]),
    server: {
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/vendor/**',
            ],
        },
    },
    lint: {
        ignorePatterns: [
            'vendor/**',
            'node_modules/**',
            'public/**',
            'bootstrap/ssr/**',
            'tailwind.config.js',
            'resources/js/actions/**',
            'resources/js/components/ui/*',
            'resources/js/routes/**',
            'resources/js/wayfinder/**',
        ],
        options: {
            denyWarnings: true,
            typeAware: true,
        },
    },
    fmt: {
        printWidth: 80,
        tabWidth: 4,
        singleQuote: true,
        semi: true,
        singleAttributePerLine: false,
        htmlWhitespaceSensitivity: 'css',
        ignorePatterns: [
            '.github/**',
            'composer.json',
            'resources/js/components/ui/*',
            'resources/views/mail/*',
            // Written by `php artisan showroom:eval --write`; reformatting it would make every
            // eval run show up as a diff.
            'evals/results.md',
        ],
        sortTailwindcss: {
            functions: ['clsx', 'cn', 'cva'],
            stylesheet: 'resources/css/app.css',
        },
    },
});
