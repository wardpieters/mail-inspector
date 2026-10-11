FROM debian:bookworm-slim
RUN apt-get update && apt-get install -y --no-install-recommends bind9 bind9-utils dnsutils unbound && rm -rf /var/lib/apt/lists/*
COPY . /fixtures
RUN chmod +x /fixtures/sign-zone.sh
ENTRYPOINT ["/fixtures/sign-zone.sh"]
