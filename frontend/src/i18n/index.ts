import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import LanguageDetector from 'i18next-browser-languagedetector';
import it from './locales/it.json';
import en from './locales/en.json';

/** Tiene `<html lang>` allineato alla lingua dell'interfaccia (screen reader, sillabazione, correzione). */
function syncHtmlLang(lng: string | undefined) {
  if (lng) document.documentElement.lang = lng.split('-')[0] ?? lng;
}

// Registrato prima di init: il detector fissa la lingua durante init e l'evento parte già lì.
i18n.on('languageChanged', syncHtmlLang);

void i18n
  .use(LanguageDetector)
  .use(initReactI18next)
  .init({
    resources: {
      it: { translation: it },
      en: { translation: en },
    },
    fallbackLng: 'it',
    interpolation: { escapeValue: false },
    detection: {
      order: ['localStorage', 'navigator'],
      caches: ['localStorage'],
    },
  })
  .then(() => syncHtmlLang(i18n.language));

export default i18n;
