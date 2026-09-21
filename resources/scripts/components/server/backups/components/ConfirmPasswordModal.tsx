import { TriangleExclamation } from '@gravity-ui/icons';
import { useStoreState } from 'easy-peasy';
import { useState } from 'react';
import { Dialog } from '@/components/elements/dialog';
import Spinner from '@/components/elements/Spinner';
import FlashMessageRender from '@/components/FlashMessageRender';
import { Button } from '@/components/ui/button';
import type { ApplicationStore } from '@/state';

interface ConfirmPasswordModalProps {
    open: boolean;
    onClose: () => void;
    onConfirmed: (password: string, totpCode?: string) => Promise<void>;
    title: string;
    flashKey: string;
    loading?: boolean;
    description?: string;
    warningItems?: string[];
    confirmText?: string;
    showWarning?: boolean;
}

const ConfirmPasswordModal = ({
    open,
    onClose,
    onConfirmed,
    title,
    flashKey,
    loading = false,
    description,
    warningItems,
    confirmText = 'Confirm',
    showWarning = true,
}: ConfirmPasswordModalProps) => {
    const [password, setPassword] = useState('');
    const [totpCode, setTotpCode] = useState('');
    const hasTwoFactor = useStoreState((state: ApplicationStore) => state.user.data?.useTotp || false);

    const handleClose = () => {
        setPassword('');
        setTotpCode('');
        onClose();
    };

    const handleConfirm = () => {
        onConfirmed(password, hasTwoFactor ? totpCode : undefined);
    };

    return (
        <Dialog open={open} onClose={handleClose} title={title}>
            <FlashMessageRender byKey={flashKey} />
            <div className='space-y-4'>
                {description && <p className='text-sm text-cream-400'>{description}</p>}

                {showWarning && warningItems && warningItems.length > 0 && (
                    <div className='p-2 bg-red-500/10 border border-red-500/20 rounded-lg'>
                        <div className='flex items-start gap-3'>
                            <TriangleExclamation
                                width={22}
                                height={22}
                                fill='currentColor'
                                className='text-red-400 mt-0.5 flex-shrink-0'
                            />
                            <div className='text-sm'>
                                <p className='font-medium text-red-300'>Warning</p>
                                <ul className='text-red-400 mt-2 space-y-1 list-disc list-inside'>
                                    {warningItems.map((item, i) => (
                                        <li key={i}>{item}</li>
                                    ))}
                                </ul>
                            </div>
                        </div>
                    </div>
                )}

                <div className='space-y-3'>
                    <div>
                        <label
                            htmlFor={`${flashKey}-password`}
                            className='block text-sm font-medium text-cream-400 mb-1'
                        >
                            Password
                        </label>
                        <input
                            id={`${flashKey}-password`}
                            type='password'
                            className='w-full px-4 py-2 rounded-lg outline-hidden bg-cream-50/9 text-sm border border-zinc-700 focus:border-brand'
                            placeholder='Enter your password'
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            disabled={loading}
                        />
                    </div>

                    {hasTwoFactor && (
                        <div>
                            <label
                                htmlFor={`${flashKey}-totp`}
                                className='block text-sm font-medium text-cream-400 mb-1'
                            >
                                Two-Factor Authentication Code
                            </label>
                            <input
                                id={`${flashKey}-totp`}
                                type='text'
                                className='w-full px-4 py-2 rounded-lg outline-hidden bg-cream-50/9 text-sm border border-zinc-700 focus:border-brand'
                                placeholder='6-digit code'
                                maxLength={6}
                                value={totpCode}
                                onChange={(e) => setTotpCode(e.target.value.replace(/[^0-9]/g, ''))}
                                disabled={loading}
                            />
                        </div>
                    )}
                </div>
            </div>

            <Dialog.Footer>
                <Button onClick={handleClose} variant='secondary' disabled={loading}>
                    Cancel
                </Button>
                <Button onClick={handleConfirm} variant='attention' disabled={loading || !password}>
                    {loading && <Spinner size='small' />}
                    {loading ? 'Processing...' : confirmText}
                </Button>
            </Dialog.Footer>
        </Dialog>
    );
};

export default ConfirmPasswordModal;
