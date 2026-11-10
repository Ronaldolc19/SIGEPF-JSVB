import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem, useSidebar } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';

export function NavMain({ items = [], label = 'Mi espacio' }: { items: NavItem[]; label?: string }) {
    const page = usePage();
    const { setOpenMobile } = useSidebar();
    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>{label}</SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) => (
                    <SidebarMenuItem key={item.title}>
                        <SidebarMenuButton
                            asChild
                            tooltip={item.title}
                            className="data-[active=true]:bg-primary data-[active=true]:text-primary-foreground h-10"
                            isActive={
                                new URL(item.url, 'http://localhost').pathname === page.url.split('?')[0] ||
                                page.url.split('?')[0].startsWith(new URL(item.url, 'http://localhost').pathname + '/')
                            }
                        >
                            <Link
                                href={item.url}
                                prefetch
                                onClick={() => setOpenMobile(false)}
                                aria-current={new URL(item.url, 'http://localhost').pathname === page.url.split('?')[0] ? 'page' : undefined}
                            >
                                {item.icon && <item.icon />}
                                <span>{item.title}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}
