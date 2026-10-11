FROM debian:bookworm-slim
RUN apt-get update && apt-get install -y --no-install-recommends unbound dns-root-data ca-certificates && rm -rf /var/lib/apt/lists/*
COPY unbound.conf /etc/unbound/unbound.conf
COPY entrypoint.sh healthcheck.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/*.sh
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
