import { randomBytes, pbkdf2Sync, timingSafeEqual } from "node:crypto";

const ITERATIONS = 160_000;
const KEY_LENGTH = 32;
const DIGEST = "sha256";
const SALT_BYTES = 16;

export function hashPassword(password: string) {
  const salt = randomBytes(SALT_BYTES).toString("base64");
  const derivedKey = pbkdf2Sync(password, salt, ITERATIONS, KEY_LENGTH, DIGEST);
  const fingerprint = derivedKey.toString("base64");
  return `pbkdf2_${DIGEST}$${ITERATIONS}$${salt}$${fingerprint}`;
}

export function verifyPassword(password: string, storedHash: string | null | undefined) {
  if (!storedHash || typeof storedHash !== "string") return false;
  const parts = storedHash.split("$");
  if (parts.length !== 4) return false;

  const [prefix, iterationsText, salt, fingerprint] = parts;
  if (!prefix.startsWith("pbkdf2_")) return false;

  const iterations = Number(iterationsText);
  if (!Number.isInteger(iterations) || iterations <= 0) return false;

  const derivedKey = pbkdf2Sync(password, salt, iterations, KEY_LENGTH, DIGEST).toString("base64");

  try {
    return timingSafeEqual(Buffer.from(derivedKey, "utf8"), Buffer.from(fingerprint, "utf8"));
  } catch {
    return false;
  }
}
