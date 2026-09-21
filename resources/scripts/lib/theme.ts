import type { SiteSettings } from '@/state/settings';

export type Theme = 'dark' | 'light';

const STORAGE_KEY = 'nebulodactyl:theme';

/**
 * Resolve the active theme. A saved preference wins, then the configured
 * default, then the OS preference when the default is "system".
 */
export function currentTheme(settings?: SiteSettings): Theme {
    const saved = localStorage.getItem(STORAGE_KEY);
    if (saved === 'dark' || saved === 'light') {
        return saved;
    }

    const configured = settings?.theme?.defaultTheme ?? 'dark';
    if (configured === 'system') {
        return window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
    }
    return configured === 'light' ? 'light' : 'dark';
}

export function applyTheme(theme: Theme): void {
    document.documentElement.dataset.theme = theme;
    if (document.body) {
        document.body.dataset.theme = theme;
    }
}

export function setTheme(theme: Theme): void {
    localStorage.setItem(STORAGE_KEY, theme);
    applyTheme(theme);
}

export function toggleTheme(): Theme {
    const next: Theme = document.documentElement.dataset.theme === 'light' ? 'dark' : 'light';
    setTheme(next);
    return next;
}

/**
 * Apply operator configured brand colors. Any CSS color value is accepted,
 * unset values leave the stylesheet defaults alone.
 */
export function applyBranding(settings?: SiteSettings): void {
    const accent = settings?.theme?.accent;
    const accentForeground = settings?.theme?.accentForeground;
    const root = document.documentElement;

    if (accent) {
        root.style.setProperty('--color-accent', accent);
        root.style.setProperty('--color-brand', accent);
        root.style.setProperty('--color-primary', accent);
    }
    if (accentForeground) {
        root.style.setProperty('--color-accent-foreground', accentForeground);
    }
}
