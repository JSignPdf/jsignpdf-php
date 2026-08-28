FROM php:8.4-cli

# zip: ZipArchive in JSignPdfRuntimeService | sockets: donatj/mock-webserver in the tests
RUN apt-get update \
 && apt-get install -y --no-install-recommends libzip-dev unzip \
 && docker-php-ext-install zip sockets \
 && rm -rf /var/lib/apt/lists/*

# PharData exceeds the default 128M just to open the JRE tarball
RUN echo 'memory_limit=512M' > /usr/local/etc/php/conf.d/app.ini

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app
