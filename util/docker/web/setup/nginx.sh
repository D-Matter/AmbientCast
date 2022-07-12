#!/bin/bash
set -e
set -x

apt-get install -y --no-install-recommends nginx nginx-common openssl libnginx-mod-nchan

# Install nginx and configuration
cp /bd_build/web/nginx/proxy_params.conf /etc/nginx/proxy_params
cp /bd_build/web/nginx/nginx.conf.tmpl /etc/nginx/nginx.conf.tmpl
cp /bd_build/web/nginx/ambientcast.conf.tmpl /etc/nginx/ambientcast.conf.tmpl

mkdir -p /etc/nginx/ambientcast.conf.d/

# Create nginx temp dirs
mkdir -p /tmp/app_nginx_client /tmp/app_fastcgi_temp
touch /tmp/app_nginx_client/.tmpreaper
touch /tmp/app_fastcgi_temp/.tmpreaper
chmod -R 777 /tmp/app_*
