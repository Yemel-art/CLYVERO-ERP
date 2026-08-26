/** Student cycles and the school's approved specialities. */
export type Cycle = 'secondary_general' | 'secondary_technical';

export interface CycleOption {
  value: Cycle;
  label_en: string;
  label_fr: string;
}

export const CYCLES: CycleOption[] = [
  { value: 'secondary_general', label_en: 'Secondary General', label_fr: 'Secondaire général' },
  { value: 'secondary_technical', label_en: 'Secondary Technical', label_fr: 'Secondaire Technique' },
];

export const CYCLE_LABELS: Record<Cycle, string> = {
  secondary_general: 'Secondary General',
  secondary_technical: 'Secondary Technical',
};

export function cycleRequiresSpeciality(cycle: Cycle | string): boolean {
  return cycle === 'secondary_technical';
}

export interface SpecialityOption {
  code: string;
  name: string;
  name_en: string;
  sector: 'tertiary' | 'industrial';
  cycle: 'first_cycle' | 'second_cycle' | 'both';
}

export const SPECIALITIES: Record<string, SpecialityOption> = {
  MA:   { code: 'MA',   name: 'Mécanique Automobile', name_en: 'Motor Mechanics', sector: 'industrial', cycle: 'both' },
  EE:   { code: 'EE',   name: 'Génie Électricité', name_en: 'Electrical Engineering', sector: 'industrial', cycle: 'both' },
  BT:   { code: 'BT',   name: 'Couture sur Mesure', name_en: 'Bespoke Tailoring', sector: 'industrial', cycle: 'both' },
  CARP: { code: 'CARP', name: 'Menuiserie', name_en: 'Carpentry', sector: 'industrial', cycle: 'both' },
  WELD: { code: 'WELD', name: 'Soudure', name_en: 'Welding', sector: 'industrial', cycle: 'both' },
  BC:   { code: 'BC',   name: 'Maçonnerie', name_en: 'Building and Construction', sector: 'industrial', cycle: 'both' },
  NUR:  { code: 'NUR',  name: 'Soins Infirmiers', name_en: 'Nursing', sector: 'tertiary', cycle: 'both' },
  ACC:  { code: 'ACC',  name: 'Comptabilité', name_en: 'Accounting', sector: 'tertiary', cycle: 'both' },
  ESF:  { code: 'ESF',  name: 'Économie Sociale et Familiale', name_en: 'Home Economics', sector: 'tertiary', cycle: 'both' },
};

export const SPECIALITY_OPTIONS = Object.values(SPECIALITIES);

export interface GeneralStreamOption {
  code: string;
  name: string;
  name_en: string;
  language: 'fr' | 'en';
}

export const GENERAL_STREAMS: Record<string, GeneralStreamOption> = {
  A:    { code: 'A', name: 'Série A — Littéraire', name_en: 'Arts and Literature', language: 'fr' },
  C:    { code: 'C', name: 'Série C — Mathématiques et Sciences Physiques', name_en: 'Mathematics and Physical Sciences', language: 'fr' },
  D:    { code: 'D', name: 'Série D — Sciences de la Vie et de la Terre', name_en: 'Life and Earth Sciences', language: 'fr' },
  TI:   { code: 'TI', name: 'Technologies de l’Information', name_en: 'Information Technology', language: 'fr' },
  ARTS: { code: 'ARTS', name: 'Arts et Lettres', name_en: 'Arts', language: 'en' },
  SCI:  { code: 'SCI', name: 'Sciences', name_en: 'Science', language: 'en' },
};

export const GENERAL_STREAM_OPTIONS = Object.values(GENERAL_STREAMS);
