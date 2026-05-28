<?php
$files = [
    'c:/xampp/htdocs/hr-new/HRM/posts/lessons_documents.php',
    'c:/xampp/htdocs/hr-new/HRM/posts/announcements.php',
    'c:/xampp/htdocs/hr-new/HRM/posts/post_announcements.php',
    'c:/xampp/htdocs/hr-new/HRM/posts/edit_post.php',
    'c:/xampp/htdocs/hr-new/HRM/posts/view_posts.php',
    'c:/xampp/htdocs/hr-new/HRM/posts/post_lesson.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        // Match various styles of assignment
        $content = preg_replace("/\\\$username = 'root';/", "\$username = 'root';", $content);
        $content = preg_replace("/new mysqli\(([^,]+), 'samann1_admin_panel'/", "new mysqli($1, 'root'", $content);
        $content = preg_replace("/new PDO\(([^,]+), 'samann1_admin_panel'/", "new PDO($1, 'root'", $content);
        
        file_put_contents($file, $content);
        echo "Fixed $file\n";
    }
}
?>
