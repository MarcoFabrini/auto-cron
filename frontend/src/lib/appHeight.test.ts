import { describe, expect, it } from 'vitest';
import { appHeightFor } from './appHeight';

const iphone = { standalone: true, innerWidth: 402, innerHeight: 812, screenWidth: 402, screenHeight: 874 };

describe('appHeightFor', () => {
  it('iPhone standalone con viewport corto: usa l\'altezza dello schermo', () => {
    expect(appHeightFor(iphone)).toBe(874);
  });

  it('viewport già pieno: niente da forzare', () => {
    expect(appHeightFor({ ...iphone, innerHeight: 874 })).toBeNull();
  });

  it('fuori da standalone (Safari, desktop): niente', () => {
    expect(appHeightFor({ ...iphone, standalone: false })).toBeNull();
  });

  it('in orizzontale non si fida di screen.*', () => {
    expect(appHeightFor({ ...iphone, innerWidth: 874, innerHeight: 402 })).toBeNull();
  });

  it('scarto troppo grande (non è un inset): niente', () => {
    expect(appHeightFor({ ...iphone, innerHeight: 600 })).toBeNull();
  });
});
