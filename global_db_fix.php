<?php
// Global DB Fix Script
$rootPath = 'c:/xampp/htdocs/hr-new/HRM';

function fixFile($filePath) {
    if (is_dir($filePath)) return;
    if (pathinfo($filePath, PATHINFO_EXTENSION) !== 'php') return;
    
    $content = file_get_contents($filePath);
    $changed = false;
    
    // Check various ways the username might be defined
    if (strpos($content, "'samann1_admin_panel'") !== false) {
        // Replacement 1: Variable assignment
        $newContent = preg_replace("/\\\$username\s*=\s*'samann1_admin_panel';/", "\$username = 'root';", $content);
        if ($newContent !== $content) {
            $content = $newContent;
            $changed = true;
        }
        
        // Replacement 2: mysqli constructor
        $newContent = preg_replace("/new\s+mysqli\(([^,]+),\s*'samann1_admin_panel'/", "new mysqli($1, 'root'", $content);
        if ($newContent !== $content) {
            $content = $newContent;
            $changed = true;
        }
        
        // Replacement 3: PDO constructor
        $newContent = preg_replace("/new\s+PDO\(([^,]+),\s*'samann1_admin_panel'/", "new PDO($1, 'root'", $content);
        if ($newContent !== $content) {
            $content = $newContent;
            $changed = true;
        }
    }
    
    if ($changed) {
        file_put_contents($filePath, $content);
        echo "Fixed: $filePath\n";
    }
}

function iterateDir($dir) {
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            iterateDir($path);
        } else {
            fixFile($path);
        }
    }
}

iterateDir($rootPath);
echo "Cleanup complete.";
?>
