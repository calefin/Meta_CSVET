import { describe, expect, it } from 'vitest';
import { adsConfig, adsForBrowser, adsTxt } from './ads.js';

describe('ads config (D-66)', () => {
  it('client alone verifies the site (ads.txt), client + slot shows ads', () => {
    expect(adsConfig({})).toBeNull();
    expect(adsConfig({ ADSENSE_CLIENT: 'ca-pub-123' })).toBeNull();
    const verifyOnly = adsConfig({ ADSENSE_CLIENT: ' ca-pub-1234567890123456 ' });
    expect(verifyOnly).toEqual({ client: 'ca-pub-1234567890123456', slot: null });
    expect(adsTxt(verifyOnly)).toBe('google.com, pub-1234567890123456, DIRECT, f08c47fec0942fa0\n');
    expect(adsForBrowser(verifyOnly)).toBeNull();
    const live = adsConfig({ ADSENSE_CLIENT: 'ca-pub-1234567890123456', ADSENSE_SLOT: '1234567890' });
    expect(adsForBrowser(live)).toEqual({ client: 'ca-pub-1234567890123456', slot: '1234567890' });
    expect(adsConfig({ ADSENSE_CLIENT: 'ca-pub-1234567890123456', ADSENSE_SLOT: 'x<script>' })?.slot).toBeNull();
    expect(adsTxt(null)).toBeNull();
  });
});
