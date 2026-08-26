'use client';

import { translate } from '@/i18n/translations';
import { useLanguageStore } from '@/store/language';

export function useTranslation() {
  const language = useLanguageStore((state) => state.language);

  return {
    language,
    t: (key: string) => translate(language, key),
  };
}
