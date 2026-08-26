import type { Gender, StudentStatus } from './student';

export interface ParentChildLink {
  id: string;
  admission_number: string;
  full_name: string;
  photo_url: string | null;
  status: StudentStatus;
  pivot: {
    relationship: string;
    is_primary: boolean;
    can_pickup: boolean;
  };
}

export interface ParentGuardian {
  id: string;
  first_name: string;
  last_name: string;
  middle_name: string | null;
  full_name: string;
  gender: Gender;
  email: string;
  phone: string;
  alternate_phone: string | null;
  address: string | null;
  city: string | null;
  country: string;
  occupation: string | null;
  workplace: string | null;
  national_id?: string | null;
  is_active: boolean;
  user?: { id: string; email: string; is_active: boolean; last_login_at: string | null } | null;
  children?: ParentChildLink[];
  children_count?: number;
  created_at: string | null;
  archived_at: string | null;
  temporary_password?: string;
}

export interface ParentStatistics {
  total: number;
  with_login: number;
  archived: number;
}

export interface ParentFilters {
  q?: string;
  gender?: Gender;
  is_active?: boolean;
  has_children?: boolean;
  include_archived?: boolean;
  page?: number;
  per_page?: number;
}
