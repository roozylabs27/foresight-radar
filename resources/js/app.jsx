import './bootstrap';
import '../css/app.css';

import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ThemeProvider } from '@/Components/ThemeProvider';
import { I18nProvider } from '@/context/I18nContext';
import { IngestionProvider } from '@/context/IngestionContext';
import { Toaster } from '@/Components/ui/sonner';
import { registerFlashToasts } from '@/lib/flash-toast';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: async (name) => {
        const pages = import.meta.glob(['./Pages/**/*.jsx', './Pages/**/*.tsx'], { eager: false });
        const path = `./Pages/${name}.tsx` in pages ? `./Pages/${name}.tsx` : `./Pages/${name}.jsx`;
        const page = await resolvePageComponent(path, pages);
        const PageComponent = page.default;
        const WrappedPage = (props) => (
            <I18nProvider>
                <IngestionProvider>
                    <PageComponent {...props} />
                </IngestionProvider>
            </I18nProvider>
        );
        Object.assign(WrappedPage, PageComponent);
        return { ...page, default: WrappedPage };
    },
    setup({ el, App, props }) {
        const root = createRoot(el);
        registerFlashToasts(props.initialPage);

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
