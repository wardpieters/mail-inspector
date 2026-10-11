# Browser acceptance — 2026-10-11

Reviewed production PHP/Nginx images served by Docker Compose on loopback. Report data was seeded into an isolated acceptance database and clearly labeled fixture domains; no public SMTP target was scanned.

- [x] Homepage renders compiled Inertia/Vue assets; domain and optional selector labels are accessible.
- [x] Invalid email input displays a domain validation message; empty optional selectors are accepted (also regression tested).
- [x] Queued and running reports show automatic updates and incomplete coverage.
- [x] An open running report updates to partial without navigation and removes its automatic-update indicator. Terminal timer cleanup is independently tested with fake timers against Inertia's polling boundary.
- [x] Completed reports retain separate IPv4 and IPv6 rows and show the IPv6 failure in the overall assessment.
- [x] Failed reports display their error; null-MX reports explain intentionally disabled incoming mail.
- [x] Evidence expands as escaped text. JSON download works; feature tests check export equality with the shared report resource.
- [x] Desktop layout and 390px mobile viewport inspected; page width equals viewport width and the wide endpoint table scrolls within its container.
- [x] Light palette and focus CSS inspected; preference-driven dark palette is present in CSS without JavaScript/theme storage.
- [ ] Live light→dark→light preference switching: the available browser API exposes no color-preference emulation. Dark native controls, contrast, and switching behavior still require manual preference QA. No claim of a performed dark-mode browser check.
- [ ] External IPv6 and provider outbound port 25: local fixture sockets prove both address families, not external hosting connectivity. Use `scanner:diagnose` on the deployment host.
