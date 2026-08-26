'use client';

import { useEffect, useMemo, useState } from 'react';
import { useRouter } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { toast } from 'sonner';
import { ArrowRight, CalendarDays, Plus, Printer, Settings2, Trash2, WandSparkles, UserRoundSearch } from 'lucide-react';
import { PageHeader } from '@/components/ui/PageHeader';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/Card';
import { Skeleton } from '@/components/ui/Skeleton';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { useAcademicYears, useClasses } from '@/hooks/academic';
import { useGenerateTimetable, useGenerationConfig, useSaveGenerationConfig } from '@/hooks/timetable';
import { teachersApi } from '@/lib/api/teachers';
import {
  DAYS,
  type DayOfWeek,
  type TimetableAvailability,
  type TimetablePeriod,
} from '@/types/timetable';
import { useTranslation } from '@/hooks/useTranslation';

const FIXED_BREAKS = [
  { label: 'Break 1', start_time: '11:00', end_time: '11:30' },
  { label: 'Break 2', start_time: '14:00', end_time: '14:30' },
] as const;

const isFixedBreak = (period: TimetablePeriod) => FIXED_BREAKS.some(
  (item) => item.start_time === period.start_time && item.end_time === period.end_time,
);

function normalizeSchoolPeriods(source: TimetablePeriod[], sourceBreaks: number[]) {
  const rows: Array<{ period: TimetablePeriod; sourceIndex: number | null; isBreak: boolean }> = [];

  source.forEach((original, sourceIndex) => {
    let period = { ...original };
    const wasBreak = sourceBreaks.includes(sourceIndex);

    if (!wasBreak) {
      FIXED_BREAKS.forEach((schoolBreak) => {
        if (period.start_time < schoolBreak.start_time && period.end_time > schoolBreak.start_time) {
          period = { ...period, end_time: schoolBreak.start_time };
        } else if (period.start_time < schoolBreak.end_time && period.end_time > schoolBreak.end_time) {
          period = { ...period, start_time: schoolBreak.end_time };
        }
      });
    }

    if (period.start_time < period.end_time) {
      rows.push({ period, sourceIndex, isBreak: wasBreak || isFixedBreak(period) });
    }
  });

  FIXED_BREAKS.forEach((schoolBreak) => {
    if (!rows.some(({ period }) => (
      period.start_time === schoolBreak.start_time && period.end_time === schoolBreak.end_time
    ))) {
      rows.push({ period: { ...schoolBreak }, sourceIndex: null, isBreak: true });
    }
  });

  rows.sort((left, right) => left.period.start_time.localeCompare(right.period.start_time));
  return {
    periods: rows.map(({ period }) => period),
    breakPeriods: rows.flatMap(({ isBreak }, index) => isBreak ? [index] : []),
    sourceToNormalized: new Map(
      rows.flatMap(({ sourceIndex }, index) => sourceIndex === null ? [] : [[sourceIndex, index] as const]),
    ),
  };
}

export default function TimetableHubPage() {
  const router = useRouter();
  const { language } = useTranslation();
  const ui = (french: string, english: string) => language === 'fr' ? french : english;
  const dayLabel = (day: DayOfWeek, fallback: string) => language === 'fr'
    ? ({ monday: 'Lundi', tuesday: 'Mardi', wednesday: 'Mercredi', thursday: 'Jeudi', friday: 'Vendredi', saturday: 'Samedi', sunday: 'Dimanche' } as Record<DayOfWeek, string>)[day]
    : fallback;
  const { data: years } = useAcademicYears();
  const activeYear = useMemo(() => years?.find((year) => year.status === 'active') ?? years?.[0], [years]);
  const [yearId, setYearId] = useState<string | undefined>();
  const effectiveYear = yearId ?? activeYear?.id;
  const { data: classes, isLoading } = useClasses({ academic_year_id: effectiveYear });
  const { data: generationData, isLoading: configLoading } = useGenerationConfig(effectiveYear);
  const saveConfig = useSaveGenerationConfig();
  const generate = useGenerateTimetable();
  const { data: teachersData } = useQuery({
    queryKey: ['teachers', 'timetable-config'],
    queryFn: () => teachersApi.list({ per_page: 200 }),
  });

  const [showConfiguration, setShowConfiguration] = useState(false);
  const [workingDays, setWorkingDays] = useState<DayOfWeek[]>([]);
  const [periods, setPeriods] = useState<TimetablePeriod[]>([]);
  const [breakPeriods, setBreakPeriods] = useState<number[]>([]);
  const [frequencies, setFrequencies] = useState<Record<string, number>>({});
  const [availability, setAvailability] = useState<TimetableAvailability[]>([]);
  const [selectedTeacherId, setSelectedTeacherId] = useState('');
  const [conflicts, setConflicts] = useState<string[]>([]);

  useEffect(() => {
    if (!generationData) return;
    const normalized = normalizeSchoolPeriods(
      generationData.config.periods,
      generationData.config.break_periods,
    );
    setWorkingDays(generationData.config.working_days);
    setPeriods(normalized.periods);
    setBreakPeriods(normalized.breakPeriods);
    setAvailability(generationData.availability.flatMap((row) => {
      if (row.is_available) return [];
      const periodIndex = normalized.sourceToNormalized.get(row.period_index);
      return periodIndex === undefined || normalized.breakPeriods.includes(periodIndex)
        ? []
        : [{ ...row, period_index: periodIndex }];
    }));
    const nextFrequencies: Record<string, number> = {};
    generationData.classes.forEach((schoolClass) => {
      schoolClass.subjects.forEach((subject) => {
        nextFrequencies[`${schoolClass.id}|${subject.id}`] = subject.weekly_frequency;
      });
    });
    setFrequencies(nextFrequencies);
  }, [generationData]);

  useEffect(() => {
    if (!selectedTeacherId && teachersData?.data[0]?.id) {
      setSelectedTeacherId(teachersData.data[0].id);
    }
  }, [selectedTeacherId, teachersData]);

  const save = async () => {
    if (!effectiveYear) return false;
    try {
      await saveConfig.mutateAsync({
        academic_year_id: effectiveYear,
        working_days: workingDays,
        periods,
        break_periods: breakPeriods,
        frequencies: Object.entries(frequencies).map(([key, weeklyFrequency]) => {
          const [classId, subjectId] = key.split('|');
          return { class_id: classId, subject_id: subjectId, weekly_frequency: weeklyFrequency };
        }),
        availability,
      });
      toast.success(ui('Configuration de l’emploi du temps enregistrée.', 'Timetable configuration saved.'));
      return true;
    } catch (error: any) {
      const errors = error?.response?.data?.errors as Record<string, string[]> | undefined;
      const firstError = errors ? Object.values(errors).flat()[0] : undefined;
      toast.error(firstError ?? ui('Impossible d’enregistrer la configuration. Vérifiez les périodes et les fréquences.', 'Could not save timetable configuration. Check all periods and frequencies.'));
      return false;
    }
  };

  const runGenerator = async () => {
    if (!effectiveYear || !(await save())) return;
    const replaceExisting = window.confirm(
      ui(
        'Générer maintenant l’emploi du temps complet ? Les créneaux existants ne seront remplacés que si un emploi du temps complet sans conflit est trouvé.',
        'Generate the full timetable now? Existing timetable slots for this academic year will be replaced only if a complete conflict-free timetable is found.',
      ),
    );
    if (!replaceExisting) return;

    try {
      const result = await generate.mutateAsync({ academicYearId: effectiveYear, replaceExisting: true });
      setConflicts(result.conflicts);
      if (result.generated) {
        toast.success(language === 'fr' ? `${result.slots} créneaux générés.` : `${result.slots} timetable slots generated.`);
        setShowConfiguration(false);
        router.push(`/admin/timetable/school?year=${effectiveYear}`);
      } else {
        toast.error(ui('L’emploi du temps n’a pas pu être terminé. Consultez les conflits affichés.', 'The timetable could not be completed. Review the conflicts shown.'));
      }
    } catch {
      toast.error(ui('La génération de l’emploi du temps a échoué.', 'Timetable generation failed.'));
    }
  };

  const addPeriod = () => {
    const lastEnd = periods.at(-1)?.end_time ?? '08:00';
    const [hours, minutes] = lastEnd.split(':').map(Number);
    const totalMinutes = (hours * 60) + minutes + 60;
    const nextEnd = `${String(Math.floor(totalMinutes / 60)).padStart(2, '0')}:${String(totalMinutes % 60).padStart(2, '0')}`;
    setPeriods((current) => [...current, { label: `P${current.filter((_, index) => !breakPeriods.includes(index)).length + 1}`, start_time: lastEnd, end_time: nextEnd }]);
  };

  const setTeacherPeriodAvailable = (day: DayOfWeek, periodIndex: number, isAvailable: boolean) => {
    if (!selectedTeacherId) return;
    setAvailability((current) => {
      const withoutCell = current.filter((row) => !(
        row.teacher_id === selectedTeacherId
        && row.day_of_week === day
        && row.period_index === periodIndex
      ));
      return isAvailable ? withoutCell : [...withoutCell, {
        teacher_id: selectedTeacherId,
        day_of_week: day,
        period_index: periodIndex,
        is_available: false,
      }];
    });
  };

  return (
    <div>
      <PageHeader
        breadcrumb={[{ label: ui('Accueil', 'Home'), href: '/admin/dashboard' }, { label: ui('Emploi du temps', 'Timetable') }]}
        title={ui('Emploi du temps', 'Timetable')}
        description={ui('Générez automatiquement l’emploi du temps, puis ouvrez une classe pour effectuer des ajustements.', 'Generate the school timetable automatically, then open any class for manual adjustments.')}
        actions={
          <>
            <Button
              variant="outline"
              leftIcon={<UserRoundSearch className="h-4 w-4" />}
              onClick={() => router.push('/admin/timetable/teachers')}
            >
              {ui('Voir par enseignant', 'View by teacher')}
            </Button>
            <Button
              variant="outline"
              leftIcon={<Printer className="h-4 w-4" />}
              disabled={!effectiveYear}
              onClick={() => router.push(`/admin/timetable/school?year=${effectiveYear}`)}
            >
              {ui('Emploi du temps général', 'School timetable')}
            </Button>
            <Button
              variant="outline"
              leftIcon={<Settings2 className="h-4 w-4" />}
              onClick={() => setShowConfiguration((visible) => !visible)}
            >
              {ui('Paramètres du générateur', 'Generator settings')}
            </Button>
          </>
        }
      />

      <Card className="mb-6">
        <CardContent className="flex flex-wrap items-center gap-4 py-4">
          <CalendarDays className="h-5 w-5 text-secondary-500" />
          <select
            value={effectiveYear ?? ''}
            onChange={(event) => setYearId(event.target.value)}
            className="h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm"
          >
            {(years ?? []).map((year) => <option key={year.id} value={year.id}>{year.title}</option>)}
          </select>
        </CardContent>
      </Card>

      {showConfiguration && (
        <div className="mb-6 space-y-4">
          {configLoading ? <Skeleton className="h-64" /> : (
            <>
              <Card>
                <CardHeader>
                  <CardTitle>{ui('Jours ouvrables et périodes', 'Working days and periods')}</CardTitle>
                  <CardDescription>{ui('Les périodes de pause sont exclues de l’affectation des cours.', 'Break rows are excluded from lesson allocation.')}</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="flex flex-wrap gap-2">
                    {DAYS.map((day) => (
                      <label key={day.value} className="flex items-center gap-2 rounded-button border border-secondary-200 px-3 py-2 text-sm">
                        <input
                          type="checkbox"
                          checked={workingDays.includes(day.value)}
                          onChange={(event) => setWorkingDays((current) => (
                            event.target.checked
                              ? [...current, day.value]
                              : current.filter((value) => value !== day.value)
                          ))}
                        />
                        {dayLabel(day.value, day.label)}
                      </label>
                    ))}
                  </div>
                  <div className="space-y-2">
                    {periods.map((period, index) => (
                      <div key={index} className="grid grid-cols-[100px_1fr_1fr_100px_40px] items-end gap-2">
                        <Input
                          label={ui('Libellé', 'Label')}
                          value={period.label ?? ''}
                          disabled={isFixedBreak(period)}
                          onChange={(event) => setPeriods((current) => current.map((row, rowIndex) => (
                            rowIndex === index ? { ...row, label: event.target.value } : row
                          )))}
                        />
                        <Input
                          type="time"
                          label={ui('Début', 'Start')}
                          value={period.start_time}
                          disabled={isFixedBreak(period)}
                          onChange={(event) => setPeriods((current) => current.map((row, rowIndex) => (
                            rowIndex === index ? { ...row, start_time: event.target.value } : row
                          )))}
                        />
                        <Input
                          type="time"
                          label={ui('Fin', 'End')}
                          value={period.end_time}
                          disabled={isFixedBreak(period)}
                          onChange={(event) => setPeriods((current) => current.map((row, rowIndex) => (
                            rowIndex === index ? { ...row, end_time: event.target.value } : row
                          )))}
                        />
                        <label className="mb-2 flex items-center gap-2 text-sm">
                          <input
                            type="checkbox"
                            checked={breakPeriods.includes(index)}
                            disabled={isFixedBreak(period)}
                            onChange={(event) => setBreakPeriods((current) => (
                              event.target.checked ? [...current, index] : current.filter((value) => value !== index)
                            ))}
                          />
                          {ui('Pause', 'Break')}
                        </label>
                        <button
                          type="button"
                          aria-label={`Remove period ${index + 1}`}
                          disabled={isFixedBreak(period)}
                          onClick={() => {
                            setPeriods((current) => current.filter((_, rowIndex) => rowIndex !== index));
                            setBreakPeriods((current) => current.filter((value) => value !== index).map((value) => value > index ? value - 1 : value));
                          }}
                          className="mb-1 rounded-button p-2 text-danger hover:bg-danger-light disabled:cursor-not-allowed disabled:opacity-30"
                        >
                          <Trash2 className="h-4 w-4" />
                        </button>
                      </div>
                    ))}
                  </div>
                  <Button size="sm" variant="outline" leftIcon={<Plus className="h-4 w-4" />} onClick={addPeriod}>
                    {ui('Ajouter une période', 'Add period')}
                  </Button>
                </CardContent>
              </Card>

              <Card>
                <CardHeader>
                  <CardTitle>{ui('Fréquence hebdomadaire des matières', 'Weekly subject frequency')}</CardTitle>
                  <CardDescription>{ui('Nombre de cours requis chaque semaine pour chaque matière et classe.', 'Number of lessons each subject requires per class every week.')}</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  {(generationData?.classes ?? []).map((schoolClass) => (
                    <div key={schoolClass.id}>
                      <p className="mb-2 font-semibold text-ink">{schoolClass.name}</p>
                      <div className="grid gap-2 md:grid-cols-2 lg:grid-cols-3">
                        {schoolClass.subjects.map((subject) => (
                          <label key={subject.id} className="flex items-center justify-between gap-3 rounded-card border border-secondary-200 p-3 text-sm">
                            <span>
                              {subject.name}
                              {!subject.teacher_id && <span className="block text-xs text-danger">{ui('Enseignant requis', 'Teacher required')}</span>}
                            </span>
                            <input
                              type="number"
                              min={1}
                              max={15}
                              value={frequencies[`${schoolClass.id}|${subject.id}`] ?? 1}
                              onChange={(event) => setFrequencies((current) => ({
                                ...current,
                                [`${schoolClass.id}|${subject.id}`]: Number(event.target.value),
                              }))}
                              className="h-9 w-16 rounded-input border border-secondary-300 px-2 text-right"
                            />
                          </label>
                        ))}
                      </div>
                    </div>
                  ))}
                </CardContent>
              </Card>

              <Card>
                <CardHeader>
                  <CardTitle>{ui('Disponibilités des enseignants', 'Teacher availability')}</CardTitle>
                  <CardDescription>{ui('Choisissez un enseignant, puis cochez toutes les périodes où il est disponible. Les pauses sont automatiquement exclues.', 'Choose a teacher, then check every period when the teacher is available. Breaks are excluded automatically.')}</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  <select
                    value={selectedTeacherId}
                    onChange={(event) => setSelectedTeacherId(event.target.value)}
                    className="h-10 w-full rounded-input border border-secondary-300 bg-surface px-3 text-sm md:max-w-md"
                  >
                    {(teachersData?.data ?? []).map((teacher) => (
                      <option key={teacher.id} value={teacher.id}>{teacher.full_name}</option>
                    ))}
                  </select>

                  {selectedTeacherId ? (
                    <div className="overflow-x-auto rounded-card border border-secondary-200">
                      <table className="min-w-full border-collapse text-sm">
                        <thead>
                          <tr className="bg-secondary-50">
                            <th className="sticky left-0 bg-secondary-50 px-3 py-2 text-left font-medium">{ui('Jour', 'Day')}</th>
                            {periods.map((period, periodIndex) => (
                              <th key={periodIndex} className="min-w-24 px-2 py-2 text-center font-medium">
                                {period.label || `P${periodIndex + 1}`}
                                <span className="block text-xs font-normal text-secondary-500">{period.start_time}–{period.end_time}</span>
                              </th>
                            ))}
                          </tr>
                        </thead>
                        <tbody>
                          {DAYS.filter((day) => workingDays.includes(day.value)).map((day) => (
                            <tr key={day.value} className="border-t border-secondary-200">
                              <th className="sticky left-0 bg-surface px-3 py-2 text-left font-medium">{dayLabel(day.value, day.label)}</th>
                              {periods.map((period, periodIndex) => {
                                const fixedBreak = breakPeriods.includes(periodIndex);
                                const unavailable = availability.some((row) => (
                                  row.teacher_id === selectedTeacherId
                                  && row.day_of_week === day.value
                                  && row.period_index === periodIndex
                                ));
                                return (
                                  <td key={periodIndex} className={`px-2 py-2 text-center ${fixedBreak ? 'bg-warning-light' : ''}`}>
                                    {fixedBreak ? (
                                      <span className="text-xs font-medium text-warning-dark">{ui('Pause', 'Break')}</span>
                                    ) : (
                                      <input
                                        type="checkbox"
                                        aria-label={`${day.label} ${period.label ?? periodIndex}`}
                                        checked={!unavailable}
                                        onChange={(event) => setTeacherPeriodAvailable(day.value, periodIndex, event.target.checked)}
                                        className="h-4 w-4"
                                      />
                                    )}
                                  </td>
                                );
                              })}
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  ) : (
                    <p className="text-sm text-secondary-500">{ui('Enregistrez d’abord un enseignant.', 'Register a teacher first.')}</p>
                  )}
                  <p className="text-xs text-secondary-500">
                    {ui('Coché = disponible. Décochez uniquement les périodes où cet enseignant ne peut pas enseigner.', 'Checked = available. Uncheck only the periods when this teacher cannot teach.')}
                  </p>
                </CardContent>
              </Card>

              {conflicts.length > 0 && (
                <Card className="border-danger">
                  <CardHeader><CardTitle className="text-danger">{ui('Conflits de génération', 'Generation conflicts')}</CardTitle></CardHeader>
                  <CardContent>
                    <ul className="list-disc space-y-1 pl-5 text-sm text-danger">
                      {conflicts.map((conflict, index) => <li key={index}>{conflict}</li>)}
                    </ul>
                  </CardContent>
                </Card>
              )}

              <div className="flex justify-end gap-2">
                <Button variant="outline" onClick={() => void save()} isLoading={saveConfig.isPending}>{ui('Enregistrer la configuration', 'Save configuration')}</Button>
                <Button leftIcon={<WandSparkles className="h-4 w-4" />} onClick={() => void runGenerator()} isLoading={generate.isPending}>
                  {ui('Générer l’emploi du temps', 'Generate timetable')}
                </Button>
              </div>
            </>
          )}
        </div>
      )}

      {isLoading ? <Skeleton className="h-48" /> : (
        <div className="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
          {(classes ?? []).filter((schoolClass) => schoolClass.is_active).map((schoolClass) => (
            <Card
              key={schoolClass.id}
              className="cursor-pointer transition-shadow hover:shadow-card"
              onClick={() => router.push(`/admin/timetable/${schoolClass.id}`)}
            >
              <CardContent className="flex items-center justify-between py-4">
                <div>
                  <p className="font-semibold text-ink">{schoolClass.name}</p>
                  <p className="text-xs text-secondary-500">{schoolClass.grade_level}</p>
                </div>
                <ArrowRight className="h-4 w-4 text-secondary-400" />
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}
