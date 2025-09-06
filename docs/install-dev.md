


## Misconfigured ldap? 

docker compose -f docker-compose.dev.yaml down -v ldap
docker compose -f docker-compose.dev.yaml up --build ldap
