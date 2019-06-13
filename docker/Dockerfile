FROM php:7.1-cli

RUN apt-get update
RUN apt-get install -y --no-install-recommends zip unzip

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY . /usr/src/s3-analytics

WORKDIR /usr/src/s3-analytics
