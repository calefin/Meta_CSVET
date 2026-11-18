import { describe, expect, it } from 'vitest';
import { publicUrlWarning, siteOrigin } from './siteOrigin.js';

const req = (host: string, protocol = 'https') => ({ protocol, headers: { host } });

describe('siteOrigin (D-62)', () => {
  it('never sends real visitors to a local PUBLIC_URL', () => {
    expect(siteOrigin('http://localhost:3080', req('bagparser.com'))).toBe('https://bagparser.com');
    expect(siteOrigin('http://127.0.0.1:3080/', req('www.bagparser.com'))).toBe('https://www.bagparser.com');
  });
  it('keeps a real PUBLIC_URL and local development as they are', () => {
    expect(siteOrigin('https://bagparser.com/', req('bagparser.com'))).toBe('https://bagparser.com');
    expect(siteOrigin('http://localhost:3080', req('localhost:3080', 'http'))).toBe('http://localhost:3080');
    expect(siteOrigin('', req('bagparser.com'))).toBe('https://bagparser.com');
  });
  it('warns the admin only when a public host is served with a local PUBLIC_URL', () => {
    expect(publicUrlWarning('http://localhost:3080', 'bagparser.com')).toBe('PUBLIC_URL=http://localhost:3080');
    expect(publicUrlWarning('https://bagparser.com', 'bagparser.com')).toBeNull();
    expect(publicUrlWarning('http://localhost:3080', 'localhost:3080')).toBeNull();
  });
});
