'use client';

import { useState, useMemo } from 'react';
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';
import { Plus, Search, ArrowRight, Archive as ArchiveIcon, RotateCcw } from 'lucide-react';
import { PageHeader } from '@/components/ui/PageHeader';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Card, CardContent } from '@/components/ui/Card';
import { Modal } from '@/components/ui/Modal';
import { Badge } from '@/components/ui/Badge';
import { Skeleton } from '@/components/ui/Skeleton';
import { useAcademicYears, useClasses, useCreateClass, useArchiveClass, useRestoreClass } from '@/hooks/academic';
import { teachersApi } from '@/lib/api/teachers';
import { useQuery } from '@tanstack/react-query';
import { GENERAL_STREAM_OPTIONS, SPECIALITY_OPTIONS } from '@/config/student';
import { useAuthStore } from '@/store/auth';
import { dashboardRouteFor } from '@/types/user';

type ClassCycle = '' | 'first_cycle' | 'second_cycle';
type EducationSystem = 'secondary_general' | 'secondary_technical';

const CYCLES: Array<{ value: Exclude<ClassCycle, ''>; label: string }> = [
  { value: 'first_cycle', label: 'First Cycle (Premier cycle)' },
  { value: 'second_cycle', label: 'Second Cycle (Second cycle)' },
];

const LEVELS_BY_CYCLE: Record<Exclude<ClassCycle, ''>, string[]> = {
  first_cycle: [
    'Form 1 (Première année)',
    'Form 2 (Deuxième année)',
    'Form 3 (Troisième année)',
    'Form 4 (Quatrième année)',
    'Form 5 (2nde)',
  ],
  second_cycle: [
    'Lower Sixth (Première)',
    'Upper Sixth (Terminale)',
  ],
};

const GENERAL_FRENCH_LEVELS: Record<Exclude<ClassCycle, ''>, string[]> = {
  first_cycle: ['6ème', '5ème', '4ème', '3ème'],
  second_cycle: ['2nde', '1ère', 'Terminale'],
};
const TECHNICAL_FRENCH_LEVELS: Record<Exclude<ClassCycle, ''>, string[]> = {
  first_cycle: ['Première année', 'Deuxième année', 'Troisième année', 'Quatrième année', '2nde'],
  second_cycle: ['1ère', 'Terminale'],
};
const ENGLISH_LEVELS: Record<Exclude<ClassCycle, ''>, string[]> = {
  first_cycle: ['Form 1', 'Form 2', 'Form 3', 'Form 4', 'Form 5'],
  second_cycle: ['Lower Sixth', 'Upper Sixth'],
};

export default function ClassesPage() {
  void LEVELS_BY_CYCLE;
  const router = useRouter();
  const user = useAuthStore((state) => state.user);
  const hasPermission = useAuthStore((state) => state.hasPermission);
  const isSecretary = user?.role.name === 'secretary';
  const classesBase = isSecretary ? '/secretary/classes' : '/admin/academic/classes';
  const dashboardHref = user ? dashboardRouteFor(user.role.name) : '/login';
  const canCreateClass = hasPermission('class.create');
  const canArchiveClass = hasPermission('class.delete');
  const { data: years } = useAcademicYears();
  const activeYear = useMemo(() => years?.find((y) => y.status === 'active') ?? years?.[0], [years]);
  const [yearId, setYearId] = useState<string | undefined>(undefined);
  const [search, setSearch] = useState('');
  const [showArchived, setShowArchived] = useState(false);
  const effectiveYear = yearId ?? activeYear?.id;

  const { data: classes, isLoading } = useClasses({
    q: search || undefined,
    academic_year_id: effectiveYear,
    include_archived: showArchived || undefined,
  });
  const create = useCreateClass();
  const archive = useArchiveClass();
  const restore = useRestoreClass();

  // Form-master picker uses teacher search
  const { data: teachersData } = useQuery({
    queryKey: ['teachers', 'list', { all: true }],
    queryFn: () => teachersApi.list({ per_page: 100 }),
  });

  const [open, setOpen] = useState(false);
  const [educationSystem, setEducationSystem] = useState<EducationSystem>('secondary_general');
  const [speciality, setSpeciality] = useState<string>(SPECIALITY_OPTIONS[0]?.code ?? '');
  const [cycle, setCycle] = useState<ClassCycle>('');
  const [gradeLevel, setGradeLevel] = useState('');
  const [language, setLanguage] = useState<'fr' | 'en'>('fr');
  const [capacity, setCapacity] = useState(40);
  const [formMasterId, setFormMasterId] = useState<string>('');
  const requiresSpeciality = educationSystem === 'secondary_technical' || (educationSystem === 'secondary_general' && cycle === 'second_cycle');
  const specialityOptions = educationSystem === 'secondary_technical'
    ? SPECIALITY_OPTIONS
    : GENERAL_STREAM_OPTIONS.filter((item) => item.language === language);
  const specialityLabel = useMemo(() => {
    const option = [...SPECIALITY_OPTIONS, ...GENERAL_STREAM_OPTIONS].find((item) => item.code === speciality);
    return option ? `${option.name_en.toUpperCase()} (${option.name})` : speciality;
  }, [speciality]);
  const cycleLabel = CYCLES.find((item) => item.value === cycle)?.label ?? '';
  const availableLevels = cycle
    ? (language === 'en' ? ENGLISH_LEVELS[cycle] : educationSystem === 'secondary_general' ? GENERAL_FRENCH_LEVELS[cycle] : TECHNICAL_FRENCH_LEVELS[cycle])
    : [];

  const submit = async () => {
    if (!effectiveYear) { toast.error('Please select an academic year first.'); return; }
    try {
      const c = await create.mutateAsync({
        academic_year_id: effectiveYear,
        education_system: educationSystem,
        speciality: requiresSpeciality ? speciality : null,
        cycle: cycle || undefined, grade_level: gradeLevel, language, capacity,
        form_master_id: formMasterId || undefined,
      });
      toast.success(`${c.name} created.`);
      setOpen(false);
      setSpeciality(SPECIALITY_OPTIONS[0]?.code ?? ''); setCycle(''); setGradeLevel('');
      setLanguage('fr'); setFormMasterId('');
    } catch (err: any) {
      toast.error(err?.response?.data?.message ?? 'Could not create class.');
    }
  };

  return (
    <div>
      <PageHeader
        breadcrumb={isSecretary
          ? [{ label: 'Home', href: dashboardHref }, { label: 'Classes' }]
          : [
              { label: 'Home', href: dashboardHref },
              { label: 'Academic structure', href: '/admin/academic' },
              { label: 'Classes' },
            ]}
        title="Classes"
        description={isSecretary
          ? 'Consult class rosters and maintain the class information used for attendance and daily administration.'
          : 'Create class sections, assign form masters, and attach subjects.'}
        actions={effectiveYear && canCreateClass && <Button leftIcon={<Plus className="h-4 w-4" />} onClick={() => setOpen(true)}>New class</Button>}
      />

      <div className="mb-4 flex flex-wrap items-center gap-3">
        <div className="flex-1">
          <Input placeholder="Search classes…" value={search} onChange={(e) => setSearch(e.target.value)} leftIcon={<Search className="h-4 w-4" />} />
        </div>
        <select value={effectiveYear ?? ''} onChange={(e) => setYearId(e.target.value)}
          className="h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm">
          <option value="" disabled>Select year</option>
          {(years ?? []).map((y) => <option key={y.id} value={y.id}>{y.title}</option>)}
        </select>
        <label className="flex h-10 items-center gap-2 rounded-button border border-secondary-300 px-3 text-sm">
          <input type="checkbox" checked={showArchived} onChange={(event) => setShowArchived(event.target.checked)} />
          Show archived
        </label>
      </div>

      {isLoading ? (
        <Skeleton className="h-48" />
      ) : (classes ?? []).length === 0 ? (
        <Card><CardContent className="py-12 text-center text-sm text-secondary-500">No classes yet for this academic year.</CardContent></Card>
      ) : (
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
          {(classes ?? []).map((c) => (
            <Card key={c.id} className={`${c.is_active ? 'cursor-pointer hover:shadow-card' : ''} transition-shadow`} onClick={() => {
              if (c.is_active) router.push(`${classesBase}/${c.id}`);
            }}>
              <CardContent className="py-4">
                <div className="flex items-start justify-between">
                  <div>
                    <div className="flex items-center gap-2">
                      <p className="font-semibold text-ink">{c.name}</p>
                      {!c.is_active && <Badge variant="secondary">Archived</Badge>}
                    </div>
                    <p className="text-sm text-secondary-500">{c.cycle_label ?? 'Cycle not assigned'} · {c.grade_level}</p>
                    <p className="mt-1 text-xs font-medium text-primary-700">
                      {c.language === 'en' ? 'English programme' : 'French programme'}
                    </p>
                    {c.form_master && <p className="mt-1 text-xs text-secondary-500">Form master: {c.form_master.full_name}</p>}
                  </div>
                  <div className="text-right">
                    <p className="text-2xl font-semibold text-ink">{c.students_count ?? 0}</p>
                    <p className="text-xs text-secondary-500">/ {c.capacity} seats</p>
                  </div>
                </div>
                <div className="mt-3 flex items-center justify-between">
                  <span className="flex items-center gap-1 text-xs text-primary-600 hover:text-primary-700">
                    View details <ArrowRight className="h-3 w-3" />
                  </span>
                  {c.is_active && canArchiveClass && (
                    <button onClick={(e) => {
                      e.stopPropagation();
                      if (!window.confirm(`Archive class "${c.name}"?`)) return;
                      archive.mutateAsync(c.id).then(() => toast.success('Class archived.')).catch(() => toast.error('Failed.'));
                    }}
                      className="rounded-button p-1.5 text-secondary-500 hover:bg-danger-light hover:text-danger" aria-label="Archive">
                      <ArchiveIcon className="h-4 w-4" />
                    </button>
                  )}
                  {!c.is_active && canArchiveClass && (
                    <button onClick={(event) => {
                      event.stopPropagation();
                      restore.mutateAsync(c.id).then(() => toast.success('Class restored.')).catch(() => toast.error('Could not restore class.'));
                    }}
                      className="rounded-button p-1.5 text-primary-700 hover:bg-primary-50" aria-label="Restore">
                      <RotateCcw className="h-4 w-4" />
                    </button>
                  )}
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}

      <Modal open={open} onClose={() => setOpen(false)} title="New class" size="md"
        footer={<>
          <Button variant="outline" onClick={() => setOpen(false)}>Cancel</Button>
          <Button onClick={submit} isLoading={create.isPending} disabled={(requiresSpeciality && !speciality) || !cycle || !gradeLevel}>Create class</Button>
        </>}>
        <div className="space-y-3">
          <div className="flex flex-col gap-1.5">
            <label className="text-sm font-medium text-secondary-700">Education system</label>
            <select
              value={educationSystem}
              onChange={(event) => { const next = event.target.value as EducationSystem; setEducationSystem(next); setSpeciality(next === 'secondary_technical' ? (SPECIALITY_OPTIONS[0]?.code ?? '') : ''); setGradeLevel(''); }}
              className="h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm"
            >
              <option value="secondary_general">Secondary General Education</option>
              <option value="secondary_technical">Secondary Technical Education</option>
            </select>
          </div>
          <div className="flex flex-col gap-1.5">
            <label className="text-sm font-medium text-secondary-700">{educationSystem === 'secondary_general' ? 'Stream / Série' : 'Speciality / Spécialité'}</label>
            <select
              value={speciality}
              disabled={!requiresSpeciality}
              onChange={(event) => setSpeciality(event.target.value)}
              className="h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm"
            >
              <option value="">{requiresSpeciality ? 'Choose a stream / speciality' : 'Common curriculum / Tronc commun'}</option>
              {specialityOptions.map((item) => (
                <option key={item.code} value={item.code}>{item.name_en.toUpperCase()} ({item.name})</option>
              ))}
            </select>
          </div>
          <div className="flex flex-col gap-1.5">
            <label className="text-sm font-medium text-secondary-700">Cycle</label>
            <select
              value={cycle}
              onChange={(event) => {
                setCycle(event.target.value as ClassCycle);
                setGradeLevel('');
              }}
              className="h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm"
            >
              <option value="">Choose first or second cycle</option>
              {CYCLES.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}
            </select>
          </div>
          <div className="flex flex-col gap-1.5">
              <label className="text-sm font-medium text-secondary-700">Level / Niveau</label>
              <select
                value={gradeLevel}
                onChange={(event) => setGradeLevel(event.target.value)}
                disabled={!cycle}
                className="h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm"
              >
                <option value="">{cycle ? 'Choose level' : 'Choose cycle first'}</option>
                {availableLevels.map((item) => <option key={item} value={item}>{item}</option>)}
              </select>
          </div>
          <div className="rounded-card border border-primary-200 bg-primary-50 p-3 text-sm">
            <span className="font-medium">Generated class:</span>{' '}
            {requiresSpeciality && specialityLabel ? `${specialityLabel} · ` : ''}{cycleLabel ? `${cycleLabel} · ` : ''}{gradeLevel ? `${gradeLevel} · ` : ''}{language === 'fr' ? 'Programme français' : 'English programme'}
          </div>
          <div className="flex flex-col gap-1.5">
            <label className="text-sm font-medium text-secondary-700">Langue d'enseignement</label>
            <select
              value={language}
              onChange={(e) => { const next = e.target.value as 'fr' | 'en'; setLanguage(next); if (educationSystem === 'secondary_general') setSpeciality(''); }}
              className="h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm"
            >
              <option value="fr">Français</option>
              <option value="en">English</option>
            </select>
          </div>
          <Input type="number" label="Capacity" min={1} max={200} value={capacity} onChange={(e) => setCapacity(Number(e.target.value))} />
          <div className="flex flex-col gap-1.5">
            <label className="text-sm font-medium text-secondary-700">Form master (optional)</label>
            <select value={formMasterId} onChange={(e) => setFormMasterId(e.target.value)}
              className="h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm">
              <option value="">No form master yet</option>
              {(teachersData?.data ?? []).map((t) => <option key={t.id} value={t.id}>{t.full_name} · {t.employee_number}</option>)}
            </select>
          </div>
        </div>
      </Modal>
    </div>
  );
}
