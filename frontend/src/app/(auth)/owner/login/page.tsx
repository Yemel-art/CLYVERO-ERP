import { redirect } from 'next/navigation';

/** Uses the hardened shared login flow while forcing platform-owner mode. */
export default function OwnerLoginPage() {
  redirect('/login?role=platform&next=/platform/dashboard');
}
