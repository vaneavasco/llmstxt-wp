# PHP 8.1 test environment for LLMs.txt WordPress plugin
FROM php:8.1-cli

LABEL maintainer="LLMs.txt"
LABEL description="PHPUnit test environment for LLMs.txt WordPress plugin"

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    && docker-php-ext-install zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Xdebug for code coverage
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

# Configure Xdebug for coverage
RUN echo "xdebug.mode=coverage" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer files first for better layer caching
COPY composer.json composer.lock* ./

# Install dependencies
RUN composer install --no-scripts --no-autoloader --prefer-dist

# Copy source code
COPY . .

# Regenerate autoloader with all source files
RUN composer dump-autoload --optimize

# Default command: run tests
CMD ["vendor/bin/phpunit"]
