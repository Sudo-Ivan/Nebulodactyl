import { createRoot } from 'react-dom/client';

import App from '@/components/App';
import { initErrorReporting } from '@/lib/sentry';
import { applyBranding, applyTheme, currentTheme } from '@/lib/theme';
import type { SiteSettings } from '@/state/settings';

// Apply theme and brand overrides before the first paint so there is no
// flash of the wrong theme.
const siteConfiguration = (window as unknown as { SiteConfiguration?: SiteSettings }).SiteConfiguration;
applyBranding(siteConfiguration);
applyTheme(currentTheme(siteConfiguration));
initErrorReporting(siteConfiguration);

const container = document.getElementById('app');
if (container) {
    const root = createRoot(container);
    root.render(<App />);
} else {
    console.error('Failed to find the root element');
}
