import CopyOnClick from '@/components/elements/CopyOnClick';

import { cn } from '@/lib/utils';

import styles from './style.module.css';

interface StatBlockProps {
    title: string;
    copyOnClick?: string;
    children: React.ReactNode;
    className?: string;
}

const StatBlock = ({ title, copyOnClick, className, children }: StatBlockProps) => {
    return (
        <CopyOnClick text={copyOnClick}>
            <div className={cn(styles.stat_block, 'bg-cream-50/4 border-[1px] border-cream-50/7', className)}>
                <div className={'flex flex-col justify-center overflow-hidden w-full'}>
                    <p className={'leading-tight text-xs md:text-sm text-cream-500'}>{title}</p>
                    <div className={'text-[32px] font-extrabold leading-[98%] tracking-[-0.07rem] w-full truncate'}>
                        {children}
                    </div>
                </div>
            </div>
        </CopyOnClick>
    );
};

export default StatBlock;
