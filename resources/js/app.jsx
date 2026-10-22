import './bootstrap';
import '../css/app.css';

import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ThemeProvider } from '@/Components/ThemeProvider';
import { Toaster } from '@/Components/ui/sonner';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => {
        const pages = import.meta.glob(['./Pages/**/*.jsx', './Pages/**/*.tsx'], { eager: false });
        const path = `./Pages/${name}.tsx` in pages ? `./Pages/${name}.tsx` : `./Pages/${name}.jsx`;
        return resolvePageComponent(path, pages);
    },
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <ThemeProvider>
                <App {...props} />
                <Toaster position="top-right" closeButton />
            </ThemeProvider>
        );
    },
    progress: {
        color: 'var(--primary)',
    },
});
