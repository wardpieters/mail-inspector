# Mail Inspector verification — 2026-10-11

Implementation branch: `codex/mail-tester-inertia`; original base: `45b7269`.
All thirteen approved plan tasks are complete. A fresh-context review of
`45b7269..1e742e9` found no Critical issues, nine Important issues and two
Minor issues. All nine Important findings were reproduced with failing
regressions, fixed, and verified in one pass. None were rejected.

## Verification evidence

| Check | Result |
| --- | --- |
| Host PHP 8.5 suite | 73 tests, 234 assertions passed |
| Production PHP 8.4 suite | 72 passed, one host-only configuration test skipped; 214 assertions |
| Actual DNS/SMTP/queue/database fixtures | 5 tests, 54 assertions passed |
| Production Compose migration gating and restart persistence | 2 tests, 10 assertions passed |
| Vue polling tests | 2 passed |
| TypeScript, Vite production build, Pint, diff whitespace | Passed |
| Rebuilt production Compose application | Healthy; migration service exited successfully |
| Operator scanner diagnostic | Validating Unbound root answer secure |

The host-only configuration test runs in the passing host suite. Fixtures
exercise real IPv4 and IPv6 sockets, STARTTLS and observed MX SNI,
signed/unsigned/bogus DNS responses through Unbound, Redis concurrency
permits, and concurrent PostgreSQL endpoint claims. Lifecycle tests prove
that failed migrations prevent application/worker/scheduler startup and
that reports and application keys persist after restarts.

Browser acceptance covers compiled assets, validation, report lifecycle
states, polling, evidence, JSON export, desktop and 390px mobile layouts.
See [the checklist](../../../tests/Browser/acceptance-checklist.md).
External IPv6 and hosted outbound port 25 remain deployment checks. The
local environment has no external IPv6 support; controlled Docker IPv6
loopback tests pass. Live browser light/dark preference switching could
not be emulated by the available browser API; CSS uses browser preference.

The standard repository Compose project is running locally at
http://127.0.0.1:8080, with generated missing credentials, the existing
application key preserved, and persistent volumes. Disposable acceptance
and fixture projects were cleaned up.

## Rulings I made

1. Used the feature branch in the writable main checkout after managed
   worktree writes were blocked by the sandbox. Cost if wrong: isolation
   is by branch rather than a separate checkout. The unused managed
   worktree was archived through the app.
2. Followed [RFC 8461 section 3.2](https://www.rfc-editor.org/rfc/rfc8461#section-3.2):
   the first duplicate MTA-STS field wins, with duplicate warnings. The
   plan's expected failure was corrected. Cost if wrong: duplicate policy
   interpretation changes the verdict.
3. Combined full-scan and dual-stack assertions in one integration scenario
   instead of duplicating a FullScanTest harness. It checks persisted
   coverage and separate address-family observations. Cost if wrong:
   independent full-scan fixture coverage may need expansion.

## Deferred minors

- Low-severity warnings do not change the summary label to “Improvements recommended”.
- TLSRPT evidence does not validate version, multiplicity or destination syntax.

## Execution ledger

The following chronology preserves task commits, test evidence, resolved
limitations and all final review decisions before temporary workspace cleanup.

# SDD ledger — plan: docs/superpowers/plans/2026-10-11-mail-server-tester.md
Execution: native; approved recommended method on 2026-10-11. Base: 45b7269.
Ruling: managed worktree blocked by sandbox — use feature branch codex/mail-tester-inertia in writable checkout per skill fallback — cost if wrong: checkout includes implementation changes.
Verification limitation: Docker daemon unavailable; Compose runtime checks remain pending until daemon is available.
Pre-flight: 1→2 Inertia routes/components match; 2→10 persistence/outbox state names match; 3→4/7/9 budget and target contracts match; 4→5/6/9 DNS shapes match; 7→8 session interface extends startTls; 8→9 probe chain/PKIX remain independent; 9→10 policy snapshots persist; 10→11 report aggregation must not overwrite lifecycle; 11→12 Vite manifests ship in both images; 12→13 test networks remain separate.
Task checklist: 1–13 pending.
Task 1: complete (commits 45b7269..5232b8c, tests: php artisan test → {"tool":"phpunit","result":"passed","tests":9,"passed":9,"assertions":36,"duration_ms":282})
Task 2: complete (commits 45b7269..5232b8c, tests: php artisan test → {"tool":"phpunit","result":"passed","tests":9,"passed":9,"assertions":36,"duration_ms":335})
Task 3: complete (commits 5232b8c..1cbe023, tests: vendor/bin/phpunit '--filter=TargetGuardTest|ScanBudgetTest' → {"tool":"phpunit","result":"passed","tests":3,"passed":3,"assertions":12,"duration_ms":133})
Task 4: complete (commits 1cbe023..0c409de, tests: vendor/bin/phpunit '--filter=MxDiscoveryTest|DnssecDiagnosticsTest|NetDnsClientTest|ReverseDnsTest' → {"tool":"phpunit","result":"passed","tests":6,"passed":6,"assertions":17,"duration_ms":189})
Task 5: complete (commits 0c409de..1067abc, tests: vendor/bin/phpunit '--filter=CaaCheckTest|SpfCheckTest' → {"tool":"phpunit","result":"passed","tests":5,"passed":5,"assertions":13,"duration_ms":1074})
Task 6: complete (commits 1067abc..9a48f2f, tests: vendor/bin/phpunit '--filter=DkimCheckTest|DmarcCheckTest' → {"tool":"phpunit","result":"passed","tests":6,"passed":6,"assertions":10,"duration_ms":235})
Task 7: complete (commits 9a48f2f..143eb1a, tests: vendor/bin/phpunit '--filter=ReplyReaderTest|SmtpProbeTest' → {"tool":"phpunit","result":"passed","tests":4,"passed":4,"assertions":9,"duration_ms":240})
Task 8: complete (commits 143eb1a..6d88a5e, tests: vendor/bin/phpunit '--filter=TlsSecurityCheckTest|CertificateCheckTest|SmtpProbeTest' → {"tool":"phpunit","result":"passed","tests":7,"passed":7,"assertions":17,"duration_ms":709})

Task 9 ruling: RFC 8461 §3.2 requires first duplicate field wins; warn for duplicates without invalidating otherwise valid policy. Plan test expectation corrected against the primary standard. Redis integration now verified on cached services; Docker PHP image build blocked by registry metadata timeout.
Task 9: complete (commits 6d88a5e..a8325f2, tests: vendor/bin/phpunit '--filter=MtaStsCheckTest|DaneCheckTest|PinnedHttpsClientTest' → {"tool":"phpunit","result":"passed","tests":6,"passed":6,"assertions":16,"duration_ms":374})
Task 10: complete (commits a8325f2..ee88888, tests: vendor/bin/phpunit '--filter=ScanSubmissionTest|ScanLifecycleTest|ScanRecoveryTest|ScanRetentionTest' → {"tool":"phpunit","result":"passed","tests":11,"passed":11,"assertions":42,"duration_ms":391})
Task 11: complete (commits ee88888..67b3976, tests: vendor/bin/phpunit '--filter=ScanReportTest|ScanExportTest' → {"tool":"phpunit","result":"passed","tests":3,"passed":3,"assertions":21,"duration_ms":542})
Task 12: complete (commits 67b3976..09f9c6b, tests: vendor/bin/phpunit --filter=ProductionConfigurationTest → {"tool":"phpunit","result":"passed","tests":2,"passed":2,"assertions":18,"duration_ms":667})
Task 13: integration evidence: real SMTP/SNI plus dual-stack pipeline, signed/unsigned/bogus validating resolver, Redis permits and concurrent Postgres claim → 5 tests/54 assertions passing. Compose migration failure gating and persistence/key restart → 2 tests/10 assertions passing. Earlier Docker-unavailable limitation resolved using a separate public-image client config; user Docker credentials unchanged.
Task 13: acceptance evidence: compiled production assets; validation regression fixed; desktop/mobile, queued/running/completed/partial/failed/null-MX, polling transition, expanded evidence and browser JSON download inspected. Preference emulation unavailable; live light/dark switching explicitly remains manual QA. User confirms local external IPv6 unavailable; controlled Docker loopback IPv6 passed, hosted external routing remains unverified.
Task 13: Ruling: combine full-scan and dual-stack assertions in one integration scenario instead of duplicate FullScanTest harness — same persisted all-check coverage plus distinct address-family observations — cost if wrong: independent full-scan fixture coverage may need expansion.
Task 13: complete (commits 09f9c6b..1e742e9, tests: php artisan test → {"tool":"phpunit","result":"passed","tests":57,"passed":57,"assertions":190,"duration_ms":5647})
Final review: fresh gpt-6-astra reviewer, range 45b7269..1e742e9; no Critical, nine Important accepted for one TDD fix pass; declined-to-judge list empty.
Final: minor (deferred): low-severity warnings do not currently change the summary label to Improvements recommended.
Final: minor (deferred): TLSRPT evidence does not yet validate version, multiplicity, or destination syntax.
Final: fixed SPF broad address ranges and pass-all dependencies — SpfAuthorizationTest RED→GREEN, suite 73/73 (234 assertions).
Final: fixed dimension passes without complete endpoint observations — ReportCompletenessTest::test_pending_or_failed_endpoints_cannot_receive_dimension_passes and test_policy_information_without_transport_observations_cannot_pass RED→GREEN, suite 73/73 (234 assertions).
Final: fixed null MX masking DNS failures and incompleteness — ReportCompletenessTest::test_null_mx_preserves_dns_failures_and_dns_incompleteness RED→GREEN, suite 73/73 (234 assertions).
Final: fixed optional TLSA/TLSRPT/PTR NXDOMAIN classified as resolver failure — PolicyNegativeAnswerTest RED→GREEN, suite 73/73 (234 assertions).
Final: fixed local network unreachable classified as remote failure — SmtpScopeTest::test_local_network_unreachable_is_a_scanner_limitation RED→GREEN, suite 73/73 (234 assertions).
Final: fixed relay probe identities local to the scanned domain — SmtpScopeTest::test_local_envelope_domains_are_not_tested_as_external_relay RED→GREEN, suite 73/73 (234 assertions).
Final: fixed malformed recognized CAA properties receiving a pass — CaaSyntaxTest RED→GREEN, suite 73/73 (234 assertions).
Final: fixed MX/address resolver errors reported as definitive configuration failures — RoutingFailureTest RED→GREEN, suite 73/73 (234 assertions).
Final: fixed stale discovery updates reopening or overwriting terminal reports — ScanFencingTest RED→GREEN, suite 73/73 (234 assertions).
Final verification: PHP 8.4 container suite 72 passed/1 host-only skip (214 assertions); real fixture integration 5/5 (54 assertions); production Compose lifecycle 2/2 (10 assertions); frontend 2/2, TypeScript and production Vite build passed; Pint and diff whitespace passed; rebuilt production application healthy; scanner diagnostic root DNS secure.
Task checklist: 1–13 complete; accepted Important review findings fixed; two Minor findings deferred; no rejected or declined-to-judge findings.
Final housekeeping: full-tree Pint exposed formatting in older task commits; applied Pint, full suite 73/73 (234 assertions), Pint passed, diff whitespace clean. Formatting only; no additional behavioral fix pass.
Final runtime: normal repository mail-inspector Compose project started with preserved existing APP_KEY and generated missing database/Redis secrets; migrations exited 0, application dependencies healthy, worker/scheduler running. Disposable acceptance and fixture projects removed. Browser confirmed compiled homepage at http://127.0.0.1:8080. External hosting was not deployed.
