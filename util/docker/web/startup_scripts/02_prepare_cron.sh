#!/bin/bash

# Touch cron files to fix 'NUMBER OF HARD LINKS > 1' issue. See  https://github.com/phusion/baseimage-docker/issues/198
touch -c /var/spool/cron/crontabs/*
touch -c /etc/crontab
touch -c /etc/cron.*/*
