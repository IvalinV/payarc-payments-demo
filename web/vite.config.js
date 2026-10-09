import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [react()],
    build: {
        outDir: 'dist',
        emptyOutDir: true,
    },
    test: {
        environment: 'jsdom',
        setupFiles: ['./resources/js/portal/test-setup.js'],
        globals: true,
        include: ['resources/js/portal/**/*.test.{js,jsx,ts,tsx}'],
    },
});
