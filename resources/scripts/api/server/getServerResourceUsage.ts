import http from '@/api/http';

export type ServerPowerState = 'offline' | 'starting' | 'running' | 'stopping' | 'installing';

export type ServerHealth = 'healthy' | 'unhealthy' | 'starting' | null;

export interface ServerStats {
    status: ServerPowerState;
    isSuspended: boolean;
    isInstalling: boolean;
    health: ServerHealth;
    memoryUsageInBytes: number;
    cpuUsagePercent: number;
    diskUsageInBytes: number;
    networkRxInBytes: number;
    networkTxInBytes: number;
    uptime: number;
}

export default (server: string): Promise<ServerStats> => {
    return new Promise((resolve, reject) => {
        http.get(`/api/client/servers/${server}/resources`)
            .then(({ data: { attributes } }) =>
                resolve({
                    status: attributes.current_state,
                    isSuspended: attributes.is_suspended,
                    isInstalling: attributes.is_installing,
                    health: attributes.health ?? null,
                    memoryUsageInBytes: attributes.resources.memory_bytes,
                    cpuUsagePercent: attributes.resources.cpu_absolute,
                    diskUsageInBytes: attributes.resources.disk_bytes,
                    networkRxInBytes: attributes.resources.network_rx_bytes,
                    networkTxInBytes: attributes.resources.network_tx_bytes,
                    uptime: attributes.resources.uptime,
                }),
            )
            .catch(reject);
    });
};
