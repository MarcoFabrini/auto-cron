import { describe, expect, it } from 'vitest';
import { createCornerTapCounter, readDebugFlag, writeDebugFlag } from './viewportDebug';

function memoryStorage() {
  const data = new Map<string, string>();
  return {
    getItem: (k: string) => data.get(k) ?? null,
    setItem: (k: string, v: string) => void data.set(k, v),
    removeItem: (k: string) => void data.delete(k),
  };
}

describe('readDebugFlag', () => {
  it('è spento di default', () => {
    expect(readDebugFlag('', memoryStorage())).toBe(false);
  });

  it('?debug=1 lo accende e lo ricorda, ?debug=0 lo spegne', () => {
    const storage = memoryStorage();
    expect(readDebugFlag('?debug=1', storage)).toBe(true);
    expect(readDebugFlag('', storage)).toBe(true);
    expect(readDebugFlag('?debug=0', storage)).toBe(false);
    expect(readDebugFlag('', storage)).toBe(false);
  });

  it('writeDebugFlag scrive e toglie il flag', () => {
    const storage = memoryStorage();
    writeDebugFlag(true, storage);
    expect(readDebugFlag('', storage)).toBe(true);
    writeDebugFlag(false, storage);
    expect(readDebugFlag('', storage)).toBe(false);
  });

  it('senza storage utilizzabile segue solo il parametro', () => {
    const broken = {
      getItem: () => {
        throw new Error('blocked');
      },
      setItem: () => {
        throw new Error('blocked');
      },
      removeItem: () => {
        throw new Error('blocked');
      },
    };
    expect(readDebugFlag('?debug=1', broken)).toBe(true);
    expect(readDebugFlag('', broken)).toBe(false);
  });
});

describe('createCornerTapCounter', () => {
  it('scatta al quinto tocco nell\'angolo entro la finestra', () => {
    let now = 0;
    const tap = createCornerTapCounter(() => now);
    const results = [0, 1, 2, 3, 4].map((i) => {
      now = i * 200;
      return tap(10, 10);
    });
    expect(results).toEqual([false, false, false, false, true]);
  });

  it('un tocco fuori dall\'angolo azzera il conteggio', () => {
    const tap = createCornerTapCounter(() => 0);
    tap(10, 10);
    tap(10, 10);
    tap(300, 300);
    expect([tap(10, 10), tap(10, 10), tap(10, 10), tap(10, 10)]).toEqual([false, false, false, false]);
  });

  it('i tocchi troppo vecchi non contano', () => {
    let now = 0;
    const tap = createCornerTapCounter(() => now);
    [0, 100, 200, 300].forEach((t) => {
      now = t;
      tap(10, 10);
    });
    now = 10_000;
    expect(tap(10, 10)).toBe(false);
  });
});
