#!/bin/sh
set -eu
mkdir -p /var/lib/unbound /var/run/unbound
if [ ! -s /var/lib/unbound/root.key ]; then cp /usr/share/dns/root.key /var/lib/unbound/root.key; fi
chown -R unbound:unbound /var/lib/unbound /var/run/unbound
unbound-checkconf /etc/unbound/unbound.conf
exec unbound -d -c /etc/unbound/unbound.conf
