# Project Summary — ProvaGen

This memory captures project structure, responsibilities and key notes to help AI continuity.

Top-level structure:
- `client/` — Frontend (Vite, React, TypeScript). Entry: `client/src/main.tsx`, `client/index.html`.
- `server/` — Backend (Express, tRPC). Entry: `server/_core/index.ts`.
- `drizzle/` — DB schema and migrations.
- `shared/` — Shared types and utilities.
- `doc/` — Documentation (created by assistant).
- `memories/` — AI persistence (this directory).

Key responsibilities:
- Import and processing of question banks (QTI, JSON) via `server/` and Python helpers.
- PDF generation via `pdf-generator.py` and related utilities.
- Authentication: Passport + Google OAuth.
- Image handling and ArUco markers in `client/src/lib/answerSheetSvg.ts`.

Environment and run hints:
- Use `.env` to set `SITE_BASE` (canonical URL), DB credentials, OAuth secrets.
- Frontend served via Vite in dev; production served as static build.

SEO notes:
- Server exposes `/robots.txt` and `/sitemap.xml`; both use `SITE_BASE`.
- `client/index.html` contains hardcoded canonical/OG tags pointing to `https://www.prof-aid.com` — consider making them dynamic.

Suggestions for AI continuity:
- Keep `memories/repo/ai_continuity.md` updated with priorities and environment changes.
- Store short facts (one per line) for quick loading.

Files inspected when creating this summary:
- `README.md`, `IMPLEMENTATION_SUMMARY.md`, `client/index.html`, `server/_core/index.ts`.
