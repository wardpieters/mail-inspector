#!/bin/sh
set -eu
mkdir -p /fixtures/state
cd /fixtures/state
for zone in good.fixture.test bogus.fixture.test unsigned.fixture.test; do
  sed "s/ZONE_NAME/$zone/g" /fixtures/zones/fixture.test.zone > "$zone.zone"
  if [ "$zone" != unsigned.fixture.test ]; then
    key=$(dnssec-keygen -a ECDSAP256SHA256 -f KSK -n ZONE "$zone")
    dnssec-keygen -a ECDSAP256SHA256 -n ZONE "$zone" >/dev/null
    cp "$key.key" "$zone.anchor"
    dnssec-signzone -n 1 -S -o "$zone" -f "$zone.signed" "$zone.zone" >/dev/null
  fi
done
sed -i 's/8.8.8.8/8.8.4.4/g' bogus.fixture.test.signed
cat > /fixtures/state/unbound.conf <<'CONF'
server:
  username: ""
  chroot: ""
  interface: 0.0.0.0
  port: 1053
  access-control: 0.0.0.0/0 allow
  use-syslog: no
  logfile: ""
  do-not-query-localhost: no
  local-zone: "test." nodefault
  trust-anchor-file: "/fixtures/state/good.fixture.test.anchor"
  trust-anchor-file: "/fixtures/state/bogus.fixture.test.anchor"
  domain-insecure: "unsigned.fixture.test."
  ede: yes
  val-log-level: 2
stub-zone:
  name: good.fixture.test.
  stub-addr: 127.0.0.1@10053
stub-zone:
  name: bogus.fixture.test.
  stub-addr: 127.0.0.1@10053
stub-zone:
  name: unsigned.fixture.test.
  stub-addr: 127.0.0.1@10053
CONF
named -g -c /fixtures/named.conf -n 1 >/tmp/mail-fixture-named.log 2>&1 &
exec unbound -d -c /fixtures/state/unbound.conf
