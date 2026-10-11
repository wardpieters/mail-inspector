# Mail server tester: design specification

Date: 2026-10-11
Status: Approved by the user on 2026-10-11, including the Inertia and browser-preference theme amendment.

## 1. Purpose and accepted requirements

Build a working Laravel web application that diagnoses a domain's incoming mail infrastructure and related mail security DNS configuration. The user enters a domain, starts a scan, watches progress, and receives an evidence-based report with suggested fixes. Results must distinguish individual MX hosts and every discovered IPv4 and IPv6 address, including backup MX hosts.

The user explicitly selected Laravel and self-hosting with Docker Compose, including queues, workers, migrations, and deployment operations. The same application will support a hosted installation whose host permits outbound SMTP port 25. No external Sites deployment is required: SMTP probing needs direct TCP connections from the application's workers.

Required checks: DNSSEC, CAA, Answer, Connect, HELO, TLS, Secure, Cert, From, MTA-STS, DANE, FCRDNS, open relay, SPF, DKIM, and DMARC. This specification defines their meaning rather than treating their names as interchangeable security signals.

Success means real DNS and SMTP observations, endpoint-specific findings, useful recommendations, and a reproducible Compose deployment. Example reports may be used in tests, but live reports must never contain simulated results.

## 2. Scope and application experience

The first release includes a domain input, optional DKIM selector input, scan submission, a progress page, saved report pages, and JSON export. No user accounts, billing, recurring domain monitoring, email sending, report notifications, inbound mailbox, or email upload/signature verification are included.

Use Laravel 13 with PHP 8.4, Inertia, Vue 3, TypeScript, and Vite-built Tailwind CSS. Render application pages as Vue components served by Laravel through Inertia; the minimal HTML document shell is not an application UI. Use a restrained, readable diagnostic interface with teal actions and status labels that do not rely on color. Provide both light and dark palettes selected by the browser's `prefers-color-scheme` preference, with light as the fallback. The theme follows preference changes while the page is open, styles native controls through `color-scheme`, and does not require a saved preference or manual toggle. The page must work on mobile, support keyboard interaction, and expose progress updates without moving focus.

The report contains a domain overview, transport/security assessment, priority-ordered findings, domain DNS checks, and an MX endpoint matrix. Each endpoint is identified by MX name, preference, literal IP address, and address family. Expandable details show timestamps, timing, DNS evidence, certificate metadata, and a bounded SMTP transcript. Domain-level policies appear once, with their endpoint-specific application visible in the matrix.

Scan status is polled through Inertia partial reloads every two seconds while active; polling stops at terminal status and is cleaned up when the page unmounts. A job failure must leave a readable report rather than an indefinitely spinning screen.

Reports use random, non-enumerable public identifiers. Anyone who possesses a report URL can read it; there is no claim of account-based privacy. Report pages are excluded from indexing. Scan history is not exposed as a public list. Default retention is seven days and is configurable. Export contains the same findings and limitations as the displayed report.

## 3. Architecture and boundaries

Keep the scanner in PHP inside Laravel, behind narrow interfaces. Do not introduce a second application language or an external scanner API.

Components:

- Web layer validates input, enforces submission limits, creates scans, and renders persisted reports.
- Scan coordinator discovers routing and policies, records endpoint jobs, and finalizes reports.
- DNS client queries an internal validating Unbound resolver through Net_DNS2. It returns records, response codes, authentication metadata, and explicit lookup errors.
- DNS/policy analyzers validate domain records and return structured findings.
- SMTP probe performs bounded exchanges with a single pinned endpoint, preserving the MX hostname for SNI and identity validation.
- Certificate and DANE analyzers consume captured chains and authenticated DNS evidence independently of the SMTP session.
- Report aggregator summarizes completed findings without hiding failures on lower-priority MX hosts or one address family.
- Scheduler expires stale scans and old reports; it does not initiate recurring scans.

Use PostgreSQL 17 for persistence and Redis 7 for queues, cache, submission limits, and scan locks. Web, migration, worker, and scheduler services share one application image and dependency lockfiles.

## 4. Scan lifecycle and persistence

1. Normalize a domain to its lowercase ASCII IDNA form, removing an optional final dot. Reject URLs, email addresses, IP literals, whitespace, SMTP control characters, malformed labels, and overlength input. Accept at most ten validated DKIM selectors.
2. Create a scan in `queued` state and dispatch a coordinator through a transactional outbox record. A scheduler dispatches unsent outbox records; duplicate delivery is safe.
3. Acquire a scan lock, transition to `running`, record scanner version and capability evidence, and query MX, A/AAAA, policies, and explicitly requested selectors.
4. Persist the routing snapshot and all planned endpoints before dispatching one endpoint job per MX/address pair. Redis concurrency controls limit probes per scan, per MX hostname, and per literal target IP across the deployment. If an MX resolves to no usable address, persist an MX-scoped finding instead of silently omitting that host.
5. Each endpoint job writes findings under unique check keys. Outcomes are idempotent and timestamps preserve attempted work. Transient infrastructure errors can retry; definitive remote observations are not repeatedly probed automatically.
6. Finalize when every planned endpoint is terminal. A scheduled watchdog reconciles lost jobs and expired scan deadlines so a killed worker cannot strand a scan.
7. The terminal lifecycle is `completed`, `partial`, or `failed`. `completed` means the planned work finished, not that configuration passed. `partial` means useful findings exist but planned work was interrupted or could not be scheduled. `failed` means no useful assessment could be produced. Check-level skipped/inconclusive outcomes remain visible in any report.

Tables:

- `scans`: internal ID, public random ID, normalized domain, selectors, lifecycle, timestamps, deadline, capability snapshot, scanner version, summary, and terminal error.
- `scan_endpoints`: scan ID, MX hostname, MX preference, IP, family, lifecycle, lease/deadline, attempts, and transport metadata. Unique on scan/MX/IP.
- `scan_findings`: scan ID, optional endpoint ID, check key, scope key, outcome, severity, title, explanation, recommendation, structured evidence, duration, and timestamps. Uniqueness includes scope to support multiple DKIM selectors and policies.
- `scan_outbox`: scan ID, job kind, endpoint identity if applicable, dispatch state, timestamps, and last dispatch error. Delivery reconciliation is transactional and idempotent.
- Laravel job failure storage, plus standard cache/queue structures as needed.

Schema version and scanner version appear in JSON reports. Duplicate worker execution must not duplicate endpoints, findings, or completion counts. Expiry cascades report data and removes associated outbox rows.

## 5. MX discovery and dual-stack behavior

Query all MX records and preserve preference. For every non-null MX, resolve all A and AAAA records, follow bounded alias chains for diagnostic discovery, and flag an alias used as an MX target. Deduplicate identical addresses within a host; keep the same address under different MX names because SNI and certificate identity can differ.

An authenticated or unauthenticated negative MX response is different from a lookup failure. If the domain has no MX but does have A/AAAA records, report implicit MX routing and probe the domain itself. A valid single `MX 0 .` means the domain intentionally accepts no mail: do not connect and mark incoming transport checks not applicable. A null MX mixed with ordinary MX records is a configuration failure.

IPv6 absence is informational; advertised but unreachable IPv6 is an endpoint issue only when evidence supports that diagnosis. Local `network unreachable` and unsupported socket family errors indicate scanner limitations. Generic timeouts are inconclusive about the cause and are described as reachability failures from this scanner, without asserting a remote-server defect.

Never substitute another address when an endpoint fails. Literal-IP connections must retain the associated hostname for SNI, PKIX verification, and policy matching. A configured resource ceiling produces an explicit partial report showing omitted endpoints; no addresses are silently dropped.

## 6. DNS and authentication checks

### DNSSEC

Unbound validates from its root trust anchor. Trust the AD flag only from this deployment-controlled resolver with validation enabled; the presence of DS/DNSKEY/RRSIG records alone is not a pass. Report authenticated answers, unsigned answers, confirmed validation failures, and indeterminate resolver errors distinctly. SERVFAIL alone does not prove broken DNSSEC. A checking-disabled comparison and resolver diagnostic evidence may identify a validation failure; otherwise retain an indeterminate result.

Keep validation metadata attached to MX, alias/address, and TLSA lookups. DANE authentication must use the complete relevant secure discovery path, not an unrelated secure answer for the base domain.

### CAA

Resolve the effective certificate-issuance policy for the domain and MX hostnames, respecting aliases and parent traversal. Show `issue`, `issuewild`, `iodef`, and unknown critical properties. Validate syntax and distinguish absent restrictions from a malformed policy. CAA concerns issuance authorization; absence is informational and CAA is not used to invalidate an existing SMTP certificate.

### SPF

Find TXT records beginning with `v=spf1`, concatenate chunks within each RR, and reject multiple SPF records. Parse mechanisms/modifiers, IPv4/IPv6 prefixes, qualifiers, includes, redirects, and termination behavior. Analyze dependencies with cycle detection and bounded recursion. Identify broad authorization and weak terminal policies as warnings, not transport failures.

Show lookup-causing terms and the RFC lookup limit. Conditional paths and macros must not be reported as an exact runtime lookup count: distinguish proven invalid syntax, concrete over-limit paths, conservative estimates, and unresolved dynamic evaluation. No sender IP or actual message is supplied, so this is configuration analysis rather than an SPF authentication verdict. DNS errors remain distinct from absent records.

### DKIM

Query each user-supplied `selector._domainkey.domain` name, supporting bounded CNAME delegation. Validate record multiplicity, tags, key encoding/type, revoked empty keys, and supported RSA/Ed25519 key material. Report key strength and selector-specific DNS errors. Without selectors, mark the check skipped with an explanation and input guidance; do not claim DKIM is missing or exhaustively discover selectors. A published key does not establish that outgoing messages are signed correctly.

### DMARC

Use RFC 9989 policy discovery and its bounded DNS tree walk. Parse applicable records, duplicate tags/records, `p`, `sp`, `np`, alignment, and PSD policy metadata. Show the policy's source domain and inheritance. Missing policy and monitoring-only policy are security warnings, separate from SMTP reachability.

Inspect reporting destination syntax and external aggregate-report authorization under RFC 9990. Identify obsolete legacy tags as compatibility information instead of rejecting an otherwise valid policy. This scan cannot establish message alignment or a DMARC authentication pass.

## 7. Per-endpoint SMTP and transport checks

All sessions use a configured scanner HELO hostname, bounded read sizes, explicit command timeouts, and valid CRLF framing. Parse multiline replies correctly, including split reads. Do not authenticate, invoke VRFY/EXPN, submit DATA, or send a message body. Commands and socket targets are structured values, never interpolated shell commands.

| Check | Observations and outcome rules |
| --- | --- |
| Connect | TCP connection to the exact IP on port 25; timing, error class, and scanner address-family limitations. |
| Answer | Initial SMTP response; expect a valid 220 greeting, preserve bounded banner, and explain temporary/permanent rejection. |
| HELO | Send EHLO, parse capabilities, and use HELO fallback only when appropriate. After STARTTLS, issue EHLO again; capability sets may change. |
| TLS | STARTTLS advertisement, command response, and actual TLS handshake. Advertisement alone does not pass. |
| Secure | Negotiated protocol/cipher plus dedicated bounded attempts to negotiate legacy TLS versions. A client unable to offer a legacy version has not established server rejection; label that subcheck inconclusive. Modern TLS/cipher policy is versioned in the report. |
| Cert | Capture leaf and available chain, fingerprint, issuer, SANs, validity, key information, and PKIX trust/hostname results. Verify against the MX hostname and bundled CA trust store. Expiring within 30 days produces a warning. |
| From | Record the response to a configured envelope sender after greeting and TLS when available. Default to the null reverse path `MAIL FROM:<>` unless an operator configures a valid test sender. Rejection can reflect sender policy; distinguish observation from a general mail-delivery claim. |
| FCRDNS | Look up PTR for the literal IP, resolve each returned name's A/AAAA addresses, and test binary address equality. Report missing, mismatching, and lookup-error cases separately. Treat this primarily as hostname hygiene. |
| Open relay | When operator-controlled external sender and recipient identities are configured, test an unauthenticated external-to-external envelope, record MAIL/RCPT replies, then RSET/QUIT before DATA. Sender/recipient domains must be outside the scanned domain. Explicit relay denial is evidence for the tested scenario; unrelated rejection is inconclusive. Recipient acceptance yields a suspected-relay warning, never confirmed message delivery. Without configured identities, skip with an actionable explanation. |

If a certificate is invalid, still capture diagnostic evidence in an explicitly untrusted TLS session; validate the certificate separately and do not describe that session as authenticated. Chain verification uses OpenSSL with argument arrays and locally controlled files. Failed early steps mark dependent checks skipped with the causal reason, without manufacturing multiple independent failures.

The configured probe sender and relay addresses are operator settings, not public arbitrary SMTP inputs. Transcript evidence omits operator mailbox local parts while preserving response codes and useful diagnostic text.

Security policy version 1 accepts TLS 1.2/1.3, requires at least 128-bit symmetric security, and flags NULL, anonymous, RC4, 3DES, and non-forward-secret negotiated TLS 1.2 suites. Legacy-version probes target TLS 1.0 and 1.1 when the client can offer them. Results describe the negotiated cipher and probed versions; they do not claim exhaustive cipher-suite enumeration. Certificate key-strength findings flag RSA below 2048 bits and EC below 256 bits, and preserve unsupported key algorithms as limitations.

## 8. Transport authentication policies

### MTA-STS

Validate the `_mta-sts` TXT advertisement and retrieve `https://mta-sts.<domain>/.well-known/mta-sts.txt` with HTTPS certificate verification. Use resolved, validated, pinned public addresses; do not follow redirects. Enforce response and body bounds. Parse version, mode, maximum age, and MX patterns.

Match each discovered MX against the policy and evaluate STARTTLS and PKIX hostname/trust requirements per endpoint. Distinguish `none`, `testing`, and `enforce`: publishing a testing policy is not enforcement. Show DNS discovery versus HTTPS fetch failures separately. Include SMTP TLS reporting DNS record presence/syntax as supporting policy evidence, without sending reports.

### DANE

Query `_25._tcp.<mx>` TLSA records and require authenticated applicable routing/TLSA DNS evidence. Support selectors 0 (certificate) and 1 (SPKI), and matching types 0 (exact), 1 (SHA-256), and 2 (SHA-512). Implement SMTP DANE certificate usages 2 and 3 with their distinct trust/name/path rules; retain unsupported and unusable records as evidence rather than silently treating them as matches.

A secure usable TLSA set passes authentication when a supported record validates the observed chain under its usage. A hash comparison alone is insufficient for usage 2. A self-signed leaf that meets usage 3 can pass DANE while failing PKIX; preserve both results. Insecure TLSA records are not authenticated DANE. Missing records mean DANE is unavailable; malformed or mismatching advertised secure records are actionable failures.

Report DANE and MTA-STS independently. An authenticated transport mechanism can be successful without the other mechanism being deployed.

## 9. Finding model and overall assessment

Each finding has an outcome (`pass`, `warning`, `fail`, `info`, `skipped`, `inconclusive`, or `not_applicable`) and an independent severity (`critical`, `high`, `medium`, `low`, or `none`). Evidence includes the actual observation, source, timestamp, and limits. A recommendation identifies the record, endpoint, or deployment setting to fix.

Provide three separate summaries: incoming reachability, transport authentication/encryption, and published sender-authentication configuration. The overall label is rule-based rather than an opaque numeric grade:

- `Attention required`: at least one definitive configuration failure or high/critical warning.
- `Incomplete assessment`: no such definitive issue, but material checks are inconclusive/skipped or work is partial.
- `Improvements available`: completed applicable checks contain medium/low warnings.
- `Checks passed`: all applicable checks pass or are informational, and necessary coverage is complete.
- `Not accepting mail`: valid null MX, with remaining DNS findings still shown.

Apply these rules in listed order, except that valid null MX always sets the incoming reachability summary to Not accepting mail; definitive DNS configuration problems still take precedence in the overall label. Material completeness covers planned DNS policy evaluation, requested selectors, requested relay probes, and connect/greeting/handshake/certificate evaluation for all applicable endpoints. Unrequested DKIM/relay checks and unavailable exhaustive cipher enumeration do not lower that coverage value. An unavailable legacy-protocol probe lowers Secure-check completeness but does not erase a verified modern TLS handshake.

Completeness is displayed independently even when the overall label is Attention required. Missing optional DKIM selectors and relay identities are always visible but do not erase independently verified transport results. Missing optional DANE/MTA-STS deployment is informational per mechanism; absence of any verified authenticated transport policy produces a combined medium warning. Unsigned DNSSEC and missing SPF/DMARC produce medium warnings; these are improvements rather than proof of broken SMTP delivery. The application never labels a domain universally secure, guarantees deliverability, or treats one good MX as covering all backups and address families.

## 10. Resource and network controls

Hosted and self-hosted modes share controls. Only hostname input is accepted, with no arbitrary TCP ports. Deny loopback, private, link-local, multicast, unspecified, documentation, metadata, and other non-public target addresses for both IPv4 and IPv6; normalize IPv4-mapped IPv6 before classification. Check every resolved target before connection and pin SMTP/HTTPS connections to checked addresses to prevent DNS rebinding.

Allow fixture network targets only through a testing-only dependency/configuration that cannot be enabled by a public request. Limit DNS aliases/dependencies, SMTP transcript length, policy body size, concurrent scans, endpoint fan-out, and scan lifetime. Escape all remote data in HTML and exports.

Initial configurable defaults: five submissions per minute per client IP, one active scan per normalized domain, 64 endpoints per scan, four simultaneous endpoints per scan, two simultaneous sessions per remote MX, five-second connection timeout, ten-second command timeout, 30-second endpoint budget, and five-minute scan deadline. Queue visibility/retry settings exceed job execution time; leases and watchdogs handle expired attempts. Report any ceiling reached instead of claiming exhaustive coverage.

Submitting an already-active domain returns its existing report URL and explains that the current selector set remains in effect. A new selector set can be submitted after that scan finishes. The scan deadline is measured from creation, including queue wait; the watchdog expires queued scans as well as running scans.

Only the HTTP service is published by default, bound to localhost for self-hosting; the hosted override supports an external reverse proxy. PostgreSQL, Redis, and Unbound are internal services. There is no inbound SMTP listener or published port 25. Host outbound DNS, HTTPS, and SMTP connectivity and a working IPv6 route are deployment prerequisites; opening outbound port 25 does not itself enable IPv6.

## 11. Docker Compose and operational lifecycle

Services: `web` (Nginx), `app` (PHP-FPM), `migrate` (one-shot Laravel migration command), `worker` (Laravel queue worker), `scheduler` (Laravel scheduler), `postgres`, `redis`, and `unbound`.

The application image installs required PHP extensions, CA certificates, and OpenSSL, installs production Composer dependencies from the lockfile, and includes compiled frontend assets from a separate Node build stage. The production runtime does not require bind-mounted source code or runtime dependency installation.

PostgreSQL and Redis have health checks. Unbound health verifies local resolver operation and the configured trust anchor without depending on an external test domain. Separate capability diagnostics verify authenticated resolution and invalid-signature rejection; an unauthenticated answer is not accepted as evidence of resolver validation. The migration service waits for the database to be healthy, runs `migrate --force`, and must finish successfully before app, workers, and scheduler start. Failed migrations block those services and expose the failure. Starting another worker does not run migrations again.

Persist PostgreSQL data, necessary Laravel storage, and resolver trust-anchor state in volumes. Laravel logs go to container output. Use graceful worker shutdown/restart settings and configurable worker count. Do not embed APP_KEY or service credentials in the image; documented setup creates deployment secrets and keeps the application key stable across restarts.

Include a base Compose definition, development override, and Linux-host IPv6 override using a dual-stack worker network. Document Docker daemon/host routing requirements and a capability diagnosis command. Container addressing is not presented as proof of external IPv6 connectivity. Local Docker Desktop limitations must remain explicit in reports and operational docs.

Deployment documentation covers initial startup, migration failure recovery, upgrades with database backup, scaling workers, graceful queue restart, HTTP reverse-proxy/TLS setup, capability diagnostics, tests, data retention, and logs. The application's health endpoint returns no credentials or report data. Operators supply a resolvable scanner HELO name and optional controlled SMTP envelope identities.

## 12. Verification and acceptance criteria

Use PHPUnit for Laravel feature tests and scanner unit/integration tests. Deterministic DNS response fixtures and a controlled SMTP fixture verify protocol behavior without depending on real domains or submitting email. Provide a Compose test profile with dual-stack SMTP and DNS fixtures where supported; inability to run IPv6 fixtures must be reported, not counted as passing.

Required verification:

1. Input normalization, selector validation, CSRF protection, rate limiting, report lookup, escaped hostile banners/records, and JSON evidence consistency.
2. MX priority, multiple IPv4/IPv6 addresses, endpoint deduplication, alias handling, implicit MX, null MX, and partial coverage limits.
3. DNS response chunks, lookup errors, signed/unsigned/bogus evidence, CAA traversal, SPF recursion/macros/limits, DKIM key parsing, and current DMARC discovery/inheritance.
4. Multiline/split SMTP replies, timeouts, greeting failure, HELO fallback, EHLO after TLS, STARTTLS rejection, invalid certificates, SNI, and distinct protocol-probe limitations.
5. PKIX hostname/trust/expiry and DANE usages 2/3, selectors and hashes, unusable records, mismatches, and self-signed DANE success independent of PKIX failure.
6. MTA-STS syntax, wildcard host matching, policy modes, verified/pinned HTTPS fetch, redirect rejection, and endpoint policy violations.
7. FCRDNS binary address equality, suspected relay acceptance, explicit denial, inconclusive rejection, and assertions that no DATA command or message body is sent.
8. Private/reserved and IPv4-mapped address blocking, malicious DNS aliases, HTTP target pinning, bounded evidence, and no shell interpolation.
9. Job retries/duplicate delivery, outbox recovery, lost-worker deadlines, partial reports, terminal progress, cleanup, and correct summary precedence.
10. Fresh Compose startup with migrations, service health, frontend asset build, worker execution, persistent reports across restarts, Inertia form errors and polling, and responsive browser review in both browser-preference themes, including a preference change while the page is open.

Live scanning is an optional operator smoke check, not the foundation for automated tests. Verify each required behavior using fixtures and record any unavailable Docker/network verification separately. Completion requires a working source tree, deployment documentation, successful applicable tests/builds, and an honest statement of remaining verification limitations.

## 13. Design decisions and tradeoffs

The Laravel monolith with PHP workers fits the user's preferred stack and avoids an additional scanner service. Queued jobs bound web request latency and allow workers to scale. Unbound provides actual DNSSEC validation; a DNS client library alone is not a validating resolver. Separate protocol observations, PKIX results, and DANE results prevent misleading conclusions about self-signed or policy-authenticated servers.

No opaque security score, selector guessing, or message delivery is included. These omissions preserve the distinction between what a domain-level scan can observe and what requires real message authentication or controlled delivery experiments. Public report links and retention are intentionally simple for this release.

## 14. References

- [Laravel 13 release notes](https://laravel.com/docs/13.x/releases)
- [Laravel queues](https://laravel.com/docs/13.x/queues)
- [Inertia documentation](https://inertiajs.com/)
- [Tailwind browser-preference dark mode](https://tailwindcss.com/docs/dark-mode)
- [Docker IPv6 networking](https://docs.docker.com/engine/daemon/ipv6/)
- [Unbound validating resolver](https://www.nlnetlabs.nl/projects/unbound/about/)
- [Net_DNS2 resource record support](https://netdns2.com/documentation/resource-records-supported/)
- [SMTP: RFC 5321](https://www.rfc-editor.org/info/rfc5321/)
- [Null MX: RFC 7505](https://www.rfc-editor.org/info/rfc7505/)
- [CAA: RFC 8659](https://www.rfc-editor.org/info/rfc8659/)
- [SPF: RFC 7208](https://www.rfc-editor.org/info/rfc7208/)
- [DKIM: RFC 6376](https://www.rfc-editor.org/info/rfc6376/) and [key requirements: RFC 8301](https://www.rfc-editor.org/info/rfc8301/)
- [DMARC: RFC 9989](https://www.rfc-editor.org/rfc/rfc9989.html) and [aggregate reporting: RFC 9990](https://www.rfc-editor.org/info/rfc9990/)
- [MTA-STS: RFC 8461](https://www.rfc-editor.org/info/rfc8461/)
- [SMTP DANE: RFC 7672](https://www.rfc-editor.org/rfc/rfc7672.html)

## 15. Specification self-review

Reviewed against the approved design and user requirements: every requested check has an explicit definition; IPv4/IPv6 coverage and limitations are represented; migration ordering, queue recovery, public report access, and retention are defined; no message delivery or actual message-authentication claim is made; authenticated DANE and PKIX are independent; current DMARC policy discovery is identified; resource ceilings produce explicit limitations; the user-approved amendment specifies Inertia and light/dark mode following browser preference. No deferred product decisions or placeholder sections remain.
