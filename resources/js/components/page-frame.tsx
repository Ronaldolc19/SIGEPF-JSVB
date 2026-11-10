import { cn } from '@/lib/utils';
import { Search } from 'lucide-react';
import { type ReactNode } from 'react';

export function PageContainer({ children, className }: { children: ReactNode; className?: string }) {
    return <div className={cn('mx-auto w-full max-w-7xl space-y-6 p-4 md:p-8', className)}>{children}</div>;
}

export function PageHeader({
    title,
    description,
    eyebrow,
    actions,
    institutional = false,
}: {
    title: string;
    description?: string;
    eyebrow?: string;
    actions?: ReactNode;
    institutional?: boolean;
}) {
    return (
        <section
            className={cn(
                'flex flex-wrap items-start justify-between gap-4',
                institutional && 'bg-institutional text-institutional-foreground rounded-2xl p-6 md:p-8',
            )}
        >
            <div className="max-w-3xl min-w-0">
                {eyebrow && (
                    <p className={cn('text-xs font-semibold tracking-widest uppercase', institutional ? 'text-white/80' : 'text-primary')}>
                        {eyebrow}
                    </p>
                )}
                <h1 className="mt-2 text-2xl font-semibold tracking-tight break-words md:text-3xl">{title}</h1>
                {description && (
                    <p className={cn('mt-3 text-sm leading-6', institutional ? 'text-white/80' : 'text-muted-foreground')}>{description}</p>
                )}
            </div>
            {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
        </section>
    );
}

export function EmptyState({ title, description, action }: { title: string; description: string; action?: ReactNode }) {
    return (
        <div role="status" className="flex flex-col items-center gap-3 rounded-xl border border-dashed p-8 text-center md:p-12">
            <Search className="text-muted-foreground size-7" aria-hidden="true" />
            <p className="font-medium">{title}</p>
            <p className="text-muted-foreground max-w-md text-sm leading-6">{description}</p>
            {action}
        </div>
    );
}
