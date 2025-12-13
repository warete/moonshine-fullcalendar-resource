import {defineConfig} from 'vite';
export default defineConfig({
    build: {
        emptyOutDir: false,
        manifest: true,
        rollupOptions: {
            input: ['resources/js/calendar.js'],
            output: {
                entryFileNames: `js/full-calendar.js`,
                assetFileNames: file => {
                    let ext = file.name.split('.').pop()
                    if (ext === 'css') {
                        return 'css/full-calendar.css'
                    }

                    if (ext === 'woff2') {
                        return 'fonts/[name].[ext]'
                    }

                    return 'assets/[name].[ext]'
                }
            }
        },
        outDir: 'public',
    },
});
