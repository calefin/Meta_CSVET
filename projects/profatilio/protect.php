<?php
/**
 * Laravel Subfolder Security Installer
 * Run: php protect.php
 * 
 * This script automatically creates .htaccess files inside sensitive Laravel directories
 * to prevent direct web access, adding a second layer of defense if mod_rewrite is disabled.
 */

$sensitiveDirs = [
    'app',
    'bootstrap',
    'config',
    'database',
    'resources',
    'routes',
    'storage',
    'tests',
    'vendor'
];

$htaccessContent = <<<EOT
# Prevent direct access to files in this directory
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Order deny,allow
    Deny from all
</IfModule>
EOT;

echo "==================================================\n";
echo " Laravel Subfolder Security Installer\n";
echo "==================================================\n\n";

$createdCount = 0;
$skippedCount = 0;

foreach ($sensitiveDirs as $dir) {
    $dirPath = __DIR__ . DIRECTORY_SEPARATOR . $dir;
    
    if (is_dir($dirPath)) {
        $htaccessPath = $dirPath . DIRECTORY_SEPARATOR . '.htaccess';
        
        if (file_put_contents($htaccessPath, $htaccessContent) !== false) {
            echo "[SUCCESS] Protected directory: /$dir (created .htaccess)\n";
            $createdCount++;
        } else {
            echo "[ERROR] Failed to write .htaccess in: /$dir\n";
        }
    } else {
        echo "[INFO] Skipped /$dir (directory does not exist yet)\n";
        $skippedCount++;
    }
}

echo "\n==================================================\n";
echo "Summary:\n";
echo " - Directories protected: $createdCount\n";
echo " - Directories skipped: $skippedCount (not found)\n";
echo "==================================================\n";
echo "Tip: Run this script again after installing/cloning Laravel to secure all folders.\n";
