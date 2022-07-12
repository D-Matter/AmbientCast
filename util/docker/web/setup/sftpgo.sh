#!/bin/bash
set -e
set -x

add-apt-repository -y ppa:sftpgo/sftpgo
apt-get update

apt-get install -y --no-install-recommends sftpgo

mkdir -p /var/ambientcast/sftpgo/persist /var/ambientcast/sftpgo/backups

cp /bd_build/web/sftpgo/sftpgo.json /var/ambientcast/sftpgo/sftpgo.json

touch /var/ambientcast/sftpgo/sftpgo.db
chown -R ambientcast:ambientcast /var/ambientcast/sftpgo
