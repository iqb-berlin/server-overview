FROM php:8.3-apache-bookworm AS base
COPY index.php /var/www/html/index.php
COPY config.json /var/www/html/config.json