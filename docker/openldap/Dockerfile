# Prod-faithful OpenLDAP for development and CI.
#
# Uses the same Symas build as production (built from git.symas.net source) and
# is configured the same way production is: a cn=config / slapd.d directory built
# from config.ldif (ported from a sanitised production `slapcat -n0` dump).
#
# The config and seed data are baked into the image, so every container start
# begins from a clean, reproducible, production-like directory. No data volume is
# mounted — this is intentional for deterministic test runs.

FROM docker.io/symascorp/symas-openldap:openldap26-basic

COPY config.ldif /bootstrap/config.ldif
COPY bootstrap/ /bootstrap/

# Build the slapd.d config directory and load the seed data at image build time.
#  - `slapadd -n0` builds cn=config from config.ldif (stock schema is pulled in
#    via include: directives from the schema files shipped in the image).
#  - `slapadd -q` loads the directory data (quick mode exits cleanly; without -q
#    it prints a spurious "entries missing" warning and exits non-zero).
USER root
RUN set -eux; \
    export PATH="/opt/symas/bin:/opt/symas/sbin:$PATH"; \
    mkdir -p /opt/symas/etc/openldap/slapd.d /opt/symas/var/openldap-data /opt/symas/var/run; \
    slapadd -n0 -F /opt/symas/etc/openldap/slapd.d -l /bootstrap/config.ldif; \
    cat /bootstrap/00-base.ldif /bootstrap/10-sample.ldif /bootstrap/20-demo.ldif > /tmp/seed.ldif; \
    slapadd -q -F /opt/symas/etc/openldap/slapd.d -l /tmp/seed.ldif; \
    rm -f /tmp/seed.ldif; \
    chown -R openldap:openldap /opt/symas/var /opt/symas/etc/openldap

USER openldap

# Listen on an unprivileged port so the image runs as the non-root openldap
# user under rootless container engines (podman) without extra capabilities.
EXPOSE 1389
CMD ["/opt/symas/lib/slapd", "-F", "/opt/symas/etc/openldap/slapd.d", \
     "-h", "ldap://:1389/", "-d", "stats"]
