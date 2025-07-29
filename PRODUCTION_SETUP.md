# Configuración de GestPro en Producción

## Problema Común: Rutas no funcionan en producción

Si la ruta `/GestPro/login` funciona en local pero no en producción, sigue estos pasos:

## 1. Verificar Configuración del Servidor

### Apache (.htaccess)
El archivo `.htaccess` ya está configurado correctamente con:
```apache
RewriteBase /GestPro
```

### Verificar que mod_rewrite esté habilitado:
```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

## 2. Configuración del Entorno

### Archivo .env en producción:
```env
APP_NAME=GestPro
APP_ENV=production
APP_KEY=base64:tu-app-key-generado
APP_DEBUG=false
APP_URL=https://tu-dominio.com/GestPro

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gestpro_production
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_password

JWT_SECRET=tu-jwt-secret-generado
JWT_TTL=60
JWT_REFRESH_TTL=20160
```

## 3. Comandos de Instalación

```bash
# Instalar dependencias
composer install --optimize-autoloader --no-dev

# Generar APP_KEY si no existe
php artisan key:generate

# Generar JWT_SECRET si no existe
php artisan jwt:secret

# Limpiar y cachear configuraciones
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Ejecutar migraciones
php artisan migrate --force

# Establecer permisos
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

## 4. Diagnóstico

Ejecuta el script de diagnóstico:
```bash
php diagnostic.php
```

## 5. Verificar Rutas

### Ruta de prueba:
```
GET https://tu-dominio.com/GestPro/api/GestPro/test
```

### Ruta de login:
```
POST https://tu-dominio.com/GestPro/api/GestPro/login
Content-Type: application/json

{
    "email": "usuario@ejemplo.com",
    "password": "password"
}
```

## 6. Problemas Comunes y Soluciones

### Error 404 - Ruta no encontrada
- Verificar que mod_rewrite esté habilitado
- Verificar que AllowOverride esté configurado en Apache
- Verificar que el .htaccess esté en el directorio correcto

### Error 500 - Error interno del servidor
- Verificar logs en `storage/logs/laravel.log`
- Verificar permisos de archivos
- Verificar configuración de base de datos

### Error CORS
- El middleware CORS ya está configurado
- Verificar que las cabeceras estén siendo enviadas correctamente

### Error de autenticación JWT
- Verificar que JWT_SECRET esté configurado
- Verificar que la tabla users exista y tenga datos
- Verificar que las credenciales sean correctas

## 7. Logs y Debugging

### Habilitar logs detallados temporalmente:
```env
APP_DEBUG=true
LOG_LEVEL=debug
```

### Verificar logs en tiempo real:
```bash
tail -f storage/logs/laravel.log
```

## 8. Verificación Final

1. ✅ La ruta de prueba responde correctamente
2. ✅ El login funciona con credenciales válidas
3. ✅ Los logs muestran información útil
4. ✅ No hay errores en la consola del navegador
5. ✅ Las cabeceras CORS están presentes

## 9. Contacto

Si persisten los problemas, revisa:
- Logs de Apache/Nginx
- Logs de Laravel
- Configuración del servidor web
- Configuración de firewall
- Configuración de DNS 