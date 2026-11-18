import { COOKIE_NAME, ONE_YEAR_MS } from "@shared/const";
import type { Express, Response } from "express";
// Using any for Request to avoid passport augmentation issues
// eslint-disable-next-line @typescript-eslint/no-explicit-any
type Request = any;
import passport from "passport";
import { getSessionCookieOptions } from "./cookies";
import { sdk } from "./sdk";

export function registerGoogleAuthRoutes(app: Express) {
  // Initiate Google login
  app.get(
    "/api/auth/google",
    passport.authenticate("google", {
      scope: ["profile", "email"],
      session: false,
    })
  );

  // Google OAuth callback
  app.get(
    "/api/auth/google/callback",
    passport.authenticate("google", {
      session: false,
      failureRedirect: "/login?error=google_auth_failed",
    }),
    async (req: Request, res: Response) => {
      try {
        const user = req.user as any;

        if (!user || !user.openId) {
          res.redirect("/login?error=invalid_user");
          return;
        }

        // Create session token
        const sessionToken = await sdk.createSessionToken(user.openId, {
          name: user.name || user.email || "",
          expiresInMs: ONE_YEAR_MS,
        });

        // Set session cookie
        const cookieOptions = getSessionCookieOptions(req);
        res.cookie(COOKIE_NAME, sessionToken, {
          ...cookieOptions,
          maxAge: ONE_YEAR_MS,
        });

        // Redirect to the system chooser (exam system vs. student management)
        res.redirect("/hub");
      } catch (error) {
        console.error("[Google Auth] Callback failed:", error);
        res.redirect("/login?error=session_creation_failed");
      }
    }
  );

  // Logout route
  app.post("/api/auth/logout", (req: Request, res: Response) => {
    const cookieOptions = getSessionCookieOptions(req);
    res.clearCookie(COOKIE_NAME, { ...cookieOptions, maxAge: -1 });
    res.json({ success: true, message: "Logged out successfully" });
  });

  // Get current user (optional)
  app.get("/api/auth/me", async (req: Request, res: Response) => {
    try {
      const user = await sdk.authenticateRequest(req);
      if (user) {
        res.json(user);
      } else {
        res.status(401).json({ error: "Not authenticated" });
      }
    } catch (error) {
      res.status(401).json({ error: "Not authenticated" });
    }
  });
}
