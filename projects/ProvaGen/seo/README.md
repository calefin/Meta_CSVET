SEO structure for ProvaGen

This folder contains templates and scripts to manage SEO assets for the project.

Files:
- `meta_templates.md`: templates for meta tags (title, description, OpenGraph, Twitter)
- `generate-sitemap.mjs`: small script to generate `public/sitemap.xml` from a list of routes
- `robots.txt`: canonical robots file (also served by server)

Usage:
- Update `SITE_BASE` in `.env` to the canonical site URL (e.g. `https://www.prof-aid.com`).
- Run `node seo/generate-sitemap.mjs` to emit `client/public/sitemap.xml`.
- Ensure the server serves `/robots.txt` and `/sitemap.xml` (server already provides dynamic routes).
