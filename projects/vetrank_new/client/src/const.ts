export { COOKIE_NAME, ONE_YEAR_MS } from "@shared/const";

// Returns Google OAuth URL in production, or local dev login in dev mode.
// When email/password auth is used, callers should open the LoginModal instead.
export const getLoginUrl = () => {
  if (import.meta.env.VITE_APP_ID === "local-dev") {
    return "/api/dev/login";
  }
  return "/api/auth/google";
};
