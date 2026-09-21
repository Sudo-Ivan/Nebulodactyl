import type { SiteSettings } from '@/state/settings';

/**
 * Lazily initialise Sentry-compatible error reporting. Works with Sentry
 * itself and any compatible backend such as GlitchTip or Bugsink, since they
 * all speak the same envelope protocol. The SDK is only imported when a DSN
 * is configured, so the default bundle carries no overhead.
 */
export function initErrorReporting(settings?: SiteSettings): void {
    const dsn = settings?.sentry?.dsn;
    if (!dsn) {
        return;
    }

    import('@sentry/react')
        .then((Sentry) => {
            Sentry.init({
                dsn,
                environment: settings?.sentry?.environment ?? undefined,
                release: settings?.sentry?.release ?? undefined,
                tracesSampleRate: settings?.sentry?.tracesSampleRate ?? 0,
                // Never attach cookies, headers, or user data by default.
                sendDefaultPii: false,
            });
        })
        .catch(() => {
            // Reporting must never break the app.
        });
}
