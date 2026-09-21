export interface UnifiedBackup {
    uuid: string;
    name: string;
    status: 'pending' | 'running' | 'completed' | 'failed' | 'cancelled';
    progress: number;
    message: string;
    isSuccessful?: boolean;
    isLocked: boolean;
    isAutomatic: boolean;
    checksum?: string;
    bytes?: number;
    createdAt: Date;
    completedAt?: Date | null;
    verifiedAt?: Date | null;
    verifyState?: 'ok' | 'missing' | 'unverifiable' | 'error' | null;
    canRetry: boolean;
    canDelete: boolean;
    canDownload: boolean;
    canRestore: boolean;
    isLiveOnly: boolean;
    isDeletion?: boolean;
}

export interface BackupContextMenuBackup {
    uuid: string;
    name: string;
    isSuccessful: boolean;
    isLocked: boolean;
}
