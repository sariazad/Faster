FROM mcr.microsoft.com/devcontainers/php:8.3

# Extensions Laravel + Filament need that the base image lacks.
# Built here (as root, at image build time) so every Codespace starts with them enabled.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libzip-dev unzip \
    && docker-php-ext-install intl zip \
    && rm -rf /var/lib/apt/lists/*

# Xdebug is preinstalled in debug mode; it slows Composer and prints connection warnings.
ENV XDEBUG_MODE=off
