# Static Prototype

Plain HTML/CSS mockups of the key screens, built **before** any Filament resource, so the workflow and layout can be agreed on cheaply. No backend, no build step — open any `.html` file directly in a browser.

This folder is scaffolded but empty of actual pages right now. Building the pages is **Phase 1**, gated on approval of the root `PLAN.md` — see `.claude/design/01-static-prototype.md` for the full page list and notes before starting.

## Structure

```
static_prototype/
├── pages/           <- one .html file per screen (login, dashboards, product catalog, request form, approval queue, issuance screen, alerts, reports, etc.)
├── assets/
│   ├── css/         <- shared stylesheet(s)
│   ├── js/          <- vanilla JS for interactive bits (tabs, modals) — no framework
│   └── img/          <- any placeholder images/icons
```
