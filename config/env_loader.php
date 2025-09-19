// config/env_loader.php (simple .env loader)
<?php
/**
 * Simple .env file loader
 * Loads environment variables from .env file
 */
function loadEnv($path = __DIR__ . '/../.env') {
    if (!file_exists($path)) {
        return false;
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue; // Skip comments
        }
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        // Remove quotes if present
        if (preg_match('/^(["\'])(.*)\\1$/', $value, $matches)) {
            $value = $matches[2];
        }
        
        if (!array_key_exists($name, $_ENV)) {
            $_ENV[$name] = $value;
        }
        
        if (!getenv($name)) {
            putenv(sprintf('%s=%s', $name, $value));
        }
    }
    
    return true;
}

// Load environment variables
loadEnv();

---

// .gitignore file
# Environment variables
.env
.env.local
.env.production
.env.staging

# Database backups
*.sql
*.dump

# Log files
logs/*.log
*.log

# Temporary files
*.tmp
*.temp

# IDE files
.vscode/
.idea/
*.swp
*.swo

# OS files
.DS_Store
Thumbs.db

# Cache directories
cache/
temp/

# Upload directories (if you add file uploads)
uploads/
files/

# Composer (if you use it later)
vendor/
composer.lock

# Config overrides
config/local.php
config/production.php