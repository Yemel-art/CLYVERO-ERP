import { redirect } from 'next/navigation';

/** Permanent, unadvertised entry point for the Clyvero platform owner. */
export default function OwnerEntryPage() {
  redirect('/owner/login');
}
