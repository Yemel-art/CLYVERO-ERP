import { AuthGuard } from '@/components/auth/AuthGuard';
import { Sidebar } from '@/components/layout/Sidebar';
import { Topbar } from '@/components/layout/Topbar';
import { Footer } from '@/components/layout/Footer';
import { MobileNavigation } from '@/components/layout/MobileNavigation';

/**
 * Layout for every authenticated page.
 *
 * Structure (per Design Philosophy §3 Information Architecture):
 *   topbar (navbar)
 *   sidebar (role-based)
 *   main content
 *   footer
 */
export default function AuthenticatedLayout({ children }: { children: React.ReactNode }) {
  return (
    <AuthGuard>
      <div className="flex min-h-screen">
        <Sidebar />
        <div className="flex flex-1 flex-col">
          <Topbar />
          <main className="flex-1 overflow-y-auto bg-background px-4 py-4 pb-24 sm:px-6 sm:py-6 lg:px-8 lg:pb-6">
            {children}
          </main>
          <Footer />
          <MobileNavigation />
        </div>
      </div>
    </AuthGuard>
  );
}
