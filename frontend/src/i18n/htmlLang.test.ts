import { describe, expect, it } from 'vitest';
import i18n from '@/i18n';

describe('<html lang>', () => {
  it("segue la lingua dell'interfaccia quando viene cambiata", async () => {
    await i18n.changeLanguage('en');
    expect(document.documentElement.lang).toBe('en');

    await i18n.changeLanguage('it');
    expect(document.documentElement.lang).toBe('it');
  });
});
