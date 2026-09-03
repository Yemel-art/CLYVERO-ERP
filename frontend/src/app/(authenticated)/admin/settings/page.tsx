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
import type { DocumentHeaderImageSettings, HonorRollRule, PerformanceRemark, StudentIdCardSettings } from '@/lib/api/admin';

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

const defaultStudentIdCardSettings: StudentIdCardSettings = {
  background_color: '#FFFFFF', border_color: '#1D4ED8', accent_color: '#1D4ED8',
  text_color: '#111827', border_width: 3, corner_style: 'soft', spacing: 'standard',
  header_height: 16, header_image_width: 100, year_gap: 1, font_scale: 100, photo_size: 'standard',
  show_title: true, title_fr: 'Carte d’identité scolaire', title_en: 'Student Identity Card',
  show_motto: true, show_cameroon_flag: true, flag_size: 10,
  show_stamp: true, stamp_label: 'School stamp', show_signature: true, signature_label: 'Authorized signature',
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
  const [studentIdCardSettings, setStudentIdCardSettings] = useState<StudentIdCardSettings>(defaultStudentIdCardSettings);
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
    setStudentIdCardSettings(data.school.student_id_card_settings ?? defaultStudentIdCardSettings);
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
        student_id_card_settings: studentIdCardSettings,
        document_footer: documentFooter,
        principal_name: principalName,
        principal_title: principalTitle,
      });
      toast.success('School settings saved.');
    } catch {
      toast.error('Could not save settings.');
    }
  };

  const onLogoSelected = async (kind: 'primary' | 'secondary' | 'document_header' | 'student_id_stamp', file?: File) => {
    if (!file) return;
    try {
      await uploadLogo.mutateAsync({ file, kind });
      toast.success(kind === 'student_id_stamp' ? 'Student ID stamp image updated.' : kind === 'document_header'
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
                <div className="rounded-card border-2 border-secondary-200 bg-secondary-50/50 p-4">
                  <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <div>
                      <p className="font-semibold text-secondary-900">Student ID card design</p>
                      <p className="mt-1 text-xs text-secondary-600">Customize every student card generated for this school. The fixed information grid remains protected so cards always print correctly.</p>
                    </div>
                    <Button type="button" variant="outline" onClick={() => setStudentIdCardSettings({ ...defaultStudentIdCardSettings, border_color: primaryColor, accent_color: primaryColor })}>Restore professional default</Button>
                  </div>

                  <div
                    className="relative mx-auto mb-5 aspect-[1.586/1] w-full max-w-md overflow-hidden border bg-white p-3 shadow-sm"
                    style={{
                      backgroundColor: studentIdCardSettings.background_color,
                      borderColor: studentIdCardSettings.border_color,
                      color: studentIdCardSettings.text_color,
                      borderWidth: `${studentIdCardSettings.border_width + 1}px`,
                      borderRadius: studentIdCardSettings.corner_style === 'square' ? 0 : studentIdCardSettings.corner_style === 'rounded' ? 18 : 7,
                      fontSize: `${studentIdCardSettings.font_scale}%`,
                    }}
                  >
                    {studentIdCardSettings.show_cameroon_flag && <div className="absolute -left-6 top-2 flex h-3 w-20 -rotate-45 overflow-hidden" style={{ transform: `rotate(-45deg) scale(${studentIdCardSettings.flag_size / 10})` }}><span className="flex-1 bg-green-600" /><span className="flex-1 bg-red-600" /><span className="flex-1 bg-yellow-400" /></div>}
                    <div className="border-b pb-2 text-center" style={{ borderColor: studentIdCardSettings.border_color, minHeight: `${studentIdCardSettings.header_height * 2}px` }}>
                      <p className="text-[9px] font-semibold uppercase">{name || 'School name'}</p>
                      {studentIdCardSettings.show_motto && motto && <p className="text-[8px]">{motto}</p>}
                      {studentIdCardSettings.show_title && <p className="text-xs font-black uppercase" style={{ color: studentIdCardSettings.accent_color }}>{defaultLocale === 'fr' ? studentIdCardSettings.title_fr : studentIdCardSettings.title_en}</p>}
                    </div>
                    <div className={`grid grid-cols-[78px_1fr] gap-3 ${studentIdCardSettings.spacing === 'compact' ? 'py-2' : 'py-3'}`}>
                      <div>
                        <div className="flex h-20 items-center justify-center border bg-secondary-100 text-xs" style={{ borderColor: studentIdCardSettings.border_color }}>PHOTO</div>
                        <div className="mt-1 truncate px-1 py-0.5 text-center text-[8px] font-bold text-white" style={{ backgroundColor: studentIdCardSettings.accent_color }}>MAT. XXXXXXXX</div>
                      </div>
                      <div className="grid grid-cols-[72px_1fr] content-start gap-x-2 gap-y-0.5 text-[9px]">
                        <span>Name:</span><strong>STUDENT NAME</strong>
                        <span>Born on:</span><strong>01-01-2010</strong>
                        <span>Gender / Age:</span><strong>M / 16</strong>
                        <span className="border-l-2 bg-secondary-100 px-1" style={{ borderColor: studentIdCardSettings.accent_color }}>Class:</span><strong className="bg-secondary-100 px-1" style={{ color: studentIdCardSettings.accent_color }}>Form 2 Electrical Engineering</strong>
                      </div>
                    </div>
                    <div className="absolute bottom-2 right-3 flex items-end gap-3 text-[7px]">
                      {studentIdCardSettings.show_signature && <span className="border-t border-current px-2 pt-0.5">{studentIdCardSettings.signature_label}</span>}
                      {studentIdCardSettings.show_stamp && <span className="flex h-9 w-9 items-center justify-center rounded-full border text-center" style={{ borderColor: studentIdCardSettings.accent_color, color: studentIdCardSettings.accent_color }}>{data?.school?.student_id_card_stamp_url ? 'STAMP' : studentIdCardSettings.stamp_label}</span>}
                    </div>
                  </div>

                  <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Input type="color" label="Card background" value={studentIdCardSettings.background_color} onChange={(event) => setStudentIdCardSettings((current) => ({ ...current, background_color: event.target.value }))} />
                    <Input type="color" label="Border color" value={studentIdCardSettings.border_color} onChange={(event) => setStudentIdCardSettings((current) => ({ ...current, border_color: event.target.value }))} />
                    <Input type="color" label="Accent color" value={studentIdCardSettings.accent_color} onChange={(event) => setStudentIdCardSettings((current) => ({ ...current, accent_color: event.target.value }))} />
                    <Input type="color" label="Text color" value={studentIdCardSettings.text_color} onChange={(event) => setStudentIdCardSettings((current) => ({ ...current, text_color: event.target.value }))} />
                  </div>
                  <div className="mt-4 grid gap-4 sm:grid-cols-3">
                    <label className="text-sm font-medium text-secondary-700">Border thickness
                      <select className="mt-1 h-10 w-full rounded-input border border-secondary-300 bg-surface px-3" value={studentIdCardSettings.border_width} onChange={(event) => setStudentIdCardSettings((current) => ({ ...current, border_width: Number(event.target.value) }))}>
                        <option value={1}>Thin</option><option value={2}>Medium</option><option value={3}>Thick</option><option value={4}>Extra thick</option>
                      </select>
                    </label>
                    <label className="text-sm font-medium text-secondary-700">Corner style
                      <select className="mt-1 h-10 w-full rounded-input border border-secondary-300 bg-surface px-3" value={studentIdCardSettings.corner_style} onChange={(event) => setStudentIdCardSettings((current) => ({ ...current, corner_style: event.target.value as StudentIdCardSettings['corner_style'] }))}>
                        <option value="square">Square</option><option value="soft">Soft corners</option><option value="rounded">Rounded</option>
                      </select>
                    </label>
                    <label className="text-sm font-medium text-secondary-700">Information spacing
                      <select className="mt-1 h-10 w-full rounded-input border border-secondary-300 bg-surface px-3" value={studentIdCardSettings.spacing} onChange={(event) => setStudentIdCardSettings((current) => ({ ...current, spacing: event.target.value as StudentIdCardSettings['spacing'] }))}>
                        <option value="standard">Standard</option><option value="compact">Compact</option>
                      </select>
                    </label>
                  </div>
                  <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <label className="text-sm font-medium text-secondary-700">Header height: {studentIdCardSettings.header_height} mm<input type="range" min="13" max="20" value={studentIdCardSettings.header_height} onChange={(event) => setStudentIdCardSettings((current) => ({ ...current, header_height: Number(event.target.value) }))} className="mt-2 w-full" /></label>
                    <label className="text-sm font-medium text-secondary-700">Header image width: {studentIdCardSettings.header_image_width}%<input type="range" min="50" max="100" value={studentIdCardSettings.header_image_width} onChange={(event) => setStudentIdCardSettings((current) => ({ ...current, header_image_width: Number(event.target.value) }))} className="mt-2 w-full" /></label>
                    <label className="text-sm font-medium text-secondary-700">Space below header: {studentIdCardSettings.year_gap} mm<input type="range" min="0" max="4" value={studentIdCardSettings.year_gap} onChange={(event) => setStudentIdCardSettings((current) => ({ ...current, year_gap: Number(event.target.value) }))} className="mt-2 w-full" /></label>
                    <label className="text-sm font-medium text-secondary-700">Text size: {studentIdCardSettings.font_scale}%<input type="range" min="85" max="115" value={studentIdCardSettings.font_scale} onChange={(event) => setStudentIdCardSettings((current) => ({ ...current, font_scale: Number(event.target.value) }))} className="mt-2 w-full" /></label>
                    <label className="text-sm font-medium text-secondary-700">Photo size<select className="mt-1 h-10 w-full rounded-input border border-secondary-300 bg-surface px-3" value={studentIdCardSettings.photo_size} onChange={(event) => setStudentIdCardSettings((current) => ({ ...current, photo_size: event.target.value as StudentIdCardSettings['photo_size'] }))}><option value="small">Small</option><option value="standard">Standard</option><option value="large">Large</option></select></label>
                  </div>
                  <div className="mt-4 grid gap-3 rounded border border-secondary-200 bg-white p-3 sm:grid-cols-2">
                    {([
                      ['show_title', 'Show card title'], ['show_motto', 'Show school motto'],
                      ['show_cameroon_flag', 'Show Cameroon corner flag'], ['show_stamp', 'Show school stamp area'],
                      ['show_signature', 'Show authorized signature area'],
                    ] as const).map(([key, label]) => <label key={key} className="flex items-center gap-2 text-sm text-secondary-700"><input type="checkbox" checked={studentIdCardSettings[key]} onChange={(event) => setStudentIdCardSettings((current) => ({ ...current, [key]: event.target.checked }))} />{label}</label>)}
                  </div>
                  <div className="mt-4 grid gap-4 sm:grid-cols-2">
                    <Input label="French card title" value={studentIdCardSettings.title_fr} disabled={!studentIdCardSettings.show_title} onChange={(event) => setStudentIdCardSettings((current) => ({ ...current, title_fr: event.target.value }))} />
                    <Input label="English card title" value={studentIdCardSettings.title_en} disabled={!studentIdCardSettings.show_title} onChange={(event) => setStudentIdCardSettings((current) => ({ ...current, title_en: event.target.value }))} />
                    <Input label="Stamp label" value={studentIdCardSettings.stamp_label} disabled={!studentIdCardSettings.show_stamp} onChange={(event) => setStudentIdCardSettings((current) => ({ ...current, stamp_label: event.target.value }))} />
                    <Input label="Signature label" value={studentIdCardSettings.signature_label} disabled={!studentIdCardSettings.show_signature} onChange={(event) => setStudentIdCardSettings((current) => ({ ...current, signature_label: event.target.value }))} />
                  </div>
                  <div className="mt-4 rounded border border-secondary-200 bg-white p-3">
                    <div className="flex flex-wrap items-center gap-3">
                      {data?.school?.student_id_card_stamp_url && <img src={data.school.student_id_card_stamp_url} alt="School stamp" className="h-16 w-16 object-contain" />}
                      <label className="inline-flex cursor-pointer items-center gap-2 rounded-button border border-secondary-300 px-3 py-2 text-sm font-medium hover:bg-secondary-50"><Upload className="h-4 w-4" />{uploadLogo.isPending ? 'Uploading…' : 'Upload school stamp'}<input type="file" className="sr-only" accept="image/jpeg,image/png,image/webp" disabled={uploadLogo.isPending} onChange={(event) => { void onLogoSelected('student_id_stamp', event.target.files?.[0]); event.currentTarget.value = ''; }} /></label>
                    </div>
                    <p className="mt-2 text-xs text-secondary-500">A transparent PNG of the official stamp gives the best result.</p>
                  </div>
                  {studentIdCardSettings.show_cameroon_flag && <label className="mt-4 block text-sm font-medium text-secondary-700">Cameroon flag size: {studentIdCardSettings.flag_size} mm<input type="range" min="6" max="16" value={studentIdCardSettings.flag_size} onChange={(event) => setStudentIdCardSettings((current) => ({ ...current, flag_size: Number(event.target.value) }))} className="mt-2 w-full" /></label>}
                  <p className="mt-3 text-xs text-secondary-500">Use “Save changes” below to apply this design to all future student ID downloads for this school.</p>
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
