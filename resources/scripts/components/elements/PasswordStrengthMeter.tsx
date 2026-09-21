import { useFormikContext } from 'formik';
import { cn } from '@/lib/utils';

const COMMON = [
    'password',
    'letmein',
    'welcome',
    'qwerty',
    'dragon',
    'monkey',
    'football',
    'iloveyou',
    'admin',
    'login',
    'abc123',
    '123456',
    '111111',
    'master',
    'sunshine',
    'shadow',
    'superman',
    'minecraft',
];

interface Score {
    level: 0 | 1 | 2 | 3 | 4;
    hint: string | null;
}

function hasSequence(pw: string): boolean {
    const lower = pw.toLowerCase();
    for (let i = 0; i < lower.length - 2; i++) {
        const a = lower.charCodeAt(i);
        const b = lower.charCodeAt(i + 1);
        const c = lower.charCodeAt(i + 2);
        if ((b === a + 1 && c === b + 1) || (b === a - 1 && c === b - 1)) {
            return true;
        }
    }
    return false;
}

export function scorePassword(pw: string): Score {
    if (!pw) {
        return { level: 0, hint: null };
    }

    const lower = pw.toLowerCase();

    if (pw.length < 8) {
        return { level: 0, hint: 'Use at least 8 characters.' };
    }
    if (COMMON.some((w) => lower.includes(w))) {
        return { level: 0, hint: 'Avoid common words and passwords.' };
    }
    if (/^(.)\1+$/.test(pw)) {
        return { level: 0, hint: 'Avoid repeating a single character.' };
    }

    let points = 0;
    if (pw.length >= 8) points++;
    if (pw.length >= 12) points++;
    if (pw.length >= 16) points++;
    if (/[a-z]/.test(pw) && /[A-Z]/.test(pw)) points++;
    if (/\d/.test(pw)) points++;
    if (/[^A-Za-z0-9]/.test(pw)) points++;

    let level: Score['level'];
    if (points <= 3) level = 1;
    else if (points === 4) level = 2;
    else if (points === 5) level = 3;
    else level = 4;

    if (hasSequence(pw) && level > 1) {
        level = (level - 1) as Score['level'];
    }

    let hint: string | null = null;
    if (pw.length < 12) hint = 'Longer is stronger.';
    else if (!/[^A-Za-z0-9]/.test(pw)) hint = 'Add a symbol for a stronger password.';
    else if (!/\d/.test(pw)) hint = 'Add a number for a stronger password.';
    else if (!/[A-Z]/.test(pw)) hint = 'Mix in uppercase letters.';

    return { level, hint };
}

const LEVELS: { label: string; bar: string; text: string }[] = [
    { label: 'Too weak', bar: 'bg-red-400', text: 'text-red-400' },
    { label: 'Weak', bar: 'bg-orange-400', text: 'text-orange-400' },
    { label: 'Fair', bar: 'bg-yellow-400', text: 'text-yellow-400' },
    { label: 'Good', bar: 'bg-lime-400', text: 'text-lime-400' },
    { label: 'Strong', bar: 'bg-emerald-400', text: 'text-emerald-400' },
];

interface Props {
    /** Formik field name to watch. Falls back to the explicit value prop. */
    name?: string;
    value?: string;
    className?: string;
}

export default function PasswordStrengthMeter({ name, value, className }: Props) {
    const formik = useFormikContext<Record<string, unknown>>();
    const password = name ? String(formik?.values?.[name] ?? '') : (value ?? '');

    if (!password) {
        return null;
    }

    const { level, hint } = scorePassword(password);
    const { label, bar, text } = LEVELS[level];

    return (
        <div className={cn('mt-2.5', className)} aria-live='polite'>
            <div className='flex items-center gap-3'>
                <div className='flex gap-1 flex-1'>
                    {[0, 1, 2, 3].map((i) => (
                        <div
                            key={i}
                            className={cn(
                                'h-1 flex-1 rounded-full transition-colors duration-200',
                                i < level || (level === 0 && i === 0) ? bar : 'bg-cream-400/15',
                            )}
                        />
                    ))}
                </div>
                <span className={cn('text-xs tabular-nums w-16 text-right', text)}>{label}</span>
            </div>
            {hint && <p className='mt-1.5 text-xs text-secondary'>{hint}</p>}
        </div>
    );
}
