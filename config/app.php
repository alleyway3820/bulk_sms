<?php
require_once __DIR__ . '/env_loader.php';

class AppConfig {
    public static function get($key, $default = null) {
        return $_ENV[$key] ?? getenv($key) ?: $default;
    }
    
    public static function isProduction() {
        return self::get('APP_ENV') === 'production';
    }
    
    public static function isDebug() {
        return self::get('APP_DEBUG', 'false') === 'true';
    }
    
    public static function getAppUrl() {
        return rtrim(self::get('APP_URL', 'http://localhost'), '/');
    }
    
    public static function getAppName() {
        return self::get('APP_NAME', 'BulkVS Portal');
    }
    
    public static function getBulkVSCredentials() {
        return [
            'username' => self::get('BULKVS_API_USERNAME'),
            'password' => self::get('BULKVS_API_PASSWORD'),
            'webhook_url' => self::get('BULKVS_WEBHOOK_URL')
        ];
    }
    
    public static function getSessionConfig() {
        return [
            'lifetime' => (int)self::get('SESSION_LIFETIME', 3600),
            'name' => self::get('SESSION_NAME', 'bulkvs_session'),
            'secure' => self::get('SECURE_COOKIES', 'true') === 'true'
        ];
    }
}
?>