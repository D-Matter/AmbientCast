#!/bin/bash

PUID=${PUID:-1000}
PGID=${PGID:-1000}

groupmod -o -g "$PGID" ambientcast
usermod -o -u "$PUID" ambientcast

echo "Docker 'ambientcast' User UID: $(id -u ambientcast)"
echo "Docker 'ambientcast' User GID: $(id -g ambientcast)"
