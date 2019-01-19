FROM php:7.3.0-fpm-alpine

LABEL maintainer="Jim Winstead <jimw@trainedmonkey.com>"

RUN apk add --no-cache \
      mysql-client \
      tzdata

RUN docker-php-ext-install \
      bcmath \
      mysqli \
      pdo \
      pdo_mysql

WORKDIR /app

COPY . /app

RUN curl -sS https://getcomposer.org/installer | php \
        && mv composer.phar /usr/local/bin/ \
        && ln -s /usr/local/bin/composer.phar /usr/local/bin/composer

RUN composer install --no-interaction
