const RECAPTCHA_VERIFY_URL = "https://www.google.com/recaptcha/api/siteverify";

export async function verifyRecaptcha(token: string): Promise<boolean> {
  const secret = process.env.RECAPTCHA_SECRET_KEY;

  // If no secret configured, skip verification (dev mode / not configured)
  if (!secret) {
    console.warn("[reCAPTCHA] RECAPTCHA_SECRET_KEY not set — skipping verification");
    return true;
  }

  try {
    const body = new URLSearchParams({ secret, response: token });
    const res = await fetch(RECAPTCHA_VERIFY_URL, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: body.toString(),
    });
    const data: { success: boolean; "error-codes"?: string[] } = await res.json();
    if (!data.success) {
      console.warn("[reCAPTCHA] Verification failed:", data["error-codes"]);
    }
    return data.success;
  } catch (error) {
    console.error("[reCAPTCHA] Verification error:", error);
    return false;
  }
}
