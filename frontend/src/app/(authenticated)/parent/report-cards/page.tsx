'use client';

import Link from 'next/link';
import { PageHeader } from '@/components/ui/PageHeader';
import { Card, CardContent } from '@/components/ui/Card';

export default function ParentReportCardsPage() {
  return <div>
    <PageHeader breadcrumb={[{ label: 'Home', href: '/parent/dashboard' }, { label: 'Report cards' }]}
      title="Report cards" description="Review published results before collecting the official signed report card." />
    <Card><CardContent className="py-10 text-center">
      <p className="text-sm text-secondary-600">Published term results are available in the Grades section.</p>
      <Link href="/parent/grades" className="mt-4 inline-flex rounded-button bg-primary-600 px-4 py-2 text-sm font-medium text-white">
        View published grades
      </Link>
    </CardContent></Card>
  </div>;
}
