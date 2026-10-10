# Technology decisions

The brief listed several candidate libraries. Each was judged against a concrete use in Vitaliq; nothing was added just because it appeared on the list.

## Selected

| Choice | Why | Where | Cost and maintenance |
| --- | --- | --- | --- |
| **PHP 8.2+, no framework** | The brief requires PHP/MySQL. A small kernel (about 1,300 lines in `src/Core`) is easier to read and examine for an STQA project than a framework, and needs no Composer packages at runtime, so it runs on a stock XAMPP install. | Everywhere | We maintain the router, sessions and CSRF ourselves; each is unit- or functionally tested. |
| **MariaDB/MySQL through PDO** | Required stack. Native prepared statements (`ATTR_EMULATE_PREPARES=false`), strict SQL mode, transactions. | `src/Core/Database.php`, services | None beyond the database server |
| **PHPUnit 12** (dev only) | Standard PHP test runner; JUnit output feeds the STQA lab | `tests/` | Composer dev dependency |
| **GSAP 3.15 + ScrollTrigger** | Staged hero entrance, the status-rail draw-in, count-up figures and scroll reveals, with a timeline API and `prefers-reduced-motion` handling done once. Free to use under GSAP's standard licence. | Public layout only (`views/partials/scripts.php`, `initMotion()` in `app.js`) | 73 KB + 45 KB minified, loaded with `defer` on public pages only. Signed-in pages use CSS transitions instead. |
| **D3 v7, custom subset** | Two data-driven charts (daily platform activity, WHO monthly bars) with axes, scales and hover read-outs. The esbuild bundle in `tools/d3-bundle` contains only selection, scale, shape, axis, array, time-format and transition. | `/statistics`, `/admin` (`charts.js`) | 78 KB instead of 280 KB for full D3; loaded only where `scripts` includes `charts`. Rebuild steps are in `tools/d3-bundle`. |
| **Native ES modules, Fetch and CSSOM** | Validation, popovers, polling and the double-submit guard need nothing more | `app.js`, `validation.js`, `qa-lab.js` | No dependency |

## Considered and omitted

| Library | Decision | Reason |
| --- | --- | --- |
| **jQuery** | Not used | No existing jQuery code to integrate. `fetch`, `querySelector`, `classList` and `<details>` cover every interaction. |
| **Velocity.js** | Not used | Overlaps GSAP entirely; two animation engines would add weight and two ways to do the same thing. |
| **PixiJS** | Not used | No interactive 2D scene that SVG cannot express. A WebGL renderer for charts and transitions would cost weight and accessibility. |
| **Three.js** | Not used | No 3D content earns its place in a health service; a rotating virus or particle hero is exactly what the design brief rules out. |
| **Snap.svg** | Not used | The only SVG work is charts (D3) and simple icons (inline SVG). No path morphing is needed. |
| **Chart.js and similar** | Not used | D3 is already justified for custom charts; a second chart library would duplicate it. |
| **CSS framework (Tailwind, Bootstrap)** | Not used | A single token-based stylesheet keeps the art direction specific and the CSP simple (no inline styles). |
| **CDN-hosted assets** | Not used | Everything is self-hosted, so the CSP can stay `'self'`, there are no third-party requests, and the app works offline. |

## Performance and accessibility notes

- **Fonts.** Three WOFF2 files totalling 208 KB. The 1 MB Junicode file was subset to Latin and two axes, giving 67 KB. Fonts use `font-display: swap`, and the body and display fonts are preloaded.
- **Motion.** Only `opacity` and `transform` are animated. `prefers-reduced-motion` disables all of it, and content is never hidden without JavaScript (see `motion-gate.js`).
- **Charts.** Every chart has a `role="img"` summary and a data table in a `<details>` element.
- **Lighthouse.** Accessibility, Best Practices and SEO scored 100 on the audited pages (see the test execution report). Lighthouse performance was not measured; the PHP built-in server serves uncompressed assets, so its numbers would not represent a real deployment.
