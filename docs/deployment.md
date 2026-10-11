# Deployment and operations

## Prerequisites and networking

The default stack is HTTP on 127.0.0.1:8080. Use an HTTPS reverse proxy for hosted deployments. The hosted override binds web HTTP to 0.0.0.0; set HOSTED_HTTP_BIND to limit it. It adds no inbound SMTP listener. Allow **outbound TCP 25 and 443**, plus UDP/TCP 53 for Unbound's recursive DNS lookups. The hosted provider must permit port 25 independently of Docker configuration.

Set SCAN_HELO to an operator-owned FQDN identifying the scanner, ideally with matching forward/reverse records. SCAN_SENDER defaults to the SMTP null sender. SCAN_RELAY_SENDER and SCAN_RELAY_RECIPIENT must both be operator-controlled identities external to the domains you inspect. The relay probe never sends DATA. Do not use third-party mailbox identities without authorization.

The Compose resolver validates DNSSEC using a persisted root trust anchor. SCAN_DNS_TRUSTED=true is valid only with that controlled resolver or an equivalently trusted validating resolver on a protected network. An arbitrary public resolver's AD flag is not a trusted assertion. Resolver health checks its local control socket without relying on a public test domain.

IPv6 requires a working IPv6 route on the host, Docker forwarding/NAT or routed addresses, and provider firewall rules. The IPv6 override creates a dual-stack egress network with a configurable DOCKER_IPV6_SUBNET; this alone does not prove connectivity. Docker Desktop often lacks usable external IPv6 even when IPv6 sockets work. Run scanner:diagnose --connect=<public IPv6> --mx=<MX hostname> to verify a specific route. Test IPv4 separately.

## Secrets and startup

Run php scripts/setup-env.php before Compose. The script creates missing secrets using cryptographic randomness, preserves existing values, sets file mode 0600, and never prints secrets. Keep .env outside source control and back it up securely. APP_KEY must remain stable across restarts/upgrades. Containers refuse startup without APP_KEY, DB_PASSWORD or REDIS_PASSWORD; no secrets or dependencies are generated at container startup.

PostgreSQL must become healthy before migrations. App, workers and scheduler depend on successful migrations, and workers also require Redis and the resolver. App dependency health checks query DB/Redis; /up is application liveness. Workers have job-specific timeouts (discovery 240 seconds, endpoint 45 seconds with a 30-second internal budget). Queue visibility is 300 seconds. Stop grace is 260 seconds. Redis leases fence stale workers; database row locks guard claims and finalization. Scheduler dispatches saved outbox work and reconciles deadlines every minute, and prunes daily.

Scale workers with docker compose up -d --scale worker=4. Per-scan/MX/IP Redis permits bound simultaneous probes. Run only one scheduler deployment. Inspect docker compose logs worker scheduler unbound and php artisan queue:failed. A failed or partial report retains useful evidence. Retries do not automatically repeat scans on a recurring schedule.

For HTTPS set APP_URL to the public https URL, SESSION_SECURE_COOKIE=true, and TRUSTED_PROXIES to a comma-separated list of **actual proxy IPs**. Forward X-Forwarded-Proto and Host correctly. Avoid wildcard trust unless network isolation ensures only the proxy can reach the app. The submission limiter uses the resulting client IP. Public report links act as bearer links and expire after SCAN_RETENTION_DAYS (default 7).

## Upgrades and backups

Back up .env and PostgreSQL before upgrading. Stop workers and scheduler gracefully, rebuild images, rerun the migration service, then start the stack:

```sh
docker compose stop worker scheduler
docker compose build
docker compose run --rm migrate
docker compose up -d --force-recreate app web worker scheduler
```

Do not use down -v on a deployment with reports you want to keep. PostgreSQL, Redis queues/AOF, app storage, and resolver trust-anchor state use named volumes. Use pg_dump through docker compose exec -T postgres for a consistent database backup and restore it on an isolated deployment before relying on it. APP_KEY changes invalidate encrypted sessions.

## Development and isolated tests

compose.dev.yaml bind-mounts the source and serves Vite on loopback port 5173; install composer dependencies on the host first. Run with -f compose.yaml -f compose.dev.yaml and use localhost consistently for Vite. Production images embed identical compiled assets/manifests in PHP and Nginx.

The test project uses throwaway PostgreSQL tmpfs and Redis, with no production environment secrets. For PHP 8.4 container tests:

```sh
docker compose -p mail-inspector-test -f compose.test.yaml run --rm php-test vendor/bin/phpunit tests/Integration
docker compose -p mail-inspector-test -f compose.test.yaml down -v
```

If registry access prevents PHP image builds but cached PostgreSQL/Redis are available, expose only ephemeral loopback test ports:

```sh
docker compose -p mail-inspector-test -f compose.test.yaml -f compose.test.host.yaml up -d redis-test postgres-test
docker compose -p mail-inspector-test -f compose.test.yaml -f compose.test.host.yaml port redis-test 6379
# Use the printed port:
MAIL_TEST_INTEGRATION=true REDIS_HOST=127.0.0.1 REDIS_PORT=<port> vendor/bin/phpunit tests/Integration/ProbePermitsTest.php
```

No production private-target guard is disabled. Local SMTP fixture connections are injected only by tests. Run scripts/compose-smoke.sh on a host with image registry access to verify startup/migrations/dependencies and scanner diagnostics. The smoke script does not delete volumes or submit an unsolicited public-domain scan.
