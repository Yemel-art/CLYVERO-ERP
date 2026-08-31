import { redirect } from 'next/navigation';

export default function TeacherStudentRegistrationBlockedPage() {
  redirect('/forbidden');
}
