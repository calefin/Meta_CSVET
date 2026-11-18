/**
 * YouTube Data API v3 helpers (D-38). Only public metadata is used (no OAuth, no downloads).
 * YouTube API Services policies: show videos via the official embedded player or thumbnail links,
 * attribute the channel, and refresh or delete stored metadata within 30 days.
 */

export const YT_API = 'https://www.googleapis.com/youtube/v3';

/** ISO 8601 duration (PT1H2M3S) → seconds */
export function parseDuration(iso: string | undefined): number {
  const m = /^P(?:(\d+)D)?T?(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/.exec(iso ?? '');
  if (!m) return 0;
  const [, d, h, min, s] = m.map((v) => Number(v ?? 0));
  return d * 86400 + h * 3600 + min * 60 + s;
}

/** Accepts a video id or any youtube.com / youtu.be URL */
export function extractVideoId(input: string): string | null {
  const s = input.trim();
  if (/^[\w-]{11}$/.test(s)) return s;
  const m = /(?:youtu\.be\/|[?&]v=|\/embed\/|\/shorts\/|\/live\/)([\w-]{11})/.exec(s);
  return m ? m[1] : null;
}

export interface VideoMeta {
  youtube_id: string;
  title: string;
  channel_id: string;
  channel_title: string;
  published_at: string;
  duration_s: number;
  thumbnail_url: string;
  view_count: number;
  language: string | null;
  embeddable: boolean;
  public: boolean;
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export function toVideoMeta(item: any): VideoMeta | null {
  if (!item?.id || !item.snippet) return null;
  const sn = item.snippet;
  const thumbs = sn.thumbnails ?? {};
  return {
    youtube_id: typeof item.id === 'string' ? item.id : item.id.videoId,
    title: String(sn.title ?? '').slice(0, 300),
    channel_id: String(sn.channelId ?? ''),
    channel_title: String(sn.channelTitle ?? '').slice(0, 120),
    published_at: sn.publishedAt ?? new Date().toISOString(),
    duration_s: parseDuration(item.contentDetails?.duration),
    thumbnail_url: (thumbs.medium ?? thumbs.high ?? thumbs.default)?.url ?? '',
    view_count: Number(item.statistics?.viewCount ?? 0),
    language: (sn.defaultAudioLanguage ?? sn.defaultLanguage ?? null)?.slice(0, 10) ?? null,
    embeddable: item.status?.embeddable !== false,
    public: (item.status?.privacyStatus ?? 'public') === 'public',
  };
}
