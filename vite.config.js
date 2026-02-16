import { defineConfig } from 'vite';

export default defineConfig({
    build: {
        emptyOutDir: false,
        manifest: true,
        rollupOptions: {
            input: ['resources/js/register.js'],
            output: {
                entryFileNames: 'js/full-calendar.js',
                chunkFileNames: 'js/[name]-[hash].js',
                assetFileNames: file => {
                    let ext = file.name.split('.').pop()
                    if (ext === 'css') {
                        return 'css/full-calendar.css'
                    }

                    if (['woff', 'woff2', 'ttf', 'eot'].includes(ext)) {
                        return 'fonts/[name].[ext]'
                    }

                    return 'assets/[name].[ext]'
                }
            }
        },
        outDir: 'public',
        format: 'iife', // Immediately Invoked Function Expression
        target: 'es2015',
        minify: 'terser',
    },
});
