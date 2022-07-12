#!/bin/bash
set -e
set -x

apt-get install -y --no-install-recommends sudo

# Workaround for sudo errors in containers, see: https://github.com/sudo-project/sudo/issues/42
echo "Set disable_coredump false" >> /etc/sudo.conf

adduser --home /var/ambientcast --disabled-password --gecos "" ambientcast

usermod -aG www-data ambientcast

mkdir -p /var/ambientcast/www /var/ambientcast/stations /var/ambientcast/servers/shoutcast2 \
  /var/ambientcast/servers/stereo_tool /var/ambientcast/backups /var/ambientcast/www_tmp \
  /var/ambientcast/uploads /var/ambientcast/geoip /var/ambientcast/dbip \
  /var/ambientcast/acme

chown -R ambientcast:ambientcast /var/ambientcast
chmod -R 777 /var/ambientcast/www_tmp

echo 'ambientcast ALL=(ALL) NOPASSWD: ALL' >> /etc/sudoers
