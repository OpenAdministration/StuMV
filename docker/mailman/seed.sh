#!/bin/sh
# Creates the demo domain + list that DemoMailmanSeeder maps ref-finanzen onto,
# plus one stale member so the first mailman:sync-lists run shows a removal.
# Mailman's SQLite data is ephemeral, so this runs on every start; Mailman
# answers 400 for things that already exist, which is fine here.
set -e

api="http://mailman-core:8001/3.1"
auth="restadmin:mailman-not-production"

post() {
    path=$1
    shift
    status=$(curl -s -o /dev/null -w '%{http_code}' -u "$auth" "$@" "$api/$path")
    case $status in
        2??|400) echo "POST /$path -> $status" ;;
        *) echo "POST /$path failed with $status" >&2; exit 1 ;;
    esac
}

post domains -d mail_host=lists.demo.stumv.de
post lists -d fqdn_listname=finanzen@lists.demo.stumv.de
post members -d list_id=finanzen.lists.demo.stumv.de -d subscriber=ehemalig@demo.stumv.de \
    -d pre_verified=true -d pre_confirmed=true -d pre_approved=true
