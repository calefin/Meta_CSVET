import { describe, expect, it } from 'vitest';
import { extractVideoId, parseDuration, toVideoMeta } from './youtube.js';

describe('youtube helpers', () => {
  it('parses ISO durations', () => {
    expect(parseDuration('PT1H2M3S')).toBe(3723);
    expect(parseDuration('PT15M')).toBe(900);
    expect(parseDuration('P1DT1S')).toBe(86401);
    expect(parseDuration(undefined)).toBe(0);
  });
  it('extracts video ids from urls', () => {
    expect(extractVideoId('https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=10s')).toBe('dQw4w9WgXcQ');
    expect(extractVideoId('https://youtu.be/dQw4w9WgXcQ')).toBe('dQw4w9WgXcQ');
    expect(extractVideoId('https://www.youtube.com/shorts/dQw4w9WgXcQ')).toBe('dQw4w9WgXcQ');
    expect(extractVideoId('dQw4w9WgXcQ')).toBe('dQw4w9WgXcQ');
    expect(extractVideoId('not a video')).toBeNull();
  });
  it('maps videos.list items', () => {
    const v = toVideoMeta({
      id: 'abcdefghijk',
      snippet: { title: 'Hunt guide', channelId: 'UC1', channelTitle: 'Chan', publishedAt: '2026-01-01T00:00:00Z', thumbnails: { medium: { url: 'https://i.ytimg.com/x.jpg' } }, defaultAudioLanguage: 'pt-BR' },
      contentDetails: { duration: 'PT10M' },
      statistics: { viewCount: '1234' },
      status: { embeddable: true, privacyStatus: 'public' },
    });
    expect(v).toMatchObject({ youtube_id: 'abcdefghijk', duration_s: 600, view_count: 1234, language: 'pt-BR', embeddable: true, public: true });
  });
});
