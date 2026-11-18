import passport from "passport";
import { Strategy as GoogleStrategy } from "passport-google-oauth20";
import { ENV } from "./env";
import * as db from "../db";
import { ONE_YEAR_MS } from "@shared/const";
import { sdk } from "./sdk";

export function initializePassport(callbackUrl?: string) {
  const resolvedCallbackUrl = callbackUrl ?? ENV.googleCallbackUrl;

  // Google OAuth Strategy
  passport.use(
    new GoogleStrategy(
      {
        clientID: ENV.googleClientId,
        clientSecret: ENV.googleClientSecret,
        callbackURL: resolvedCallbackUrl,
      },
      async (accessToken: any, refreshToken: any, profile: any, done: any) => {
        try {
          const googleId = profile.id;
          const email = profile.emails?.[0]?.value || null;
          const name = profile.displayName || profile.name?.givenName || null;
          const profilePicture = profile.photos?.[0]?.value || null;

          // Create a unique openId for Google users
          const openId = `google:${googleId}`;

          // Upsert user in database
          await db.upsertUser({
            openId,
            email: email ?? undefined,
            name,
            loginMethod: "google",
            lastSignedIn: new Date(),
          });

          // Return user data
          done(null, {
            openId,
            email,
            name,
            googleId,
            profilePicture,
          });
        } catch (error) {
          done(error);
        }
      }
    )
  );

  // Serialize user for session
  passport.serializeUser((user: any, done: any) => {
    done(null, user.openId);
  });

  // Deserialize user from session
  passport.deserializeUser(async (openId: string, done: any) => {
    try {
      const user = await db.getUserByOpenId(openId);
      done(null, user);
    } catch (error) {
      done(error);
    }
  });
}

export default passport;
