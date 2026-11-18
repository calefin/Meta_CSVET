/** Posts to a Discord channel webhook (D-71/D-75/D-76). Only URLs accepted by cleanDiscordWebhook reach here. */
export type PostResult = 'ok' | 'gone' | 'error';

export async function postDiscord(url: string, body: unknown): Promise<PostResult> {
  try {
    const res = await fetch(`${url}?wait=true`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
      redirect: 'error',
      signal: AbortSignal.timeout(10_000),
    });
    if (res.status === 404 || res.status === 401) return 'gone';
    return res.status < 300 ? 'ok' : 'error';
  } catch {
    return 'error';
  }
}
