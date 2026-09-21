import clsx from 'clsx';
import type React from 'react';

interface Props extends React.ButtonHTMLAttributes<HTMLButtonElement> {
    className?: string;
}

const Button = ({ className, ...props }: Props) => {
    return (
        <button
            className={clsx(
                'flex items-center justify-center h-8 px-4 text-sm font-medium text-cream-50 transition-colors duration-150 bg-linear-to-b from-cream-50/6 to-cream-50/4 border border-cream-50/8 rounded-full shadow-xs hover:from-cream-50/2 hover:to-cream-50/2 cursor-pointer',
                className,
            )}
            {...props}
        />
    );
};
Button.displayName = 'Button';

export default Button;
