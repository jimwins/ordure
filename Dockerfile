FROM php:8.0.9-fpm-alpine

LABEL maintainer="Jim Winstead <jimw@trainedmonkey.com>"

RUN apk add --no-cache \
      mysql-client \
      libzip-dev \
      tzdata \
      zip \
      zlib-dev

RUN docker-php-ext-install \
      bcmath \
      mysqli \
      pdo \
      pdo_mysql \
      zip

WORKDIR /app

COPY . /app

COPY log.conf /usr/local/etc/php-fpm.d/

RUN curl -sS https://getcomposer.org/installer | php \
        && mv composer.phar /usr/local/bin/ \
        && ln -s /usr/local/bin/composer.phar /usr/local/bin/composer

RUN composer install --no-interaction
