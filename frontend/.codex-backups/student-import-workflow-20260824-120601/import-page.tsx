'use client';

import Link from 'next/link';
import { useMemo, useState } from 'react';
import { AlertCircle, CheckCircle2, FileSpreadsheet, Upload, Users, WalletCards } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/Button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/Card';
import { PageHeader } from '@/components/ui/PageHeader';
import { useClasses } from '@/hooks/academic';
import { studentsApi } from '@/lib/api/students';
import type { StudentImport, StudentImportAnalysis } from '@/types/student';
import { useTranslation } from '@/hooks/useTranslation';
import { useAuthStore } from '@/store/auth';

type Step = 1 | 2 | 3;

export default function OfficialStudentImportPage() {
  const { language } = useTranslation();
  const role = useAuthStore((state) => state.user?.role.name);
  const studentsBase = role === 'secretary' ? '/secretary/students' : '/admin/students';
  const classesBase = role === 'secretary' ? '/secretary/classes' : '/admin/academic/classes';
  const ui = (fr: string, en: string) => language === 'fr' ? fr : en;
  const [step, setStep] = useState<Step>(1);
  const [file, setFile] = useState<File | null>(null);
  const [analysis, setAnalysis] = useState<StudentImportAnalysis | null>(null);
  const [columnMapping, setColumnMapping] = useState<Record<string, string>>({});
  const [preview, setPreview] = useState<StudentImport | null>(null);
  const [classMapping, setClassMapping] = useState<Record<string, string>>({});
  const [duplicateAction, setDuplicateAction] = useState<'skip' | 'update'>('skip');
  const [busy, setBusy] = useState(false);
  const [pageError, setPageError] = useState<string | null>(null);
  const yearId = analysis?.active_academic_year.id ?? preview?.academic_year_id ?? '';
  const { data: classes } = useClasses(yearId ? { academic_year_id: yearId } : {});
  const externalClasses = useMemo(() => preview?.external_classes ?? [], [preview]);
  const completed = preview?.status === 'completed';

  function message(error: any, fallback: string): string {
    const validation = error?.response?.data?.errors;
    const firstValidation = validation ? Object.values(validation).flat().find(Boolean) : null;
    return String(firstValidation ?? error?.response?.data?.message ?? error?.message ?? fallback);
  }

  async function analyzeFile() {
    if (!file) { setPageError(ui('Choisissez un fichier Excel ou CSV.', 'Choose an Excel or CSV file.')); return; }
    setBusy(true); setPageError(null);
    try {
      const result = await studentsApi.analyzeOfficialImport(file);
      setAnalysis(result); setColumnMapping(result.suggested_mapping); setPreview(null); setStep(2);
      toast.success(ui(`${result.row_count} ligne(s) détectée(s).`, `${result.row_count} row(s) detected.`));
    } catch (error: any) { setPageError(message(error, ui('Impossible de lire le fichier.', 'The file could not be read.'))); }
    finally { setBusy(false); }
  }

  async function validateMapping() {
    if (!file || !analysis) return;
    const hasNames = Boolean(columnMapping.full_name || (columnMapping.first_name && columnMapping.last_name));
    if (!columnMapping.official_matricule || !columnMapping.class_name || !hasNames) {
      setPageError(ui('Mappez le matricule, la classe et soit le nom complet, soit prénom + nom.', 'Map matricule, class, and either full name or first name + last name.'));
      return;
    }
    const selected = Object.values(columnMapping).filter(Boolean);
    if (selected.length !== new Set(selected).size) {
      setPageError(ui('Une colonne ne peut pas être utilisée pour plusieurs champs.', 'One spreadsheet column cannot be used for multiple fields.'));
      return;
    }
    setBusy(true); setPageError(null);
    try {
      const result = await studentsApi.previewOfficialImport(file, columnMapping);
      setPreview(result); setClassMapping(result.class_mapping ?? {}); setStep(3);
      toast.success(ui('Validation terminée. Vérifiez les classes et les lignes.', 'Validation complete. Review classes and rows.'));
    } catch (error: any) { setPageError(message(error, ui('La validation a échoué.', 'Validation failed.'))); }
    finally { setBusy(false); }
  }

  async function confirmImport() {
    if (!preview) return;
    const missing = externalClasses.filter((name) => !classMapping[name]);
    if (missing.length) {
      setPageError(ui(`Mappez ces classes avant l’import : ${missing.join(', ')}`, `Map these classes before import: ${missing.join(', ')}`));
      return;
    }
    if ((preview.summary.error ?? 0) > 0 && !window.confirm(ui(
      `${preview.summary.error} ligne(s) comportent des erreurs et seront ignorées. Continuer ?`,
      `${preview.summary.error} row(s) contain errors and will be skipped. Continue?`,
    ))) return;

    setBusy(true); setPageError(null);
    try {
      const result = await studentsApi.confirmOfficialImport(
        preview.id,
        externalClasses.map((sourceClass) => ({ source_class: sourceClass, class_id: classMapping[sourceClass] })),
        duplicateAction,
      );
      setPreview(result);
      toast.success(ui('Import terminé sans création de paiement.', 'Import completed without creating payments.'));
    } catch (error: any) { setPageError(message(error, ui('L’import a échoué.', 'Import failed.'))); }
    finally { setBusy(false); }
  }

  function reset() {
    setStep(1); setFile(null); setAnalysis(null); setColumnMapping({}); setPreview(null);
    setClassMapping({}); setDuplicateAction('skip'); setPageError(null);
  }

  return <div>
    <PageHeader
      breadcrumb={[{ label: ui('Élèves', 'Students'), href: studentsBase }, { label: ui('Import tableur', 'Spreadsheet import') }]}
      title={ui('Importer des élèves depuis Excel ou CSV', 'Import students from Excel or CSV')}
      description={ui('Téléversez, mappez, prévisualisez puis importez. L’inscription ne crée jamais un paiement.', 'Upload, map, preview, then import. Student registration never creates a payment.')}
    />

    <div className="mb-6 grid grid-cols-3 gap-2">
      {([
        [1, ui('Téléversement', 'Upload')], [2, ui('Validation et mapping', 'Validate & map')], [3, ui('Prévisualisation et import', 'Preview & import')],
      ] as Array<[Step, string]>).map(([number, label]) => <div key={number} className={`rounded-card border px-3 py-3 text-center text-sm font-medium ${step >= number ? 'border-primary-300 bg-primary-50 text-primary-700' : 'border-secondary-200 text-secondary-400'}`}><span className="mr-2 inline-flex h-6 w-6 items-center justify-center rounded-full bg-white">{number}</span>{label}</div>)}
    </div>

    {pageError && <div role="alert" className="mb-6 flex gap-2 rounded-card border border-danger/30 bg-danger-light p-4 text-sm text-danger-dark"><AlertCircle className="mt-0.5 h-5 w-5 shrink-0" /><span>{pageError}</span></div>}

    {step === 1 && <Card><CardHeader><CardTitle>{ui('1. Choisir le fichier', '1. Choose the file')}</CardTitle><CardDescription>{ui('Formats acceptés : .xlsx, .csv et .txt, jusqu’à 10 Mo et 10 000 élèves.', 'Accepted formats: .xlsx, .csv, and .txt, up to 10 MB and 10,000 students.')}</CardDescription></CardHeader><CardContent className="space-y-4">
      <label className="block text-sm font-medium text-secondary-700">{ui('Fichier Excel/CSV', 'Excel/CSV file')}<input type="file" accept=".xlsx,.csv,.txt" onChange={(event) => { setFile(event.target.files?.[0] ?? null); setPageError(null); }} className="mt-1 block w-full rounded-input border border-secondary-300 bg-surface p-2 text-sm" /></label>
      <div className="rounded-card border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900"><strong>{ui('Année académique :', 'Academic year:')}</strong> {ui('le système utilisera automatiquement l’année active de cette école.', 'the system will automatically use this school’s active academic year.')}</div>
      <Button onClick={analyzeFile} isLoading={busy} leftIcon={<Upload className="h-4 w-4" />}>{ui('Détecter les colonnes', 'Detect columns')}</Button>
    </CardContent></Card>}

    {step === 2 && analysis && <>
      <Card className="mb-6"><CardHeader><CardTitle>{ui('2. Mapper les colonnes', '2. Map columns')}</CardTitle><CardDescription>{analysis.row_count} {ui('élève(s) · année active', 'student(s) · active year')} {analysis.active_academic_year.title}</CardDescription></CardHeader><CardContent className="grid gap-4 md:grid-cols-2">
        {analysis.supported_fields.map((field) => <label key={field.key} className="text-sm font-medium text-secondary-700">{field.label}{field.required ? ' *' : ''}<select value={columnMapping[field.key] ?? ''} onChange={(event) => setColumnMapping((current) => ({ ...current, [field.key]: event.target.value }))} className="mt-1 h-10 w-full rounded-input border border-secondary-300 bg-surface px-3"><option value="">{ui('Ne pas importer', 'Do not import')}</option>{analysis.detected_columns.map((column) => <option key={column} value={column}>{column}</option>)}</select></label>)}
        <div className="flex flex-wrap gap-2 md:col-span-2"><Button variant="ghost" onClick={() => setStep(1)}>{ui('Retour', 'Back')}</Button><Button onClick={validateMapping} isLoading={busy} leftIcon={<CheckCircle2 className="h-4 w-4" />}>{ui('Valider et prévisualiser', 'Validate and preview')}</Button></div>
      </CardContent></Card>
      <Card><CardHeader><CardTitle>{ui('Aperçu du fichier source', 'Source file sample')}</CardTitle><CardDescription>{ui('Les cinq premières lignes uniquement. Rien n’est encore enregistré.', 'First five rows only. Nothing has been imported yet.')}</CardDescription></CardHeader><CardContent className="overflow-x-auto"><table className="min-w-full text-xs"><thead><tr>{analysis.detected_columns.map((column) => <th key={column} className="border-b p-2 text-left">{column}</th>)}</tr></thead><tbody>{analysis.sample_rows.map((row, index) => <tr key={index}>{analysis.detected_columns.map((column) => <td key={column} className="border-b border-secondary-100 p-2">{row[column]}</td>)}</tr>)}</tbody></table></CardContent></Card>
    </>}

    {step === 3 && preview && <>
      {completed ? <Card className="mb-6 border-success/30 bg-success-light"><CardHeader><CardTitle>{ui('Import terminé', 'Import completed')}</CardTitle><CardDescription>{ui('Le résultat est conservé pour audit. Aucun paiement n’a été créé.', 'The result is retained for audit. No payment was created.')}</CardDescription></CardHeader><CardContent><div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">{[
        [ui('Nouveaux élèves', 'New students'), 'new_students'], [ui('Élèves mis à jour', 'Students updated'), 'existing_students_updated'],
        [ui('Élèves inscrits', 'Students enrolled'), 'students_enrolled'], [ui('Ignorés', 'Skipped'), 'skipped'],
        [ui('Frais assignés', 'Fee structures assigned'), 'fee_structures_assigned'], [ui('Sans configuration de frais', 'Without fee configuration'), 'students_without_fee_configuration'],
        [ui('Erreurs', 'Errors'), 'errors'], [ui('Paiements créés', 'Payments created'), 'payments_created'],
      ].map(([label, key]) => <div key={key} className="rounded-card bg-white p-3"><p className="text-xs text-secondary-500">{label}</p><p className="text-2xl font-semibold">{preview.summary[key] ?? 0}</p></div>)}</div><Button className="mt-4" variant="outline" onClick={reset}>{ui('Importer un autre fichier', 'Import another file')}</Button></CardContent></Card> : <>
        <div className="mb-6 grid grid-cols-2 gap-3 md:grid-cols-4">{[
          [ui('Total', 'Total'), 'total', Users], [ui('Prêts', 'Ready'), 'ready', CheckCircle2],
          [ui('Existants', 'Existing'), 'existing', WalletCards], [ui('Erreurs', 'Errors'), 'error', AlertCircle],
        ].map(([label, key, Icon]: any) => <Card key={key}><CardContent className="flex items-center gap-3 py-4"><Icon className="h-5 w-5 text-primary-600" /><div><p className="text-xs uppercase text-secondary-500">{label}</p><p className="text-2xl font-semibold">{preview.summary[key] ?? 0}</p></div></CardContent></Card>)}</div>

        <Card className="mb-6"><CardHeader><CardTitle>{ui('Mapper les classes', 'Map classes')}</CardTitle><CardDescription>{ui(`Année active : ${preview.academic_year?.title}. Une classe inconnue ne sera jamais créée automatiquement.`, `Active year: ${preview.academic_year?.title}. An unknown class is never created automatically.`)}</CardDescription></CardHeader><CardContent className="space-y-3">
          {externalClasses.map((sourceClass) => <label key={sourceClass} className="grid items-center gap-2 text-sm md:grid-cols-2"><span>{sourceClass}{!classMapping[sourceClass] && <span className="ml-2 text-danger">{ui('Classe inexistante', 'Class not found')}</span>}</span><select value={classMapping[sourceClass] ?? ''} onChange={(event) => setClassMapping((current) => ({ ...current, [sourceClass]: event.target.value }))} className="h-10 rounded-input border border-secondary-300 bg-surface px-3"><option value="">{ui('Choisir une classe existante', 'Select an existing class')}</option>{(classes ?? []).filter((item) => item.is_active).map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label>)}
          {externalClasses.some((name) => !classMapping[name]) && <p className="text-sm text-secondary-600">{ui('La classe nécessaire n’existe pas ?', 'Required class does not exist?')} <Link target="_blank" href={classesBase} className="font-medium text-primary-700 underline">{ui('Créer la classe dans un nouvel onglet', 'Create the class in a new tab')}</Link>, {ui('puis actualisez cette page et recommencez l’import.', 'then refresh this page and restart the import.')}</p>}
        </CardContent></Card>

        <Card className="mb-6"><CardHeader><CardTitle>{ui('Élèves déjà existants', 'Existing students')}</CardTitle><CardDescription>{ui('Le matricule officiel est l’identifiant de rapprochement.', 'The official matricule is the matching identifier.')}</CardDescription></CardHeader><CardContent><div className="grid gap-3 sm:grid-cols-2"><label className={`rounded-card border p-4 ${duplicateAction === 'skip' ? 'border-primary-400 bg-primary-50' : 'border-secondary-200'}`}><input type="radio" className="mr-2" checked={duplicateAction === 'skip'} onChange={() => setDuplicateAction('skip')} />{ui('Ignorer les élèves existants', 'Skip existing students')}</label><label className={`rounded-card border p-4 ${duplicateAction === 'update' ? 'border-primary-400 bg-primary-50' : 'border-secondary-200'}`}><input type="radio" className="mr-2" checked={duplicateAction === 'update'} onChange={() => setDuplicateAction('update')} />{ui('Mettre à jour leur profil et inscription', 'Update their profile and enrollment')}</label></div></CardContent></Card>
      </>}

      <Card><CardHeader><CardTitle>{ui('Vérification des lignes', 'Row review')}</CardTitle><CardDescription>{ui('Jusqu’à 500 lignes sont affichées; le serveur conserve l’audit complet.', 'Up to 500 rows are shown; the server keeps the complete audit.')}</CardDescription></CardHeader><CardContent className="overflow-x-auto"><table className="min-w-full text-sm"><thead><tr className="border-b text-left"><th className="p-2">{ui('Ligne', 'Row')}</th><th className="p-2">Matricule</th><th className="p-2">{ui('Élève', 'Student')}</th><th className="p-2">{ui('Classe', 'Class')}</th><th className="p-2">{ui('Statut', 'Status')}</th><th className="p-2">{ui('Problème / avertissement', 'Issue / warning')}</th></tr></thead><tbody>{preview.rows.map((row) => <tr key={row.id} className="border-b border-secondary-100"><td className="p-2">{row.row_number}</td><td className="p-2 font-mono">{row.normalized_data.official_matricule}</td><td className="p-2">{row.normalized_data.last_name} {row.normalized_data.first_name}</td><td className="p-2">{row.normalized_data.class_name}</td><td className="p-2 capitalize">{row.status}</td><td className="p-2"><span className="text-danger">{row.errors ? Object.values(row.errors).join(' ') : ''}</span><span className="text-warning-dark">{row.warnings ? Object.values(row.warnings).join(' ') : ''}</span></td></tr>)}</tbody></table>
        {!completed && <div className="mt-5 flex flex-wrap gap-2"><Button variant="ghost" onClick={() => setStep(2)}>{ui('Modifier le mapping', 'Change mapping')}</Button><Button onClick={confirmImport} isLoading={busy} leftIcon={<FileSpreadsheet className="h-4 w-4" />}>{ui('Confirmer l’import', 'Confirm import')}</Button></div>}
      </CardContent></Card>
    </>}
  </div>;
}
