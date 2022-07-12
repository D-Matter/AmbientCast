#!/bin/bash

mkdir -p /var/ambientcast/acme/challenges || true

if [ -f /var/ambientcast/acme/default.crt ]; then
    rm -rf /var/ambientcast/acme/default.key || true
    rm -rf /var/ambientcast/acme/default.crt || true
fi

# Generate a self-signed certificate if one doesn't exist in the certs path.
if [ ! -f /var/ambientcast/acme/default.crt ]; then
    echo "Generating self-signed certificate..."

    openssl req -new -nodes -x509 -subj "/C=US/ST=Texas/L=Austin/O=IT/CN=localhost" \
        -days 365 -extensions v3_ca \
        -keyout /var/ambientcast/acme/default.key \
        -out /var/ambientcast/acme/default.crt
fi

if [ ! -f /var/ambientcast/acme/ssl.crt ]; then
    ln -s /var/ambientcast/acme/default.key /var/ambientcast/acme/ssl.key
    ln -s /var/ambientcast/acme/default.crt /var/ambientcast/acme/ssl.crt
fi

chown -R ambientcast:ambientcast /var/ambientcast/acme || true
chmod -R u=rwX,go=rX /var/ambientcast/acme || true
