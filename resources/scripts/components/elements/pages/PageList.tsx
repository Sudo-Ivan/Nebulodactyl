import clsx from 'clsx';

interface Props {
    children: React.ReactNode;
    className?: string;
}

const PageListContainer = ({ className, children }: Props) => {
    return (
        <div
            style={{
                background: 'radial-gradient(124.75% 124.75% at 50.01% -10.55%, rgb(16, 16, 16) 0%, rgb(4, 4, 4) 100%)',
            }}
            className={clsx(className, 'p-2 border-[1px] border-cream-50/7 rounded-xl')}
        >
            <div className='flex h-full w-full flex-col gap-3 overflow-hidden rounded-lg'>{children}</div>
        </div>
    );
};
PageListContainer.displayName = 'PageListContainer';

const PageListItem = ({ className, children }: Props) => {
    return (
        <div
            className={clsx(
                className,
                'bg-linear-to-b from-cream-50/3 to-cream-50/2 border-[1px] border-cream-50/8 px-5 py-4 rounded-xl hover:border-cream-50/13 transition-all flex items-center gap-3 flex-col sm:flex-row',
            )}
        >
            {children}
        </div>
    );
};
PageListItem.displayName = 'PageListItem';

export { PageListContainer, PageListItem };
