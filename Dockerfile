FROM php:8.4-cli

# zip: ZipArchive in JSignPdfRuntimeService | sockets: donatj/mock-webserver in the tests
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod uga+x /usr/local/bin/install-php-extensions && sync \
 && install-php-extensions \
    sockets \
    zip \
    @composer \
 && rm /usr/local/bin/install-php-extensions

# PharData exceeds the default 128M just to open the JRE tarball
RUN echo 'memory_limit=512M' > /usr/local/etc/php/conf.d/app.ini

WORKDIR /app
