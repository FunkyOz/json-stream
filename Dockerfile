# Global ARG for PHP version (supported: 8.0, 8.1, 8.2, 8.3, 8.4, 8.5)
ARG PHP_VERSION=8.0

FROM php:${PHP_VERSION}-cli-alpine

LABEL maintainer="lorenzo.dessimoni@gmail.com" \
      version="1.0"

# Install runtime dependencies
RUN apk add --no-cache \
    bash \
    git \
    libstdc++ \
    vim \
    nano \
    curl \
    aspell \
    aspell-en

# Install build dependencies for PHP extensions
RUN apk add --no-cache --virtual .build-deps \
    $PHPIZE_DEPS \
    autoconf \
    g++ \
    make \
    linux-headers

# Install Xdebug
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

# Remove build dependencies
RUN apk del .build-deps

# Install Composer
ARG COMPOSER_VERSION=2.9.2
RUN curl -L -o /usr/local/bin/composer \
    https://getcomposer.org/download/${COMPOSER_VERSION}/composer.phar \
    && chmod +x /usr/local/bin/composer

# Verify installations
RUN php -v && composer --version

# Create non-root user
ARG USER_ID=1000
ARG GROUP_ID=1000
RUN addgroup -g ${GROUP_ID} app \
    && adduser -D -u ${USER_ID} -G app app

# Create Composer directories with proper permissions
RUN mkdir -p /home/app/.composer/cache \
    && chown -R app:app /home/app/.composer

# Set working directory
WORKDIR /app

# Change ownership
RUN chown -R app:app /app

# Switch to non-root user
USER app

# Set default command
CMD ["bash"]

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD php -v || exit 1
