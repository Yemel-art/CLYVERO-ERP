import type { LucideIcon } from 'lucide-react';
import {
  LayoutDashboard, GraduationCap, Users, UserCog, BookOpen,
  ClipboardCheck, BarChart3, Calendar, DollarSign, FileText,
  Bell, Settings, ShieldCheck, Heart, TrendingUp, Upload,
} from 'lucide-react';
import type { UserRoleName } from '@/types/user';

export interface NavItem {
  label: string;
  href: string;
  icon: LucideIcon;
  /** Optional permission to gate visibility. */
  permission?: string;
}

export interface NavGroup {
  label: string;
  items: NavItem[];
}

/**
 * Role-based navigation map. Each role sees its own dashboard + the
 * modules its permissions allow. Module routes will be wired in their
 * respective phases (Students = Phase 2, Teachers = Phase 3, etc.).
 */
export const NAV_MAP: Record<UserRoleName, NavGroup[]> = {
  super_administrator: [
    { label: 'Platform', items: [
      { label: 'Dashboard', href: '/platform/dashboard', icon: LayoutDashboard },
      { label: 'Schools', href: '/platform/schools', icon: ShieldCheck },
    ]},
  ],
  administrator: [
    {
      label: 'Overview',
      items: [
        { label: 'Dashboard', href: '/admin/dashboard', icon: LayoutDashboard },
      ],
    },
    {
      label: 'People',
      items: [
        { label: 'Students', href: '/admin/students', icon: GraduationCap, permission: 'student.view' },
        { label: 'Spreadsheet Student Import', href: '/admin/students/import', icon: Upload, permission: 'student.create' },
        { label: 'Test Data Cleanup', href: '/admin/students/permanent-delete', icon: ShieldCheck, permission: 'student.delete' },
        { label: 'Teachers', href: '/admin/teachers', icon: UserCog,       permission: 'teacher.view' },
        { label: 'Parents',  href: '/admin/parents',  icon: Heart,         permission: 'parent.view'  },
        { label: 'Users',    href: '/admin/users',    icon: Users,         permission: 'user.view'    },
      ],
    },
    {
      label: 'Academics',
      items: [
        { label: 'Academic',   href: '/admin/academic',           icon: BookOpen,        permission: 'class.view'      },
        { label: 'Classes',    href: '/admin/academic/classes',   icon: BookOpen,        permission: 'class.view'      },
        { label: 'Subjects',   href: '/admin/academic/subjects',  icon: BookOpen,        permission: 'subject.view'    },
        { label: 'Progression', href: '/admin/academic/progression', icon: TrendingUp, permission: 'grade.view' },
        { label: 'Attendance', href: '/admin/attendance',         icon: ClipboardCheck,  permission: 'attendance.view' },
        { label: 'Grades',     href: '/admin/grades',             icon: BarChart3,       permission: 'grade.view'      },
        { label: 'Timetable',  href: '/admin/timetable',          icon: Calendar,        permission: 'timetable.view'  },
      ],
    },
    {
      label: 'Operations',
      items: [
        { label: 'Finance',      href: '/admin/finance',      icon: DollarSign, permission: 'invoice.view'     },
        { label: 'Report Cards', href: '/admin/report-cards', icon: FileText,   permission: 'report_card.view' },
        { label: 'Official Documents', href: '/admin/documents', icon: FileText, permission: 'report_card.generate' },
        { label: 'Notifications', href: '/admin/notifications', icon: Bell,     permission: 'notification.view' },
      ],
    },
    {
      label: 'System',
      items: [
        { label: 'Audit Logs', href: '/admin/audit-logs', icon: ShieldCheck, permission: 'audit.view' },
        { label: 'Settings',   href: '/admin/settings',   icon: Settings,    permission: 'settings.view' },
      ],
    },
  ],

  secretary: [
    { label: 'Overview', items: [
      { label: 'Dashboard', href: '/secretary/dashboard', icon: LayoutDashboard },
    ]},
    { label: 'People', items: [
      { label: 'Students', href: '/secretary/students', icon: GraduationCap, permission: 'student.view' },
      { label: 'Spreadsheet Student Import', href: '/secretary/students/import', icon: Upload, permission: 'student.create' },
      { label: 'Parents',  href: '/secretary/parents',  icon: Heart,         permission: 'parent.view'  },
      { label: 'Teachers', href: '/secretary/teachers', icon: UserCog,       permission: 'teacher.view' },
    ]},
    { label: 'Academics', items: [
      { label: 'Attendance', href: '/secretary/attendance', icon: ClipboardCheck, permission: 'attendance.view' },
      { label: 'Classes',    href: '/secretary/classes',    icon: BookOpen,       permission: 'class.view'    },
    ]},
    { label: 'Operations', items: [
      { label: 'Finance',      href: '/secretary/finance',      icon: DollarSign, permission: 'invoice.view'     },
      { label: 'Report Cards', href: '/secretary/report-cards', icon: FileText,   permission: 'report_card.view' },
    ]},
  ],

  teacher: [
    { label: 'Overview', items: [
      { label: 'Dashboard', href: '/teacher/dashboard', icon: LayoutDashboard },
    ]},
    { label: 'My Work', items: [
      { label: 'My Classes',  href: '/teacher/classes',    icon: BookOpen,       permission: 'class.view'      },
      { label: 'Attendance',  href: '/teacher/attendance', icon: ClipboardCheck, permission: 'attendance.view' },
      { label: 'Grades',      href: '/teacher/grades',     icon: BarChart3,      permission: 'grade.view'      },
      { label: 'Timetable',   href: '/teacher/timetable',  icon: Calendar,       permission: 'timetable.view'  },
    ]},
  ],

  parent: [
    { label: 'Overview', items: [
      { label: 'Dashboard', href: '/parent/dashboard', icon: LayoutDashboard },
    ]},
    { label: 'My Children', items: [
      { label: 'Grades',       href: '/parent/grades',       icon: BarChart3,      permission: 'grade.view'      },
      { label: 'Attendance',   href: '/parent/attendance',   icon: ClipboardCheck, permission: 'attendance.view' },
      { label: 'Report Cards', href: '/parent/report-cards', icon: FileText,      permission: 'report_card.view' },
      { label: 'Tuition',      href: '/parent/tuition',      icon: DollarSign,    permission: 'finance.view'     },
    ]},
  ],
};
