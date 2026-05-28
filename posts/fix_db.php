<?php
$file = 'c:/xampp/htdocs/hr-new/HRM/posts/lessons_documents.php';
$content = file_get_contents($file);

// Fix the mangled part
$content = preg_replace("/\\\$dbname\\\$username = 'root';/", "\$dbname = 'samann1_admin_panel';\n\$username = 'root';", $content);
$content = preg_replace("/\\\$username\\\$username = 'root';/", "", $content);

file_put_contents($file, $content);
echo "File fixed.";
?>
