#!/bin/bash
set -e
set -x

cd /tmp

YEAR=$(date +'%Y')
MONTH=$(date +'%m')

wget --quiet -O dbip-city-lite.mmdb.gz "https://download.db-ip.com/free/dbip-city-lite-${YEAR}-${MONTH}.mmdb.gz"
gunzip dbip-city-lite.mmdb.gz

mv dbip-city-lite.mmdb /var/ambientcast/dbip/

chown -R ambientcast:ambientcast /var/ambientcast/dbip
