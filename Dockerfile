#
# Icecast build stage (for later copy)
#
FROM dmatterhub/icecast-kh-ac:2.4.0-kh15-ac2 AS icecast

#
# MariaDB stage (for later copy)
#
FROM mariadb:10.7-focal AS mariadb

#
# Golang dependencies build step
#
FROM golang:1.17-buster AS dockerize

RUN apt-get update \
    && apt-get install -y --no-install-recommends openssl git

RUN go install github.com/jwilder/dockerize@latest

#
# Final build image
#
FROM ubuntu:focal

ENV TZ="UTC"

# Add Dockerize
COPY --from=dockerize /go/bin/dockerize /usr/local/bin

# Import Icecast-KH from build container
COPY --from=icecast /usr/local/bin/icecast /usr/local/bin/icecast
COPY --from=icecast /usr/local/share/icecast /usr/local/share/icecast

# Import MariaDB scripts.
COPY --from=mariadb /usr/local/bin/healthcheck.sh /usr/local/bin/db_healthcheck.sh
COPY --from=mariadb /usr/local/bin/docker-entrypoint.sh /usr/local/bin/db_entrypoint.sh

# Run base build process
COPY ./util/docker/common /bd_build/
RUN chmod a+x /bd_build/*.sh \
    && /bd_build/prepare.sh \
    && /bd_build/add_user.sh \
    && /bd_build/cleanup.sh \
    && rm -rf /bd_build

# Build each set of dependencies in their own step for cacheability.
ARG ARM_FULL_BUILD

COPY ./util/docker/supervisor /bd_build/supervisor/
RUN bash /bd_build/supervisor/setup.sh \
    && rm -rf /bd_build/supervisor

COPY ./util/docker/stations /bd_build/stations/
RUN bash /bd_build/stations/setup.sh \
    && rm -rf /bd_build/stations

COPY ./util/docker/web /bd_build/web/
RUN bash /bd_build/web/setup.sh \
    && rm -rf /bd_build/web

COPY ./util/docker/mariadb /bd_build/mariadb/
RUN bash /bd_build/mariadb/setup.sh \
    && rm -rf /bd_build/mariadb

COPY ./util/docker/redis /bd_build/redis/
RUN bash /bd_build/redis/setup.sh \
    && rm -rf /bd_build/redis

#
# START Operations as `ambientcast` user
#
USER ambientcast

WORKDIR /var/ambient/www

COPY --chown=ambientcast:ambientcast ./composer.json ./composer.lock ./
RUN composer install \
    --no-dev \
    --no-ansi \
    --no-autoloader \
    --no-interaction

COPY --chown=ambientcast:ambientcast . .

RUN composer dump-autoload --optimize --classmap-authoritative \
    && touch /var/ambient/.docker

VOLUME ["/var/ambient/stations", "/var/ambient/uploads", "/var/ambient/backups", "/var/ambient/sftpgo/persist", "/var/ambient/servers/shoutcast2"]
ENV PATH="${PATH}:/var/ambient/servers/shoutcast2"

#
# END Operations as `ambientcast` user
#
USER root

EXPOSE 80 2022
EXPOSE 8000-8999

# Sensible default environment variables.
ENV LANG="en_US.UTF-8" \
    DOCKER_IS_STANDALONE="true" \
    APPLICATION_ENV="production" \
    MARIADB_AUTO_UPGRADE=1 \
    MYSQL_HOST="localhost" \
    MYSQL_PORT=3306 \
    MYSQL_USER="ambientcast" \
    MYSQL_PASSWORD="ambient4c457" \
    MYSQL_DATABASE="ambientcast" \
    ENABLE_REDIS="true" \
    REDIS_HOST="localhost" \
    REDIS_PORT=6379 \
    REDIS_DB=1 \
    NGINX_RADIO_PORTS="default" \
    NGINX_WEBDJ_PORTS="default" \
    PREFER_RELEASE_BUILDS="false" \
    COMPOSER_PLUGIN_MODE="false" \
    ADDITIONAL_MEDIA_SYNC_WORKER_COUNT=0 \
    PROFILING_EXTENSION_ENABLED=0 \
    PROFILING_EXTENSION_ALWAYS_ON=0 \
    PROFILING_EXTENSION_HTTP_KEY=dev \
    PROFILING_EXTENSION_HTTP_IP_WHITELIST=*

# Entrypoint and default command
ENTRYPOINT ["tini", "--", "/usr/local/bin/my_init"]
CMD ["--no-main-command"]