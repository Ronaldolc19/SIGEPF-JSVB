import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { FolderOpen, LayoutGrid, Settings2, ShieldCheck, Users } from 'lucide-react';
import AppLogo from './app-logo';

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;
    const titles: Record<string, string> = { administrador: 'Inicio · Administrador', asesor: 'Inicio · Asesor', pasante: 'Inicio · Pasante' };
    const homeItems: NavItem[] = auth.roles
        .filter((role) => role in titles)
        .map((role) => ({ title: titles[role], url: route(`${role}.dashboard`), icon: LayoutGrid }));
    if (!homeItems.length) homeItems.push({ title: 'Acceso pendiente', url: route('dashboard'), icon: LayoutGrid });
    const isAdmin = auth.roles.includes('administrador');
    const managementItems: NavItem[] = isAdmin
        ? [
              { title: 'Promociones', url: route('promociones.index'), icon: Users },
              { title: 'Personas', url: route('personas.index'), icon: Users },
              { title: 'Usuarios y permisos', url: route('usuarios.index'), icon: ShieldCheck },
              { title: 'Catálogos', url: route('catalogos.index'), icon: Settings2 },
          ]
        : [];
    if (auth.canViewPersonal)
        managementItems.unshift({
            title: isAdmin ? 'Personal en formación' : 'Mis participaciones y asignaciones',
            url: route('personal.index'),
            icon: Users,
        });
    if (isAdmin || auth.modulesByRole.pasante?.includes('expedientes')) {
        managementItems.push({
            title: isAdmin ? 'Expedientes digitales' : 'Mi expediente digital',
            url: route('expedientes.index'),
            icon: FolderOpen,
        });
    }
    if (isAdmin || auth.modulesByRole.pasante?.includes('incidencias') || auth.modulesByRole.asesor?.includes('incidencias')) {
        managementItems.push({ title: 'Incidencias y oficios', url: route('incidencias.index'), icon: FolderOpen });
    }
    if (isAdmin || auth.modulesByRole.pasante?.includes('consulta')) {
        managementItems.push({ title: 'Portal de consulta', url: route('consulta.index'), icon: FolderOpen });
    }
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader className="gap-3 p-3">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={route('dashboard')} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <div className="bg-muted/50 rounded-lg border p-3 group-data-[collapsible=icon]:hidden">
                    <p className="text-xs font-semibold">Enseñanza, Investigación y Calidad</p>
                    <p className="text-muted-foreground mt-1 text-xs">JS 09 · Valle de Bravo</p>
                </div>
            </SidebarHeader>

            <SidebarContent>
                <nav aria-label="Navegación principal" className="space-y-3">
                    <NavMain items={homeItems} />
                    {managementItems.length > 0 && (
                        <NavMain items={managementItems} label={isAdmin ? 'Gestión del sistema' : 'Consulta habilitada'} />
                    )}
                </nav>
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
