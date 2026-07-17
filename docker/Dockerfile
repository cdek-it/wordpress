FROM wordpress:7.0.1-php8.2-apache

RUN curl -sSLf -o /usr/local/bin/install-php-extensions \
        https://github.com/mlocati/docker-php-extension-installer/raw/master/install-php-extensions \
    && chmod +x /usr/local/bin/install-php-extensions \
    && install-php-extensions xdebug

COPY docker/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/* \
    && curl -sSLf -o /tmp/woocommerce.zip https://downloads.wordpress.org/plugin/woocommerce.latest-stable.zip \
    && unzip -q /tmp/woocommerce.zip -d /usr/src/wordpress/wp-content/plugins/ \
    && rm /tmp/woocommerce.zip

# Русский язык по умолчанию: кладём core-переводы в wp-content/languages
# (копируется entrypoint'ом при первом старте на пустом volume) и задаём
# WPLANG в шаблоне wp-config, чтобы мастер установки сразу предлагал ru_RU.
RUN WP_VERSION="$(php -r "require '/usr/src/wordpress/wp-includes/version.php'; echo \$wp_version;")" \
    && mkdir -p /usr/src/wordpress/wp-content/languages \
    && curl -sSLf -o /tmp/ru_RU.zip "https://downloads.wordpress.org/translation/core/${WP_VERSION}/ru_RU.zip" \
    && unzip -q -o /tmp/ru_RU.zip -d /usr/src/wordpress/wp-content/languages/ \
    && rm /tmp/ru_RU.zip \
    && sed -i "/\/\* Add any custom values between/a define( 'WPLANG', 'ru_RU' );" /usr/src/wordpress/wp-config-docker.php

# Переводы плагинов WooCommerce и CDEKDelivery (берём актуальный пакет
# через translations API wordpress.org, версия ядра плагина тут не важна).
RUN mkdir -p /usr/src/wordpress/wp-content/languages/plugins \
    && for slug in woocommerce cdekdelivery; do \
        url="$(curl -sSLf "https://api.wordpress.org/translations/plugins/1.0/?slug=${slug}" \
            | php -r '$d = json_decode(stream_get_contents(STDIN), true); foreach ($d["translations"] as $t) { if ($t["language"] === "ru_RU") { echo $t["package"]; break; } }')"; \
        if [ -n "$url" ]; then \
            curl -sSLf -o /tmp/t.zip "$url" \
            && unzip -q -o /tmp/t.zip -d /usr/src/wordpress/wp-content/languages/plugins/ \
            && rm /tmp/t.zip; \
        fi; \
    done
