FROM debian:9

ARG RC_VERSION
ARG MAIL_CA_URL=https://www.terhaak.de/ca/terhaak.de-ca.pem

VOLUME /data
EXPOSE 8080

RUN apt-get update && apt-get install -y --no-install-recommends \
        apache2 \
        aspell \
        aspell-de \
        aspell-en \
        ca-certificates \
        curl \
        libapache2-mod-php \
        php7.0 \
        php-intl \
        php-imagick \
        php-json \
        php-ldap \
        php-mbstring \
        php-mysql \
        php-pspell \
        php-xml \
        php-zip \
    && apt-get clean -y && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /var/www/html/* /usr/share/doc/* /usr/share/man/* /usr/share/info/*

ADD ${MAIL_CA_URL} /usr/local/share/ca-certificates/mail-ca.crt
RUN chmod 644 /usr/local/share/ca-certificates/mail-ca.crt \
    && update-ca-certificates

COPY ./rootfs/site.conf /etc/apache2/sites-available/000-default.conf
COPY ./rootfs/ports.conf /etc/apache2/ports.conf
COPY ./rootfs/php.ini /etc/php/7.0/apache2/conf.d/99-roundcube.ini

RUN ln -sf /dev/stdout /var/log/apache2/access.log && ln -sf /dev/stdout /var/log/apache2/error.log \
    && chmod 777 /var/log/apache2/ /var/run/apache2/ /var/lock/apache2/ \
    && a2disconf other-vhosts-access-log.conf && a2enmod rewrite 

RUN	curl -o roundcube.tar.gz -SL https://github.com/roundcube/roundcubemail/releases/download/${RC_VERSION}/roundcubemail-${RC_VERSION}-complete.tar.gz \
	&& tar -xzf roundcube.tar.gz --strip 1 -C /var/www/html \
    && mkdir -p /usr/local/lib/roundcube \
    && chmod 700 /var/www/html/installer \
    && chown -R 33:0 /var/www/html/installer \
    && rm roundcube.tar.gz

WORKDIR /var/www/html

RUN mv config /usr/local/lib/roundcube/config \
    && ln -s /data/config config

RUN mv logs /usr/local/lib/roundcube/logs \
    && ln -s /data/logs logs \
    && mv temp /usr/local/lib/roundcube/temp \
    && ln -s /data/temp temp

COPY ./rootfs/entrypoint.sh /entrypoint.sh

USER 33
CMD /entrypoint.sh

