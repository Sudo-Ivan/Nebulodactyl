import { ChevronDown } from '@gravity-ui/icons';
import * as CollapsiblePrimitive from '@radix-ui/react-collapsible';
import * as React from 'react';

import { cn } from '@/lib/utils';

const CollapseRoot = CollapsiblePrimitive.Root;

const CollapseTrigger = React.forwardRef<
    React.ElementRef<typeof CollapsiblePrimitive.Trigger>,
    React.ComponentPropsWithoutRef<typeof CollapsiblePrimitive.Trigger> & {
        icon?: boolean;
    }
>(({ className, children, icon = true, ...props }, ref) => (
    <CollapsiblePrimitive.Trigger
        ref={ref}
        className={cn(
            'group flex w-full cursor-pointer items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition hover:bg-cream-50/5 data-[state=open]:bg-cream-50/4',
            className,
        )}
        {...props}
    >
        {icon && (
            <ChevronDown
                width={16}
                height={16}
                fill='currentColor'
                className='shrink-0 transition-transform duration-200 group-data-[state=open]:rotate-0 group-data-[state=closed]:-rotate-90'
            />
        )}
        {children}
    </CollapsiblePrimitive.Trigger>
));
CollapseTrigger.displayName = CollapsiblePrimitive.Trigger.displayName;

const CollapseContent = React.forwardRef<
    React.ElementRef<typeof CollapsiblePrimitive.Content>,
    React.ComponentPropsWithoutRef<typeof CollapsiblePrimitive.Content>
>(({ className, children, ...props }, ref) => (
    <CollapsiblePrimitive.Content
        ref={ref}
        className={cn(
            'overflow-hidden transition-all data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:slide-out-to-top-1 data-[state=open]:slide-in-from-top-1',
            className,
        )}
        {...props}
    >
        <div className='pt-1'>{children}</div>
    </CollapsiblePrimitive.Content>
));
CollapseContent.displayName = CollapsiblePrimitive.Content.displayName;

export { CollapseContent, CollapseRoot, CollapseTrigger };
