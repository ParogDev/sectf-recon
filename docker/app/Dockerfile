FROM serversideup/php:8.4-fpm-nginx

USER root

# intl/bcmath for Laravel helpers; the recon CLIs the MCP tools shell out to;
# headless Chromium prints the dossier to PDF (app/Services/DossierExporter.php).
RUN install-php-extensions intl bcmath \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        libimage-exiftool-perl \
        dnsutils \
        jq \
        chromium \
        fonts-liberation \
    && rm -rf /var/lib/apt/lists/*

# Match www-data to the host user so files written through the bind mount stay editable.
ARG USER_ID=1000
ARG GROUP_ID=1000
RUN docker-php-serversideup-set-id www-data ${USER_ID}:${GROUP_ID} \
    && docker-php-serversideup-set-file-permissions --owner ${USER_ID}:${GROUP_ID} --service nginx

# Long-running SSE responses from laravel/mcp streaming tools must not be buffered.
# OPcache stays on in dev so vendor/ isn't recompiled per request; timestamps are
# rechecked every second so edits still show up.
ENV NGINX_FASTCGI_BUFFERING=off \
    PHP_OPCACHE_ENABLE=1 \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=1 \
    PHP_OPCACHE_REVALIDATE_FREQ=1 \
    AUTORUN_ENABLED=false \
    SSL_MODE=off

USER www-data
