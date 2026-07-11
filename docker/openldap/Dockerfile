# Prod-faithful OpenLDAP for development and CI.
#
# Uses the same Symas build as production (built from git.symas.net source).
# The server config and seed data are baked into the image, so every container
# start begins from a clean, reproducible, production-like directory. No data
# volume is mounted — this is intentional for deterministic test runs.

FROM docker.io/symascorp/symas-openldap:openldap26-basic

COPY slapd.conf /opt/symas/etc/openldap/slapd.conf
COPY bootstrap/ /bootstrap/

# Build the directory database from the seed LDIFs at image build time.
# `slapadd -q` (quick mode) loads the data and exits cleanly; without -q it
# prints a spurious "entries missing" warning and exits non-zero.
USER root
RUN set -eux; \
    export PATH="/opt/symas/bin:/opt/symas/sbin:$PATH"; \
    mkdir -p /opt/symas/var/openldap-data /opt/symas/var/run; \
    cat /bootstrap/00-base.ldif /bootstrap/10-sample.ldif > /tmp/seed.ldif; \
    slapadd -q -f /opt/symas/etc/openldap/slapd.conf -b "dc=stumv,dc=de" -l /tmp/seed.ldif; \
    rm -f /tmp/seed.ldif; \
    chown -R openldap:openldap /opt/symas/var /opt/symas/etc/openldap

USER openldap

# Listen on an unprivileged port so the image runs as the non-root openldap
# user under rootless container engines (podman) without extra capabilities.
EXPOSE 1389
CMD ["/opt/symas/lib/slapd", "-f", "/opt/symas/etc/openldap/slapd.conf", \
     "-h", "ldap://:1389/", "-d", "stats"]
