import http from '@/api/http';
import deleteFiles from '@/api/server/files/deleteFiles';
import loadDirectory from '@/api/server/files/loadDirectory';

/**
 * A single recorded install. Persisted in the panel database (never on the
 * daemon), so the install history is private to the panel: it does not appear
 * in the file manager and is never bundled into a server backup/archive.
 */
export interface InstalledEntry {
    filename: string;
    version_id: string;
    version_name: string;
    project_title: string;
    installed_at: string | null;
}

/**
 * One item in the "Installed" view: an install record plus its folder type and
 * source/project identity, so the UI can offer manage/reinstall/uninstall.
 */
export interface InstalledItem {
    type: 'mod' | 'plugin';
    source: string;
    projectId: string;
    entry: InstalledEntry;
}

const DIRECTORY_FOR_TYPE: Record<'mod' | 'plugin', string> = {
    mod: 'mods',
    plugin: 'plugins',
};

const TYPE_FOR_DIRECTORY: Record<string, 'mod' | 'plugin'> = {
    mods: 'mod',
    plugins: 'plugin',
};

/** Legacy daemon-side manifest filename, kept only to clean up old installs. */
const LEGACY_MANIFEST = '.hydro-installer.json';

/** Shape returned by GET /marketplace/installed. */
interface InstallRecord {
    type: 'mod' | 'plugin';
    source: string;
    project_id: string;
    project_title: string;
    version_id: string;
    version_name: string;
    filename: string;
    installed_at: string | null;
}

const toInstalledItem = (r: InstallRecord): InstalledItem => ({
    type: r.type,
    source: r.source,
    projectId: r.project_id,
    entry: {
        filename: r.filename,
        version_id: r.version_id,
        version_name: r.version_name,
        project_title: r.project_title,
        installed_at: r.installed_at,
    },
});

/**
 * Record (or replace on reinstall) an install in the panel database.
 *
 * @param directory 'mods' | 'plugins' (kept in the signature so existing callers don't change).
 */
export async function recordInstall(
    uuid: string,
    directory: string,
    source: string,
    projectId: string,
    entry: InstalledEntry,
): Promise<void> {
    const type = TYPE_FOR_DIRECTORY[directory] ?? 'mod';
    await http.post(`/api/client/servers/${uuid}/marketplace/installed`, {
        type,
        source,
        project_id: projectId,
        project_title: entry.project_title,
        version_id: entry.version_id,
        version_name: entry.version_name,
        filename: entry.filename,
    });
}

/**
 * Remove an install record from the panel database. (The jar file itself is
 * deleted separately by the caller via the file-delete endpoint.)
 *
 * @param directory 'mods' | 'plugins'.
 */
export async function removeInstall(uuid: string, directory: string, source: string, projectId: string): Promise<void> {
    const type = TYPE_FOR_DIRECTORY[directory] ?? 'mod';
    await http.delete(`/api/client/servers/${uuid}/marketplace/installed`, {
        params: { type, source, project_id: projectId },
    });
}

/**
 * Fetch the server's install records and reconcile them against the real
 * contents of the mods/plugins folders:
 *   - a record whose jar was deleted (e.g. via the file manager) is pruned;
 *   - any leftover legacy `.hydro-installer.json` (from before records moved
 *     server-side) is deleted so it is not visible in the file manager.
 *
 * If a folder listing fails, that folder's records are returned as-is rather
 * than risk dropping them on a transient error.
 */
export async function readAllInstalled(uuid: string): Promise<InstalledItem[]> {
    let records: InstallRecord[] = [];
    try {
        const { data } = await http.get(`/api/client/servers/${uuid}/marketplace/installed`);
        records = Array.isArray((data as { installs?: InstallRecord[] })?.installs) ? data.installs : [];
    } catch {
        return [];
    }

    const listings = await Promise.all(
        (['mod', 'plugin'] as const).map(async (type) => {
            try {
                const entries = await loadDirectory(uuid, DIRECTORY_FOR_TYPE[type]);
                // loadDirectory caps at 1000 entries; if the folder hit the cap we
                // can't be sure a jar is truly absent, so signal "incomplete" and
                // skip pruning that folder rather than risk dropping a real install.
                if (entries.length >= 1000) {
                    return { type, files: null as Set<string> | null };
                }
                const files = new Set(entries.filter((f) => f.isFile).map((f) => f.name));
                return { type, files: files as Set<string> | null };
            } catch {
                return { type, files: null as Set<string> | null };
            }
        }),
    );
    const listingFor = (type: 'mod' | 'plugin'): Set<string> | null =>
        listings.find((l) => l.type === type)?.files ?? null;

    // Best-effort cleanup of legacy daemon-side manifests so they're not visible.
    await Promise.all(
        (['mod', 'plugin'] as const).map(async (type) => {
            if (listingFor(type)?.has(LEGACY_MANIFEST)) {
                try {
                    await deleteFiles(uuid, DIRECTORY_FOR_TYPE[type], [LEGACY_MANIFEST]);
                } catch {
                    // ignore - best-effort
                }
            }
        }),
    );

    const surviving: InstallRecord[] = [];
    for (const record of records) {
        const present = listingFor(record.type);
        if (present !== null && !present.has(record.filename)) {
            try {
                await removeInstall(uuid, DIRECTORY_FOR_TYPE[record.type], record.source, record.project_id);
            } catch {
                // Keep the record in the list if the prune call itself failed.
                surviving.push(record);
            }
            continue;
        }
        surviving.push(record);
    }

    return surviving.map(toInstalledItem);
}
