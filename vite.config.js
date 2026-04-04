import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import fs from 'fs'

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue(),
        tailwindcss(),
    ],
    server: {
        host: '0.0.0.0',
        port: 3002,
         https: {
            key:  fs.readFileSync('./certs/localhost-key.pem'),
            cert: fs.readFileSync('./certs/localhost-cert.pem'),
        },
        hmr: {
            protocol: 'wss',
            host: 'localhost',
            port: 3002,
        },
    },
})