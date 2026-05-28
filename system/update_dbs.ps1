$targetDir = "c:\xampp\htdocs\HR App\App"
$files = Get-ChildItem -Path $targetDir -Recurse -Filter *.php

$dbs = @{
    "samann1_scan_logs_worker_db" = "scan_logs_worker_db@2025";
    "samann1_location_db" = "location@2025";
    "samann1_fingerprint_db" = "Fingerprint@2025";
    "samann1_Fingerprint" = "Fingerprint@2025"; 
    "samann1_file_manager_db" = "file_manager_db";
    "samann1_facebook-bot" = "facebook-bot!@#";
    "samann1_admin_panel" = "admin_panel@2025"
}

foreach ($file in $files) {
    try {
        $c = Get-Content $file.FullName -Raw
        if ($null -eq $c) { continue }
        $orig = $c
        $modified = $false
        
        foreach ($key in $dbs.Keys) {
            $pass = $dbs[$key]
            
            # 1. Remove password if present (regex escape)
            $safePass = [regex]::Escape($pass)
            if ($c -match $safePass) {
                $c = $c -replace $safePass, ""
                $modified = $true
            }
            
            # 2. Replace DB Name/User string with samann1_admin_panel
            # We use case-insensitive replace by default in PS
            $safeKey = [regex]::Escape($key)
            if ($c -match $safeKey) {
                $c = $c -replace $safeKey, "samann1_admin_panel"
                $modified = $true
            }
        }
        
        if ($modified) {
             # 3. Clean up "samann1_admin_panel" when used as USER to "root"
             
             # Constant DB_USER
             $c = $c -replace "define\(\s*['""]DB_USER['""]\s*,\s*['""]samann1_admin_panel['""]\s*\)", "define('DB_USER', 'root')"
             
             # Variables $db_user, $user, $username
             $c = $c -replace "`\$db_user\s*=\s*['""]samann1_admin_panel['""]", "`$db_user = 'root'"
             $c = $c -replace "`\$user\s*=\s*['""]samann1_admin_panel['""]", "`$user = 'root'"
             $c = $c -replace "`\$username\s*=\s*['""]samann1_admin_panel['""]", "`$username = 'root'"
             
             # DB args in new mysqli/PDO: 'samann1_admin_panel', ''
             # Handles "samann1_admin_panel", "" and 'samann1_admin_panel', ''
             # and spaces
             $c = $c -replace "['""]samann1_admin_panel['""]\s*,\s*['""]\s*['""]", "'root', ''"
             
             if ($c -ne $orig) {
                Set-Content -Path $file.FullName -Value $c -Encoding UTF8
                Write-Host "Updated $($file.FullName)"
             }
        }
    } catch {
        Write-Warning "Failed to process $($file.FullName): $_"
    }
}
