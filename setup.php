<?php

// Script de configuración para GestPro
echo "Configurando GestPro...\n";

// Verificar si existe el archivo .env
if (!file_exists('.env')) {
    echo "Creando archivo .env...\n";
    
    $envContent = "APP_NAME=GestPro
APP_ENV=production
APP_KEY=base64:" . base64_encode(random_bytes(32)) . "
APP_DEBUG=false
APP_URL=https://ingeer.co/GestPro

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=sqlite
DB_DATABASE=" . __DIR__ . "/database/database.sqlite

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=database
SESSION_LIFETIME=120

MEMCACHED_HOST=127.0.0.1

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS=\"hello@example.com\"
MAIL_FROM_NAME=\"\${APP_NAME}\"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_HOST=
PUSHER_PORT=443
PUSHER_SCHEME=https
PUSHER_APP_CLUSTER=mt1

VITE_APP_NAME=\"\${APP_NAME}\"
VITE_PUSHER_APP_KEY=\"\${PUSHER_APP_KEY}\"
VITE_PUSHER_HOST=\"\${PUSHER_HOST}\"
VITE_PUSHER_PORT=\"\${PUSHER_PORT}\"
VITE_PUSHER_SCHEME=\"\${PUSHER_SCHEME}\"
VITE_PUSHER_APP_CLUSTER=\"\${PUSHER_APP_CLUSTER}\"

# Sanctum Configuration
SANCTUM_STATEFUL_DOMAINS=ingeer.co,localhost,localhost:3000,127.0.0.1,127.0.0.1:8000
SESSION_DOMAIN=.ingeer.co
SESSION_SECURE_COOKIE=false
SESSION_SAME_SITE=lax";

    file_put_contents('.env', $envContent);
    echo "Archivo .env creado exitosamente.\n";
} else {
    echo "El archivo .env ya existe.\n";
}

// Verificar si la base de datos existe
if (!file_exists('database/database.sqlite')) {
    echo "Creando base de datos SQLite...\n";
    touch('database/database.sqlite');
    echo "Base de datos SQLite creada.\n";
} else {
    echo "La base de datos SQLite ya existe.\n";
}

// Verificar si las migraciones están ejecutadas
echo "Verificando migraciones...\n";
$output = shell_exec('php artisan migrate:status 2>&1');
echo $output;

echo "\nConfiguración completada. Por favor ejecuta:\n";
echo "1. php artisan migrate\n";
echo "2. php artisan db:seed (si tienes seeders)\n";
echo "3. php artisan config:cache\n";
echo "4. php artisan route:cache\n"; 