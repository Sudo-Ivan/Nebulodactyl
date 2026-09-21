import { type Action, action } from 'easy-peasy';

export interface SiteSettings {
    name: string;
    locale: string;
    timezone: string;
    logo?: string | null;
    customNavItems?: {
        label: string;
        url: string;
        icon: string;
    }[];
    theme?: {
        accent?: string | null;
        accentForeground?: string | null;
        defaultTheme?: 'dark' | 'light' | 'system';
    };
    sentry?: {
        dsn?: string | null;
        environment?: string | null;
        release?: string | null;
        tracesSampleRate?: number | null;
    };
}

export interface SettingsStore {
    data?: SiteSettings;
    setSettings: Action<SettingsStore, SiteSettings>;
}

const settings: SettingsStore = {
    data: undefined,
    setSettings: action((state, payload) => {
        state.data = payload;
    }),
};

export default settings;
