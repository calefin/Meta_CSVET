# Release History / Changelog

Use this file to record release notes for each version of the site.

Format:

## v0.1.0 — YYYY-MM-DD
- Initial site version tracking created.


How to add a new release (manual):
1. Update `VERSION` with the new semver (e.g. `0.1.1`).
2. Add a new section here with date and bullet points describing changes.
3. Commit: `git add VERSION doc/RELEASES.md && git commit -m "Release vX.Y.Z"`.

Automated update (recommended):
- Use the helper script `node scripts/update-version.mjs` to bump or set a version; it will update `VERSION` and append an entry to `memories/repo/version_history.md`.
