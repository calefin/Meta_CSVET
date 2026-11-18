import { describe, expect, it } from 'vitest';
import { cleanPath, clientErrorInput, errorFingerprint, isNoise } from './errorLog.js';

describe('error log', () => {
  it('drops query strings and hashes from paths', () => {
    expect(cleanPath('/reset?token=secret#x')).toBe('/reset');
    expect(cleanPath('https://bagparser.com/hunt-analyser/h/abc?x=1')).toBe('/hunt-analyser/h/abc');
    expect(cleanPath('javascript:alert(1)')).toBeNull();
    expect(cleanPath(42)).toBeNull();
  });

  it('groups the same error across builds and line numbers', () => {
    const a = errorFingerprint({ kind: 'client', message: "Cannot read properties of undefined (reading 'x')", stack: 'TypeError\n    at f (https://bagparser.com/assets/index-AbCd1234.js:10:200)' });
    const b = errorFingerprint({ kind: 'client', message: "Cannot read properties of undefined (reading 'x')", stack: 'TypeError\n    at f (https://bagparser.com/assets/index-ZzYy9876.js:12:7)' });
    const c = errorFingerprint({ kind: 'client', message: 'Other error', stack: null });
    expect(a).toBe(b);
    expect(a).not.toBe(c);
  });

  it('ignores browser noise and empty reports', () => {
    expect(isNoise('ResizeObserver loop completed with undelivered notifications.')).toBe(true);
    expect(isNoise('Script error.')).toBe(true);
    expect(isNoise('x', 'at chrome-extension://abc/content.js')).toBe(true);
    expect(isNoise('TypeError: Failed to fetch')).toBe(true);
    expect(clientErrorInput({ message: '  ' }, null, null)).toBeNull();
    const e = clientErrorInput({ message: 'Boom', stack: 'at x', path: '/admin?y=1', version: '0.7.0' }, 5, 'UA');
    expect(e).toMatchObject({ kind: 'client', message: 'Boom', path: '/admin', version: '0.7.0', user_id: 5 });
  });
});
