# Message Header Analyzer Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a browser-only message header analyzer to Mail Inspector with delivery timing, reported authentication, multi-provider antispam evidence, and local export.

**Architecture:** A named Laravel GET route serves an Inertia Vue page. Pure TypeScript modules parse and analyze headers; a page-scoped composable owns temporary state and browser export interactions. Results use existing presentation and translation conventions, without involving the domain scanner.

**Tech Stack:** PHP 8.5, Laravel 13, Inertia 3, Vue 3, TypeScript, Tailwind 4, Vitest/Vue Test Utils, PHPUnit 12. Follow installed and locked versions rather than assuming newer APIs.

**Spec:** `docs/superpowers/specs/2026-10-22-message-header-analyzer-design.md` (approved 23 October 2026).

## Global Constraints

- “Analyze entirely in the browser, with no stored headers or shareable reports.”
- “No additional dependencies are planned.”
- “Do not add a new top-level source folder.”
- “Use original product copy and design. Do not name or link a comparison tool in the product or these feature documents.”
- “Set an explicit input limit of 1 MiB measured as UTF-8 bytes, 10,000 physical lines, and 2,000 parsed fields.”
- “Reject an input exceeding any limit with a translated error; do not truncate silently.”
- “Never decode structured protocol fields before parsing them.”
- “Scores belong to their originating systems: do not normalize, compare, sum, or apply guessed thresholds.”
- “Provider attribution is indicative, not authenticated.”
- “Do not select a trusted reporting server automatically, merge conflicting sources into one authoritative verdict, calculate a message safety score, or claim independent SPF/DKIM/DMARC/ARC verification.”
- “Exports contain only the header section, never the discarded message body, and use a fixed filename rather than incorporating message content.”
- English and Dutch labels/explanations; unchanged raw evidence; mobile, keyboard, accessible announcements, and browser-selected light/dark themes.

## Review Focus

These easily overlooked cases have explicit owning tests below:

1. A valid header block followed by a body containing header-like lines must never expose that body in results or exports (Tasks 1, 5).
2. Two gateways reporting different scores or verdicts must remain separate, including Rspamd emitting SpamAssassin-compatible fields (Task 4).
3. A missing intermediate hop date must not become an apparent measured delay across two hops (Task 2).
4. Navigation, persisted pageshow, and a late clipboard promise must not restore or reveal stale sensitive state (Task 5 plus Task 6 browser acceptance).
5. Quoted delimiters, nested options, malformed encodings, and multibyte limit boundaries must preserve evidence without inventing a successful interpretation (Tasks 1, 3, 4).

---

## File map and shared conventions

All paths below are relative to the repository root `/Users/ward/Git/mail-tester`. Pure analysis files are colocated in `resources/js/Pages/Headers/analysis/`, UI in `resources/js/Components/Headers/`, shared contracts in `resources/js/types/headers.ts`, and state in the existing Composables directory. These are subdirectories of established source folders.

Each task lists its owned files; colocated `.test.ts` files are discovered by the existing Vitest configuration. Use synthetic literal fixtures inline unless repeated across tests; repeated fixtures belong in `analysis/fixtures.test-support.ts`. Keep the user-facing example in `analysis/example.ts`, separate from test-only data.

No module-scoped mutable message state. Pure analyzers return codes and parameters for translation, not locale-specific strings. Use zero-based stable field IDs in source order and one-based inclusive source line numbers. Null means unavailable, never an implied zero or successful outcome.

### Shared result contracts (Task 1 owns the complete declarations)

```ts
type HeaderIssue = { code: string; fieldIds: number[]; params: Record<string, string | number> };
type HeaderField = {
    id: number; name: string; lookupName: string; raw: string;
    value: string; displayValue: string; startLine: number; endLine: number;
};
type HeaderDocument = {
    rawHeaders: string; fields: HeaderField[];
    malformedLines: { line: number; raw: string }[]; warnings: HeaderIssue[];
};
type ParseError = { code: 'empty' | 'no_fields' | 'byte_limit' | 'line_limit' | 'field_limit' };
type ParseResult = { document: HeaderDocument; error: null } | { document: null; error: ParseError };
type HeaderTimestamp = { raw: string; iso: string | null; epochMs: number | null };
type ReceivedHop = {
    fieldId: number; hop: number; from: string | null; by: string | null;
    protocol: string | null; id: string | null; for: string | null; ips: string[];
    timestamp: HeaderTimestamp; delayMs: number | null; delayPercent: number | null;
};
type HopAnalysis = { hops: ReceivedHop[]; observedTransitMs: number | null; warnings: HeaderIssue[] };
type AuthObservation = {
    fieldId: number; reporter: string | null; instance: number | null;
    kind: 'result' | 'signature' | 'seal'; method: string; result: string | null;
    properties: { name: string; value: string }[]; raw: string;
};
type AuthAnalysis = { observations: AuthObservation[]; warnings: HeaderIssue[] };
type SpamFamily = 'rspamd' | 'spamassassin-compatible' | 'microsoft' | 'barracuda' | 'proofpoint' | 'ironport' | 'generic';
type SpamObservation = {
    family: SpamFamily; sourceFieldIds: number[];
    values: { name: string; value: string; explanationKey: string | null }[];
    score: number | null; threshold: number | null; action: string | null;
    verdict: string | null; metric: string | null;
    rules: { name: string; score: number | null; options: string | null }[];
    unknownFragments: string[];
};
type SpamAnalysis = { observations: SpamObservation[]; warnings: HeaderIssue[] };
type HeaderAnalysis = {
    document: HeaderDocument; summary: Record<string, HeaderField[]>;
    delivery: HopAnalysis; authentication: AuthAnalysis; filtering: SpamAnalysis;
    warnings: HeaderIssue[];
};
type AnalysisResult = { analysis: HeaderAnalysis; error: null } | { analysis: null; error: ParseError };
type HeaderText = (key: string, replacements?: Record<string, string | number>) => string;
```

Export these types. The issue code vocabulary is defined alongside each owning analyzer and documented through its tests. `HeaderAnalysis.warnings` aggregates each analyzer's warnings once. Keep `rawHeaders` and raw field slices faithful to original line endings; never retain the body in a result. Generic spam header observations are per occurrence; do not infer a cross-header gateway grouping from adjacency.

## Task 1: Header parsing, bounded input, and display decoding

**Files:** Create `resources/js/types/headers.ts`; `resources/js/Pages/Headers/analysis/parseHeaders.ts`, `decodeHeaderValue.ts`, and colocated `parseHeaders.test.ts`, `decodeHeaderValue.test.ts`.

**Interfaces:** Produce `parseHeaders(input: string): ParseResult`; `decodeHeaderValue(value: string): { value: string; warnings: string[] }`; exported constants `MAX_HEADER_BYTES = 1048576`, `MAX_HEADER_LINES = 10000`, `MAX_HEADER_FIELDS = 2000`. Task 1 adds all shared declarations above so later tasks consume one contract.

- [ ] **Step 1: Write failing parser and decoder tests.** Cover `preserves_order_duplicates_raw_lines_and_unfolds_values`, `stops_at_body_boundary`, `retains_malformed_lines_without_swallowing_fields`, `rejects_empty_or_headerless_input`, `enforces_each_exact_limit`, and `decodes_adjacent_words_without_changing_raw_evidence`. Parameterize line endings, leading empty lines, B/Q encodings, and case variations. Pin these assertions:

```ts
const parsed = parseHeaders('Subject: Hello\r\n\tthere\r\nX-Test: a\r\nx-test: b\r\n\r\nX-Body: SECRET');
expect(parsed.document?.fields.map(f => f.lookupName)).toEqual(['subject', 'x-test', 'x-test']);
expect(parsed.document?.fields[0]).toMatchObject({ id: 0, value: 'Hello there', startLine: 1, endLine: 2 });
expect(parsed.document?.rawHeaders).not.toContain('SECRET');
expect(decodeHeaderValue('=?UTF-8?B?SGVsbG8=?= =?UTF-8?Q?_world?=').value).toBe('Hello world');
expect(parseHeaders('X: ' + 'a'.repeat(1048573)).error).toBeNull();
expect(parseHeaders('X: ' + 'a'.repeat(1048574)).error?.code).toBe('byte_limit');
```

Use an additional multibyte test with `é` to prove UTF-8 byte measurement, 2,000/2,001 valid fields, and 10,000/10,001 physical lines built from one field plus continuations. Count the complete pasted input for byte/line limits before discarding its body; a trailing newline starts another physical line. Test invalid Base64, Q escapes, unsupported charset, orphan continuations, HTML payload text, and punctuation in valid field names. Decode UTF-8 and Windows-1252 at minimum with browser `TextDecoder`; unsupported labels preserve source and warn. Structured field `value` stays undecoded.

- [ ] **Step 2: Run** `npm run test:unit -- --run resources/js/Pages/Headers/analysis/parseHeaders.test.ts resources/js/Pages/Headers/analysis/decodeHeaderValue.test.ts`. Expect missing implementation failures, not a test-runner configuration failure.
- [ ] **Step 3: Implement** the contracts with bounded scans, UTF-8 `TextEncoder` measurement, strict field-name recognition, source ranges, and separate display decoding for Subject/From/To/Cc/Reply-To. Preserve damage and decoding warnings, not exception details containing input. Do not use `Date.parse` or HTML decoding.
- [ ] **Step 4: Rerun** Step 2; expect all tests passing. Run `npm run typecheck`; expect exit 0.
- [ ] **Step 5: Commit** only the Task 1 files with `feat: parse message headers locally`.

## Task 2: Delivery journey and deterministic timestamps

**Files:** Create `analysis/parseHeaderTimestamp.ts`, `analysis/analyzeReceived.ts`, and their colocated `.test.ts` files under `resources/js/Pages/Headers/`.

**Interfaces:** Consume `HeaderDocument`; produce `parseHeaderTimestamp(value: string): HeaderTimestamp` and `analyzeReceived(document: HeaderDocument): HopAnalysis`.

- [ ] **Step 1: Write failing tests** for numeric offsets, UTC/GMT and trailing timezone comments, invalid calendar dates, ambiguous named zones, quoted/commented Received tokens, IPv4/IPv6 extraction, unknown fragments available in raw evidence, equal dates, negative dates, missing dates, and no/one hop. Never treat comments containing `from`/`by` as field boundaries. Pin:

```ts
expect(parseHeaderTimestamp('Fri, 23 Oct 2026 12:00:00 +0200').iso).toBe('2026-10-23T10:00:00.000Z');
expect(parseHeaderTimestamp('31 Feb 2026 12:00:00 +0000').epochMs).toBeNull();
const doc = parseHeaders([
    'Received: from b by c; Fri, 23 Oct 2026 10:01:00 +0000',
    'Received: from a by b; invalid',
    'Received: by a; Fri, 23 Oct 2026 10:00:00 +0000',
].join('\n')).document!;
expect(analyzeReceived(doc).hops.map(h => h.fieldId)).toEqual([2, 1, 0]);
expect(analyzeReceived(doc).hops.map(h => h.delayMs)).toEqual([null, null, null]);
expect(analyzeReceived(doc).observedTransitMs).toBe(60000);
```

An additional three-valid-hop fixture must yield delays `[null, 10000, 30000]` and bars `[null, 25, 75]`. A reversed-clock fixture must retain `-10000` and a warning, without sorting. No positive delays means no bars. Unknown first/last dates or fewer than two hops means unknown total; zero observed transit remains zero.

- [ ] **Step 2: Run** `npm run test:unit -- --run resources/js/Pages/Headers/analysis/parseHeaderTimestamp.test.ts resources/js/Pages/Headers/analysis/analyzeReceived.test.ts`; expect missing implementations.
- [ ] **Step 3: Implement** deterministic token/calendar parsing with numeric timezone offsets, rejecting rollovers and ambiguous timezones. Reverse source Received order, compute adjacent signed deltas only, and compute the total from endpoints. Attach issue field IDs for unknown or negative timestamps. Keep hostname/IP extraction textual and local.
- [ ] **Step 4: Rerun** Step 2; expect all passing; run `npm run typecheck`.
- [ ] **Step 5: Commit** owned files with `feat: analyze message delivery hops and timing`.

## Task 3: Reported authentication and source provenance

**Files:** Create `analysis/splitHeaderTokens.ts`, `analysis/analyzeAuthentication.ts`, and their colocated `.test.ts` files under `resources/js/Pages/Headers/`.

**Interfaces:** Produce `splitHeaderTokens(value: string, delimiter: ';' | ','): string[]` (top-level splitting that respects escaped quoted strings, balanced parentheses and brackets); `analyzeAuthentication(document: HeaderDocument): AuthAnalysis`. Preserve the original fragment when nesting is malformed, and let the analyzer warn instead of guessing.

- [ ] **Step 1: Write failing tests** for Authentication-Results, ARC-Authentication-Results instance/reporter, Received-SPF, DKIM-Signature d/s/a metadata, ARC-Seal i/d/s/cv metadata, repeated DKIM results, unknown methods/results, malformed fragments, and absent results. Assert source field IDs and all conflicting observations survive. Pin:

```ts
expect(splitHeaderTokens('mx.example; spf=pass reason="a;b"; dkim=fail (a; b)', ';'))
    .toEqual(['mx.example', 'spf=pass reason="a;b"', 'dkim=fail (a; b)']);
const doc = parseHeaders('Authentication-Results: mx.example; spf=pass smtp.mailfrom=example.org\nDKIM-Signature: v=1; d=example.org; s=mail').document!;
expect(analyzeAuthentication(doc).observations).toEqual(expect.arrayContaining([
    expect.objectContaining({ fieldId: 0, reporter: 'mx.example', kind: 'result', method: 'spf', result: 'pass' }),
    expect.objectContaining({ fieldId: 1, kind: 'signature', method: 'dkim', result: null }),
]));
```

Test explicit `none` separately from no result, `temperror`, `permerror`, and unknown tokens. A lone DKIM signature or ARC cv value never creates a locally verified pass. Unmatched delimiters produce a warning and retained raw content; nested/escaped delimiters terminate safely.

- [ ] **Step 2: Run** `npm run test:unit -- --run resources/js/Pages/Headers/analysis/splitHeaderTokens.test.ts resources/js/Pages/Headers/analysis/analyzeAuthentication.test.ts`; expect missing implementations.
- [ ] **Step 3: Implement** a linear balanced-token scanner and source-attributed authentication parser. Do not establish trust from field placement, choose a winning observation, or perform network calls. Keep signature/seal metadata distinct from authentication result claims.
- [ ] **Step 4: Rerun** Step 2; expect all passing; run `npm run typecheck`.
- [ ] **Step 5: Commit** owned files with `feat: explain reported message authentication`.

## Task 4: Extensible multi-provider antispam recognition

**Files:** Create `analysis/analyzeSpam.ts`, `analysis/spam/{rspamd,spamAssassin,microsoft,generic}.ts`, and a matching colocated `.test.ts` for each, under `resources/js/Pages/Headers/`.

**Interfaces:** Consume `HeaderDocument` and `splitHeaderTokens`. Each recognizer exports `recognize<FieldFamily>(field: HeaderField): SpamObservation | null`, named `recognizeRspamd`, `recognizeSpamAssassin`, `recognizeMicrosoft`, and `recognizeGeneric`. Registry entry point `analyzeSpam(document: HeaderDocument): SpamAnalysis` tries those functions in that order per field and stops at the first match. Each observation has exactly that occurrence's `sourceFieldIds: [field.id]`; never associate fields with the same gateway by proximity.

- [ ] **Step 1: Write failing tests** for all exact field names and fallback prefixes listed in spec section 7, including mixed casing, negative and zero scores, unknown keys, duplicate fields, nested symbol options, malformed scores, and contradictory reports. Pin Rspamd evidence:

```ts
const doc = parseHeaders('X-Spamd-Result: default: False [-2.50 / 15.00]; RULE_A(-1.50)[foo;bar]; RULE_B(-1.00)[]').document!;
expect(analyzeSpam(doc).observations[0]).toMatchObject({
    family: 'rspamd', sourceFieldIds: [0], metric: 'default', verdict: 'False', score: -2.5, threshold: 15,
    rules: [ { name: 'RULE_A', score: -1.5, options: 'foo;bar' }, { name: 'RULE_B', score: -1, options: '' } ],
});
```

For `X-Spam-Status: Yes, score=6.2 required=5.0 tests=RULE_A,RULE_B autolearn=no version=4.0.0`, assert compatible family, verdict `Yes`, score `6.2`, threshold `5`, both rule names, and autolearn/version observations. Also test legacy `hits=`, score-only fields, unstructured reports, and checker version. Recognize X-Spam-Level/X-Spamd-Bar without reverse-engineering a precise numeric score from display characters.

For `X-Forefront-Antispam-Report: SCL:5;CAT:SPM;CIP:192.0.2.1;UNKNOWN:value`, assert SCL/CAT/CIP are explained, unknown key preserved, and no universal score or inferred delivery action. Test Microsoft standalone SCL and antispam BCL. Test raw-only Barracuda/Proofpoint/IronPort families and generic antispam fields. The fixture combining X-Spamd-Result, X-Spam-Status, and Microsoft fields must yield separate observations with unique source IDs, conflicting verdicts retained, and no summed score. An unrecognized `X-Other` remains in the document and receives no spam observation. Every unsupported fragment must be retained, not silently dropped.

- [ ] **Step 2: Run** `npm run test:unit -- --run resources/js/Pages/Headers/analysis/analyzeSpam.test.ts resources/js/Pages/Headers/analysis/spam`; expect missing implementations.
- [ ] **Step 3: Implement** the ordered recognizers. Split only at top-level delimiters. Use finite numeric parsing and no guessed thresholds. Record unknown key/value pairs or fragments. Link explanations to maintained translation keys for documented meanings; unknown symbols remain literal. Add a conflict warning when explicit positive and negative verdicts coexist, identifying their sources without selecting one. Rspamd action/pre-result values remain reported actions, not final mailbox disposition.
- [ ] **Step 4: Rerun** Step 2; expect all passing; run `npm run typecheck`.
- [ ] **Step 5: Commit** owned files with `feat: recognize multi-provider antispam evidence`.

## Task 5: Analysis composition, ephemeral state, and safe local export

**Files:** Create `analysis/analyzeHeaders.ts`, `analysis/formatHeaderAnalysis.ts`, `analysis/example.ts`, their matching tests, and `resources/js/Composables/useHeaderAnalyzer.ts` with `useHeaderAnalyzer.test.ts`.

**Interfaces:** Produce `analyzeHeaders(input: string): AnalysisResult`; `formatHeaderAnalysis(analysis: HeaderAnalysis, t: HeaderText): string`; `serializeHeaderAnalysis(analysis: HeaderAnalysis): string`; exported `EXAMPLE_HEADERS: string`. Produce `useHeaderAnalyzer()` returning `input: Ref<string>`, `analysis: ShallowRef<HeaderAnalysis | null>`, `error: Ref<ParseError | null>`, `status: Ref<string | null>`, `manualCopy: Ref<string | null>`, and methods `analyze(): void`, `clear(): void`, `loadExample(): void`, `copyAnalysis(t: HeaderText): Promise<void>`, `downloadJson(): void`.

- [ ] **Step 1: Write failing tests** for aggregate results, summary singleton duplicates, missing sections, input edits invalidating results, manual reanalysis, example replacement without auto-analysis, clear, unmount/remount, persisted pageshow, clipboard rejection/unavailability, and pending clipboard completion after input changes. Mount a tiny Vue harness to exercise actual composable lifecycle. Assert no fetch, XHR, beacon, Inertia router call, or Storage write during analyze/edit/export/clear; instrument browser boundaries rather than mocking the analyzers. Use a body sentinel and pin:

```ts
const result = analyzeHeaders('Subject: Example\nFrom: sender@example.org\n\nSECRET_BODY');
expect(result.analysis?.summary.subject[0].displayValue).toBe('Example');
expect(serializeHeaderAnalysis(result.analysis!)).not.toContain('SECRET_BODY');
expect(JSON.parse(serializeHeaderAnalysis(result.analysis!))).toMatchObject({ schemaVersion: 1 });
```

Assert text export has section headings, source evidence, and trust limitations in the requested locale. JSON includes `{ schemaVersion: 1, analysis, limitations: string[] }`, uses stable English limitation text and machine codes, and excludes any original pasted body. Test duplicate Subject/From/Date/Message-ID warnings while retaining all values. The production example must exercise at least two hops, authentication, Rspamd and compatible spam evidence using example domains and documentation addresses only.

Assert JSON download uses Blob type `application/json`, filename `message-header-analysis.json`, and revokes its object URL after download activation. No export before analysis or after edits. An out-of-date clipboard promise must not repopulate status/manualCopy. A clipboard operation explicitly requested before Clear may already have written to the system clipboard; do not claim Clear erases that clipboard.

- [ ] **Step 2: Run** `npm run test:unit -- --run resources/js/Pages/Headers/analysis/analyzeHeaders.test.ts resources/js/Pages/Headers/analysis/formatHeaderAnalysis.test.ts resources/js/Pages/Headers/analysis/example.test.ts resources/js/Composables/useHeaderAnalyzer.test.ts`; expect missing implementations.
- [ ] **Step 3: Implement** composition and per-instance state. Build summary arrays for subject/from/to/cc/reply-to/date/message-id/return-path. Aggregate warnings once. Input watcher synchronously invalidates analysis/export state. A monotonically increasing per-instance generation invalidates asynchronous clipboard completions. Clear on unmount and persisted pageshow; unregister event listeners and revoke outstanding URLs on cleanup. Do not use useForm, useRemember, page props for content, persistence, remote error reporting, or content-bearing console output.
- [ ] **Step 4: Rerun** Step 2; expect all passing; run `npm run typecheck`. Review serialized output to confirm raw headers are present and body text is absent.
- [ ] **Step 5: Commit** owned files with `feat: manage private header analysis and local exports`.

## Task 6: Inertia page, navigation, localized results, and acceptance

**Files:** Create `resources/js/Pages/Headers/Show.vue`, `Show.test.ts`; `resources/js/Components/Headers/{MessageSummary,DeliveryJourney,AuthenticationResults,FilteringDetails,HeaderList}.vue`; `resources/js/Layouts/AppLayout.test.ts`; and `tests/Feature/HeaderAnalyzerPageTest.php`. Modify `routes/web.php`, `app/Http/Middleware/HandleInertiaRequests.php`, `resources/js/Layouts/AppLayout.vue`, `resources/js/Pages/Privacy.vue`, `resources/css/app.css`, `lang/en_US.json`, `lang/nl.json`, and `resources/js/Components/Localization.test.ts`. Extend existing tests only where a shared navigation shape actually requires it.

**Interfaces:** Page `Headers/Show` has no message-related props. Each result component takes `analysis: HeaderAnalysis`; `HeaderList` additionally owns a local `query` string. Layout navigation gains `headers: string` at `/headers`. Route is GET `/headers`, named `headers.show`. The page owns a single `useHeaderAnalyzer()` instance; it passes only that instance's result to children. Use input `#header-input`, results `#header-results`, and a `role=status` live region; use proper labels for all buttons and search.

- [ ] **Step 1: Resolve framework prerequisites, then write failing integration tests.** Read the installed Boost testing-best-practices skill at `vendor/laravel/boost/.ai/laravel/skill/testing-best-practices/SKILL.blade.php` and its applicable rules. Locate/activate inertia-vue-development; installed Boost has versioned templates under `vendor/laravel/boost/.ai/inertia-vue/`, but verify guidance against installed Inertia 3. Obtain the project's required version-specific `search-docs` capability before changing Inertia patterns; it was not exposed during planning, so surface an unresolved requirement rather than silently treating v2 guidance as v3. Recheck `.ai/rules` and dependency versions. Create the feature test with `php artisan make:test --phpunit HeaderAnalyzerPageTest --no-interaction` after checking command help.

In the feature test use `withoutVite()` and the existing Inertia assertions to verify `/headers` returns component `Headers/Show`, navigation URLs from named routes, and English/Dutch browser-language messages. No database is needed for GET `/headers`. Pin page text `Message Header Analyzer`, Dutch `Berichtkoppen analyseren`, privacy statement `Your headers stay in your browser.` and Dutch `Je berichtkoppen blijven in je browser.` through actual page rendering/props. These assertions follow the existing project test APIs; confirm version-specific usage during implementation.

Vue tests mount with the existing Inertia mock convention and actual result children. Test analyze/edit/reanalyze/clear/example, empty/limit errors, all five section headings, summary duplicates, a signed negative hop, unknown fields, reporter grouping, explicit none versus absent outcomes, raw evidence disclosure, safe rendering of `<img src=x onerror=alert(1)>`, case-insensitive field/value search with duplicates in source order, and manual-copy fallback. Assert no rendered `img`/`script` from evidence. Exports stay disabled until analysis exists, and successful analyze does not move focus. Clear returns focus to `#header-input`; persisted pageshow also clears the actual textarea value. Test layout active links with `aria-current=page` on `/` and `/headers`; scan report routes count as Domain diagnostics. Render Dutch labels while preserving raw evidence unchanged.

- [ ] **Step 2: Run** `php artisan test --compact tests/Feature/HeaderAnalyzerPageTest.php` and `npm run test:unit -- --run resources/js/Pages/Headers/Show.test.ts resources/js/Layouts/AppLayout.test.ts resources/js/Components/Localization.test.ts`. Expect new tests to fail for missing page/route/behavior, with existing localization tests still runnable.
- [ ] **Step 3: Implement** the GET route, shared named URL, page and five focused components. Reuse `StatusIcon` with explicit Reported pass/fail/neutral labels; do not use scanner StatusBadge's bare Pass for authentication. Show signature/seal presence as metadata. Group authentication observations by fieldId/reporter, not by a guessed trusted host. Explain unknowns and incomplete timing. Put exported-data privacy help next to Copy analysis/Download JSON and the local-processing statement beside the input. Keep raw values escaped through Vue interpolation. Use a wrapping tool navigation and existing CSS variables; use semantic headings, native details, scroll containers, and text/icon status cues.

Translate all user-facing labels, errors, issue explanations, provider field explanations, empty states, help, copy outcomes and limitations in both dictionaries, leaving protocol tokens and evidence unchanged. Include a collapsed help block explaining Gmail Show original, Outlook View message details/source, and Thunderbird View Source. Update the privacy page introduction, local header-processing section, explicit export warning, and last-updated date to implementation date. Scope domain-scanner footer caveats to domain diagnostics and show the reported-evidence limitation on the header page so it does not imply headers were scanned over the network.

- [ ] **Step 4: Run affected checks.** Rerun Step 2 after each test change. Run `php artisan test --compact tests/Feature/HomePageTest.php tests/Feature/PrivacyPageTest.php tests/Feature/BrowserLocaleTest.php tests/Feature/HeaderAnalyzerPageTest.php`, `npm run test:unit -- --run resources/js/Pages/Headers resources/js/Composables/useHeaderAnalyzer.test.ts resources/js/Layouts/AppLayout.test.ts resources/js/Components/Localization.test.ts`, `npm run typecheck`, `vendor/bin/pint --dirty --format agent`, `npm run build`, and `git diff --check`. All must exit 0. Re-run affected PHP tests if Pint changes their source. Run `php artisan route:list --path=headers --except-vendor --no-interaction`; expect the single intended GET/HEAD route and no analysis submission endpoint.
- [ ] **Step 5: Verify in a real browser using the available browser tool or manual inspection, without adding test dependencies.** Load the built application and synthetic example, analyze, inspect each section, edit/reanalyze, clear, copy/fallback, and download JSON. Confirm keyboard focus/announcements and 390px/desktop widths with scrolling only inside tables. Inspect light/dark, preference changes and reduced motion where the browser tool supports them; report unsupported checks accurately. With the network inspector and a unique synthetic sentinel in headers/body, verify analyze/search/copy/download/clear generate no application requests; navigate away/back and reload, confirming the input and results are empty. Exercise both Inertia navigation and native history/BFCache where supported. Browser automation that lacks network/history/theme controls cannot substantiate those checks; keep them explicitly pending for manual validation. Never use real user headers for acceptance.
- [ ] **Step 6: Commit** only the Task 6 source/test files with `feat: add localized message header analyzer page`. Report performed checks and any remaining browser acceptance limits before claiming the feature complete. Follow the selected execution/review workflow; do not deploy or publish as part of this plan.

## Self-review and handoff

Plan review on 23 October 2026: spec sections 1–3 map to Task 6; section 4 to Task 1; section 5 to Task 2; section 6 to Task 3; section 7 to Task 4; section 8 to Tasks 5–6; section 9 to each task's regression checks and Task 6 acceptance; exclusions apply globally. All five Review Focus cases have owning assertions. Shared interface names and source IDs are consistent across tasks. No product code, dependency, or test file is changed by writing this plan.

Review this plan before implementation and choose an execution method. Native execution is recommended for six sequential tasks sharing a single result contract; it avoids the repeated context cost of per-task agents. Subagent-driven execution is available if independent per-task implementation/review gates are preferred. Execution may create an isolated worktree using the required worktree skill; planning does not create one.
