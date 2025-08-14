#!/bin/bash
set -e

# Make sure moodledata directory has correct permissions
mkdir -p /var/www/moodledata
chmod 777 /var/www/moodledata

# Make sure config.php is readable by www-data
if [ -f /tmp/config.php ]; then
    chown www-data:www-data /tmp/config.php
    chmod 644 /tmp/config.php
    # Create a symlink to the readable config file in a writable location
    mkdir -p /tmp/html
    ln -sf /tmp/config.php /tmp/html/config.php
    # Add the symlinked directory to PHP include path
    echo "include_path = '.:${include_path}:/tmp/html'" > /usr/local/etc/php/conf.d/include-path.ini
fi

# Enable the test virtual host
if [ -f /etc/apache2/sites-available/test.conf ]; then
    a2ensite test
    # Make sure the test directory has correct permissions
    chown -R www-data:www-data /test
    chmod -R 755 /test
fi

# Auto-setup Moodle database if not exists
if [ -f /var/www/html/init-moodle.sh ]; then
    echo "Starting Moodle auto-initialization..."
    cd /var/www/html
    
    # Run setup in background and wait for completion
    ./init-moodle.sh &
    MOODLE_PID=$!
    
    # Wait for setup to complete (max 10 minutes)
    echo "Waiting for Moodle setup to complete..."
    wait $MOODLE_PID
    
    if [ $? -eq 0 ]; then
        echo "Moodle setup completed successfully!"
    else
        echo "Moodle setup failed, but continuing with container startup..."
    fi
fi

# Execute the command provided as arguments (typically apache2-foreground)
exec "$@" 