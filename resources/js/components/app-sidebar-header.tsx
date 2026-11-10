import AppearanceToggleDropdown from '@/components/appearance-dropdown';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { PromotionCalendar } from '@/components/promotion-calendar';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { type BreadcrumbItem as BreadcrumbItemType } from '@/types';
import { type ReactNode } from 'react';

export function AppSidebarHeader({ breadcrumbs = [], actions }: { breadcrumbs?: BreadcrumbItemType[]; actions?: ReactNode }) {
    return (
        <header className="border-sidebar-border/50 bg-background flex min-h-16 shrink-0 items-center justify-between gap-3 border-b px-4 py-3 md:px-6">
            <div className="flex min-w-0 items-center gap-2">
                <SidebarTrigger className="-ml-1 shrink-0" aria-label="Abrir o cerrar menú" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <div className="flex shrink-0 items-center gap-1">
                {actions}
                <PromotionCalendar />
                <AppearanceToggleDropdown />
            </div>
        </header>
    );
}
