# Mail Inspector

A self-hosted Laravel 13 / Inertia 3 / Vue 3 application for inspecting a domain's incoming mail configuration. Light and dark palettes follow the browser preference automatically.

The interface, validation messages, and report text follow the browser's language preference: Dutch (`nl`) or US English (`en_US`), with US English as the fallback. Existing reports are translated when viewed; domain names, DNS records, and SMTP transcripts retain their original values.

It discovers every MX priority and all A/AAAA addresses, then probes each independently on port 25. Reports cover DNSSEC, CAA, Connect, Answer, HELO, TLS, Secure, Cert, From, MTA-STS, DANE, FCRDNS, suspected open relay, SPF, selector-based DKIM, and DMARC. Evidence, remediation, coverage, public report links, and JSON export are included.

## Start with Docker Compose

Requires Docker Engine with Compose **2.24.4+** (override support), working internet access for image builds, and outbound TCP 25. IPv6 additionally requires real host and container routing.

```sh
php scripts/setup-env.php
# Edit .env: APP_URL, SCAN_HELO and optionally sender/relay identities.
docker compose up --build -d --wait
```

Open http://localhost:8080. The one-shot migration service must succeed before app, workers, and scheduler start. PostgreSQL, Redis, and Unbound have no published ports. Scan jobs use Redis; reports persist in PostgreSQL; the scheduler recovers work and prunes reports after seven days.

```sh
docker compose up -d --scale worker=4
docker compose logs -f worker scheduler
docker compose exec app php artisan scanner:diagnose
```

Hosted HTTP binding and IPv6 options:

```sh
docker compose -f compose.yaml -f compose.hosted.yaml -f compose.ipv6.yaml up --build -d
```

A hosted scanner needs a provider that permits outbound port 25. Compose does not publish an inbound SMTP port or unblock provider restrictions. Put the web service behind an HTTPS reverse proxy and configure trusted proxies and secure cookies. See [deployment and operations](docs/deployment.md).

## Published container images

GitHub Actions tests PHP 8.4/8.5, the frontend, SMTP/DNS fixtures, and Compose startup before publishing AMD64 and ARM64 images to GHCR:

- `ghcr.io/wardpieters/mail-tester`: PHP application, migrations, queue workers and scheduler, with different startup commands.
- `ghcr.io/wardpieters/mail-tester-web`: Nginx and compiled frontend assets.
- `ghcr.io/wardpieters/mail-tester-unbound`: validating DNS resolver.

All images from a release use the same tag. `latest` and `main` track verified builds on main; `sha-<full commit SHA>` pins a build. Version tags such as `v1.0.0` publish `1.0.0`. Builds include provenance and SBOM attestations.

To deploy prebuilt images, prepare `.env` as above, authenticate to GHCR if the packages are private, then run:

```sh
docker compose -f compose.yaml -f compose.registry.yaml pull
docker compose -f compose.yaml -f compose.registry.yaml up -d --wait
```

Set `MAIL_IMAGE_TAG` to a commit tag for a pinned deployment. Add the hosted and IPv6 overrides after the registry override when needed. See [registry access and upgrades](docs/deployment.md#registry-images) for details.

## Local development and tests

PHP 8.4+ with intl, sockets, PDO SQLite/PostgreSQL, Redis, cURL and OpenSSL; Composer 2 and Node 24.

```sh
composer install
npm ci
npm run build
# For a local PHP server, configure local DB/Redis/resolver values in .env.
php artisan migrate
php artisan serve
php artisan queue:work redis --queue=discovery,endpoints --timeout=240
php artisan schedule:work
```

```sh
vendor/bin/phpunit
npm run test:unit -- --run
npm run typecheck
npm run build
docker compose -p mail-inspector-test -f compose.test.yaml run --rm php-test vendor/bin/phpunit tests/Integration
```

The integration suite uses isolated fixture services and generated certificates. A cached-service host alternative is documented in deployment.md. Unit tests use deterministic DNS/SMTP fakes. Production has no request-controlled private-address or port bypass.

## Interpreting reports

Results reflect one scanner network and one observation time. A scanner firewall, blocked outbound 25, or unavailable IPv6 can cause incomplete coverage. Every discovered public endpoint remains visible. A valid null MX explicitly declines inbound mail.

SPF, DKIM and DMARC analyze published policy and keys; they do not authenticate a message. DKIM selectors must be supplied. DMARC uses RFC 9989 DNS tree walk and RFC 9990 external-report authorization. CAA affects issuance and does not invalidate existing certificates. DANE usages 2 and 3 are evaluated independently of PKIX. Missing DANE anchors and local legacy TLS restrictions remain inconclusive. No DATA/body is sent: external RCPT acceptance is suspected relay behavior, never proof of delivery. Configure operator-controlled external probe identities to enable that optional check.

Reports are accessible to anyone with their unguessable URL until retention expires. No recurring scans or email notifications are scheduled.

## Verification

The isolated suite runs real IPv4/IPv6 SMTP sockets, STARTTLS with observed MX SNI, cryptographically signed DNS zones through Unbound, Redis permits, and concurrent PostgreSQL claims. A separate host-side lifecycle suite verifies production migration gating and report/key persistence across restarts:

```sh
MAIL_TEST_COMPOSE=true vendor/bin/phpunit tests/Integration/ComposeLifecycleTest.php
```

Build production images first. These tests create and remove their own random Compose projects and volumes. Test-container runs skip host-only lifecycle tests; run them separately as above. Browser observations and explicit QA limitations are in [the acceptance checklist](tests/Browser/acceptance-checklist.md).

Completed implementation checks, review fixes, and remaining limitations are recorded in [the verification report](docs/superpowers/reports/2026-10-11-mail-server-tester-verification.md).
