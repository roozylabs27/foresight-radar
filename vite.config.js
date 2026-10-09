import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.jsx',
            refresh: true,
        }),
        react(),
    ],
    build: {
        chunkSizeWarningLimit: 600,
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('node_modules')) {
                        if (id.includes('react') || id.includes('react-dom') || id.includes('scheduler')) {
                            return 'vendor-react';
                        }
                        if (id.includes('antd') || id.includes('@ant-design')) {
                            return 'vendor-antd';
                        }
                        if (id.includes('@inertiajs')) {
                            return 'vendor-inertia';
                        }
                        if (id.includes('echarts') || id.includes('zrender')) {
                            return 'vendor-charts';
                        }
                        if (id.includes('dayjs') || id.includes('axios') || id.includes('qs')) {
                            return 'vendor-utils';
                        }
                    }
                },
            },
        },
    },
});
