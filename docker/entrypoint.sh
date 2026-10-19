#!/bin/sh
# Start: auf DB warten, Schema/Content-Repository aktualisieren, Ressourcen veroeffentlichen, dann Apache
set -e
cd /app
mkdir -p Data/Persistent Data/Logs
chown -R www-data:www-data Data

i=0
until MYSQL_PWD="$DB_PASSWORD" mariadb-admin --skip-ssl ping -h "$DB_HOST" -u "$DB_USER" --silent; do
  i=$((i+1)); [ $i -ge 60 ] && { echo "DB nicht erreichbar"; MYSQL_PWD="$DB_PASSWORD" mariadb-admin --skip-ssl ping -h "$DB_HOST" -u "$DB_USER"; exit 1; }
  sleep 2
done

flow() { runuser -u www-data -- ./flow "$@"; }
echo "DB ok, Migrationen"
flow doctrine:migrate
flow cr:setup --content-repository default
flow resource:publish

exec "$@"
