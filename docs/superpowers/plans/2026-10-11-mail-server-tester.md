# Mail Server Tester Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the approved Laravel/Inertia mail diagnostic application with real dual-stack probes, saved reports, browser-preference themes, and a complete Docker Compose deployment.

**Architecture:** Laravel coordinates idempotent Redis jobs and persists observations in PostgreSQL. PHP scanner services use deployment-controlled Unbound for validated DNS and pinned SMTP/HTTPS connections. Inertia serves Vue pages and partial report reloads; report outcomes reflect evidence and coverage independently.

**Tech Stack:** Laravel 13, PHP 8.4, Inertia 3, Vue 3, TypeScript, Tailwind CSS 4, Vite, PostgreSQL 17, Redis 7, Unbound, Net_DNS2, OpenSSL, PHPUnit, Vitest, Vue Test Utils. Node 24 builds assets; application runtime does not require Node or an Inertia SSR daemon. Commit Composer and npm lockfiles.

**Spec:** `docs/superpowers/specs/2026-10-11-mail-server-tester-design.md`, approved on 2026-10-11 with the Inertia/browser-preference theme amendment.

## Global Constraints

- Use Laravel 13 with PHP 8.4, Inertia, Vue 3, TypeScript, and Vite-built Tailwind CSS.
- Keep the scanner in PHP inside Laravel, behind narrow interfaces. Do not introduce a second application language or an external scanner API.
- Default retention is seven days and is configurable.
- Scan status is polled through Inertia partial reloads every two seconds while active; polling stops at terminal status and is cleaned up when the page unmounts.
- Provide both light and dark palettes selected by the browser's `prefers-color-scheme` preference, with light as the fallback. The theme follows preference changes while the page is open, styles native controls through `color-scheme`, and does not require a saved preference or manual toggle.
- Initial configurable defaults: five submissions per minute per client IP, one active scan per normalized domain, 64 endpoints per scan, four simultaneous endpoints per scan, two simultaneous sessions per remote MX, five-second connection timeout, ten-second command timeout, 30-second endpoint budget, and five-minute scan deadline.
- Accept at most ten validated DKIM selectors.
- Do not authenticate, invoke VRFY/EXPN, submit DATA, or send a message body.
- Never substitute another address when an endpoint fails.
- No inbound SMTP listener or published port 25; only the HTTP service is published by default, bound to localhost for self-hosting.
- Use RFC 9989 policy discovery and its bounded DNS tree walk.
- No user accounts, billing, recurring domain monitoring, email sending, report notifications, inbound mailbox, or email upload/signature verification are included.
- Follow the user's selected execution method. Do not dispatch implementation agents before the plan is approved and an execution method is selected.

## Review Focus

1. A delayed job arrives after the watchdog has finalized a scan: it must not reopen the report or overwrite newer results. Pin this in Task 10.
2. Two submissions for the same internationalized domain use different spellings/selectors: return the same active report and explain its unchanged selector set. Pin this in Task 2.
3. An MX returns IPv4-mapped IPv6 pointing to a private service: reject the normalized target before any connection. Pin this in Task 3.
4. A server accepts an external RCPT but rejects mail later: show suspected relay only, and never transmit DATA. Pin this in Task 7.
5. An open dark-mode report changes to light mode while polling finishes: follow the browser preference without flashing, moving focus, or retaining a polling timer. Pin this in Task 11 and browser QA in Task 13.

## File structure and common contracts

All PHP application classes use `App\` namespaces corresponding to their paths. Tests use `Tests\`. Prefer one named class per file. Vue page names are `Home` and `Scans/Show`, resolved from `resources/js/Pages/`.

| Area | Responsibility |
| --- | --- |
| `app/Mail/Value/`, `app/Mail/Enums/` | Immutable scan inputs, targets, findings, lookup/probe results, budgets, outcomes, and lifecycle definitions |
| `app/Mail/Network/` | Address classification, connection pinning, deadline enforcement, and concurrency permits |
| `app/Mail/Dns/` | DNS adapter, normalized results, MX discovery, DNSSEC diagnostics, reverse lookup |
| `app/Mail/Checks/` | Independent DNS, SMTP, certificate, and transport-policy checks |
| `app/Mail/Scans/` | Persistence, submission, outbox dispatch, coordination, finalization, and expiry |
| `app/Jobs/`, `app/Console/Commands/` | Thin queue/CLI adapters for scanner services |
| `app/Http/` | Form requests, controllers, Inertia middleware, and report serialization |
| `resources/js/Pages/`, `Components/`, `Layouts/`, `types/` | Inertia UI and report presentation |
| `docker/`, `compose*.yaml` | Application images, HTTP configuration, validating resolver, and Compose lifecycle |
| `tests/Support/`, `tests/Fixtures/` | Controlled DNS/SMTP responses, certificates, clocks, and test-only network implementations |

Shared immutable values are created in the task that first produces them:

- Task 2: `ScanInput(domain: string, selectors: array<string>)`; `Finding(key: string, scope: string, outcome: Outcome, severity: Severity, title: string, explanation: string, recommendation: ?string, evidence: array, durationMs: int = 0)`. Evidence carries `observed_at` in UTC ISO 8601.
- Task 3: `EndpointTarget(mx: string, preference: int, ip: string, family: int, discoverySecure: bool)`; `ScanBudget(deadline: DateTimeImmutable, ?Clock $clock = null)` with `remainingSeconds(): float` and `assertRemaining(): void`. `Clock::now(): DateTimeImmutable` is implemented by `SystemClock` and the test `FakeClock`.
- Task 4: `DnsResult(name: string, type: string, records: array, rcode: string, validation: string, evidence: array)`. Validation is `secure`, `insecure`, `bogus`, or `indeterminate`; rcode includes real DNS codes plus explicit transport errors. Records use `{name, type, ttl, data}`; data names are `address`, `exchange/preference`, `chunks`, `target`, `flags/tag/value`, or `usage/selector/matching_type/association` for their respective types.
- Task 4: `RoutingSnapshot(targets: array<EndpointTarget>, findings: array<Finding>, nullMx: bool, omitted: array, dnsEvidence: array)`.
- Task 7: `SmtpReply(code: int, lines: array<string>, raw: string)`; `ProbeResult(findings: array<Finding>, session: array, chainPem: array<string>)`. Session metadata includes endpoint identity, capabilities, TLS negotiation, timings, and redacted transcript; it does not contain live sockets.
- Task 9: `PolicySnapshot(mtasts: ?array, tlsaByMx: array<string,DnsResult>, findings: array<Finding>)`.
- Task 11: serialized report `{schema_version, scanner_version, scan, summaries, coverage, capabilities, dns_findings, endpoints}`. `scan` includes `public_id`, `domain`, `selectors`, `status`, timestamps, and limitations. Never expose internal IDs, secrets, exception stacks, or operator mailbox local parts.

Tests run through `php artisan test`; the focused command per task must fail because the requested behavior is absent before implementation, then pass afterwards. Do not treat missing dependencies or invalid test setup as the red phase. Bootstrap/dependency setup is the prerequisite to Task 1's behavioral cycle; production scanner code is written after its owning failing tests.

---

### Task 1: Runnable Laravel/Inertia application and browser-preference shell

**Files:** Create Laravel skeleton files at repository root without replacing `.git` or `docs/`; create `app/Http/Middleware/HandleInertiaRequests.php`, `resources/views/app.blade.php`, `resources/js/app.ts`, `resources/js/Layouts/AppLayout.vue`, `resources/js/Pages/Home.vue`, `resources/css/app.css`, `vite.config.ts`, `tsconfig.json`, `vitest.config.ts`; modify `bootstrap/app.php`, `routes/web.php`, `composer.json`, `package.json`; test `tests/Feature/HomePageTest.php`.

**Interfaces:** Produce `GET /` named `home` rendering Inertia component `Home`, and `GET /up` for local app health. Root Blade file only mounts Inertia. Vue layout accepts its default slot; no account/auth starter screens.

- [ ] **Step 1: Establish tooling.** Create a Laravel 13 skeleton in a temporary directory, copy application files while preserving existing docs/Git, and install the Laravel Inertia adapter, Vue adapter, TypeScript, Tailwind/Vite integration, Vitest, and Vue Test Utils. Use compatible stable versions and lockfiles. Add `build`, `typecheck`, and `test:unit` npm scripts; configure SQLite in-memory for Laravel tests. Do not add unused authentication packages.
- [ ] **Step 2: Write the failing homepage test.** In `HomePageTest::test_home_uses_inertia`, assert `$this->get('/')->assertOk()->assertInertia(fn ($page) => $page->component('Home'))`; assert `/up` returns 200.
- [ ] **Step 3: Run `php artisan test --filter=HomePageTest`.** Expected: behavioral failure because the homepage is not the specified Inertia component.
- [ ] **Step 4: Implement the Inertia root/middleware/page and theme shell.** Use Vue 3 page resolution and Vite asset integration. Define CSS palette variables with a `prefers-color-scheme: dark` media override, native `color-scheme`, visible focus indicators, and reduced-motion support. Inherit system theme through CSS without localStorage or runtime theme hydration. Home initially explains domain scanning; submission arrives in Task 2.
- [ ] **Step 5: Verify.** Run the focused PHP test, `npm run typecheck`, and `npm run build`; expect all successful and a generated asset manifest. Inspect initial light/dark styles during final browser QA rather than writing a test that simply restates CSS selectors.
- [ ] **Step 6: Commit.** Stage only Task 1 application/tooling/test paths and lockfiles; commit `feat: bootstrap Laravel Inertia mail tester`.

### Task 2: Scan submission, identity, and persistence

**Files:** Create `app/Mail/Value/{ScanInput,Finding}.php`, `app/Mail/Enums/{Outcome,Severity,ScanStatus,EndpointStatus}.php`, `app/Mail/Scans/{SubmitScan,ScanRepository}.php`, `app/Models/{Scan,ScanEndpoint,ScanFinding,ScanOutbox}.php`, `app/Http/Requests/StoreScanRequest.php`, `app/Http/Controllers/ScanController.php`, `database/migrations/2026_10_11_000001_create_scanner_tables.php`, `database/factories/{ScanFactory,ScanEndpointFactory}.php`, `config/mail_scan.php`, `resources/js/Pages/Scans/Show.vue`; modify `routes/web.php`, `bootstrap/app.php`, `resources/js/Pages/Home.vue`; test `tests/Feature/ScanSubmissionTest.php` and `tests/Unit/ScanInputTest.php`.

**Interfaces:** `ScanInput::fromRaw(string $domain, array $selectors): ScanInput`; `SubmitScan::submit(ScanInput $input): Scan`; `ScanRepository::putFinding(Scan $scan, ?ScanEndpoint $endpoint, Finding $finding): void`; `POST /scans` named `scans.store`; `GET /scans/{publicId}` named `scans.show`. Public IDs use UUIDv4. Store `routing_snapshot` and `policy_snapshot` JSON on scans for later jobs. `Outcome`/`Severity` values exactly match the spec; scan states include queued/running/completed/partial/failed, endpoint states queued/running/completed/failed/expired.

- [ ] **Step 1: Write tests.** `test_submits_normalized_domain_with_outbox` posts `EXAMPLE.COM.` and asserts redirect to a random report ID, stored `example.com`, queued status, five-minute deadline, and one coordinator outbox row. `test_same_idna_domain_reuses_active_report_without_changing_selectors` submits Unicode and equivalent A-label spellings with different selectors and asserts one scan and unchanged selectors. Reject URLs, SMTP control characters, IP literals, invalid selectors, and eleven selectors. Sixth submission in a minute returns 429; unrelated client quota is independent.

  Assertion pin: `$this->post('/scans', ['domain' => 'EXAMPLE.COM.', 'selectors' => []])->assertRedirect(); $this->assertDatabaseHas('scans', ['domain' => 'example.com', 'status' => 'queued']); $this->assertDatabaseCount('scan_outbox', 1);`
- [ ] **Step 2: Run `php artisan test --filter='ScanSubmissionTest|ScanInputTest'`.** Expected: absent routes/types/behavior failures after valid test setup.
- [ ] **Step 3: Implement persistence and submission.** Normalize with intl IDNA conversion and strict label validation; accept dotted valid DKIM selectors. Use database transactions and a cache lock to enforce one active normalized domain, including simultaneous submissions. Unique finding identity is `(scan_id, check_key, scope_key)` with non-null scope to avoid PostgreSQL nullable-unique surprises. Create outbox atomically with the scan; keep public-ID lookup separate from internal keys. Report duplicate-scan reuse through an Inertia flash message. Render a minimal queued report page and a Vue `useForm` submission with field errors.
- [ ] **Step 4: Verify.** Run focused tests plus `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan migrate:fresh --env=testing`; expect ephemeral schema creation and passing tests. Never run destructive schema checks against deployment data. Test expired/unknown report IDs return 404; report HTML has noindex metadata. Ensure public errors expose no stack traces.
- [ ] **Step 5: Commit.** Commit Task 2 paths with `feat: persist and submit domain scans`.

### Task 3: Target validation, deadlines, and shared probe permits

**Files:** Create `app/Mail/Value/{EndpointTarget,ScanBudget}.php`, `app/Mail/Network/{TargetGuard,ProbePermits,Clock,SystemClock}.php`, `app/Mail/Exceptions/{UnsafeTarget,ScanDeadlineExceeded}.php`, `tests/Support/FakeClock.php`, `compose.test.yaml`, `docker/php/Dockerfile.test`; test `tests/Unit/{TargetGuardTest,ScanBudgetTest}.php`, `tests/Integration/ProbePermitsTest.php`.

**Interfaces:** `TargetGuard::assertPublic(string $ip): void`; `TargetGuard::normalize(string $ip): string`; `ProbePermits::acquire(string $scanId, EndpointTarget $target): ?string`; `ProbePermits::release(string $token): void`. Permits are token-owned and time-limited; acquisition atomically enforces scan/MX/IP quotas in Redis. Budget uses an injectable clock for deterministic tests.

- [ ] **Step 1: Write tests.** Data-provider `test_rejects_unsafe_target` includes `127.0.0.1`, `10.0.0.1`, `169.254.169.254`, `::1`, `fe80::1`, `fd00::1`, `2001:db8::1`, and `::ffff:10.0.0.1`; assert `UnsafeTarget` before connection. Test allowed global addresses and IPv6 normalization. `test_budget_expires_at_deadline` advances the clock to the five-minute boundary and expects `ScanDeadlineExceeded`. Permit test: four scan tokens accepted, fifth denied; two sessions for an MX/IP accepted, third denied; expiry/wrong-token release cannot delete another owner's permit.

  Assertion pin: `$this->expectException(UnsafeTarget::class); app(TargetGuard::class)->assertPublic('::ffff:10.0.0.1');`
- [ ] **Step 2: Establish the isolated test services, then run `php artisan test --filter='TargetGuardTest|ScanBudgetTest|ProbePermitsTest'`.** The minimal `compose.test.yaml` provides Redis/PostgreSQL and a PHP 8.4 CLI test image with required extensions, mounted source, testing-only credentials, and isolated volumes. Run integration tests there with `docker compose -f compose.test.yaml run --rm php-test php artisan test --filter=ProbePermitsTest`. Later tasks extend this same test setup. Expect missing behavior failures, not missing Redis; flag unavailable Docker explicitly.
- [ ] **Step 3: Implement typed targets, classification, budgets, and permits.** Normalize addresses with binary conversion, match special-use ranges, and reject invalid/mapped non-public targets. Use Redis Lua or equivalent atomic operations for multi-key quotas; per-IP controls cannot be bypassed with a new MX alias. Budget clamps connect/read timeouts to remaining time. Production uses only the public-address guard; fixture substitution is registered exclusively under Laravel's testing environment.
- [ ] **Step 4: Verify focused tests.** Expected: all pass, including contention/lease tests against Redis. Fixtures never require a public-request bypass.
- [ ] **Step 5: Commit** `feat: bound and validate network probes`.

### Task 4: Validating DNS client, routing discovery, and FCRDNS

**Files:** Create `app/Mail/Dns/{DnsClient,NetDnsClient,MxDiscovery,DnssecDiagnostics,ReverseDns}.php`, `app/Mail/Value/{DnsResult,RoutingSnapshot}.php`, `app/Providers/MailScannerServiceProvider.php`, `tests/Support/FakeDnsClient.php`, `tests/Fixtures/dns/routing.json`, `tests/Fixtures/dns/validation.json`; create `docker/unbound/{Dockerfile,unbound.conf,healthcheck.sh}`; modify `composer.json`, `bootstrap/providers.php`; test `tests/Unit/{MxDiscoveryTest,DnssecDiagnosticsTest,ReverseDnsTest}.php` and `tests/Integration/NetDnsClientTest.php`.

**Interfaces:** `DnsClient::query(string $name, string $type, ScanBudget $budget, bool $checkingDisabled = false): DnsResult`; `MxDiscovery::discover(ScanInput $input, ScanBudget $budget): RoutingSnapshot`; `DnssecDiagnostics::analyze(DnsResult $result, ScanBudget $budget): Finding`; `ReverseDns::analyze(EndpointTarget $target, ScanBudget $budget): Finding`. Fake DNS is a map of `(name,type,checkingDisabled)` to normalized responses/errors, loaded with `FakeDnsClient::fromFixture(string $path): self`; `queries(): array` records query history.

- [ ] **Step 1: Write tests.** Fixture with two MX hosts and two A/two AAAA addresses on each yields eight distinct targets with correct preference/hostname. Assert same IP under different MX names remains distinct, aliases produce warnings, no-address hosts produce findings, 65 targets report one omission, null MX yields zero probes, and absent MX uses implicit A/AAAA. Distinguish NXDOMAIN, NODATA, timeout, and SERVFAIL. AD from the trusted validated resolver passes; RRSIG presence alone does not; CD fallback alone does not label arbitrary SERVFAIL bogus. FCRDNS verifies binary IPv6 equality and retains partial PTR lookup failures.

  Assertion pin after binding the eight-address fixture: `$snapshot = app(MxDiscovery::class)->discover(new ScanInput('fixture.test', []), $budget); self::assertCount(8, $snapshot->targets); self::assertFalse($snapshot->nullMx);`
- [ ] **Step 2: Run `php artisan test --filter='MxDiscoveryTest|DnssecDiagnosticsTest|ReverseDnsTest|NetDnsClientTest'`.** Expect behavior failures. Run adapter integration against controlled DNS packet fixtures rather than the public internet.
- [ ] **Step 3: Implement normalization and discovery.** Add `mikepultz/netdns2`; adapt its locked version behind `DnsClient`, including TCP fallback, TXT chunks, TLSA/CAA, aliases, and authentication metadata. Return errors as explicit result values. Implicit MX fallback requires NOERROR/NODATA for MX, not NXDOMAIN or a resolver failure. Attach DNSSEC evidence to each routing path. Unbound validates with a persisted root trust anchor and listens only on the Compose resolver network; its local health check is independent of internet test-domain availability.
- [ ] **Step 4: Verify focused tests and `unbound-checkconf` in the resolver image.** Expect success. Actual signed-zone validation and resolver health behavior are additionally exercised in Task 13.
- [ ] **Step 5: Commit** `feat: discover dual-stack MX routing with validated DNS`.

### Task 5: CAA and SPF configuration analysis

**Files:** Create `app/Mail/Checks/{CaaCheck,SpfCheck}.php`, `app/Mail/Dns/SpfParser.php`, `tests/Fixtures/dns/{caa,spf}.json`; test `tests/Unit/{CaaCheckTest,SpfCheckTest}.php`.

**Interfaces:** `CaaCheck::analyze(string $name, ScanBudget $budget): array<Finding>`; `SpfCheck::analyze(ScanInput $input, ScanBudget $budget): array<Finding>`; both consume `DnsClient`. `SpfParser::parse(string $record): array` returns `mechanisms`, `modifiers`, and `diagnostics` without network access. Stable check keys are `caa` and `spf`; scope identifies the domain/dependency path.

- [ ] **Step 1: Write tests.** Assert parent/alias CAA policy discovery, critical unknown property warning, absent CAA informational result, and no certificate invalidation from CAA. SPF tests concatenate RR chunks, reject two SPF RRs, validate IPv4/IPv6 CIDRs, detect include/redirect cycles, distinguish DNS errors, and recognize `+all`, `~all`, and `-all`. A concrete eleven-term lookup path fails; a conditional include tree or macro expansion reports an estimate/unresolved limitation rather than asserting an exact authentication verdict. Unknown SPF modifiers are tolerated as defined by the standard.

  Assertion pin for `test_invalid_ip4_mechanism_is_rejected`: `$parsed = app(SpfParser::class)->parse('v=spf1 ip4:999.1.1.1 -all'); self::assertNotEmpty($parsed['diagnostics']);`
- [ ] **Step 2: Run `php artisan test --filter='CaaCheckTest|SpfCheckTest'`.** Expected: missing analyzer/parser behavior failures.
- [ ] **Step 3: Implement bounded record analysis.** Follow effective CAA resolution and parse SPF grammar rather than counting substrings. Track dependency paths, macro limitations, actual lookup-causing mechanisms, recursion depth, and evidence. Enforce global deadline. Use outcomes/severities from spec; published policy is not a sender authentication pass.
- [ ] **Step 4: Re-run focused tests.** Expected: all cases pass with no external DNS queries from fixture tests.
- [ ] **Step 5: Commit** `feat: analyze CAA and SPF configuration`.

### Task 6: DKIM selectors and current DMARC policy discovery

**Files:** Create `app/Mail/Checks/{DkimCheck,DmarcCheck}.php`, `app/Mail/Dns/{DmarcDiscovery,TagRecordParser}.php`, `tests/Fixtures/dns/{dkim,dmarc}.json`, `tests/Fixtures/certificates/dkim-keys.json`; test `tests/Unit/{DkimCheckTest,DmarcCheckTest}.php`.

**Interfaces:** `DkimCheck::analyze(ScanInput $input, ScanBudget $budget): array<Finding>`; `DmarcCheck::analyze(ScanInput $input, ScanBudget $budget): array<Finding>`; `DmarcDiscovery::discover(string $domain, ScanBudget $budget): array` returns source domain, parsed policy, lookup path, and diagnostics. `TagRecordParser::parse(string $record): array` retains duplicate-tag errors. Keys/scopes preserve each selector independently.

- [ ] **Step 1: Write tests.** Empty selector input yields skipped DKIM; valid RSA/Ed25519 records pass key-publication checks; revoked/invalid/weak keys, duplicates, and CNAME delegation yield appropriate findings. DMARC tests exercise direct policy, inherited policy, `psd=y`, `sp`/`np`, `adkim`/`aspf`, duplicate records/tags, and bounded RFC 9989 tree walk. Include external `rua` authorization, obsolete tags, and a DNS failure distinct from missing policy. No test expects a message-level DKIM/DMARC pass.

  Assertion pin for `test_no_selector_is_skipped`: `$findings = app(DkimCheck::class)->analyze(new ScanInput('fixture.test', []), $budget); self::assertSame(Outcome::Skipped, $findings[0]->outcome);`
- [ ] **Step 2: Run `php artisan test --filter='DkimCheckTest|DmarcCheckTest'`.** Expected: missing behavior failures.
- [ ] **Step 3: Implement analyzers.** Validate keys cryptographically where supported and report unsupported algorithms explicitly. Implement the RFC 9989 discovery algorithm and RFC 9990 reporting authorization using normalized DNS responses; do not substitute a public-suffix guess for current DMARC discovery. Preserve syntactically valid legacy metadata as compatibility information.
- [ ] **Step 4: Re-run focused tests.** Expected: all pass, bounded query history, and selector-specific findings.
- [ ] **Step 5: Commit** `feat: inspect DKIM keys and DMARC policies`.

### Task 7: Bounded SMTP dialogue and envelope probes

**Files:** Create `app/Mail/Smtp/{SmtpSessionFactory,SmtpSession,StreamSmtpSessionFactory,StreamSmtpSession,ReplyReader,TranscriptRedactor}.php`, `app/Mail/Value/{SmtpReply,ProbeResult}.php`, `app/Mail/Checks/SmtpProbe.php`, `tests/Support/{FakeSmtpSession,FakeSmtpSessionFactory,SmtpSocketFixture}.php`, `tests/Fixtures/smtp/dialogues.json`; test `tests/Unit/{ReplyReaderTest,SmtpProbeTest}.php` and `tests/Integration/SmtpSessionTest.php`.

**Interfaces:** `SmtpSessionFactory::open(EndpointTarget $target, ScanBudget $budget): SmtpSession`; `SmtpSession::greeting(): SmtpReply`, `command(string $line): SmtpReply`, `transcript(): array`, `close(): void`; `SmtpProbe::probe(EndpointTarget $target, ScanBudget $budget): ProbeResult`. Commands exclude their terminal CRLF; session validates and adds framing. Config holds scanner HELO hostname, null/explicit test sender, and optional operator-controlled relay identities. Probe results use keys `connect`, `answer`, `helo`, `from`, `open_relay`.

- [ ] **Step 1: Write tests.** `test_parses_split_multiline_reply` feeds partial 250 lines and asserts one complete reply with correct code/capabilities. Test overlong/mixed-code replies, EOF, 220 greeting, 421/554 greeting, EHLO rejection/HELO fallback, command timeout, and local family errors. `test_accepted_external_recipient_is_only_suspected_relay` supplies 250 MAIL/RCPT replies, asserts warning outcome, and asserts the command log contains RSET/QUIT but never DATA. Distinguish explicit relay denial from unrelated recipient rejection; missing configured identities yields skipped. A failed RSET stops a reused envelope session.

  Assertion pins after the scripted accepted-relay session: `self::assertSame(Outcome::Warning, $relayFinding->outcome); self::assertNotContains('DATA', $commands); self::assertContains('RSET', $commands);` Record command verbs separately from their arguments for these assertions.
- [ ] **Step 2: Run `php artisan test --filter='ReplyReaderTest|SmtpProbeTest|SmtpSessionTest'`.** Expected: absent protocol behavior failures with fake sessions or `SmtpSocketFixture`, a testing-only child process serving the selected dialogue on an ephemeral loopback port. No production public request controls its address/port; test dependency injection owns the substitution.
- [ ] **Step 3: Implement sessions and probes.** Validate/pin literal addresses with Task 3's guard, bracket IPv6 socket addresses, retain MX hostname, apply remaining-budget timeouts, and parse bounded replies incrementally. Session command allowlist permits EHLO/HELO/STARTTLS/MAIL/RCPT/RSET/QUIT only. Never send DATA. Redact operator mailbox local parts. Close sockets in `finally`; dependent findings explain the earliest failed prerequisite. Initial TLS checks are not fabricated; actual negotiation is added in Task 8.
- [ ] **Step 4: Verify focused tests.** Expected: protocol cases pass, transcripts are bounded/redacted, and private targets never reach the connection factory. Generic timeout findings describe the scanner's vantage point.
- [ ] **Step 5: Commit** `feat: probe SMTP greetings and envelope policy`.

### Task 8: STARTTLS negotiation, protocol probes, and certificate validation

**Files:** Create `app/Mail/Value/TlsObservation.php`, `app/Mail/Smtp/{TlsNegotiator,LegacyTlsProbe}.php`, `app/Mail/Checks/{TlsSecurityCheck,CertificateCheck}.php`, `app/Mail/Certificates/OpenSslVerifier.php`, `tests/Support/CertificateFixtures.php`, `tests/Fixtures/certificates/manifest.json`; modify `app/Mail/Smtp/{SmtpSession,StreamSmtpSession}.php`, `app/Mail/Checks/SmtpProbe.php`; test `tests/Unit/{TlsSecurityCheckTest,CertificateCheckTest}.php`, `tests/Integration/StartTlsTest.php`.

**Interfaces:** `SmtpSession::startTls(string $mx, ?int $protocolMask = null): TlsObservation`; observation contains success/error, negotiated protocol/cipher/bits, client offer capability, and available PEM chain. `CertificateCheck::analyze(EndpointTarget $target, array $chainPem, ScanBudget $budget): Finding`; `TlsSecurityCheck::analyze(TlsObservation $negotiated, array $legacyAttempts): Finding`; `OpenSslVerifier::verifyPkix(array $chainPem, string $hostname, ScanBudget $budget): array`; `LegacyTlsProbe::probe(EndpointTarget $target, ScanBudget $budget): array` returns version-specific attempts. `CertificateFixtures::chain(string $scenario): array<string>` supplies generated PEM chains. `ProbeResult` now captures chain and TLS metadata and keys `tls`, `secure`, `cert`.

- [ ] **Step 1: Write tests.** STARTTLS advertisement without successful handshake cannot pass. Assert literal-IP connection uses MX SNI, EHLO runs again after TLS, and expired/wrong-name/self-signed certificates are reported independently of successful encryption. Test RSA 1024/2048 and EC key thresholds, 29-day expiry warning, trusted chain, missing intermediate, and wildcard SANs. Legacy version accepted yields warning; local inability to offer TLS 1.0/1.1 yields inconclusive rather than server rejection. Capture allowed TLS 1.2/1.3 negotiation and flagged cipher properties without claiming enumeration.

  Assertion pin for `test_expired_certificate_fails_pkix`: `$finding = app(CertificateCheck::class)->analyze($target, CertificateFixtures::chain('expired'), $budget); self::assertSame(Outcome::Fail, $finding->outcome);`
- [ ] **Step 2: Run `php artisan test --filter='TlsSecurityCheckTest|CertificateCheckTest|StartTlsTest'`.** Expected: missing TLS/certificate behavior failures; certificate fixture helper generates the necessary local CA/leaf chains with argument-array OpenSSL commands.
- [ ] **Step 3: Implement negotiation and checks.** Capture untrusted chains for diagnostics, then verify PKIX offline against the configured CA bundle/hostname. Allocate separate sessions for legacy-version tests, all sharing the endpoint's 30-second budget. Record local crypto-policy restrictions separately from remote responses. Do not resume envelope commands on a failed TLS session; dependent checks become skipped. Verify through Symfony Process argument arrays and private temporary files removed in `finally`.
- [ ] **Step 4: Verify focused tests.** Expected: passing fixture handshakes/validation and no hostname substitution, shell interpolation, certificate-validation bypass claim, or DATA transmission.
- [ ] **Step 5: Commit** `feat: inspect SMTP TLS and certificate identity`.

### Task 9: MTA-STS, SMTP DANE, and endpoint policy assessment

**Files:** Create `app/Mail/Network/{PinnedHttpsClient,HttpsResult}.php`, `app/Mail/Value/PolicySnapshot.php`, `app/Mail/Checks/{TransportPolicies,MtaStsCheck,DaneCheck,EndpointInspector}.php`, `app/Mail/Certificates/CertificateAssociation.php`, `tests/Fixtures/policies/{mta-sts,tlsa}.json`; modify `app/Mail/Certificates/OpenSslVerifier.php`; test `tests/Unit/{MtaStsCheckTest,DaneCheckTest,EndpointInspectorTest}.php`, `tests/Integration/PinnedHttpsClientTest.php`.

**Interfaces:** `PinnedHttpsClient::get(string $hostname, string $path, ScanBudget $budget): HttpsResult` only supports port 443 and verified TLS with checked/pinned public targets. `TransportPolicies::discover(ScanInput $input, RoutingSnapshot $routing, ScanBudget $budget): PolicySnapshot`; `MtaStsCheck::evaluate(EndpointTarget $target, ProbeResult $probe, ?array $policy): Finding`; `DaneCheck::evaluate(EndpointTarget $target, ProbeResult $probe, DnsResult $tlsa, ScanBudget $budget): Finding`; `OpenSslVerifier::verifyDaneAnchor(array $chainPem, string $anchorPem, array $referenceNames, ScanBudget $budget): array`; `EndpointInspector::inspect(EndpointTarget $target, PolicySnapshot $policies, ScanBudget $budget): array<Finding>` combines SMTP, certificate, FCRDNS, and policy findings.

- [ ] **Step 1: Write tests.** MTA-STS cases cover missing/multiple advertisements, valid policy, duplicate required fields, mode none/testing/enforce, wildcard label matching, missing MX coverage, HTTPS verification failure, redirect rejection, oversize policy, and rebinding toward a private IP. DANE data providers cover usages 2/3, certificate/SPKI selectors, exact/SHA-256/SHA-512 associations, unsupported usages/malformed hashes, insecure discovery, authenticated no-record responses, and multiple alternative records. Self-signed usage 3 can pass despite PKIX failure; usage 2 hash match without a valid anchored chain cannot pass. EndpointInspector always retains independent policy outcomes.

  Assertion pin for `test_dane_ee_self_signed_is_independent_of_pkix`: `self::assertSame(Outcome::Pass, app(DaneCheck::class)->evaluate($target, $probe, $secureMatchingUsage3, $budget)->outcome); self::assertSame(Outcome::Fail, $pkixFinding->outcome);`
- [ ] **Step 2: Run `php artisan test --filter='MtaStsCheckTest|DaneCheckTest|EndpointInspectorTest|PinnedHttpsClientTest'`.** Expected: missing evaluation/pinning behavior failures.
- [ ] **Step 3: Implement bounded policy discovery/assessment.** Use cURL checked address pinning, original hostname for HTTPS certificate validation, no redirects, 64 KiB policy maximum, and budgeted requests. Validate MTA-STS policy with its exact matching/mode semantics and include TLSRPT record evidence. Match DER certificate/SPKI associations and perform the required DANE usage-specific validation using trusted discovery/reference names, not merely fingerprint equality. Retain unsupported trust-anchor availability as a limitation. An unusable TLSA set differs from a secure usable set that mismatches.
- [ ] **Step 4: Verify focused tests.** Expected: independent verified policy results, blocked private redirection/rebinding, and no borrowed PKIX verdict for DANE usage 3.
- [ ] **Step 5: Commit** `feat: validate MTA-STS and SMTP DANE policies`.

### Task 10: Idempotent queued scans, outbox recovery, and watchdog

**Files:** Create `app/Mail/Scans/{ScanCoordinator,OutboxDispatcher,ScanFinalizer,ScanWatchdog,ScanCleaner}.php`, `app/Jobs/{DiscoverScan,ProbeEndpoint}.php`, `app/Console/Commands/{DispatchScanOutbox,ReconcileScans,PruneScans,DiagnoseScanner}.php`; modify `app/Mail/Scans/{SubmitScan,ScanRepository}.php`, `routes/console.php`, `config/queue.php`, `config/mail_scan.php`; test `tests/Feature/{ScanLifecycleTest,ScanRecoveryTest,ScanRetentionTest}.php` and `tests/Integration/ConcurrentScanTest.php`.

**Interfaces:** `ScanCoordinator::discover(Scan $scan): void`; `OutboxDispatcher::dispatchPending(): int`; `ScanFinalizer::finalize(Scan $scan): void`; `ScanWatchdog::reconcile(): int`; `ScanCleaner::prune(): int`. Repository additionally exposes `planEndpoints(Scan $scan, RoutingSnapshot $routing, PolicySnapshot $policies): void`, `claimEndpoint(ScanEndpoint $endpoint): ?string`, and `completeEndpoint(ScanEndpoint $endpoint, string $leaseToken, array $findings): void`. Completion is fenced by lease token and terminal scan state. CLI commands are `scans:dispatch`, `scans:reconcile`, `scans:prune`, and `scanner:diagnose`.

- [ ] **Step 1: Write tests.** Given fake DNS/SMTP dependencies, dispatch discovery and endpoint jobs, assert persisted per-IP findings, terminal status, and exact planned/completed counts. Inject crash after enqueue before outbox acknowledgement and assert duplicate delivery is harmless. Kill an endpoint lease and reconcile past the deadline: useful report becomes partial; empty report fails. `test_late_endpoint_job_cannot_reopen_finalized_scan` asserts no result overwrite or state regression. Competing finalizers produce one terminal result. Null MX and no usable addresses both finalize without waiting for nonexistent endpoints. Cleanup deletes scans older than seven days with cascading rows.

  Assertion pins after late completion: `self::assertSame(ScanStatus::Partial, $scan->fresh()->status); self::assertSame($originalFindingCount, $scan->findings()->count());` Construct a finalized partial scan and a stale lease before invoking `completeEndpoint`.
- [ ] **Step 2: Run `php artisan test --filter='ScanLifecycleTest|ScanRecoveryTest|ScanRetentionTest|ConcurrentScanTest'`.** Use PostgreSQL/Redis integration services for races, not SQLite as evidence of production atomicity. Expected: absent lifecycle/recovery behavior failures.
- [ ] **Step 3: Implement lifecycle.** Persist discovery/policy snapshots and all endpoints/outbox rows transactionally before enqueue. Attempt dispatch after transaction commit; scheduler retries unsent rows. Dispatch before acknowledgement, accepting at-least-once delivery. Use owner-token locks and database row locks for claims/finalization; stale job completion cannot write findings. Endpoint job timeout is 45 seconds with a 30-second internal budget, coordinator timeout 240 seconds, queue retry-after 300 seconds, two attempts for infrastructure failures, and bounded backoff. Jobs stop when the scan deadline expires. Reconcile every minute and prune daily. Diagnostic CLI distinguishes DNS validation, socket families, configured identities, and optional operator-provided public connectivity targets; it never treats a created IPv6 interface as verified egress.
- [ ] **Step 4: Verify focused tests and `php artisan schedule:list`.** Expected: race/recovery tests pass, dispatcher/watchdog appear each minute, cleanup daily, and no recurring domain scans or email notifications.
- [ ] **Step 5: Commit** `feat: run and recover queued mail scans`.

### Task 11: Report assessment, Inertia UI, and JSON export

**Files:** Create `app/Mail/Scans/ReportAssessment.php`, `app/Http/Resources/ScanReportResource.php`, `app/Http/Controllers/ScanExportController.php`, `resources/js/types/report.ts`, `resources/js/Components/{StatusBadge,SummaryCards,FindingCard,EndpointMatrix,ScanProgress}.vue`, `resources/js/Composables/useScanPolling.ts`; modify `app/Http/Controllers/ScanController.php`, `routes/web.php`, `resources/js/Pages/{Home,Scans/Show}.vue`, `resources/js/Layouts/AppLayout.vue`, `resources/css/app.css`; test `tests/Feature/{ScanReportTest,ScanExportTest}.php`, `tests/Unit/ReportAssessmentTest.php`, `resources/js/Composables/useScanPolling.test.ts`, `resources/js/Pages/Scans/Show.test.ts`.

**Interfaces:** `ReportAssessment::assess(Scan $scan): array` returns overall label, reachability/transport/sender-authentication summaries, and independent coverage; `ScanReportResource::toArray(Request $request): array` serializes the shared report shape. Inertia page prop is `report`; partial reload requests only `report`. `GET /scans/{publicId}/export` named `scans.export` serves downloadable JSON. `useScanPolling(status: Ref<ScanStatus>): void` starts/stops Inertia polling at 2000 ms and cleans up on unmount.

- [ ] **Step 1: Write tests.** Failure on backup IPv6 remains visible even with a good primary IPv4 endpoint. Assert assessment precedence, valid null MX, optional-selector skips, no authenticated transport medium warning, and material incomplete coverage. Public IDs retrieve reports; internal IDs and expired reports do not. JSON equals the displayed resource and excludes secrets. Test hostile SMTP/record text remains text and cannot inject elements. Frontend tests advance fake timers: active report reloads only `report`, terminal report stops, and unmount leaves no timer. Preference-change/theme QA is pinned in Task 13 because actual CSS/media behavior needs a browser.

  Export assertion pin: `$this->get('/scans/'.$scan->public_id.'/export')->assertOk()->assertJsonPath('scan.domain', 'fixture.test')->assertJsonMissingPath('scan.id');` Polling assertion pin after terminal props/unmount: `expect(vi.getTimerCount()).toBe(0);`
- [ ] **Step 2: Run `php artisan test --filter='ScanReportTest|ScanExportTest|ReportAssessmentTest'` and `npm run test:unit -- --run`.** Expected: missing report/export and polling behavior failures.
- [ ] **Step 3: Implement assessment/resource/UI.** Render all spec check labels, group DNS findings and MX endpoints, order priorities/severities, and preserve causal skipped findings. Use scope-specific coverage rather than an opaque numeric score. Vue text interpolation prevents HTML execution; bounded transcript display preserves formatting. Build a responsive endpoint comparison with per-family badges, expandable details, clear remediation, timestamps, and export link. Make failure/partial/null-MX reports readable. Preserve focus and use a polite aria-live progress message. Use Inertia forms and validation errors on the homepage, and polling with manual start/stop and request overlap protection.
- [ ] **Step 4: Verify tests, `npm run typecheck`, and `npm run build`.** Expected: successful assertions/build; no application page is implemented with Blade or Alpine.js. Root document shell only mounts Inertia. Theme styles apply to all cards, inputs, tables, details, transcripts, badges, and focus states in both palettes.
- [ ] **Step 5: Commit** `feat: present diagnostic reports with Inertia`.

### Task 12: Reproducible Compose deployment and operational documentation

**Files:** Create `Dockerfile`, `.dockerignore`, `compose.yaml`, `compose.dev.yaml`, `compose.ipv6.yaml`, `compose.hosted.yaml`, `docker/php/{entrypoint.sh,healthcheck.php}`, `docker/nginx/{Dockerfile,default.conf}`, `scripts/setup-env.php`, `scripts/compose-smoke.sh`, `README.md`, `docs/deployment.md`; modify `compose.test.yaml`, `.env.example`, `config/{database,cache,queue,logging}.php`, resolver image/config from Task 4; test `tests/Feature/ProductionConfigurationTest.php` and `tests/Integration/ComposeLifecycleTest.php`.

**Interfaces:** Base Compose service names are `web`, `app`, `migrate`, `worker`, `scheduler`, `postgres`, `redis`, `unbound`. Run `php scripts/setup-env.php` for a stable generated application key and deployment secrets; it refuses to overwrite existing secrets. Standard startup is `docker compose up --build -d`; worker scaling is `docker compose up -d --scale worker=4`. Hosted/IPv6 options are explicit documented `-f` overrides. `/up` is local app liveness; service health adds database/Redis checks without public secret exposure.

- [ ] **Step 1: Write deployment acceptance checks.** In an isolated test Compose project, force a migration failure and assert app/worker/scheduler do not start; successful migrations allow them. Restart services and assert a saved report persists and APP_KEY remains unchanged. Production configuration asserts debug off, logs to stderr, no external DB/Redis/DNS ports, and loopback HTTP binding by default. Confirm no shared container names prevent worker scaling and no web/app health command depends on a service that waits on it.

  Assertion pin from `docker compose config --format json`: `self::assertSame('127.0.0.1', $compose['services']['web']['ports'][0]['host_ip']); self::assertArrayNotHasKey('ports', $compose['services']['postgres']);` Run lifecycle mutations only under a unique testing project with disposable volumes.
- [ ] **Step 2: Run `php artisan test --filter='ProductionConfigurationTest|ComposeLifecycleTest'`.** Expected: missing deployment files/behavior, after Docker prerequisites are available. Docker unavailability is a verification limitation, not a passing test or a reason to weaken the deployment.
- [ ] **Step 3: Implement images/config.** Use multi-stage PHP 8.4/Node 24 builds with required intl/sockets/pgsql/Redis/cURL/OpenSSL functionality and production lockfiles. Nginx image gets compiled public assets; PHP image gets the same manifest. Use service health and `service_completed_successfully` migration dependencies. Add graceful shutdown, persistent volumes, resolver trust-anchor persistence, non-root runtime permissions, and required-secret checks. No package installation or automatic APP_KEY regeneration occurs at container startup. Queue worker CLI allows coordinator timeout 240 seconds and honors job-specific endpoint timeout; stop grace period exceeds it. IPv6 override enables the worker's dual-stack egress network without exposing infrastructure services.
- [ ] **Step 4: Document operations.** README links setup/start/test commands and report limitations. Deployment doc covers outbound 25/DNS/HTTPS, real host IPv6 routing, Docker Desktop limitations, scanner identities, reverse proxy and trusted proxies, migrations/backups/upgrades, scaling/restarting workers, watchdog/retention, logs/diagnosis, and relay probe identities. Provide hosted HTTP binding override, not inbound SMTP exposure. Never print generated secrets in scripts/logs.
- [ ] **Step 5: Verify.** Run `docker compose config --quiet` for base and each override, `docker compose build`, `scripts/compose-smoke.sh`, and deployment integration tests. Expected: validated definitions, built images, successful startup/migration/queue lifecycle, stable persistence, and documented tested limitations.
- [ ] **Step 6: Commit** `feat: package mail tester for Docker Compose`.

### Task 13: Full fixture validation and browser acceptance

**Files:** Create `tests/Fixtures/smtp/{server.php,Dockerfile}`, `tests/Fixtures/dns/{Dockerfile,named.conf,zones/fixture.test.zone,sign-zone.sh}`, `tests/Integration/{FullScanTest,DualStackScanTest,ValidatingResolverTest}.php`, `tests/Browser/acceptance-checklist.md`; extend `compose.test.yaml` and certificate fixtures; add verification notes to `README.md`/`docs/deployment.md` only when they describe actual observed behavior.

**Interfaces:** SMTP fixture behavior is selected through testing-only configuration; it binds separate IPv4/IPv6 listeners and logs commands. DNS fixture serves controlled records plus signed/unsigned/invalid-signature variants. Fixture resolver configuration trusts only the generated test zone anchor; production root validation is unchanged. Integration CLI/queue container runs with Laravel testing environment and injected fixture-address guard; public deployment cannot switch to fixture scanning via a request.

- [ ] **Step 1: Write end-to-end assertions.** `test_full_scan_persists_real_fixture_observations` submits a scan through the app, processes coordinator/endpoint jobs, and asserts all requested checks are represented with observed evidence. `test_ipv4_and_ipv6_are_connected_independently` makes IPv4/IPv6 return different greeting/TLS outcomes and asserts separate report rows/correct family identity. `test_validating_resolver_rejects_bogus_zone` verifies secure, unsigned, and invalid-signature answers through real Unbound. Fixture command logs must contain no DATA/body. Add report polling-to-terminal and export equality assertions.

  Dual-stack assertion pins: `self::assertSame([4, 6], $scan->endpoints()->orderBy('family')->pluck('family')->all()); self::assertNotContains('DATA', $fixtureCommandVerbs);` Use one MX with exactly one address per family in this scenario.
- [ ] **Step 2: Prepare the fixture harness, then run focused integration tests against the test Compose project.** Signed fixtures are cryptographically signed locally; canned AD flags alone do not prove resolver validation. Only a product behavior mismatch is a regression failure; missing fixture tools are setup errors to resolve first. Previously implemented behavior may already pass these integration assertions, in which case no artificial red phase or unnecessary production edit is needed. If host IPv6 is unavailable, label the dual-stack integration case unavailable and retain unit evidence without claiming a passed live IPv6 connection.
- [ ] **Step 3: Implement fixtures and close observed gaps.** Keep fixture services isolated with no production-enabled private-target bypass. Add deterministic server behavior and generated certificates/zones sufficient to distinguish protocol, trust, DNS, and recovery outcomes. Production changes are limited to reproducible failing cases from this plan.
- [ ] **Step 4: Run final automated checks.** `php artisan test`, `npm run test:unit -- --run`, `npm run typecheck`, `npm run build`, `docker compose config --quiet`, `docker compose build`, and the isolated Compose integration suite. Expected: all applicable checks pass, with actual unavailable cases explicitly recorded. Do not repeatedly broaden tests after success without a new failure/concern.
- [ ] **Step 5: Browser review.** Use the available browser control to inspect homepage, form errors, queued/running/completed/failed/null-MX reports, and expanded/export interactions at mobile/desktop widths. Switch browser preference from light to dark and back while a report is open; check immediate theme change, native controls, contrast, focus, and terminal polling cleanup. Confirm no content flash caused by a saved theme or delayed hydration. If browser preference emulation is unavailable, report that specific QA limitation and inspect available themes using supported tooling; do not claim an unperformed check.
- [ ] **Step 6: Review and finish.** Use the execution method's required review workflow, address actionable findings with regression tests, re-run only affected checks plus required final checks, and record concrete validation/limitations. Commit `test: verify complete dual-stack mail scans`. Integrate the finished branch only through the user's selected finishing option; no deployment to an external host is inferred.

## Requirement coverage and self-review

| Approved specification area | Owning tasks |
| --- | --- |
| Laravel/Inertia, browser-preference light/dark mode, responsive/accessibility behavior | 1, 11, 13 |
| Public report links, retention, submission limits, exports | 2, 10, 11 |
| DNSSEC, MX discovery, implicit/null MX, all A/AAAA, FCRDNS | 4, 13 |
| CAA and SPF analysis | 5 |
| Selector-based DKIM and current DMARC discovery/reporting policy | 6 |
| Connect, Answer, HELO, From, non-delivering relay probe | 7, 13 |
| TLS, Secure, Cert, client limitations and independent PKIX results | 8, 13 |
| MTA-STS, DANE, TLSRPT supporting evidence | 9, 13 |
| Resource bounds, public target validation, pinning, concurrency/deadlines | 3, 4, 7, 9, 10 |
| Outbox/retries, stale-worker recovery, partial report finalization | 2, 10 |
| Finding severity, summary precedence, evidence and coverage | 11 |
| Compose images, migration order, workers, scheduler, resolver, secrets and operational docs | 4, 12, 13 |

Self-review completed against the approved spec: every check has an owning task; shared names/signatures and lifecycle states are consistent; fixture substitutes are testing-only; Review Focus cases are assigned explicit tests; theme QA tests actual browser preference behavior; no implementation step depends on an undefined product service. Bootstrap is explicitly separated from the first behavioral red/green cycle. The plan has been kept to decisions, interfaces, assertions, and checks rather than implementation bodies.

## Execution handoff

The written specification is approved; this implementation plan awaits user review and execution-method selection. Recommend native execution in this chat because the scanner, jobs, and report share many contracts and can be implemented sequentially without repeated agent contexts. Subagent-driven execution remains available if the user prefers task-by-task independent implementation/review. Neither method starts before the plan review gate is satisfied.
