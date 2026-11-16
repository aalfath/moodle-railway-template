# Moodle LMS for Railway.
# Base: Moodle HQ's PHP 8.4 + Apache image (all PHP extensions Moodle needs), pinned by digest.
FROM moodlehq/moodle-php-apache:8.4-bookworm@sha256:3e910075c08dc73c8e18732f5e80c8fb9a60ae72eaf52b1ee198bb510e4fd9d6

# Moodle release, taken from the official GitHub tag and checked against its sha256.
ARG MOODLE_VERSION=5.3.1
ARG MOODLE_SHA256=7c2ac3297f2d4cad9732d15bde94d2a7f5b055d57a935406b4fb8124157dfe12

RUN set -eux; \
    curl -fsSL -o /tmp/moodle.tgz "https://github.com/moodle/moodle/archive/refs/tags/v${MOODLE_VERSION}.tar.gz"; \
    echo "${MOODLE_SHA256}  /tmp/moodle.tgz" | sha256sum -c -; \
    rm -rf /var/www/html; mkdir -p /var/www/html; \
    tar -xzf /tmp/moodle.tgz -C /var/www/html --strip-components=1; \
    rm /tmp/moodle.tgz; \
    mkdir -p /var/www/moodledata /var/cache/moodle; \
    chown www-data:www-data /var/www/moodledata /var/cache/moodle

COPY config.php /var/www/html/config.php
COPY moodle.ini /usr/local/etc/php/conf.d/30-railway-moodle.ini
COPY apache-moodle.conf /etc/apache2/conf-enabled/railway-moodle.conf
COPY railway/ /opt/moodle-railway/
RUN chmod +x /opt/moodle-railway/*.sh \
    && sed -ri 's/^Listen 80$/Listen ${PORT}/' /etc/apache2/ports.conf \
    && sed -ri 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/' /etc/apache2/sites-enabled/000-default.conf

ENV PORT=8080 \
    APACHE_DOCUMENT_ROOT=/var/www/html/public
EXPOSE 8080

ENTRYPOINT ["/opt/moodle-railway/start.sh"]
