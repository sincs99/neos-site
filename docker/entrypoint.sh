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
# Fehler hier sollen den Start nicht verhindern (Webseite zeigt sie dann an)
flow doctrine:migrate || echo "WARNUNG: doctrine:migrate fehlgeschlagen"
flow cr:setup --content-repository default || { echo "WARNUNG: cr:setup fehlgeschlagen - Cache leeren und erneut"; flow flow:cache:flush --force; flow cr:setup --content-repository default || echo "WARNUNG: cr:setup erneut fehlgeschlagen"; }
flow resource:publish || echo "WARNUNG: resource:publish fehlgeschlagen"

exec "$@"
