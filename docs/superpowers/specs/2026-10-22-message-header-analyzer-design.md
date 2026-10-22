# Message Header Analyzer Design

Date: 22 October 2026

Status: Written specification for review. The user approved the proposed design with the addition of Rspamd and other antispam headers. Implementation planning follows review of this document.

## 1. Intent and success criteria

Add a dedicated Message Header Analyzer to Mail Inspector so visitors can understand a message's delivery journey, delays, reported authentication outcomes, and spam-filter decisions from pasted headers. Fit the current site's appearance and English/Dutch localization. Analyze entirely in the browser, with no stored headers or shareable reports.

Success means a visitor can paste headers, identify the observed route and delays, inspect the evidence behind reported authentication and filtering outcomes, and copy or download the analysis. Incomplete or unfamiliar headers remain useful and visible without producing invented conclusions.

Use original product copy and design. Do not name or link a comparison tool in the product or these feature documents.

## 2. Approach and boundaries

Use a dedicated `/headers` page rather than placing message input inside the existing domain scan form. Laravel serves the page through Inertia; pure TypeScript modules perform parsing and analysis locally. Vue renders their structured results.

The existing domain scanner continues to handle public DNS and SMTP diagnostics. Header analysis does not dispatch scan jobs or use the scanner's database, queues, or network services. No additional dependencies are planned.

Confirmed environment: PHP 8.5, Laravel 13.35.0, inertia-laravel 3.5.1. The frontend declares Vue ^3.5, @inertiajs/vue3 ^3.0, TypeScript ^5.9, Vite ^8.0.0, and Vitest ^4.0. Confirm locked versions and applicable project guidance before implementation.

## 3. Navigation and presentation

Add a named GET route `headers.show` for `/headers`, rendering `Headers/Show`. Share its relative URL through the existing navigation props. Add Domain diagnostics and Message headers navigation links to the shared layout with an accessible current-page indication. Keep the existing brand, privacy link, and domain scan workflow.

Reuse existing CSS variables, panels, typography, badges, status icons, and browser-selected light/dark themes. Use translated labels and explanations through `useTranslations` and the existing `en_US.json` and `nl.json` dictionaries. Raw header names, protocol tokens, and source values remain unchanged.

### Input

Show the heading Message Header Analyzer, a short description, a labeled monospace textarea, and Analyze headers, Clear, and Load example actions. Provide short instructions for obtaining internet headers. Load example uses a synthetic message containing only reserved example domains and documentation IP addresses.

Display this privacy statement beside the input: “Your headers stay in your browser.” Explain that analysis is temporary and exports can contain email addresses, IP addresses, and server names.

Analysis runs only when requested. Editing input invalidates and hides the previous analysis immediately, so exports cannot accidentally describe an older input. Clear empties input, analysis, errors, and transient export state and returns focus to the textarea. Loading an example replaces the input and clears previous results without analyzing automatically.

### Results

Display sections in this order:

1. Message summary: decoded Subject, From, To, Cc, Reply-To, Date, Message-ID, Return-Path, received-hop count, and observed transit time when available. Repeated summary fields remain inspectable and generate a duplicate warning where a singleton is expected.
2. Delivery journey: oldest-to-newest Received entries with hop number, from/by servers, literal IP addresses if present, protocol, timestamp, and delay. Positive delays receive proportional bars; signed negative delays carry a clock/order warning. Each hop exposes its original header.
3. Authentication: reported SPF, DKIM, DMARC, and ARC evidence grouped by source header and reporting server when available. Preserve repeated or conflicting observations.
4. Filtering details: recognized provider-specific and generic antispam fields with their original values, supported explanations, and provenance.
5. All headers: a case-insensitive search over names and values, retaining original order and duplicates, plus access to raw header text.

Result sections use ordinary headings and disclosure controls rather than a required tab interface. Tables scroll within their container on small screens; long values wrap. Statuses include text and icons. Announce validation errors and analysis/export outcomes accessibly without moving focus on successful analysis.

## 4. Parsing contract

Preserve header order, original names, duplicates, raw source, and source line ranges. Provide a lowercase lookup name, unfolded value, and optional decoded display value separately. Never decode structured protocol fields before parsing them.

Accept CRLF, LF, and CR line endings. Ignore leading empty lines, then stop at the first empty line after header content begins; do not analyze a pasted message body. Unfold lines beginning with space or tab into the preceding field. Preserve malformed lines separately with a warning rather than silently attaching arbitrary unindented text to a field. Treat field names case-insensitively.

Decode RFC 2047 B and Q encoded words for human-facing fields, including adjacent encoded words and supported character sets. Preserve the original text and warn on invalid encodings or unsupported character sets. Render every header and decoded value as text, without HTML rendering, auto-followed URLs, or embedded remote content.

Set an explicit input limit of 1 MiB measured as UTF-8 bytes, 10,000 physical lines, and 2,000 parsed fields. Reject an input exceeding any limit with a translated error; do not truncate silently. Empty input or text containing no valid field produces an actionable error. A usable header block with damaged fields produces partial analysis and warnings. Limit checking and parsing must use bounded operations; avoid nested or catastrophic-backtracking expressions over attacker-controlled values.

## 5. Delivery analysis

Reverse Received field order to show the apparent oldest-to-newest journey. Do not sort by timestamps: clocks and source data can disagree. Parse from, by, with, id, and for fields when recognizable, retaining any unparsed content in the source evidence.

Parse numeric timezone offsets deterministically rather than relying on browser-specific date guessing. Support common UTC/GMT forms. Unsupported or ambiguous timestamps remain unknown with an explanation. Preserve the original timestamp and provide a normalized UTC value when valid.

For each hop after the first, calculate delay only when that hop and its immediately preceding hop both have valid timestamps. Do not bridge an unknown timestamp. First-hop delay is unknown. Retain negative delays. Delay bars use positive known delays only.

Observed transit time is last minus first Received timestamp only when at least two hops exist and both endpoints have valid dates. Label it as observed, not end-to-end delivery time. A negative value remains visible with a warning. Message Date is user-supplied metadata and does not define transit time. Absence of Received fields does not prevent other analysis.

## 6. Authentication and trust

Read Authentication-Results, ARC-Authentication-Results, Received-SPF, DKIM-Signature metadata, and ARC-Seal metadata. Parse semicolon-delimited fields while respecting quoted strings and comments. Preserve unknown methods, result tokens, and properties.

Authentication outcomes are always reported observations: use Reported pass, Reported fail, and corresponding explicit neutral/unknown states. Distinguish absence of a result from an explicit none result. A signature's presence does not mean it passed validation; an ARC seal's cv value is reported metadata, not local chain validation.

Do not select a trusted reporting server automatically, merge conflicting sources into one authoritative verdict, calculate a message safety score, or claim independent SPF/DKIM/DMARC/ARC verification. Explain that pasted headers can be forged and that verification would require additional trusted context and, for signatures, message content.

## 7. Antispam recognition

Use an ordered registry of small recognizers sharing a result structure: family, source field IDs, parsed observations, optional reported score/threshold/action, rule hits, unknown fragments, and warnings. Recognizers consume parsed fields and perform no I/O. A field can be classified once while remaining present in All headers. Preserve distinct occurrences and do not merge values across different gateways without evidence.

### Structured first-release support

- Rspamd: recognize X-Spamd-Result, X-Spamd-Bar, X-Rspamd-Action, X-Rspamd-Pre-Result, X-Rspamd-Server, and X-Rspamd-Queue-Id. Extract reported metric, verdict, score/threshold where explicitly present, symbol names, symbol scores, and options from supported formats. Retain unknown or malformed fragments.
- SpamAssassin-compatible: recognize X-Spam-Status, X-Spam-Flag, X-Spam-Level, X-Spam-Score, X-Spam-Report, and X-Spam-Checker-Version. Extract explicit verdict, score or legacy hits, required threshold, test names, autolearn, and version where present. A shared X-Spam-* header alone is labeled generic/compatible, not proof that SpamAssassin generated it; Rspamd can emit compatible headers too.
- Microsoft: recognize X-Forefront-Antispam-Report, X-Microsoft-Antispam, and X-MS-Exchange-Organization-SCL. Interpret documented keys including SCL, BCL, CAT, SFV, CIP, H, and PTR. Preserve unfamiliar keys and avoid claiming that a score alone determines final delivery disposition.

### Recognition and generic fallback

Recognize X-Barracuda-*, X-Proofpoint-*, and X-IronPort-* families as gateway diagnostic evidence. Show family labels and raw fields; do not invent undocumented score scales or rule explanations. Additional X-Spam-*, X-Spamd-*, X-Rspamd-*, and names containing antispam are collected as generic filtering evidence. All other headers remain available in All headers regardless of recognition.

Provider attribution is indicative, not authenticated. Scores belong to their originating systems: do not normalize, compare, sum, or apply guessed thresholds. Thresholds and actions are displayed only when supplied. Negative scores are valid. Rule names without a maintained documented explanation remain literal names. Conflicting verdicts remain separate with a warning.

## 8. Privacy and local export

Keep input/results in page component memory only. Do not send header-derived data in HTTP requests, logs, exception telemetry, query strings, Inertia remembered state, localStorage, sessionStorage, or cookies. Do not fetch DNS, IP reputation, geolocation, or remote header explanations. Navigation, remount, and reload do not restore the analysis; dispose component state on unmount and clear restored form values on a browser back/forward-cache restoration.

Copy analysis writes a readable report only after a click. Download JSON creates a local Blob with a versioned format containing parsed evidence, results, and limitations. Exports contain only the header section, never the discarded message body, and use a fixed filename rather than incorporating message content. Revoke download object URLs after use.

Both export controls explain that results may contain personal information. Clipboard denial shows an error and provides selectable text for manual copying. Exports are disabled when results are missing or stale. Update the privacy page with a separate description of local header processing and explicit export behavior; normal page-request technical logging remains distinct from header content.

## 9. Implementation boundaries and verification

Use the existing Pages, Components, Composables, and types directories. Keep the parser, date handling, hop analysis, authentication interpretation, filtering recognizers, and export formatting separately testable. Do not add a new top-level source folder. The detailed plan will assign exact files and interfaces after this specification is reviewed.

Test with synthetic fixtures for valid mixed-provider headers; Rspamd symbols and pre-results; SpamAssassin and compatible output; Microsoft fields; recognized gateway families; unknown extensions; repeated/conflicting results; folded fields; malformed encoding; invalid dates; timezone offsets; missing/negative timing; malicious markup; body separation; and exact input-limit boundaries.

Component tests cover analyze/edit/reanalyze/clear/example flows, privacy-preserving state behavior, search, clipboard failure, and local export contents. Feature tests cover the GET route, shared navigation, and localization. Browser verification covers absence of header-bearing requests, reload/navigation/back restoration, mobile overflow, dark mode, keyboard interaction, and accessible status announcements.

Read the required testing-best-practices and inertia-vue-development skills and use version-specific Inertia search-docs before implementing; if unavailable, resolve that tooling prerequisite at execution time. No tests are written during specification work. At implementation completion run affected tests, npm run typecheck, npm run build, and vendor/bin/pint --dirty --format agent for changed PHP files.

## 10. Exclusions

No mailbox integration, file upload, body analysis, attachment inspection, signature verification, network lookups, saved history, shareable reports, universal spam score, or automatic provider trust. Additional structured vendor decoders can be added through the recognizer interface when documented formats and fixtures justify them.

## 11. Technical sources

- Message structure and folding: https://www.rfc-editor.org/rfc/rfc5322.html
- Encoded words: https://www.rfc-editor.org/rfc/rfc2047.html
- Authentication results and trust: https://www.rfc-editor.org/rfc/rfc8601.html
- Rspamd milter headers: https://docs.rspamd.com/modules/milter_headers/
- SpamAssassin tagging: https://spamassassin.apache.org/full/4.0.x/doc/spamassassin.html
- Microsoft filtering fields: https://learn.microsoft.com/en-us/defender-office-365/message-headers-eop-mdo
