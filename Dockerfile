FROM php:7.4.4-fpm-alpine

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

RUN curl -sS https://getcomposer.org/installer | php \
        && mv composer.phar /usr/local/bin/ \
        && ln -s /usr/local/bin/composer.phar /usr/local/bin/composer

RUN composer install --no-interaction
