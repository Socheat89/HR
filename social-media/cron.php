<?php
// កំណត់ Timezone ឲ្យ​ត្រឹមត្រូវ​ទៅតាម​តំបន់​នៃ​ប្រទេស​កម្ពុជា
date_default_timezone_set('Asia/Phnom_Penh');
set_time_limit(0); 

// --- NEW: បង្កើត Function សម្រាប់​សរសេរ Log ---
function log_message($message) {
    $logfile = 'cron.log'; // ឈ្មោះ​ឯកសារ Log
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logfile, "[$timestamp] " . $message . "\n", FILE_APPEND);
}

log_message("Cron job started.");

// បញ្ចូល (include) ឯកសារ​ដើម​របស់​អ្នក
// សូម​ប្រាកដ​ថា​ឈ្មោះ​ឯកសារ 'index.php' នេះ​ត្រឹមត្រូវ
require_once 'post.php';

// -------------------------------------------------------------------
// ចាប់ផ្តើម​ដំណើរការ Cron Job
// -------------------------------------------------------------------

log_message("Connecting to database...");
$conn = db_connect();

$now = date('Y-m-d H:i:s');

$stmt = $conn->prepare("SELECT * FROM scheduled_posts WHERE schedule_time <= ? AND status = 'pending'");
$stmt->bind_param("s", $now);
$stmt->execute();
$result = $stmt->get_result();
$posts_to_run = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (count($posts_to_run) === 0) {
    log_message("No pending posts to run.");
    $conn->close();
    exit(); 
}

log_message("Found " . count($posts_to_run) . " post(s) to run.");

foreach ($posts_to_run as $post) {
    $post_id = $post['id'];
    log_message("Processing Post ID: $post_id");
    
    $update_stmt = $conn->prepare("UPDATE scheduled_posts SET status = 'processing' WHERE id = ?");
    $update_stmt->bind_param("i", $post_id);
    $update_stmt->execute();
    $update_stmt->close();

    try {
        $caption = $post['caption'];
        $platforms = json_decode($post['platforms'], true);
        $media_paths = json_decode($post['media_paths'], true);
        $telegram_buttons = json_decode($post['telegram_buttons'], true);
        $button_texts = array_column($telegram_buttons, 'text');
        $button_urls = array_column($telegram_buttons, 'url');
        
        $media_files = [];
        foreach ($media_paths as $path) {
            if (file_exists($path)) {
                $media_files[] = [
                    'path' => $path,
                    'is_image' => strpos(mime_content_type($path), 'image/') === 0,
                    'name' => basename($path)
                ];
            }
        }
        
        foreach ($platforms as $platform) {
            log_message("Posting to $platform for Post ID: $post_id");
            if ($platform === 'facebook') postToFacebook($media_files, $caption, $FB_ACCESS_TOKEN, $FB_PAGE_ID);
            elseif ($platform === 'telegram') postToTelegram($media_files, $caption, $button_texts, $button_urls, $TG_BOT_TOKEN, $TG_CHANNEL_ID);
            elseif ($platform === 'instagram') postToInstagram($media_files, $caption, $IG_ACCESS_TOKEN, $IG_USER_ID);
        }

        $update_stmt = $conn->prepare("UPDATE scheduled_posts SET status = 'completed' WHERE id = ?");
        $update_stmt->bind_param("i", $post_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        log_message("Post ID: $post_id processed successfully.");

    } catch (Exception $e) {
        $error_message = $e->getMessage();
        $update_stmt = $conn->prepare("UPDATE scheduled_posts SET status = 'failed', failure_reason = ? WHERE id = ?");
        $update_stmt->bind_param("si", $error_message, $post_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        log_message("ERROR processing Post ID: $post_id. Reason: " . $error_message);
    }
}

$conn->close();
log_message("Cron job finished.");
?>