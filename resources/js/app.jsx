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

const appName = import.meta.env.VITE_APP_NAME || 'Foresight Radar';

// One wrapper per page, built once. Inertia resolves the page again on every visit; a new
// wrapper function each time would be a new component type, so React would remount the page
// and `preserveState: true` (dialogs kept open on a validation error) could never work.
const wrappedPages = new Map();

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: async (name) => {
        if (wrappedPages.has(name)) return wrappedPages.get(name);

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
        const resolved = { ...page, default: WrappedPage };
        wrappedPages.set(name, resolved);
        return resolved;
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
