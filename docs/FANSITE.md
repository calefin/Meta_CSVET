# Tibia Fansite Programme — checklist

Source: Tibia Fansite Agreement (tibia.com → Community → Fansites → Agreement), text read on 2026-11-04
via archive.org because the university network blocks tibia.com. Re-check the live page before applying.

How to apply: e-mail **fansites@tibia.com** with a short description of the site. Only general Tibia
fansites are accepted (not guild or single-world sites). Two levels: *supported* (link + badge in the
fansite section) and *promoted* (highlighted, yearly interview, free Premium Time for the contact person
and up to 3 helpers) — promoted requires regular own content and always up-to-date information.

| Requirement (Agreement) | Status | Where |
|---|---|---|
| State that Tibia is made by CipSoft and CipSoft content (e.g. graphics) is copyrighted by CipSoft, with links | ✅ | Footer (every page) + `/about` |
| Contact section (site creator's name + contact address) | 🚧 | `/about#contact`; confirm the e-mail in `frontend/src/site.ts` (placeholder `contato@bagparser.com`) |
| Reliable contact person: name, Tibia character, e-mail, acceptable English; checks e-mail and the internal fansite board regularly | ⏳ | Owner's data goes in the application e-mail; optionally `SITE.ownerCharacter` |
| Ads/sponsors suitable for minors, owner controls ad content | ✅ | No ads today; policy stated on `/about#monetization` |
| All means of monetisation clear to visitors (no crypto mining, no hidden stream viewbots) | ✅ | `/about#monetization`; update it **before** launching Premium/ads |
| Content about Tibia only, updated regularly, correct, good grammar, professional design | 🚧 | Replace the two draft QuickHunts articles; keep catalog updated (`import-tibiawiki.mjs`) |
| Own content clearly separated from copies/translations of official news; no plagiarism; permission for third-party images/tables | ✅ | TibiaWiki data credited (CC BY-SA) on `/about` |
| Quest spoilers marked with a disclaimer | ⏳ | Needed when a guide contains full quest solutions |
| Moderation and behaviour standards | ✅ | `/admin`: ban with reason, delete account, audit log; rules on `/about#rules` |
| No cheat tools / illegal software / links to them; downloads need a "not made by CipSoft" disclaimer | ✅ | No downloads (the PWA is the site itself) |
| Tibia Rules enforced (no account/character/item trading for real money) | ✅ | `/about#rules`; no marketplace or forum |
| Monthly traffic statistics to CipSoft by the 3rd of each month | ✅ | `/admin` → "Tráfego mensal" → "Copiar relatório (EN)" |

## Monetisation notes
- Paid features are allowed as long as they are disclosed. Keep the core tools free (they are what makes the
  site a useful fansite) and describe exactly what Premium includes and costs on `/about` before charging.
- Never sell anything that is Tibia value (gold, items, characters, Tibia Coins, boosts/services in game).

## Before sending the application
1. Confirm the contact e-mail in `frontend/src/site.ts` and deploy.
2. Replace the QuickHunts drafts with your own articles (own content weighs a lot, mandatory for promoted).
3. Let the counter run for at least a full month so the first statistics can be sent.
4. E-mail fansites@tibia.com (in English): site URL, short description of the tools, your name, Tibia character,
   e-mail, and that you have read and accept the Fansite Agreement.
