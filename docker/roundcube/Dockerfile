FROM debian:12

ARG RC_VERSION

ENV ENABLE_INSTALLER no

EXPOSE 8080

RUN apt-get update && apt-get install -y --no-install-recommends \
        apache2 \
        aspell \
        aspell-de \
        aspell-en \
        ca-certificates \
        curl \
        libapache2-mod-php \
        patch \
        php8.2 \
        php-intl \
        php-imagick \
        php-json \
        php-ldap \
        php-mbstring \
        php-mysql \
        php-pspell \
        php-xml \
        php-zip \
    && apt-get clean -y && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

COPY ./rootfs/site.conf /etc/apache2/sites-available/000-default.conf
COPY ./rootfs/php.ini /etc/php/8.2/apache2/conf.d/99-roundcube.ini
COPY ./patches /roundcube-patches

RUN ln -sf /dev/stdout /var/log/apache2/access.log && ln -sf /dev/stdout /var/log/apache2/error.log \
    && chmod 777 /var/log/apache2/ /var/run/apache2/ /var/lock/apache2/ \
    && echo "Listen 8080" > /etc/apache2/ports.conf \
    && echo "OK" > /var/www/html/status.html \
    && a2disconf other-vhosts-access-log.conf && a2enmod rewrite 

WORKDIR /roundcube

RUN	curl -o roundcube.tar.gz -SL https://github.com/roundcube/roundcubemail/releases/download/${RC_VERSION}/roundcubemail-${RC_VERSION}-complete.tar.gz \
	&& tar -xzf roundcube.tar.gz --strip 1 -C /roundcube \
    && chmod 700 /roundcube/installer \
    && chown -R 33:33 /roundcube/installer \
    && chmod 700 /roundcube/config \
    && chown 33:33 /roundcube/config \
    && rm roundcube.tar.gz

RUN mkdir -p /data /roundcube-dist/config && chown 33:33 /data \
    && patch -p1 < /roundcube-patches/001-installer-move-config-to-data.patch \
    && patch -p1 < /roundcube-patches/002-default-log-stdout.patch \
    && mv /roundcube/logs /roundcube-dist/logs \
    && ln -s /data/logs /roundcube/logs \
    && mv /roundcube/temp /roundcube-dist/temp \
    && ln -s /data/temp /roundcube/temp \
    && mv /roundcube/config/mimetypes.php /roundcube-dist/config/mimetypes.php \
    && ln -s /roundcube-dist/config/mimetypes.php /roundcube/config/mimetypes.php

COPY ./rootfs/entrypoint.php /entrypoint.php

USER 33
CMD [ "/usr/bin/php", "/entrypoint.php" ]
