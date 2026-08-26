'use client';

import { useEffect, useState } from 'react';
import { Plus, School, Power, PowerOff, Settings, Copy, KeyRound, Upload, Save } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/Button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/Card';
import { Input } from '@/components/ui/Input';
import { Modal } from '@/components/ui/Modal';
import { PageHeader } from '@/components/ui/PageHeader';
import { platformApi, type CreateSchoolPayload, type PlatformSchool, type UpdatePlatformSchoolPayload, type UpdateSchoolAdministratorCredentialsPayload } from '@/lib/api/platform';
import { useTranslation } from '@/hooks/useTranslation';

const emptyForm: CreateSchoolPayload = {
  school_name: '', email: '', phone: '', city: '', country: 'Cameroon', default_locale: 'fr',
  education_systems: ['secondary_general', 'secondary_technical'], administrator_first_name: '',
  administrator_last_name: '', administrator_email: '', administrator_password: '',
};

export default function PlatformSchoolsPage() {
  const { language } = useTranslation();
  const ui = (french: string, english: string) => language === 'fr' ? french : english;
  const [schools, setSchools] = useState<PlatformSchool[]>([]);
  const [loading, setLoading] = useState(true);
  const [open, setOpen] = useState(false);
  const [saving, setSaving] = useState(false);
  const [changingSchoolId, setChangingSchoolId] = useState<string | null>(null);
  const [form, setForm] = useState<CreateSchoolPayload>(emptyForm);
  const [editing, setEditing] = useState<PlatformSchool | null>(null);
  const [editForm, setEditForm] = useState<UpdatePlatformSchoolPayload | null>(null);
  const [savingIdentity, setSavingIdentity] = useState(false);
  const [uploadingLogo, setUploadingLogo] = useState<'primary' | 'secondary' | 'document_header' | null>(null);
  const [createdAccess, setCreatedAccess] = useState<{ schoolName: string; schoolCode: string; email: string; temporaryPassword: string | null } | null>(null);
  const [credentialSchool, setCredentialSchool] = useState<PlatformSchool | null>(null);
  const [credentialForm, setCredentialForm] = useState<UpdateSchoolAdministratorCredentialsPayload>({ first_name: '', last_name: '', email: '', administrator_password: '', administrator_password_confirmation: '' });
  const [loadingCredentials, setLoadingCredentials] = useState(false);
  const [savingCredentials, setSavingCredentials] = useState(false);
  const update = (key: keyof CreateSchoolPayload, value: unknown) => setForm((current) => ({ ...current, [key]: value }));
  const updateEdit = (key: keyof UpdatePlatformSchoolPayload, value: unknown) => setEditForm((current) => (
    current ? ({ ...current, [key]: value } as UpdatePlatformSchoolPayload) : current
  ));

  async function load() {
    setLoading(true);
    try { setSchools((await platformApi.listSchools()).data); }
    catch (error: any) { toast.error(error?.response?.data?.message ?? 'Could not load schools.'); }
    finally { setLoading(false); }
  }
  useEffect(() => { void load(); }, []);

  async function create() {
    setSaving(true);
    try {
      const school = await platformApi.createSchool(form);
      setCreatedAccess({
        schoolName: school.school_name,
        schoolCode: school.school_code,
        email: form.administrator_email,
        temporaryPassword: form.administrator_password,
      });
      setSchools((current) => [...current, school].sort((a, b) => a.school_name.localeCompare(b.school_name)));
      setOpen(false); setForm(emptyForm); toast.success(`School created with code ${school.school_code}.`);
    } catch (error: any) { toast.error(error?.response?.data?.message ?? 'Could not create the school.'); }
    finally { setSaving(false); }
  }
  function customizeSchool(school: PlatformSchool) {
    setEditing(school);
    setEditForm({
      school_name: school.school_name, school_code: school.school_code, slug: school.slug,
      slogan: school.slogan, email: school.email, phone: school.phone, address: school.address,
      website: school.website, city: school.city, country: school.country,
      default_locale: school.default_locale, education_systems: school.education_systems,
      primary_color: school.primary_color ?? '#1D4ED8', secondary_color: school.secondary_color ?? '#0F766E',
      document_header: school.document_header, document_footer: school.document_footer,
      document_header_image_settings: school.document_header_image_settings ?? { mode: 'fit', width: 100, max_height: 105, alignment: 'center' },
      principal_name: school.principal_name, principal_title: school.principal_title,
    });
  }
  function replaceSchool(updated: PlatformSchool) {
    setSchools((current) => current.map((item) => item.id === updated.id ? updated : item));
    setEditing(updated);
  }
  async function saveIdentity() {
    if (!editing || !editForm) return;
    setSavingIdentity(true);
    try {
      replaceSchool(await platformApi.updateSchool(editing.id, editForm));
      toast.success(ui('Identité de l’établissement enregistrée.', 'School identity saved.'));
    } catch (error: any) {
      toast.error(error?.response?.data?.message ?? ui('Impossible d’enregistrer l’établissement.', 'Could not save the school.'));
    } finally { setSavingIdentity(false); }
  }
  async function uploadLogo(kind: 'primary' | 'secondary' | 'document_header', file?: File) {
    if (!editing || !file) return;
    setUploadingLogo(kind);
    try {
      replaceSchool(await platformApi.uploadSchoolLogo(editing.id, file, kind));
      toast.success(ui('Logo enregistré pour cet établissement.', 'Logo saved for this school.'));
    } catch (error: any) {
      toast.error(error?.response?.data?.message ?? ui('Impossible de charger le logo.', 'Could not upload the logo.'));
    } finally { setUploadingLogo(null); }
  }
  async function toggleSchool(school: PlatformSchool) {
    const nextStatus = !school.is_active;
    if (!window.confirm(nextStatus
      ? `Activate ${school.school_name}?`
      : `Deactivate ${school.school_name}? All active school sessions will be revoked.`)) return;
    setChangingSchoolId(school.id);
    try {
      const updated = await platformApi.setSchoolActive(school.id, nextStatus);
      setSchools((current) => current.map((item) => item.id === updated.id ? updated : item));
      toast.success(nextStatus ? 'School activated.' : 'School deactivated and active sessions revoked.');
    } catch (error: any) {
      toast.error(error?.response?.data?.message ?? 'Could not update the school status.');
    } finally {
      setChangingSchoolId(null);
    }
  }

  async function openPrincipalCredentials(school: PlatformSchool) {
    setCredentialSchool(school);
    setLoadingCredentials(true);
    try {
      const administrator = await platformApi.getSchoolAdministratorCredentials(school.id);
      setCredentialForm({
        first_name: administrator.first_name,
        last_name: administrator.last_name,
        email: administrator.email,
        administrator_password: '',
        administrator_password_confirmation: '',
      });
    } catch (error: any) {
      toast.error(error?.response?.data?.message ?? ui('Impossible de charger le compte du proviseur.', 'Could not load the principal account.'));
      setCredentialSchool(null);
    } finally { setLoadingCredentials(false); }
  }

  async function savePrincipalCredentials() {
    if (!credentialSchool) return;
    if (credentialForm.administrator_password !== credentialForm.administrator_password_confirmation) {
      toast.error(ui('Les mots de passe ne correspondent pas.', 'The passwords do not match.'));
      return;
    }
    setSavingCredentials(true);
    try {
      const password = credentialForm.administrator_password?.trim() ?? '';
      const payload: UpdateSchoolAdministratorCredentialsPayload = {
        first_name: credentialForm.first_name,
        last_name: credentialForm.last_name,
        email: credentialForm.email,
        ...(password ? {
          administrator_password: password,
          administrator_password_confirmation: credentialForm.administrator_password_confirmation,
        } : {}),
      };
      const administrator = await platformApi.updateSchoolAdministratorCredentials(credentialSchool.id, payload);
      setCreatedAccess({
        schoolName: credentialSchool.school_name,
        schoolCode: credentialSchool.school_code,
        email: administrator.email,
        temporaryPassword: password || null,
      });
      setCredentialSchool(null);
      toast.success(ui('Identifiants du proviseur mis à jour.', 'Principal login credentials updated.'));
    } catch (error: any) {
      const errors = error?.response?.data?.errors;
      const firstError = errors && Object.values(errors).flat()[0];
      toast.error(typeof firstError === 'string' ? firstError : (error?.response?.data?.message ?? ui('Impossible de modifier les identifiants.', 'Could not update the credentials.')));
    } finally { setSavingCredentials(false); }
  }

  return <div>
    <PageHeader title={ui('Établissements clients', 'Customer schools')} description={ui('Créez les écoles et configurez uniquement leur identité, leurs coordonnées et leurs documents.', 'Create schools and configure only their identity, contacts, and document branding.')}
      actions={<Button onClick={() => setOpen(true)} leftIcon={<Plus className="h-4 w-4" />}>{ui('Créer une école', 'Create school')}</Button>} />
    {createdAccess && <Card className="mb-6 border-primary-300 bg-primary-50"><CardHeader><div className="flex items-start gap-3"><KeyRound className="h-6 w-6 text-primary-700" /><div><CardTitle>Principal access updated</CardTitle><CardDescription>Copy the corrected login information and transmit it securely. Existing sessions have been revoked.</CardDescription></div></div></CardHeader><CardContent><div className="grid gap-2 text-sm md:grid-cols-3"><p><span className="text-secondary-500">School code</span><br /><strong className="font-mono">{createdAccess.schoolCode}</strong></p><p><span className="text-secondary-500">Principal email</span><br /><strong>{createdAccess.email}</strong></p><p><span className="text-secondary-500">Temporary password</span><br /><strong className="font-mono">{createdAccess.temporaryPassword ?? 'Unchanged'}</strong></p></div><div className="mt-4 flex flex-wrap gap-2"><Button size="sm" leftIcon={<Copy className="h-4 w-4" />} onClick={async () => { await navigator.clipboard.writeText(`CLYVERO ERP\nSchool: ${createdAccess.schoolName}\nSchool code: ${createdAccess.schoolCode}\nEmail: ${createdAccess.email}\nPassword: ${createdAccess.temporaryPassword ?? 'Use the existing password'}\nLogin: ${window.location.origin}/login`); toast.success('Principal credentials copied.'); }}>Copy credentials</Button><Button size="sm" variant="ghost" onClick={() => setCreatedAccess(null)}>Close securely</Button></div></CardContent></Card>}
    {loading ? <p>Loading schools…</p> : <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">{schools.map((school) =>
      <Card key={school.id}><CardHeader><div className="flex items-start gap-3">{school.logo_url ? <img src={school.logo_url} alt="" className="h-12 w-12 rounded object-contain" /> : <div className="flex h-12 w-12 items-center justify-center rounded bg-primary-50"><School className="h-6 w-6 text-primary-600" /></div>}<div><CardTitle>{school.school_name}</CardTitle><CardDescription>{school.school_code} · {school.current_academic_year?.title ?? ui('Aucune année active', 'No active year')} · {school.is_active ? ui('Active', 'Active') : ui('Inactive', 'Inactive')}</CardDescription></div></div></CardHeader><CardContent><p className="mb-4 text-sm text-secondary-600">{school.email}<br />{[school.city, school.country].filter(Boolean).join(', ')}</p><div className="flex flex-wrap gap-2"><Button variant="outline" onClick={() => customizeSchool(school)} leftIcon={<Settings className="h-4 w-4" />}>{ui('Configurer l’école', 'Configure school')}</Button><Button variant="outline" onClick={() => void openPrincipalCredentials(school)} leftIcon={<KeyRound className="h-4 w-4" />}>{ui('Connexion du proviseur', 'Principal login')}</Button><Button variant="outline" onClick={() => toggleSchool(school)} isLoading={changingSchoolId === school.id} leftIcon={school.is_active ? <PowerOff className="h-4 w-4" /> : <Power className="h-4 w-4" />}>{school.is_active ? ui('Désactiver', 'Deactivate') : ui('Activer', 'Activate')}</Button></div></CardContent></Card>
    )}</div>}
    <Modal open={open} onClose={() => setOpen(false)} title="Create customer school" description="Creates an isolated school, its current academic year, and a school administrator." size="xl" footer={<><Button variant="ghost" onClick={() => setOpen(false)}>Cancel</Button><Button onClick={create} isLoading={saving}>Create school</Button></>}>
      <div className="grid gap-4 md:grid-cols-2">
        <Input label="School name" required value={form.school_name} onChange={(e) => update('school_name', e.target.value)} />
        <Input label="School code (optional)" placeholder="Generated automatically" value={form.school_code ?? ''} onChange={(e) => update('school_code', e.target.value.toUpperCase())} />
        <Input type="email" label="School email" required value={form.email} onChange={(e) => update('email', e.target.value)} />
        <Input label="School phone" value={form.phone ?? ''} onChange={(e) => update('phone', e.target.value)} />
        <Input label="School address" value={form.address ?? ''} onChange={(e) => update('address', e.target.value)} />
        <Input label="City" value={form.city ?? ''} onChange={(e) => update('city', e.target.value)} />
        <Input label="Country" value={form.country ?? ''} onChange={(e) => update('country', e.target.value)} />
        <label className="text-sm font-medium text-secondary-700">Default language<select className="mt-1 h-10 w-full rounded-input border border-secondary-300 bg-surface px-3" value={form.default_locale} onChange={(e) => update('default_locale', e.target.value)}><option value="fr">Français</option><option value="en">English</option></select></label>
        <Input label="Administrator first name" required value={form.administrator_first_name} onChange={(e) => update('administrator_first_name', e.target.value)} />
        <Input label="Administrator last name" required value={form.administrator_last_name} onChange={(e) => update('administrator_last_name', e.target.value)} />
        <Input type="email" label="Administrator email" required value={form.administrator_email} onChange={(e) => update('administrator_email', e.target.value)} />
        <Input type="password" label="Temporary strong password" required value={form.administrator_password} onChange={(e) => update('administrator_password', e.target.value)} />
      </div>
    </Modal>
    <Modal
      open={Boolean(editing && editForm)}
      onClose={() => { setEditing(null); setEditForm(null); }}
      title={ui('Configurer l’établissement', 'Configure school')}
      description={ui('Identité officielle, logos, coordonnées et présentation des documents. Les réglages académiques restent au proviseur.', 'Official identity, logos, contacts, and document branding. Academic settings remain with the principal.')}
      size="xl"
      footer={<><Button variant="ghost" onClick={() => { setEditing(null); setEditForm(null); }}>{ui('Fermer', 'Close')}</Button><Button leftIcon={<Save className="h-4 w-4" />} onClick={saveIdentity} isLoading={savingIdentity}>{ui('Enregistrer', 'Save')}</Button></>}
    >
      {editing && editForm && <div className="space-y-6">
        <div className="grid gap-4 sm:grid-cols-2">
          {(['primary', 'secondary'] as const).map((kind) => {
            const logo = kind === 'primary' ? editing.logo_url : editing.secondary_logo_url;
            return <div key={kind} className="rounded-card border border-secondary-200 p-4">
              <p className="mb-3 text-sm font-semibold text-secondary-700">{kind === 'primary' ? ui('Logo principal', 'Primary logo') : ui('Second logo / emblème', 'Secondary logo / emblem')}</p>
              <div className="mb-3 flex h-28 items-center justify-center rounded bg-secondary-50">{logo ? <img src={logo} alt="" className="max-h-24 max-w-full object-contain" /> : <School className="h-10 w-10 text-secondary-300" />}</div>
              <label className="inline-flex cursor-pointer items-center gap-2 rounded-button border border-secondary-300 px-3 py-2 text-sm font-medium hover:bg-secondary-50">
                <Upload className="h-4 w-4" /> {uploadingLogo === kind ? ui('Chargement…', 'Uploading…') : ui('Choisir le logo', 'Choose logo')}
                <input type="file" className="sr-only" accept="image/jpeg,image/png,image/webp" disabled={Boolean(uploadingLogo)} onChange={(event) => { void uploadLogo(kind, event.target.files?.[0]); event.currentTarget.value = ''; }} />
              </label>
            </div>;
          })}
        </div>

        <div className="rounded-card border-2 border-primary-200 bg-primary-50/40 p-4">
          <div className="mb-3">
            <p className="text-sm font-semibold text-secondary-800">{ui('Image d’en-tête officielle', 'Official document header image')}</p>
            <p className="mt-1 text-xs text-secondary-600">{ui('Cette image remplace automatiquement l’en-tête saisi, les logos séparés et le nom reconstruit sur tous les bulletins, reçus, attestations et tableaux d’honneur. Le rapport largeur/hauteur est conservé.', 'This image automatically replaces the typed header, separate logos, and reconstructed school name on every report card, receipt, certificate, and honor-roll document. Its aspect ratio is preserved.')}</p>
          </div>
          <div className="mb-3 flex min-h-32 items-center overflow-hidden rounded border border-secondary-200 bg-white p-3" style={{ justifyContent: editForm.document_header_image_settings.alignment === 'left' ? 'flex-start' : editForm.document_header_image_settings.alignment === 'right' ? 'flex-end' : 'center' }}>
            {editing.document_header_image_url
              ? <img
                  src={editing.document_header_image_url}
                  alt={ui('Aperçu de l’en-tête', 'Header preview')}
                  style={editForm.document_header_image_settings.mode === 'full_width'
                    ? { width: `${editForm.document_header_image_settings.width}%`, height: 'auto' }
                    : { maxWidth: `${editForm.document_header_image_settings.width}%`, maxHeight: `${editForm.document_header_image_settings.max_height}px`, width: 'auto', height: 'auto' }}
                />
              : <div className="text-center text-sm text-secondary-400"><Upload className="mx-auto mb-2 h-8 w-8" />{ui('Aucune image d’en-tête chargée', 'No header image uploaded')}</div>}
          </div>
          <label className="inline-flex cursor-pointer items-center gap-2 rounded-button bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-700">
            <Upload className="h-4 w-4" /> {uploadingLogo === 'document_header' ? ui('Chargement…', 'Uploading…') : ui('Charger l’image d’en-tête', 'Upload header image')}
            <input type="file" className="sr-only" accept="image/png,image/jpeg,image/webp" disabled={Boolean(uploadingLogo)} onChange={(event) => { void uploadLogo('document_header', event.target.files?.[0]); event.currentTarget.value = ''; }} />
          </label>
          <p className="mt-2 text-xs text-secondary-500">PNG transparent recommandé · JPG/WEBP acceptés · maximum 5 Mo.</p>
          <div className="mt-4 grid gap-4 rounded border border-secondary-200 bg-white p-3 sm:grid-cols-2">
            <label className="text-sm font-medium text-secondary-700">{ui('Mode d’affichage', 'Display mode')}<select className="mt-1 h-10 w-full rounded-input border border-secondary-300 bg-surface px-3" value={editForm.document_header_image_settings.mode} onChange={(event) => updateEdit('document_header_image_settings', { ...editForm.document_header_image_settings, mode: event.target.value as 'fit' | 'full_width' })}><option value="fit">{ui('Ajuster sans étirer', 'Fit without stretching')}</option><option value="full_width">{ui('Remplir la largeur choisie', 'Fill selected width')}</option></select></label>
            <label className="text-sm font-medium text-secondary-700">{ui('Alignement', 'Alignment')}<select className="mt-1 h-10 w-full rounded-input border border-secondary-300 bg-surface px-3" value={editForm.document_header_image_settings.alignment} onChange={(event) => updateEdit('document_header_image_settings', { ...editForm.document_header_image_settings, alignment: event.target.value as 'left' | 'center' | 'right' })}><option value="left">{ui('Gauche', 'Left')}</option><option value="center">{ui('Centre', 'Centre')}</option><option value="right">{ui('Droite', 'Right')}</option></select></label>
            <label className="text-sm font-medium text-secondary-700">{ui('Largeur', 'Width')}: {editForm.document_header_image_settings.width}%<input type="range" min="30" max="100" value={editForm.document_header_image_settings.width} onChange={(event) => updateEdit('document_header_image_settings', { ...editForm.document_header_image_settings, width: Number(event.target.value) })} className="mt-2 w-full" /></label>
            <label className="text-sm font-medium text-secondary-700">{ui('Hauteur maximale', 'Maximum height')}: {editForm.document_header_image_settings.max_height}px<input type="range" min="40" max="200" value={editForm.document_header_image_settings.max_height} onChange={(event) => updateEdit('document_header_image_settings', { ...editForm.document_header_image_settings, max_height: Number(event.target.value) })} className="mt-2 w-full" disabled={editForm.document_header_image_settings.mode === 'full_width'} /></label>
          </div>
          <p className="mt-2 text-xs text-secondary-500">{ui('Enregistrez la configuration après avoir ajusté l’aperçu.', 'Save the school configuration after adjusting the preview.')}</p>
        </div>

        <div className="grid gap-4 md:grid-cols-2">
          <Input label={ui('Nom de l’établissement', 'School name')} required value={editForm.school_name} onChange={(e) => updateEdit('school_name', e.target.value)} />
          <Input label={ui('Code école', 'School code')} required value={editForm.school_code} onChange={(e) => updateEdit('school_code', e.target.value.toUpperCase().replace(/[^A-Z0-9-]/g, ''))} />
          <Input label={ui('Identifiant interne', 'Internal identifier')} required value={editForm.slug} onChange={(e) => updateEdit('slug', e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, ''))} />
          <Input label={ui('Devise / slogan', 'Motto')} value={editForm.slogan ?? ''} onChange={(e) => updateEdit('slogan', e.target.value)} />
          <Input type="email" label={ui('E-mail officiel', 'Official email')} value={editForm.email ?? ''} onChange={(e) => updateEdit('email', e.target.value)} />
          <Input label={ui('Téléphone', 'Phone')} value={editForm.phone ?? ''} onChange={(e) => updateEdit('phone', e.target.value)} />
          <Input label={ui('Ville', 'City')} value={editForm.city ?? ''} onChange={(e) => updateEdit('city', e.target.value)} />
          <Input label={ui('Pays', 'Country')} value={editForm.country ?? ''} onChange={(e) => updateEdit('country', e.target.value)} />
          <Input label={ui('Site web', 'Website')} value={editForm.website ?? ''} onChange={(e) => updateEdit('website', e.target.value)} placeholder="https://…" />
          <label className="text-sm font-medium text-secondary-700">{ui('Langue par défaut', 'Default language')}<select className="mt-1 h-10 w-full rounded-input border border-secondary-300 bg-surface px-3" value={editForm.default_locale} onChange={(e) => updateEdit('default_locale', e.target.value)}><option value="fr">Français</option><option value="en">English</option></select></label>
        </div>
        <div><label className="mb-1.5 block text-sm font-medium text-secondary-700">{ui('Adresse', 'Address')}</label><textarea rows={2} value={editForm.address ?? ''} onChange={(e) => updateEdit('address', e.target.value)} className="w-full rounded-input border border-secondary-300 bg-surface p-3 text-sm" /></div>

        <div className="rounded-card border border-secondary-200 p-4">
          <p className="mb-3 text-sm font-semibold text-secondary-700">{ui('Systèmes d’enseignement autorisés', 'Enabled education systems')}</p>
          <div className="flex flex-wrap gap-5">{[
            ['secondary_general', ui('Secondaire général', 'General secondary')],
            ['secondary_technical', ui('Secondaire technique', 'Technical secondary')],
          ].map(([value, label]) => <label key={value} className="flex items-center gap-2 text-sm"><input type="checkbox" checked={editForm.education_systems.includes(value)} onChange={(event) => { const systems = event.target.checked ? [...new Set([...editForm.education_systems, value])] : editForm.education_systems.filter((item) => item !== value); if (systems.length) updateEdit('education_systems', systems); }} />{label}</label>)}</div>
        </div>

        <div className="grid gap-4 md:grid-cols-2">
          <Input type="color" label={ui('Couleur principale des documents', 'Primary document color')} value={editForm.primary_color ?? '#1D4ED8'} onChange={(e) => updateEdit('primary_color', e.target.value)} />
          <Input type="color" label={ui('Couleur secondaire des documents', 'Secondary document color')} value={editForm.secondary_color ?? '#0F766E'} onChange={(e) => updateEdit('secondary_color', e.target.value)} />
          <Input label={ui('Nom du proviseur', 'Principal name')} value={editForm.principal_name ?? ''} onChange={(e) => updateEdit('principal_name', e.target.value)} />
          <Input label={ui('Titre du proviseur', 'Principal title')} value={editForm.principal_title ?? ''} onChange={(e) => updateEdit('principal_title', e.target.value)} />
        </div>
        <div><label className="mb-1.5 block text-sm font-medium text-secondary-700">{ui('En-tête texte de secours', 'Fallback text header')}</label><textarea rows={3} value={editForm.document_header ?? ''} onChange={(e) => updateEdit('document_header', e.target.value)} className="w-full rounded-input border border-secondary-300 bg-surface p-3 text-sm" /><p className="mt-1 text-xs text-secondary-500">{ui('Utilisé uniquement si aucune image d’en-tête n’est chargée.', 'Used only when no header image has been uploaded.')}</p></div>
        <div><label className="mb-1.5 block text-sm font-medium text-secondary-700">{ui('Pied de page officiel des documents', 'Official document footer')}</label><textarea rows={3} value={editForm.document_footer ?? ''} onChange={(e) => updateEdit('document_footer', e.target.value)} className="w-full rounded-input border border-secondary-300 bg-surface p-3 text-sm" /></div>
      </div>}
    </Modal>
    <Modal
      open={Boolean(credentialSchool)}
      onClose={() => { if (!savingCredentials) setCredentialSchool(null); }}
      title={ui('Identifiants du proviseur', 'Principal login credentials')}
      description={credentialSchool ? `${credentialSchool.school_name} · ${credentialSchool.school_code}` : undefined}
      size="md"
      footer={<><Button variant="ghost" onClick={() => setCredentialSchool(null)} disabled={savingCredentials}>{ui('Annuler', 'Cancel')}</Button><Button leftIcon={<KeyRound className="h-4 w-4" />} onClick={savePrincipalCredentials} isLoading={savingCredentials} disabled={loadingCredentials || !credentialForm.first_name || !credentialForm.last_name || !credentialForm.email}>{ui('Mettre à jour', 'Update credentials')}</Button></>}
    >
      {loadingCredentials ? <p className="py-8 text-center text-sm text-secondary-500">{ui('Chargement…', 'Loading…')}</p> : <div className="space-y-4">
        <div className="grid gap-4 sm:grid-cols-2">
          <Input label={ui('Prénom', 'First name')} required value={credentialForm.first_name} onChange={(e) => setCredentialForm((current) => ({ ...current, first_name: e.target.value }))} />
          <Input label={ui('Nom', 'Last name')} required value={credentialForm.last_name} onChange={(e) => setCredentialForm((current) => ({ ...current, last_name: e.target.value }))} />
        </div>
        <Input type="email" label={ui('E-mail de connexion et OTP', 'Login and OTP email')} required value={credentialForm.email} onChange={(e) => setCredentialForm((current) => ({ ...current, email: e.target.value }))} hint={ui('Le prochain code OTP sera envoyé à cette adresse.', 'The next OTP code will be sent to this address.')} />
        <Input type="password" label={ui('Nouveau mot de passe temporaire', 'New temporary password')} value={credentialForm.administrator_password ?? ''} onChange={(e) => setCredentialForm((current) => ({ ...current, administrator_password: e.target.value }))} hint={ui('Laissez vide pour conserver le mot de passe actuel.', 'Leave blank to keep the current password.')} />
        <Input type="password" label={ui('Confirmer le nouveau mot de passe', 'Confirm new password')} value={credentialForm.administrator_password_confirmation ?? ''} onChange={(e) => setCredentialForm((current) => ({ ...current, administrator_password_confirmation: e.target.value }))} />
        <p className="rounded-card border border-warning/40 bg-warning-light p-3 text-xs text-secondary-700">{ui('La modification révoque les sessions et les anciens codes OTP du proviseur.', 'Updating credentials revokes the principal’s existing sessions and previous OTP codes.')}</p>
      </div>}
    </Modal>
  </div>;
}
