'use client';

import { useState, useEffect } from 'react';
import { toast } from 'sonner';
import { Plus, Search, Key, Pencil } from 'lucide-react';
import { PageHeader } from '@/components/ui/PageHeader';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Modal } from '@/components/ui/Modal';
import { Badge } from '@/components/ui/Badge';
import { Pagination } from '@/components/ui/Pagination';
import { DataTable, type Column } from '@/components/ui/DataTable';
import { useUsers, useCreateUser, useUpdateUser, useResetUserPassword } from '@/hooks/admin';
import type { AdminUser } from '@/lib/api/admin';
import type { ApiError } from '@/types/api';
import type { AxiosError } from 'axios';

const ROLES = [
  { value: 'administrator', label: 'Administrator' },
  { value: 'secretary',     label: 'Secretary' },
  { value: 'teacher',       label: 'Teacher' },
  { value: 'parent',        label: 'Parent' },
];

function useDebounced<T>(value: T, ms = 300): T {
  const [d, set] = useState(value);
  useEffect(() => { const id = setTimeout(() => set(value), ms); return () => clearTimeout(id); }, [value, ms]);
  return d;
}

type UserField = 'first_name' | 'last_name' | 'email' | 'phone' | 'password' | 'role';

function passwordValidationMessage(value: string): string | null {
  const missing = [
    value.length < 12 ? '12 characters' : null,
    !/[A-Z]/.test(value) ? 'one uppercase letter' : null,
    !/[a-z]/.test(value) ? 'one lowercase letter' : null,
    !/\d/.test(value) ? 'one number' : null,
    !/[^A-Za-z0-9]/.test(value) ? 'one symbol' : null,
  ].filter(Boolean);

  return missing.length > 0 ? `Password requires ${missing.join(', ')}.` : null;
}

export default function UsersPage() {
  const [search, setSearch] = useState('');
  const debounced = useDebounced(search, 300);
  const [roleFilter, setRoleFilter] = useState('');
  const [page, setPage] = useState(1);
  useEffect(() => { setPage(1); }, [debounced, roleFilter]);

  const { data, isLoading, isFetching } = useUsers({
    q: debounced || undefined,
    role: roleFilter || undefined,
    page,
    per_page: 25,
  });
  const create = useCreateUser();
  const reset = useResetUserPassword();

  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState<AdminUser | null>(null);
  const update = useUpdateUser(editing?.id ?? '');

  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [password, setPassword] = useState('');
  const [role, setRole] = useState('secretary');
  const [isActive, setIsActive] = useState(true);
  const [fieldErrors, setFieldErrors] = useState<Partial<Record<UserField, string>>>({});

  const clearFieldError = (field: UserField) => {
    setFieldErrors((current) => {
      if (!current[field]) return current;
      const next = { ...current };
      delete next[field];
      return next;
    });
  };

  const openCreate = () => {
    setEditing(null);
    setFirstName(''); setLastName(''); setEmail(''); setPhone(''); setPassword(''); setRole('secretary'); setIsActive(true);
    setFieldErrors({});
    setOpen(true);
  };

  const openEdit = (u: AdminUser) => {
    setEditing(u);
    setFirstName(u.first_name); setLastName(u.last_name); setEmail(u.email);
    setPhone(u.phone ?? ''); setRole(u.roles[0]?.name ?? 'teacher'); setIsActive(u.is_active);
    setFieldErrors({});
    setOpen(true);
  };

  const submit = async () => {
    const normalizedEmail = email.trim().toLowerCase();
    const validationErrors: Partial<Record<UserField, string>> = {};
    if (!firstName.trim()) validationErrors.first_name = 'First name is required.';
    if (!lastName.trim()) validationErrors.last_name = 'Last name is required.';
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(normalizedEmail)) validationErrors.email = 'Enter a valid email address.';
    if (!editing) {
      const passwordError = passwordValidationMessage(password);
      if (passwordError) validationErrors.password = passwordError;
    }
    if (Object.keys(validationErrors).length > 0) {
      setFieldErrors(validationErrors);
      toast.error('Please correct the highlighted information.');
      return;
    }

    setFieldErrors({});
    try {
      if (editing) {
        await update.mutateAsync({
          first_name: firstName.trim(), last_name: lastName.trim(), email: normalizedEmail, phone: phone.trim() || null,
          is_active: isActive, role,
        });
        toast.success('User updated.');
      } else {
        await create.mutateAsync({
          first_name: firstName.trim(), last_name: lastName.trim(), email: normalizedEmail,
          phone: phone.trim() || undefined, password, role, is_active: isActive,
        });
        toast.success('User created.');
      }
      setOpen(false);
    } catch (err) {
      const body = (err as AxiosError<ApiError>).response?.data;
      const serverErrors: Partial<Record<UserField, string>> = {};
      for (const [field, messages] of Object.entries(body?.errors ?? {})) {
        const message = Array.isArray(messages) ? messages[0] : messages;
        if (message && ['first_name', 'last_name', 'email', 'phone', 'password', 'role'].includes(field)) {
          serverErrors[field as UserField] = message;
        }
      }
      setFieldErrors(serverErrors);
      toast.error(Object.values(serverErrors)[0] ?? body?.message ?? 'Could not save user.');
    }
  };

  const resetPassword = async (u: AdminUser) => {
    const pw = window.prompt(`Enter a strong new password for ${u.full_name} (12+ characters, upper/lowercase, number, symbol)`);
    if (!pw || pw.length < 12) return;
    try { await reset.mutateAsync({ id: u.id, password: pw }); toast.success('Password reset.'); }
    catch { toast.error('Could not reset password.'); }
  };

  const columns: Column<AdminUser>[] = [
    { key: 'name', header: 'Name', cell: (u) => (
      <div>
        <p className="font-medium text-ink">{u.full_name}</p>
        <p className="text-xs text-secondary-500">{u.email}</p>
      </div>
    )},
    { key: 'role', header: 'Role', cell: (u) => u.roles.map((r) => (
      <Badge key={r.id} variant="info">{r.display_name}</Badge>
    ))},
    { key: 'phone', header: 'Phone', cell: (u) => u.phone ?? '—' },
    { key: 'status', header: 'Status', cell: (u) => (
      <Badge variant={u.is_active ? 'success' : 'secondary'}>{u.is_active ? 'Active' : 'Disabled'}</Badge>
    )},
    { key: 'login', header: 'Last login', cell: (u) =>
      u.last_login_at ? new Date(u.last_login_at).toLocaleDateString() : <span className="text-secondary-400">Never</span> },
    { key: 'actions', header: '', cell: (u) => (
      <div className="flex justify-end gap-1">
        <button onClick={(e) => { e.stopPropagation(); openEdit(u); }}
          className="rounded-button p-1.5 text-secondary-500 hover:bg-secondary-100" aria-label="Edit"><Pencil className="h-4 w-4" /></button>
        <button onClick={(e) => { e.stopPropagation(); resetPassword(u); }}
          className="rounded-button p-1.5 text-secondary-500 hover:bg-warning-light hover:text-warning" aria-label="Reset password"><Key className="h-4 w-4" /></button>
      </div>
    ), align: 'right' },
  ];

  return (
    <div>
      <PageHeader
        breadcrumb={[{ label: 'Home', href: '/admin/dashboard' }, { label: 'Users' }]}
        title="User accounts"
        description="Manage every login that can access this school's portal."
        actions={<Button leftIcon={<Plus className="h-4 w-4" />} onClick={openCreate}>New user</Button>}
      />

      <div className="mb-4 flex items-center gap-3">
        <div className="flex-1 max-w-md">
          <Input placeholder="Search by name or email…" value={search}
            onChange={(e) => setSearch(e.target.value)} leftIcon={<Search className="h-4 w-4" />} />
        </div>
        <select value={roleFilter} onChange={(e) => setRoleFilter(e.target.value)}
          className="h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm">
          <option value="">All roles</option>
          {ROLES.map((r) => <option key={r.value} value={r.value}>{r.label}</option>)}
        </select>
      </div>

      <DataTable columns={columns} rows={data?.data ?? []} rowKey={(u) => u.id}
        isLoading={isLoading || (isFetching && !data)}
        emptyTitle="No users yet" emptyDescription="Start by inviting your secretary and form masters." />

      {data && data.meta.total > 0 && (
        <Pagination page={data.meta.page} lastPage={data.meta.last_page}
          total={data.meta.total} perPage={data.meta.per_page} onPageChange={setPage} />
      )}

      <Modal open={open} onClose={() => setOpen(false)} title={editing ? 'Edit user' : 'New user'} size="md"
        footer={<>
          <Button variant="outline" onClick={() => setOpen(false)}>Cancel</Button>
          <Button onClick={submit} isLoading={editing ? update.isPending : create.isPending}
            disabled={!firstName || !lastName || !email}>{editing ? 'Save changes' : 'Create user'}</Button>
        </>}>
        <div className="space-y-3">
          <div className="grid grid-cols-2 gap-3">
            <Input label="First name" value={firstName} error={fieldErrors.first_name} onChange={(e) => { setFirstName(e.target.value); clearFieldError('first_name'); }} />
            <Input label="Last name" value={lastName} error={fieldErrors.last_name} onChange={(e) => { setLastName(e.target.value); clearFieldError('last_name'); }} />
          </div>
          <Input type="email" label="Email" value={email} error={fieldErrors.email} onChange={(e) => { setEmail(e.target.value); clearFieldError('email'); }} />
          <Input label="Phone (optional)" value={phone} error={fieldErrors.phone} onChange={(e) => { setPhone(e.target.value); clearFieldError('phone'); }} />
          {!editing && (
            <Input type="password" label="Initial password" value={password}
              error={fieldErrors.password}
              hint="At least 12 characters with uppercase, lowercase, a number, and a symbol."
              onChange={(e) => { setPassword(e.target.value); clearFieldError('password'); }}
              placeholder="Example: Secure@School26" />
          )}
          <div className="flex flex-col gap-1.5">
            <label className="text-sm font-medium text-secondary-700">Role</label>
            <select value={role} onChange={(e) => setRole(e.target.value)}
              className="h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm">
              {ROLES.map((r) => <option key={r.value} value={r.value}>{r.label}</option>)}
            </select>
          </div>
          <label className="flex items-center gap-2 text-sm">
            <input type="checkbox" checked={isActive} onChange={(e) => setIsActive(e.target.checked)}
              className="rounded border-secondary-300 text-primary-600" />
            Active (can log in)
          </label>
        </div>
      </Modal>
    </div>
  );
}
