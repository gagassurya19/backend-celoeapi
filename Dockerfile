FROM php:8.1-apache

# Set environment variables to avoid interactive prompts
ENV DEBIAN_FRONTEND=noninteractive
ENV APT_KEY_DONT_WARN_ON_DANGEROUS_USAGE=DontWarn

# Update package list and install required packages with proper error handling
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libxml2-dev \
    libzip-dev \
    unzip \
    git \
    ca-certificates \
    pkg-config \
    libicu-dev \
    default-mysql-client \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd mysqli zip xml intl opcache

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set recommended PHP settings
COPY php.ini /usr/local/etc/php/

# Set working directory to /var/www/html
WORKDIR /var/www/html

# Expose port
EXPOSE 80

