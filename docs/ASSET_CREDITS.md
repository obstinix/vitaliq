# Asset credits and licences

All third-party assets are self-hosted under `public/assets`. Licence texts are bundled alongside them.

## Fonts

The display and interface faces were chosen from the [Awwwards free fonts collection](https://www.awwwards.com/awwwards/collections/free-fonts/).

- **Which licences were acceptable.** Most fonts in that collection (Dirtyline, many Behance releases) are free only for personal use, or do not allow redistribution in a public repository, so they were excluded. The shortlist was the SIL Open Font License faces in the collection: Geist, Inter, Junicode, Bagnard, Archivo Narrow, HK Grotesk and Trispace.
- **Junicode, display.** Chosen for an editorial, scholarly voice (condensed width axis, strong italic), which suits careful health information better than a geometric display face.
- **Bagnard, rejected.** A single graffiti-inspired weight.

| Font | Use | Source | Licence | Notes |
| --- | --- | --- | --- | --- |
| **Junicode** (VF 2.003) by Peter S. Baker | Display headings, wordmark, figures | `webfiles/JunicodeVF-Roman.woff2` from <https://github.com/psb1558/Junicode-font> | SIL OFL 1.1 (`public/assets/fonts/LICENSE-Junicode-OFL.txt`) | Modified: subset to Latin, Latin-1, Latin Extended-A and common punctuation, with `ENLA` pinned to 0, `wght` limited to 400–700 and `wdth` to 75–100 (fontTools 4.66). Its copyright notice declares no Reserved Font Name. 1.0 MB reduced to 67 KB. |
| **Geist** 1.8 by Vercel with basement.studio | Interface and body text | npm package `geist@1.7.2` (`dist/fonts/geist-sans/Geist-Variable.woff2`), <https://github.com/vercel/geist-font> | SIL OFL 1.1 (`LICENSE-Geist-OFL.txt`) | Unmodified |
| **Geist Mono** 1.7 by Vercel | References, codes, data, labels | Same package (`geist-mono/GeistMono-Variable.woff2`) | SIL OFL 1.1 | Unmodified |

## JavaScript libraries

| Library | Version | Source | Licence | File |
| --- | --- | --- | --- | --- |
| GSAP core | 3.15.0 | npm `gsap` | GreenSock "Standard 'no charge' license" (<https://gsap.com/standard-license>) | `vendor/gsap-3.15.0.min.js` (licence header retained) |
| GSAP ScrollTrigger | 3.15.0 | npm `gsap` | Same | `vendor/ScrollTrigger-3.15.0.min.js` |
| D3 modules (selection 3.0.0, scale 4.0.2, shape 3.2.0, axis 3.0.0, array 3.2.4, time-format 4.1.0, transition 3.0.1, plus dependencies) | v7 family | npm, bundled with esbuild 0.25.10 (`tools/d3-bundle`) | ISC; d3-ease is BSD-3-Clause | `vendor/d3-vitaliq-7.min.js`; licences in `vendor/licenses/` |

## Data

| Dataset | Source | Licence | Use |
| --- | --- | --- | --- |
| WHO COVID-19 global data (weekly cases and deaths) | World Health Organization, WHO Coronavirus (COVID-19) dashboard, data.who.int. Downloaded 10 Oct 2026 from <https://srhdpeuwpubsa.blob.core.windows.net/whdh/COVID/WHO-COVID-19-global-data.csv>. | CC BY 4.0 with WHO additional terms; WHO does not endorse Vitaliq. | `public/assets/data/who-india-monthly.json`: India's weekly figures summed by calendar month. The file records the method and the source file's SHA-256. The monthly totals add up to WHO's cumulative figure of 45,056,221. |
| Indian states and union territories | Two-letter keys following ISO 3166-2:IN suffixes (2023 revision) | Factual reference data | `database/migrations/0002_reference_states.sql` |

## Health content

The public guidance pages are original summaries written for Vitaliq, paraphrasing the WHO COVID-19 fact sheet (last updated 27 Nov 2025) and the CDC symptoms and testing pages (last updated 10 Mar 2025). Each page links to its sources and shows when it was checked. WHO statements cited in the announcements link to the original statements.

## Icons and images

- The bell and person icons are hand-written inline SVG.
- The favicon (`img/favicon.svg`) and the wordmark mark are original.
- The screenshots in `docs/screenshots` were taken of this application with synthetic demo data.
- No stock photography or illustration is used.

## Platform additions (Nov 2026)

| Item | Source | Licence | Use |
| --- | --- | --- | --- |
| chillerlan/php-qrcode 5 | Composer (`chillerlan/php-qrcode`) | MIT | QR codes for health IDs, sample labels, TOTP set-up and report verification |
| anthropic-ai/sdk | Composer (`anthropic-ai/sdk`) | MIT | Optional assistant provider |
| App design | The "framed sheet" app style from the design-taste-frontend skill, with Vitaliq's own palette and Geist | Design guidance only; no files copied | `public/assets/css/app.css` |
| Navigation icons | Hand-written outline SVG paths in `src/Support/Navigation.php` | Original | Sidebar |
| Lab reference intervals | Commonly published adult intervals, as editable defaults | Factual | `database/seeds/lab-tests.json`; each laboratory must verify them |
| Medicine interactions | A small set of widely documented interactions, written for Vitaliq | Original wording | `database/seeds/drug-interactions.json`; not a substitute for a licensed database |
| LOINC codes | LOINC (Regenstrief Institute), codes only | LOINC licence (free use of codes) | FHIR Observation coding |
