


## Misconfigured ldap? 

docker compose -f docker-compose.dev.yaml down -v ldap
docker compose -f docker-compose.dev.yaml up --build ldap

## Mailman sync

The dev stack runs Mailman 3 Core (REST API at http://localhost:18001/3.1,
`restadmin` / `mailman-not-production`) and Mailpit (http://localhost:18025)
for the mails Mailman sends. `mailman-seed` creates the list
`finanzen@lists.demo.stumv.de` with a stale member, and `DemoMailmanSeeder`
maps the demo `ref-finanzen` group onto it. Mailman's data is ephemeral.

docker compose -f docker-compose.dev.yaml exec php-fpm php artisan mailman:sync-lists demo
