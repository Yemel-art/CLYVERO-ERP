'use client';

import { useState, useEffect } from 'react';
import Link from 'next/link';
import { toast } from 'sonner';
import { Save, Building2, Info, Upload, WalletCards } from 'lucide-react';
import { PageHeader } from '@/components/ui/PageHeader';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/Card';
import { Skeleton } from '@/components/ui/Skeleton';
import { useSettings, useUpdateSchool, useUploadSchoolLogo } from '@/hooks/admin';
import type { DocumentHeaderImageSettings, HonorRollRule, PerformanceRemark } from '@/lib/api/admin';

const defaultRemarks: PerformanceRemark[] = [
  { minimum: 16, fr: 'Excellent', en: 'Excellent' },
  { minimum: 14, fr: 'Très bien', en: 'Very Good' },
  { minimum: 12, fr: 'Bien', en: 'Good' },
  { minimum: 10, fr: 'Assez bien', en: 'Fair' },
  { minimum: 8, fr: 'Moyen', en: 'Average' },
  { minimum: 6, fr: 'Doit s’améliorer', en: 'Needs Improvement' },
  { minimum: 0, fr: 'Insuffisant', en: 'Poor' },
];

const defaultHonorRules: HonorRollRule[] = [
  { minimum: 16, max_rank: null, fr: 'Excellent', en: 'Excellent' },
  { minimum: 14, max_rank: null, fr: 'Tableau d’honneur', en: 'Honour Roll' },
  { minimum: 12, max_rank: null, fr: 'Très bien', en: 'Very Good' },
  { minimum: 10, max_rank: null, fr: 'Encouragement', en: 'Encouragement' },
];

const defaultHeaderImageSettings: DocumentHeaderImageSettings = {
  mode: 'fit', width: 100, max_height: 105, alignment: 'center',
};

export default function SettingsPage() {
  const { data, isLoading } = useSettings();
  const update = useUpdateSchool();
  const uploadLogo = useUploadSchoolLogo();

  const [name, setName] = useState('');
  const [slug, setSlug] = useState('');
  const [schoolCode, setSchoolCode] = useState('');
  const [address, setAddress] = useState('');
  const [phone, setPhone] = useState('');
  const [email, setEmail] = useState('');
  const [motto, setMotto] = useState('');
  const [defaultLocale, setDefaultLocale] = useState<'fr' | 'en'>('fr');
  const [remarks, setRemarks] = useState<PerformanceRemark[]>(defaultRemarks);
  const [honorRules, setHonorRules] = useState<HonorRollRule[]>(defaultHonorRules);
  const [primaryColor, setPrimaryColor] = useState('#1D4ED8');
  const [secondaryColor, setSecondaryColor] = useState('#0F766E');
  const [documentHeader, setDocumentHeader] = useState('');
  const [headerImageSettings, setHeaderImageSettings] = useState<DocumentHeaderImageSettings>(defaultHeaderImageSettings);
  const [documentFooter, setDocumentFooter] = useState('');
  const [principalName, setPrincipalName] = useState('');
  const [principalTitle, setPrincipalTitle] = useState('');

  useEffect(() => {
    if (!data?.school) return;
    setName(data.school.name ?? '');
    setSlug(data.school.slug ?? '');
    setSchoolCode(data.school.school_code ?? '');
    setAddress(data.school.address ?? '');
    setPhone(data.school.phone ?? '');
    setEmail(data.school.email ?? '');
    setMotto(data.school.motto ?? '');
    setDefaultLocale(data.school.default_locale ?? 'fr');
    setRemarks(data.school.report_card_remarks ?? defaultRemarks);
    setHonorRules(data.school.honor_roll_rules ?? defaultHonorRules);
    setPrimaryColor(data.school.primary_color ?? '#1D4ED8');
    setSecondaryColor(data.school.secondary_color ?? '#0F766E');
    setDocumentHeader(data.school.document_header ?? '');
    setHeaderImageSettings(data.school.document_header_image_settings ?? defaultHeaderImageSettings);
    setDocumentFooter(data.school.document_footer ?? '');
    setPrincipalName(data.school.principal_name ?? '');
    setPrincipalTitle(data.school.principal_title ?? '');
  }, [data?.school]);

  const submit = async () => {
    try {
      await update.mutateAsync({
        name,
        slug,
        school_code: schoolCode,
        address,
        phone,
        email,
        motto,
        default_locale: defaultLocale,
        report_card_remarks: remarks,
        honor_roll_rules: honorRules,
        primary_color: primaryColor,
        secondary_color: secondaryColor,
        document_header: documentHeader,
        document_header_image_settings: headerImageSettings,
        document_footer: documentFooter,
        principal_name: principalName,
        principal_title: principalTitle,
      });
      toast.success('School settings saved.');
    } catch {
      toast.error('Could not save settings.');
    }
  };

  const onLogoSelected = async (kind: 'primary' | 'secondary' | 'document_header', file?: File) => {
    if (!file) return;
    try {
      await uploadLogo.mutateAsync({ file, kind });
      toast.success(kind === 'document_header'
        ? 'Official document header image updated.'
        : kind === 'primary' ? 'Primary school logo updated.' : 'Secondary school logo updated.');
    } catch {
      toast.error('Could not upload the school logo. Use a JPEG, PNG, or WebP file smaller than 5 MB.');
    }
  };

  return (
    <div>
      <PageHeader
        breadcrumb={[{ label: 'Home', href: '/admin/dashboard' }, { label: 'Settings' }]}
        title="Settings"
        description="School identity, contact details, and system info."
      />

      {isLoading ? <Skeleton className="h-96" /> : (
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
          <div className="lg:col-span-2">
            <Card>
              <CardHeader>
                <div className="flex items-center gap-2"><Building2 className="h-5 w-5 text-primary-600" /><CardTitle>School identity</CardTitle></div>
                <CardDescription>This appears on report cards and invoices.</CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="grid gap-4 sm:grid-cols-2">
                  {(['primary', 'secondary'] as const).map((kind) => {
                    const url = kind === 'primary' ? data?.school?.logo_url : data?.school?.secondary_logo_url;
                    return <div key={kind} className="rounded-card border border-secondary-200 p-4">
                      <p className="mb-3 text-sm font-medium text-secondary-700">{kind === 'primary' ? 'Primary school logo' : 'Secondary school logo'}</p>
                      <div className="mb-3 flex h-24 items-center justify-center rounded bg-secondary-50">
                        {url ? <img src={url} alt={kind === 'primary' ? 'Primary school logo' : 'Secondary school logo'} className="max-h-20 max-w-full object-contain" /> : <span className="text-xs text-secondary-500">No logo uploaded</span>}
                      </div>
                      <label className="inline-flex cursor-pointer items-center gap-2 rounded-button border border-secondary-300 px-3 py-2 text-sm font-medium hover:bg-secondary-50">
                        <Upload className="h-4 w-4" /> {uploadLogo.isPending ? 'Uploading…' : 'Choose logo'}
                        <input type="file" className="sr-only" accept="image/jpeg,image/png,image/webp" disabled={uploadLogo.isPending}
                          onChange={(event) => { void onLogoSelected(kind, event.target.files?.[0]); event.currentTarget.value = ''; }} />
                      </label>
                    </div>;
                  })}
                </div>
                <div className="rounded-card border-2 border-primary-200 bg-primary-50/40 p-4">
                  <p className="text-sm font-semibold text-secondary-800">Official document header image</p>
                  <p className="mt-1 text-xs text-secondary-600">Upload the complete school letterhead as one image. It will replace the text header and separate logos on report cards, receipts, certificates, and honour-roll documents.</p>
                  <div className="my-3 flex min-h-32 items-center overflow-hidden rounded border border-secondary-200 bg-white p-3" style={{ justifyContent: headerImageSettings.alignment === 'left' ? 'flex-start' : headerImageSettings.alignment === 'right' ? 'flex-end' : 'center' }}>
                    {data?.school?.document_header_image_url
                      ? <img
                          src={data.school.document_header_image_url}
                          alt="Official document header preview"
                          style={headerImageSettings.mode === 'full_width'
                            ? { width: `${headerImageSettings.width}%`, height: 'auto' }
                            : { maxWidth: `${headerImageSettings.width}%`, maxHeight: `${headerImageSettings.max_height}px`, width: 'auto', height: 'auto' }}
                        />
                      : <div className="text-center text-sm text-secondary-400"><Upload className="mx-auto mb-2 h-8 w-8" />No header image uploaded</div>}
                  </div>
                  <label className="inline-flex cursor-pointer items-center gap-2 rounded-button bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-700">
                    <Upload className="h-4 w-4" /> {uploadLogo.isPending ? 'Uploading…' : 'Upload header image'}
                    <input type="file" className="sr-only" accept="image/jpeg,image/png,image/webp" disabled={uploadLogo.isPending}
                      onChange={(event) => { void onLogoSelected('document_header', event.target.files?.[0]); event.currentTarget.value = ''; }} />
                  </label>
                  <p className="mt-2 text-xs text-secondary-500">Transparent PNG recommended · JPEG/WebP accepted · maximum 5 MB.</p>
                  <div className="mt-4 grid gap-4 rounded border border-secondary-200 bg-white p-3 sm:grid-cols-2">
                    <label className="text-sm font-medium text-secondary-700">Display mode
                      <select className="mt-1 h-10 w-full rounded-input border border-secondary-300 bg-surface px-3" value={headerImageSettings.mode} onChange={(event) => setHeaderImageSettings((current) => ({ ...current, mode: event.target.value as DocumentHeaderImageSettings['mode'] }))}>
                        <option value="fit">Fit without stretching</option><option value="full_width">Fill selected width</option>
                      </select>
                    </label>
                    <label className="text-sm font-medium text-secondary-700">Alignment
                      <select className="mt-1 h-10 w-full rounded-input border border-secondary-300 bg-surface px-3" value={headerImageSettings.alignment} onChange={(event) => setHeaderImageSettings((current) => ({ ...current, alignment: event.target.value as DocumentHeaderImageSettings['alignment'] }))}>
                        <option value="left">Left</option><option value="center">Centre</option><option value="right">Right</option>
                      </select>
                    </label>
                    <label className="text-sm font-medium text-secondary-700">Width: {headerImageSettings.width}%
                      <input type="range" min="30" max="100" value={headerImageSettings.width} onChange={(event) => setHeaderImageSettings((current) => ({ ...current, width: Number(event.target.value) }))} className="mt-2 w-full" />
                    </label>
                    <label className="text-sm font-medium text-secondary-700">Maximum height: {headerImageSettings.max_height}px
                      <input type="range" min="40" max="200" value={headerImageSettings.max_height} onChange={(event) => setHeaderImageSettings((current) => ({ ...current, max_height: Number(event.target.value) }))} className="mt-2 w-full" disabled={headerImageSettings.mode === 'full_width'} />
                    </label>
                  </div>
                  <p className="mt-2 text-xs text-secondary-500">Click “Save changes” after adjusting the preview controls.</p>
                </div>
                <Input label="School name" value={name} onChange={(e) => setName(e.target.value)} />
                <Input
                  label="School code"
                  value={schoolCode}
                  onChange={(event) => setSchoolCode(event.target.value.toUpperCase().replace(/[^A-Z0-9-]/g, '').replace(/-{2,}/g, '-'))}
                  placeholder="e.g. CLY-000001"
                  hint="Used on the login page. Share this code with staff and parents after changing it."
                  minLength={3}
                  maxLength={32}
                  required
                />
                <Input label="Internal URL identifier" value={slug} onChange={(event) => setSlug(event.target.value.toLowerCase().replace(/[^a-z0-9-]/g, '').replace(/-{2,}/g, '-'))} hint="Technical identifier; changing it is normally unnecessary." />
                <Input label="Motto" value={motto} onChange={(e) => setMotto(e.target.value)} placeholder="Excellence · Integrity · Service" />
                <div>
                  <label htmlFor="default-locale" className="mb-1.5 block text-sm font-medium text-secondary-700">Default language</label>
                  <select
                    id="default-locale"
                    value={defaultLocale}
                    onChange={(event) => setDefaultLocale(event.target.value as 'fr' | 'en')}
                    className="h-10 w-full rounded-input border border-secondary-300 bg-surface px-3 text-sm"
                  >
                    <option value="fr">Français</option>
                    <option value="en">English</option>
                  </select>
                  <p className="mt-1 text-xs text-secondary-500">Used for emails and documents when a user has not chosen a language.</p>
                </div>
                <div>
                  <label className="mb-1.5 block text-sm font-medium text-secondary-700">Address</label>
                  <textarea rows={2} value={address} onChange={(e) => setAddress(e.target.value)}
                    className="w-full rounded-input border border-secondary-300 bg-surface p-3 text-sm" />
                </div>
                <div className="grid grid-cols-2 gap-4">
                  <Input label="Phone" value={phone} onChange={(e) => setPhone(e.target.value)} placeholder="+237 …" />
                  <Input type="email" label="Email" value={email} onChange={(e) => setEmail(e.target.value)} />
                </div>
                <div className="grid grid-cols-2 gap-4">
                  <Input type="color" label="Primary document color" value={primaryColor} onChange={(e) => setPrimaryColor(e.target.value)} />
                  <Input type="color" label="Secondary document color" value={secondaryColor} onChange={(e) => setSecondaryColor(e.target.value)} />
                </div>
                <Input label="Principal name" value={principalName} onChange={(e) => setPrincipalName(e.target.value)} />
                <Input label="Principal title" value={principalTitle} onChange={(e) => setPrincipalTitle(e.target.value)} placeholder="Principal / Head of school" />
                <div><label className="mb-1.5 block text-sm font-medium text-secondary-700">Fallback text header</label><textarea rows={3} value={documentHeader} onChange={(e) => setDocumentHeader(e.target.value)} className="w-full rounded-input border border-secondary-300 bg-surface p-3 text-sm" /><p className="mt-1 text-xs text-secondary-500">Used only when no official header image has been uploaded.</p></div>
                <div><label className="mb-1.5 block text-sm font-medium text-secondary-700">Official document footer</label><textarea rows={3} value={documentFooter} onChange={(e) => setDocumentFooter(e.target.value)} className="w-full rounded-input border border-secondary-300 bg-surface p-3 text-sm" /></div>
                <div>
                  <Button leftIcon={<Save className="h-4 w-4" />} onClick={submit} isLoading={update.isPending}>Save changes</Button>
                </div>
              </CardContent>
            </Card>

            <Card className="mt-6 border-primary-200 bg-primary-50/40">
              <CardHeader>
                <div className="flex items-center gap-2"><WalletCards className="h-5 w-5 text-primary-600" /><CardTitle>School fees</CardTitle></div>
                <CardDescription>Only the principal/administrator defines required fees. They can apply to every class or to one class for the active academic year.</CardDescription>
              </CardHeader>
              <CardContent>
                <Link href="/admin/finance/fees" className="inline-flex h-10 items-center rounded-button bg-primary-600 px-4 text-sm font-semibold text-white hover:bg-primary-700">
                  Configure school fees
                </Link>
              </CardContent>
            </Card>

            <Card className="mt-6">
              <CardHeader>
                <CardTitle>Règles du tableau d’honneur</CardTitle>
                <CardDescription>Configurez la moyenne minimale, le rang maximal facultatif et les catégories bilingues.</CardDescription>
              </CardHeader>
              <CardContent className="space-y-3">
                {honorRules.map((rule, index) => (
                  <div key={index} className="grid grid-cols-[100px_100px_1fr_1fr] gap-3">
                    <Input type="number" label="Moy. min." min={0} max={20} step={0.5} value={rule.minimum}
                      onChange={(event) => setHonorRules((rows) => rows.map((row, rowIndex) => rowIndex === index ? { ...row, minimum: Number(event.target.value) } : row))} />
                    <Input type="number" label="Rang max." min={1} value={rule.max_rank ?? ''}
                      onChange={(event) => setHonorRules((rows) => rows.map((row, rowIndex) => rowIndex === index ? { ...row, max_rank: event.target.value ? Number(event.target.value) : null } : row))} />
                    <Input label="Français" value={rule.fr}
                      onChange={(event) => setHonorRules((rows) => rows.map((row, rowIndex) => rowIndex === index ? { ...row, fr: event.target.value } : row))} />
                    <Input label="English" value={rule.en}
                      onChange={(event) => setHonorRules((rows) => rows.map((row, rowIndex) => rowIndex === index ? { ...row, en: event.target.value } : row))} />
                  </div>
                ))}
                <Button leftIcon={<Save className="h-4 w-4" />} onClick={submit} isLoading={update.isPending}>
                  Enregistrer les règles
                </Button>
              </CardContent>
            </Card>

            <Card className="mt-6">
              <CardHeader>
                <CardTitle>Appréciations des bulletins</CardTitle>
                <CardDescription>
                  Configurez la moyenne minimale et le texte automatiquement affiché en français et en anglais.
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-3">
                <div className="grid grid-cols-[110px_1fr_1fr] gap-3 text-xs font-semibold uppercase text-secondary-500">
                  <span>Moyenne min.</span><span>Français</span><span>English</span>
                </div>
                {remarks.map((remark, index) => (
                  <div key={index} className="grid grid-cols-[110px_1fr_1fr] gap-3">
                    <Input
                      type="number"
                      min={0}
                      max={20}
                      step={0.5}
                      aria-label={`Minimum ${index + 1}`}
                      value={remark.minimum}
                      onChange={(event) => setRemarks((current) => current.map((row, rowIndex) => (
                        rowIndex === index ? { ...row, minimum: Number(event.target.value) } : row
                      )))}
                    />
                    <Input
                      aria-label={`Appréciation française ${index + 1}`}
                      value={remark.fr}
                      onChange={(event) => setRemarks((current) => current.map((row, rowIndex) => (
                        rowIndex === index ? { ...row, fr: event.target.value } : row
                      )))}
                    />
                    <Input
                      aria-label={`English remark ${index + 1}`}
                      value={remark.en}
                      onChange={(event) => setRemarks((current) => current.map((row, rowIndex) => (
                        rowIndex === index ? { ...row, en: event.target.value } : row
                      )))}
                    />
                  </div>
                ))}
                <Button leftIcon={<Save className="h-4 w-4" />} onClick={submit} isLoading={update.isPending}>
                  Enregistrer les appréciations
                </Button>
              </CardContent>
            </Card>
          </div>

          <Card>
            <CardHeader>
              <div className="flex items-center gap-2"><Info className="h-5 w-5 text-secondary-500" /><CardTitle className="text-base">System info</CardTitle></div>
            </CardHeader>
            <CardContent className="space-y-2 text-sm">
              <p><span className="text-secondary-500">App:</span> <span className="font-medium">{data?.app.name}</span></p>
              <p><span className="text-secondary-500">Version:</span> <span className="font-medium">{data?.app.version}</span></p>
              <p><span className="text-secondary-500">Environment:</span> <span className="font-medium">{data?.app.env}</span></p>
              <p><span className="text-secondary-500">School ID:</span> <span className="font-mono text-xs">{data?.school?.id}</span></p>
              <p><span className="text-secondary-500">School code:</span> <span className="rounded bg-primary-50 px-2 py-1 font-mono text-xs font-semibold text-primary-700">{data?.school?.school_code}</span></p>
              <p><span className="text-secondary-500">Currency:</span> <span className="font-medium">{data?.school?.currency}</span></p>
            </CardContent>
          </Card>
        </div>
      )}
    </div>
  );
}
