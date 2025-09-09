FROM php:7.4-apache

# Install extensions, MySQL client, and dos2unix
RUN apt-get update && apt-get install -y --no-install-recommends \
    default-mysql-client \
    dos2unix \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install mysqli pdo pdo_mysql

# Enable mod_rewrite and mod_headers
RUN a2enmod rewrite headers

# Create saputra user and group
RUN groupadd -g 1000 saputra && \
    useradd -u 1000 -g saputra -m -s /bin/bash saputra

# Set working directory
WORKDIR /var/www/html

# Copy CI3 project
COPY . /var/www/html/

# Fix permissions to use saputra user
RUN chown -R saputra:saputra /var/www/html

# Apache config for CI3
RUN echo '<Directory /var/www/html/>\n\
    AllowOverride All\n\
</Directory>' >> /etc/apache2/apache2.conf

# Create entrypoint script
RUN echo '#!/bin/bash\n\
set -e\n\
\n\
# Wait for database and run setup\n\
echo "Waiting for database..."\n\
sleep 10\n\
\n\
# Run setup script if exists\n\
if [ -f "/var/www/html/setup-celoeapi.sh" ]; then\n\
    echo "Running CeloeAPI setup..."\n\
    cp /var/www/html/setup-celoeapi.sh /tmp/setup-celoeapi.sh\n\
    dos2unix /tmp/setup-celoeapi.sh\n\
    chmod +x /tmp/setup-celoeapi.sh\n\
    cd /var/www/html\n\
    /tmp/setup-celoeapi.sh\n\
fi\n\
\n\
# Run Swagger setup if exists\n\
if [ -f "/var/www/html/setup-swagger-auto.sh" ]; then\n\
    echo "Running Swagger setup..."\n\
    cp /var/www/html/setup-swagger-auto.sh /tmp/setup-swagger-auto.sh\n\
    dos2unix /tmp/setup-swagger-auto.sh\n\
    chmod +x /tmp/setup-swagger-auto.sh\n\
    # Run Swagger setup in background\n\
    nohup /tmp/setup-swagger-auto.sh > /var/log/swagger-setup.log 2>&1 &\n\
fi\n\
\n\
# Start Apache\n\
exec apache2-foreground\n\
' > /usr/local/bin/docker-entrypoint.sh

RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Set entrypoint
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]


