# Architectural Decisions

- Canonical site URL will be taken from `SITE_BASE` environment variable (server) and `VITE_SITE_BASE` (frontend) to allow flexible deployments.
- Server serves `/robots.txt` and `/sitemap.xml` dynamically using `SITE_BASE`.
- Frontend meta tags are updated at runtime using `import.meta.env.VITE_SITE_BASE` to support both `prof-aid.com` and `www.prof-aid.com`.
- `memories/session/` is intended to be ephemeral and is excluded from Git by default (add to `.gitignore`).
- OAuth callbacks are resolved at runtime using `GOOGLE_CALLBACK_URL` environment variable.
