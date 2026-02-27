import fs from "node:fs";
import path from "node:path";
import { defineConfig } from "vite";

export default defineConfig({
    plugins: [
        {
            name: "emit-full-calendar-css-asset",
            generateBundle() {
                this.emitFile({
                    type: "asset",
                    fileName: "css/full-calendar.css",
                    source: fs.readFileSync(
                        path.resolve(
                            __dirname,
                            "resources/css/full-calendar.css"
                        ),
                        "utf8"
                    ),
                });
            },
        },
    ],
    build: {
        emptyOutDir: false,
        manifest: true,
        esbuild: {
            drop: ["console"],
            pure: [
                "console.log",
                "console.info",
                "console.warn",
                "console.error",
                "console.debug",
            ],
        },
        rollupOptions: {
            input: ["resources/js/register.js"],
            output: {
                format: "iife",
                name: "MoonShineFullCalendar",
                entryFileNames: "js/full-calendar.js",
                chunkFileNames: "js/[name]-[hash].js",
                assetFileNames: (file) => {
                    let ext = file.name.split(".").pop();
                    if (ext === "css") {
                        return "css/full-calendar.css";
                    }

                    if (["woff", "woff2", "ttf", "eot"].includes(ext)) {
                        return "fonts/[name].[ext]";
                    }

                    return "assets/[name].[ext]";
                },
            },
        },
        outDir: "public",
        target: "es2015",
        minify: "esbuild",
    },
});
