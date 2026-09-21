import { OPERATION_STATUS, type OperationStatus } from '@/api/server/serverOperations';
import {
    canCloseOperation,
    formatOperationId,
    getStatusIconType,
    getStatusStyling,
    isActiveStatus,
    isCompletedStatus,
    isFailedStatus,
} from '@/lib/server-operations';

describe('@/lib/server-operations.ts', () => {
    describe('getStatusStyling()', () => {
        it('should return pending styling for pending status', () => {
            const result = getStatusStyling(OPERATION_STATUS.PENDING);
            expect(result.color).toBe('text-yellow-400');
        });

        it('should return running styling for running status', () => {
            const result = getStatusStyling(OPERATION_STATUS.RUNNING);
            expect(result.color).toBe('text-blue-400');
        });

        it('should return completed styling for completed status', () => {
            const result = getStatusStyling(OPERATION_STATUS.COMPLETED);
            expect(result.color).toBe('text-green-400');
        });

        it('should return failed styling for failed status', () => {
            const result = getStatusStyling(OPERATION_STATUS.FAILED);
            expect(result.color).toBe('text-red-400');
        });

        it('should return cancelled styling for cancelled status', () => {
            const result = getStatusStyling(OPERATION_STATUS.CANCELLED);
            expect(result.color).toBe('text-cream-500');
        });

        it('should default to pending for unknown status', () => {
            const result = getStatusStyling('unknown' as unknown as OperationStatus);
            expect(result.color).toBe('text-yellow-400');
        });
    });

    describe('getStatusIconType()', () => {
        it('should return spinner for pending', () => {
            expect(getStatusIconType(OPERATION_STATUS.PENDING)).toBe('spinner');
        });

        it('should return spinner for running', () => {
            expect(getStatusIconType(OPERATION_STATUS.RUNNING)).toBe('spinner');
        });

        it('should return success for completed', () => {
            expect(getStatusIconType(OPERATION_STATUS.COMPLETED)).toBe('success');
        });

        it('should return error for failed', () => {
            expect(getStatusIconType(OPERATION_STATUS.FAILED)).toBe('error');
        });

        it('should return error for cancelled', () => {
            expect(getStatusIconType(OPERATION_STATUS.CANCELLED)).toBe('error');
        });

        it('should default to spinner for unknown status', () => {
            expect(getStatusIconType('unknown' as unknown as OperationStatus)).toBe('spinner');
        });
    });

    describe('canCloseOperation()', () => {
        it('should return true when operation is completed', () => {
            expect(canCloseOperation({ is_completed: true }, null)).toBe(true);
        });

        it('should return true when operation has failed', () => {
            expect(canCloseOperation({ has_failed: true }, null)).toBe(true);
        });

        it('should return true when there is an error', () => {
            expect(canCloseOperation(null, 'something went wrong')).toBe(true);
        });

        it('should return false when operation is still active with no error', () => {
            expect(canCloseOperation({ is_completed: false, has_failed: false }, null)).toBe(false);
        });

        it('should return false when operation is null and no error', () => {
            expect(canCloseOperation(null, null)).toBe(false);
        });
    });

    describe('formatOperationId()', () => {
        it('should return the first segment followed by ellipsis', () => {
            expect(formatOperationId('abc123-def456-ghi789')).toBe('abc123...');
        });

        it('should handle single segment IDs', () => {
            expect(formatOperationId('abc123')).toBe('abc123...');
        });
    });

    describe('isActiveStatus()', () => {
        it('should return true for pending', () => {
            expect(isActiveStatus(OPERATION_STATUS.PENDING)).toBe(true);
        });

        it('should return true for running', () => {
            expect(isActiveStatus(OPERATION_STATUS.RUNNING)).toBe(true);
        });

        it('should return false for completed', () => {
            expect(isActiveStatus(OPERATION_STATUS.COMPLETED)).toBe(false);
        });

        it('should return false for failed', () => {
            expect(isActiveStatus(OPERATION_STATUS.FAILED)).toBe(false);
        });

        it('should return false for cancelled', () => {
            expect(isActiveStatus(OPERATION_STATUS.CANCELLED)).toBe(false);
        });
    });

    describe('isCompletedStatus()', () => {
        it('should return true for completed', () => {
            expect(isCompletedStatus(OPERATION_STATUS.COMPLETED)).toBe(true);
        });

        it('should return false for other statuses', () => {
            expect(isCompletedStatus(OPERATION_STATUS.PENDING)).toBe(false);
            expect(isCompletedStatus(OPERATION_STATUS.RUNNING)).toBe(false);
            expect(isCompletedStatus(OPERATION_STATUS.FAILED)).toBe(false);
            expect(isCompletedStatus(OPERATION_STATUS.CANCELLED)).toBe(false);
        });
    });

    describe('isFailedStatus()', () => {
        it('should return true for failed', () => {
            expect(isFailedStatus(OPERATION_STATUS.FAILED)).toBe(true);
        });

        it('should return true for cancelled', () => {
            expect(isFailedStatus(OPERATION_STATUS.CANCELLED)).toBe(true);
        });

        it('should return false for other statuses', () => {
            expect(isFailedStatus(OPERATION_STATUS.PENDING)).toBe(false);
            expect(isFailedStatus(OPERATION_STATUS.RUNNING)).toBe(false);
            expect(isFailedStatus(OPERATION_STATUS.COMPLETED)).toBe(false);
        });
    });
});
