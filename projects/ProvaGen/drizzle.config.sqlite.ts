import "dotenv/config";
import { defineConfig } from "drizzle-kit";

export default defineConfig({
  schema: "./drizzle/schema.sqlite.ts",
  out: "./drizzle/sqlite",
  dialect: "sqlite",
  dbCredentials: {
    url: (process.env.DATABASE_URL ?? "sqlite:./dev.db").replace(/^sqlite:/, ""),
  },
});
