<?php
// =========================================================================
// ភាគទី ១៖ ការកំណត់រចនាសម្ព័ន្ធ និង​ አመክញានៃ PHP (PHP LOGIC)
// =========================================================================

// --- ⚙️ ការកំណត់រចនាសម្ព័ន្ធមូលដ្ឋានទិន្នន័យ (សូមបំពេញព័ត៌មាននេះ) ---
$DB_SERVER = "localhost";
$DB_USERNAME = "samann1_facebook-bot";
$DB_PASSWORD = "facebook-bot!@#";
$DB_NAME = "samann1_facebook-bot";


// --- Social Media API Keys (កូដដើមរបស់អ្នក) ---
$FB_ACCESS_TOKEN = 'EAAb4Bh6lZCg4BPR8KgozfVcXugdnT61dEd86FZAmh2repJ4kavFq3ohP6eM7iZAAKmPdJWqkFkFRxZBYbrL6N197oVB37yZBiJ49c8etZCNpDObD5gP3waDZBAAqT5C4iCWoxkSNYEu9l5FjB3r60FmX6KvXsjhLpFXegZBZC72KK7w9Xw5U6k53IEUDfRdz6iWSqVYl7RJfj3QZDZD';
$FB_PAGE_ID = '702125832991384';
$TG_BOT_TOKEN = '8178885681:AAGg694AoxzeiOmPIScwve9JXIqrdSCkmpw';
$TG_CHANNEL_ID = '-1002670098598';
$IG_ACCESS_TOKEN = 'EAAb4Bh6lZCg4BPEZBLUnzBZACiOayO2sodkFmnZB9a1fzGs9mwNl8bhhm7VFc75fqGg8Ea76QP2Tip7TBdQqruzV2GldJ82cIm4ZBQMZCaiZCQS0rQdWrrUyUZBnXcrudlNfpEqmRdg44iiSfbVYGmNjXJDgILwsy62soTd2rCl0NcZALp8hKNpLyucrlS9ZAeqxxyTd7grmtjqxXe9Ek6kLLee5PJ4KYrf6UkvLL1zTEZD';
$IG_USER_ID = '17841476105797400';

// --- ការកំណត់ PHP ---
ini_set('memory_limit', '256M');
ini_set('max_execution_time', 300);
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'errors.log');

// --- PHP Functions ---
function db_connect() {
    global $DB_SERVER, $DB_USERNAME, $DB_PASSWORD, $DB_NAME;
    $conn = new mysqli($DB_SERVER, $DB_USERNAME, $DB_PASSWORD, $DB_NAME);
    if ($conn->connect_error) { die("ការភ្ជាប់ล้มเหลว: " . $conn->connect_error); }
    $conn->set_charset("utf8mb4");
    return $conn;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = db_connect();
    $form_action = $_POST['form_action'] ?? '';
    if ($form_action === 'update_existing') {
        $post_id = $_POST['post_id']; $posted_by = $_POST['posted_by']; $caption = $_POST['caption']; $schedule_time = $_POST['schedule_time'];
        $stmt = $conn->prepare("UPDATE scheduled_posts SET posted_by = ?, caption = ?, schedule_time = ? WHERE id = ?");
        $stmt->bind_param("sssi", $posted_by, $caption, $schedule_time, $post_id);
        if ($stmt->execute()) { header("Location: ?view=manage&status=updated"); }
        else { die("Error updating record: " . $conn->error); }
        $stmt->close(); $conn->close(); exit();
    }
    if ($form_action === 'submit_new') {
        try {
            $caption = htmlspecialchars($_POST['caption'] ?? '', ENT_QUOTES, 'UTF-8');
            $platforms = $_POST['platforms'] ?? [];
            $button_texts = $_POST['button_text'] ?? [];
            $button_urls = $_POST['button_url'] ?? [];
            $posted_by = $_POST['posted_by'] ?? 'Unknown';
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $media_files = [];
            if (isset($_FILES['media']) && !empty($_FILES['media']['name'][0])) {
                foreach ($_FILES['media']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['media']['error'][$key] === UPLOAD_ERR_OK) {
                        $file_name = basename($_FILES['media']['name'][$key]);
                        $new_file_path = $upload_dir . uniqid() . '_' . $file_name;
                        if (move_uploaded_file($tmp_name, $new_file_path)) {
                            $media_files[] = ['path' => $new_file_path, 'url' => 'https://' . $_SERVER['HTTP_HOST'] . preg_replace('/\/[^\/]*$/', '/', $_SERVER['REQUEST_URI']) . $new_file_path, 'is_image' => strpos(mime_content_type($new_file_path), 'image/') === 0, 'name' => $file_name];
                        }
                    }
                }
            }
            if (isset($_POST['schedule_time']) && !empty($_POST['schedule_time'])) {
                if (empty($media_files)) throw new Exception("មិនអាចកំណត់ពេលបង្ហោះដោយគ្មានឯកសារមេឌៀទេ។");
                $media_paths_json = json_encode(array_column($media_files, 'path'));
                $platforms_json = json_encode($platforms);
                $telegram_buttons = [];
                foreach ($button_texts as $key => $text) {
                    if (!empty(trim($text)) && !empty(trim($button_urls[$key]))) { $telegram_buttons[] = ['text' => $text, 'url' => $button_urls[$key]]; }
                }
                $telegram_buttons_json = json_encode($telegram_buttons);
                
                // ***** FIX HERE: ADDED 'status' to the INSERT statement *****
                $stmt = $conn->prepare("INSERT INTO scheduled_posts (posted_by, caption, media_paths, platforms, telegram_buttons, schedule_time, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
                $stmt->bind_param("ssssss", $posted_by, $caption, $media_paths_json, $platforms_json, $telegram_buttons_json, $_POST['schedule_time']);

                if (!$stmt->execute()) { throw new Exception("Database error: " . $stmt->error); }
                $stmt->close(); $conn->close();
                header("Location: ?view=manage&status=created");
                exit();
            } else {
                if (empty($media_files)) { throw new Exception("គ្មានឯកសារត្រឹមត្រូវដើម្បីបង្ហោះភ្លាមៗទេ។"); }
                if (in_array('telegram', $platforms)) {
                    $tg_validation = validateTelegram($TG_BOT_TOKEN, $TG_CHANNEL_ID);
                    if ($tg_validation !== true) { echo "Telegram validation error: $tg_validation<br>"; $platforms = array_diff($platforms, ['telegram']); }
                }
                foreach ($platforms as $platform) {
                    if ($platform === 'facebook') postToFacebook($media_files, $caption, $FB_ACCESS_TOKEN, $FB_PAGE_ID);
                    elseif ($platform === 'telegram') postToTelegram($media_files, $caption, $button_texts, $button_urls, $TG_BOT_TOKEN, $TG_CHANNEL_ID);
                    elseif ($platform === 'instagram') postToInstagram($media_files, $caption, $IG_ACCESS_TOKEN, $IG_USER_ID);
                }
                echo 'ការបង្ហោះបានបញ្ចប់។';
                exit();
            }
        } catch (Exception $e) {
            die("មានបញ្ហាកើតឡើង: " . $e->getMessage());
        }
    }
}
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $conn = db_connect(); $post_id = intval($_GET['id']);
    $stmt_select = $conn->prepare("SELECT media_paths FROM scheduled_posts WHERE id = ?");
    $stmt_select->bind_param("i", $post_id); $stmt_select->execute();
    $result = $stmt_select->get_result();
    if ($result->num_rows > 0) {
        $post = $result->fetch_assoc(); $media_files = json_decode($post['media_paths'], true);
        if (is_array($media_files)) { foreach ($media_files as $file_path) { if (file_exists($file_path)) { unlink($file_path); } } }
    }
    $stmt_select->close();
    $stmt_delete = $conn->prepare("DELETE FROM scheduled_posts WHERE id = ?");
    $stmt_delete->bind_param("i", $post_id);
    if ($stmt_delete->execute()) { header("Location: ?view=manage&status=deleted"); }
    else { die("Error deleting record: " . $conn->error); }
    $stmt_delete->close(); $conn->close(); exit();
}
$view = $_GET['view'] ?? 'create';
if ($view === 'manage') {
    $conn = db_connect();
    $result = $conn->query("SELECT * FROM scheduled_posts WHERE status = 'pending' OR status = 'failed' ORDER BY schedule_time ASC");
    $posts = $result->fetch_all(MYSQLI_ASSOC);
    $conn->close();
}
if ($view === 'edit' && isset($_GET['id'])) {
    $conn = db_connect(); $post_id = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT * FROM scheduled_posts WHERE id = ?");
    $stmt->bind_param("i", $post_id); $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) { die("រកមិនឃើញការបង្ហោះទេ។"); }
    $post_to_edit = $result->fetch_assoc();
    $stmt->close(); $conn->close();
}
function validateTelegram($bot_token, $channel_id) { try { $url = "https://api.telegram.org/bot$bot_token/getMe"; $ch = curl_init($url); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_TIMEOUT, 10); curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); $response = curl_exec($ch); if (curl_errno($ch)) { throw new Exception("cURL error: " . curl_error($ch)); } curl_close($ch); $result = json_decode($response, true); if (!$result || !isset($result['ok']) || !$result['ok']) { return "Invalid Telegram bot token: $response"; } $url = "https://api.telegram.org/bot$bot_token/getChat?chat_id=$channel_id"; $ch = curl_init($url); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_TIMEOUT, 10); curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); $response = curl_exec($ch); if (curl_errno($ch)) { throw new Exception("cURL error: " . curl_error($ch)); } curl_close($ch); $result = json_decode($response, true); if (!$result || !isset($result['ok']) || !$result['ok']) { return "Invalid Telegram channel ID or bot not admin: $response"; } return true; } catch (Exception $e) { return "Telegram validation error: " . $e->getMessage(); } }
function postToFacebook($media_files, $caption, $access_token, $page_id) { try { $images = array_filter($media_files, fn($file) => $file['is_image']); $videos = array_filter($media_files, fn($file) => !$file['is_image']); if (!empty($images)) { $url = "https://graph.facebook.com/v20.0/$page_id/photos"; $photo_ids = []; foreach ($images as $image) { $data = ['access_token' => $access_token, 'message' => $caption, 'published' => false, 'source' => new CURLFile($image['path'])]; $ch = curl_init($url); curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $data); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_TIMEOUT, 30); curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); $response = curl_exec($ch); if (curl_errno($ch)) { throw new Exception("cURL error: " . curl_error($ch)); } curl_close($ch); $response_json = json_decode($response, true); if (isset($response_json['id'])) { $photo_ids[] = $response_json['id']; } } if (!empty($photo_ids)) { $url = "https://graph.facebook.com/v20.0/$page_id/feed"; $data = ['access_token' => $access_token, 'message' => $caption, 'attached_media' => json_encode(array_map(fn($id) => ['media_fbid' => $id], $photo_ids))]; $ch = curl_init($url); curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data)); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_TIMEOUT, 30); curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); curl_exec($ch); if (curl_errno($ch)) { throw new Exception("cURL error: " . curl_error($ch)); } curl_close($ch); } } foreach ($videos as $video) { $url = "https://graph-video.facebook.com/v20.0/$page_id/videos"; $data = ['access_token' => $access_token, 'description' => $caption, 'file' => new CURLFile($video['path'])]; $ch = curl_init($url); curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $data); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_TIMEOUT, 300); curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); curl_exec($ch); if (curl_errno($ch)) { throw new Exception("cURL error: " . curl_error($ch)); } curl_close($ch); } } catch (Exception $e) { error_log("Facebook posting exception: " . $e->getMessage()); } }
function postToTelegram($media_files, $caption, $button_texts, $button_urls, $bot_token, $channel_id) { try { $inline_keyboard = []; for ($i = 0; $i < count($button_texts); $i++) { $text = trim($button_texts[$i]); $url = trim($button_urls[$i]); if (!empty($text) && !empty($url) && filter_var($url, FILTER_VALIDATE_URL)) { $inline_keyboard[] = [['text' => $text, 'url' => $url]]; } } $reply_markup = !empty($inline_keyboard) ? json_encode(['inline_keyboard' => $inline_keyboard]) : ''; $media_groups = array_chunk($media_files, 10); foreach ($media_groups as $group) { if (count($group) > 1) { $url = "https://api.telegram.org/bot$bot_token/sendMediaGroup"; $media = []; foreach ($group as $index => $file) { $media[] = ['type' => $file['is_image'] ? 'photo' : 'video', 'media' => 'attach://file' . $index, ]; } $data = ['chat_id' => $channel_id, 'media' => json_encode($media), ]; foreach ($group as $index => $file) { $data["file$index"] = new CURLFile($file['path']); } $ch = curl_init($url); curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $data); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_TIMEOUT, 60); curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); $response = curl_exec($ch); if (curl_errno($ch)) { throw new Exception("cURL error (sendMediaGroup): " . curl_error($ch)); } curl_close($ch); $response_json = json_decode($response, true); if ($response_json && isset($response_json['ok']) && $response_json['ok']) { if (!empty($caption) || !empty($reply_markup)) { $message_url = "https://api.telegram.org/bot$bot_token/sendMessage"; $message_data = ['chat_id' => $channel_id, 'text' => $caption, 'reply_markup' => $reply_markup, 'parse_mode' => 'HTML' ]; $ch_msg = curl_init($message_url); curl_setopt($ch_msg, CURLOPT_POST, true); curl_setopt($ch_msg, CURLOPT_POSTFIELDS, http_build_query($message_data)); curl_setopt($ch_msg, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch_msg, CURLOPT_TIMEOUT, 30); curl_setopt($ch_msg, CURLOPT_FOLLOWLOCATION, true); curl_exec($ch_msg); if (curl_errno($ch_msg)) { throw new Exception("cURL error (sendMessage): " . curl_error($ch_msg)); } curl_close($ch_msg); } } else { error_log("Telegram media group failed: $response"); } } else { $file = reset($group); $url = "https://api.telegram.org/bot$bot_token/" . ($file['is_image'] ? 'sendPhoto' : 'sendVideo'); $data = ['chat_id' => $channel_id, 'caption' => $caption, ($file['is_image'] ? 'photo' : 'video') => new CURLFile($file['path']), 'reply_markup' => $reply_markup ]; $ch = curl_init($url); curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $data); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_TIMEOUT, 60); curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); curl_exec($ch); if (curl_errno($ch)) { throw new Exception("cURL error: " . curl_error($ch)); } curl_close($ch); } } } catch (Exception $e) { error_log("Telegram posting exception: " . $e->getMessage()); } }
function postToInstagram($media_files, $caption, $access_token, $user_id) { try { if (count($media_files) > 1) { $children = []; foreach ($media_files as $file) { $ch = curl_init($file['url']); curl_setopt($ch, CURLOPT_NOBODY, true); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); curl_setopt($ch, CURLOPT_USERAGENT, 'facebookexternalhit/1.1'); curl_exec($ch); $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch); if ($http_code !== 200) { continue; } $create_url = "https://graph.facebook.com/v20.0/$user_id/media"; $create_data = ['access_token' => $access_token, 'is_carousel_item' => true ]; if ($file['is_image']) { $create_data['image_url'] = $file['url']; } else { $create_data['media_type'] = 'VIDEO'; $create_data['video_url'] = $file['url']; } $ch = curl_init($create_url); curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($create_data)); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_TIMEOUT, 30); curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); $create_response = curl_exec($ch); if (curl_errno($ch)) { throw new Exception("cURL error: " . curl_error($ch)); } curl_close($ch); $create_json = json_decode($create_response, true); if (isset($create_json['id'])) { $children[] = $create_json['id']; } } if (!empty($children)) { $carousel_url = "https://graph.facebook.com/v20.0/$user_id/media"; $carousel_data = ['access_token' => $access_token, 'caption' => $caption, 'media_type' => 'CAROUSEL', 'children' => implode(',', $children) ]; $ch = curl_init($carousel_url); curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($carousel_data)); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_TIMEOUT, 30); curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); $carousel_response = curl_exec($ch); if (curl_errno($ch)) { throw new Exception("cURL error: " . curl_error($ch)); } curl_close($ch); $carousel_json = json_decode($carousel_response, true); if (isset($carousel_json['id'])) { $publish_url = "https://graph.facebook.com/v20.0/$user_id/media_publish"; $publish_data = ['access_token' => $access_token, 'creation_id' => $carousel_json['id'] ]; $ch = curl_init($publish_url); curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($publish_data)); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_TIMEOUT, 30); curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); curl_exec($ch); if (curl_errno($ch)) { throw new Exception("cURL error: " . curl_error($ch)); } curl_close($ch); } } } else { $file = reset($media_files); $ch = curl_init($file['url']); curl_setopt($ch, CURLOPT_NOBODY, true); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); curl_setopt($ch, CURLOPT_USERAGENT, 'facebookexternalhit/1.1'); curl_exec($ch); $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch); if ($http_code !== 200) { return; } $create_url = "https://graph.facebook.com/v20.0/$user_id/media"; $create_data = ['access_token' => $access_token, 'caption' => $caption ]; if ($file['is_image']) { $create_data['image_url'] = $file['url']; } else { $create_data['media_type'] = 'VIDEO'; $create_data['video_url'] = $file['url']; } $ch = curl_init($create_url); curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($create_data)); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_TIMEOUT, 30); curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); $create_response = curl_exec($ch); if (curl_errno($ch)) { throw new Exception("cURL error: " . curl_error($ch)); } curl_close($ch); $create_json = json_decode($create_response, true); if (isset($create_json['id'])) { $publish_url = "https://graph.facebook.com/v20.0/$user_id/media_publish"; $publish_data = ['access_token' => $access_token, 'creation_id' => $create_json['id'] ]; $ch = curl_init($publish_url); curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($publish_data)); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); curl_exec($ch); if (curl_errno($ch)) { throw new Exception("cURL error: " . curl_error($ch)); } curl_close($ch); } } } catch (Exception $e) { error_log("Instagram posting exception: " . $e->getMessage()); } }
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ផ្ទាំងគ្រប់គ្រងការបង្ហោះ</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;500;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <style>
        :root {
            --primary-color: #4A69BD; --primary-hover: #3C56A0;
            --success-color: #2ECC71; --success-hover: #27AE60;
            --danger-color: #E74C3C; --danger-hover: #C0392B;
            --warning-color: #f39c12; --warning-hover: #d35400;
            --background-color: #f4f7f9; --container-bg: #ffffff;
            --text-primary: #34495E; --text-secondary: #7f8c8d;
            --border-color: #dfe4e8; --shadow-color: rgba(0, 0, 0, 0.08);
            --font-main: 'Poppins', 'Kantumruy Pro', sans-serif;
        }
        body { font-family: var(--font-main); background-color: var(--background-color); margin: 0; padding: 20px; color: var(--text-primary); }
        .main-container { max-width: 1200px; margin: auto; }
        .container { background-color: var(--container-bg); padding: 30px 40px; border-radius: 12px; box-shadow: 0 6px 20px var(--shadow-color); border: 1px solid var(--border-color); margin-top: 20px; }
        .form-container { max-width: 700px; margin-left: auto; margin-right: auto; }
        h1 { text-align: center; color: var(--text-primary); margin-top: 0; margin-bottom: 35px; font-size: 26px; font-weight: 700; }
        h1 i { margin-right: 12px; color: var(--primary-color); }
        .tabs { display: flex; justify-content: center; margin-bottom: 20px; background: #e9ecef; border-radius: 10px; padding: 5px; }
        .tabs a { text-decoration: none; color: var(--text-secondary); padding: 10px 25px; border-radius: 8px; font-weight: 500; transition: all 0.3s ease; display: flex; align-items: center; gap: 8px; }
        .tabs a.active { color: #fff; background-color: var(--primary-color); box-shadow: 0 3px 8px rgba(74, 105, 189, 0.3); }
        .label-title { font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 8px; margin-bottom: 10px; font-size: 15px; }
        textarea, input[type="text"], input[type="url"], input[type="datetime-local"], select { width: 100%; padding: 12px 15px; margin-bottom: 20px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 16px; box-sizing: border-box; font-family: var(--font-main); transition: border-color 0.3s, box-shadow 0.3s; }
        textarea:focus, input:focus, select:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(74, 105, 189, 0.2); }
        textarea { resize: vertical; min-height: 120px; margin-bottom: 5px; }
        #media { display: none; }
        .file-upload-label { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px; background-color: #f8f9fa; color: var(--text-secondary); border: 2px dashed var(--border-color); padding: 30px; border-radius: 10px; cursor: pointer; text-align: center; font-weight: 500; transition: background-color 0.3s, border-color 0.3s; }
        .file-upload-label:hover { border-color: var(--primary-color); background-color: #eef2f7; }
        .file-upload-label i { font-size: 2.5rem; color: var(--primary-color); }
        #preview-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 15px; margin-top: 20px; }
        .preview-item { position: relative; border-radius: 8px; overflow: hidden; height: 110px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); cursor: grab; }
        .preview-item img, .preview-item video { width: 100%; height: 100%; object-fit: cover; }
        .preview-item .overlay { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s ease; }
        .preview-item:hover .overlay { opacity: 1; }
        .overlay-btn { background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer; }
        .platform-group { margin: 25px 0; display: flex; gap: 30px; flex-wrap: wrap; }
        .checkbox-label { display: flex; align-items: center; cursor: pointer; font-size: 16px; }
        .checkbox-label input[type="checkbox"] { display: none; }
        .checkbox-label i.fab { font-size: 22px; margin: 0 8px; }
        .fa-facebook { color: #1877F2; } .fa-telegram { color: #2AABEE; } .fa-instagram { color: #E4405F; }
        .checkbox-custom { width: 22px; height: 22px; border: 2px solid var(--border-color); border-radius: 6px; margin-right: 10px; display: inline-block; position: relative; transition: all 0.3s; }
        .checkbox-label input[type="checkbox"]:checked + .checkbox-custom { background-color: var(--primary-color); border-color: var(--primary-color); transform: scale(1.1); }
        .checkbox-custom::after { content: '\f00c'; font-family: "Font Awesome 6 Free"; font-weight: 900; position: absolute; color: white; display: none; font-size: 14px; left: 50%; top: 50%; transform: translate(-50%, -50%); }
        .checkbox-label input[type="checkbox"]:checked + .checkbox-custom::after { display: block; }
        .button-group { display: flex; gap: 10px; margin-bottom: 10px; align-items: center; }
        .button-group input { flex: 1; margin-bottom: 0; }
        button, button[type="submit"] { display: inline-flex; align-items: center; justify-content: center; gap: 10px; background-color: var(--primary-color); color: #fff; border: none; padding: 14px 22px; border-radius: 8px; cursor: pointer; font-size: 16px; font-weight: 600; font-family: var(--font-main); transition: all 0.3s ease; }
        button:hover, button[type="submit"]:hover { background-color: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); }
        button.remove-btn { background-color: var(--danger-color); padding: 10px 15px; }
        button.remove-btn:hover { background-color: var(--danger-hover); }
        .add-button { background-color: var(--success-color); }
        .add-button:hover { background-color: var(--success-hover); }
        .schedule-group { background-color: #f8f9fa; border: 1px solid var(--border-color); padding: 20px; border-radius: 10px; margin: 25px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 14px; border: 1px solid #dfe4e8; text-align: left; vertical-align: middle; }
        th { background-color: #f2f5f7; font-size: 14px; font-weight: 600; }
        tbody tr:hover { background-color: #f8f9fa; }
        td.actions a { color: #fff; text-decoration: none; padding: 8px 12px; border-radius: 6px; margin-right: 5px; display: inline-block; font-size: 14px; transition: all 0.2s; }
        td.actions a:hover { transform: scale(1.1); }
        .edit-btn { background-color: #3498db; }
        .delete-btn { background-color: #e74c3c; }
        .status-badge { color: #fff; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
        .status-pending { background-color: #f39c12; }
        .status-failed { background-color: #c0392b; }
        .media-info { background-color: #ecf0f1; padding: 20px; border-radius: 8px; margin: 25px 0; font-size: 14px; }
        .notification { padding: 15px; margin-bottom: 20px; border-radius: 8px; text-align: center; font-weight: 500; }
        .success { background-color: #d4edda; color: #155724; border-left: 5px solid #28a745; }
        .danger { background-color: #f8d7da; color: #721c24; border-left: 5px solid #dc3545; }
        #loading-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.6); z-index: 10000; justify-content: center; align-items: center; backdrop-filter: blur(5px); }
        .loading-popup { display: flex; align-items: center; gap: 20px; background-color: #ffffff; padding: 25px 40px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); font-size: 1.1rem; font-weight: 500; color: var(--text-primary); }
        .loading-popup .fa-spinner { font-size: 2rem; color: var(--primary-color); }
    </style>
</head>
<body>

<div id="loading-overlay">
    <div class="loading-popup">
        <i class="fas fa-spinner fa-spin"></i>
        <span>កំពុង​ដំណើរការ... សូម​រង់ចាំ</span>
    </div>
</div>

<div class="main-container">
    <div class="tabs">
        <a href="?view=create" class="<?php if($view === 'create' || $view === 'edit') echo 'active'; ?>"><i class="fas fa-plus-circle"></i> បង្កើត / កែសម្រួល</a>
        <a href="?view=manage" class="<?php if($view === 'manage') echo 'active'; ?>"><i class="fas fa-tasks"></i> គ្រប់គ្រងការកំណត់ពេល</a>
    </div>

    <?php if ($view === 'manage'): ?>
    <div class="container">
        <h1><i class="fas fa-calendar-alt"></i> គ្រប់គ្រងការបង្ហោះដែលបានកំណត់ពេល</h1>
        <?php if (isset($_GET['status'])): ?>
            <?php if ($_GET['status'] == 'updated'): ?><div class="notification success">ការកែប្រែត្រូវបានរក្សាទុកដោយជោគជ័យ!</div>
            <?php elseif ($_GET['status'] == 'deleted'): ?><div class="notification danger">ការបង្ហោះត្រូវបានលុបដោយជោគជ័យ!</div>
            <?php elseif ($_GET['status'] == 'created'): ?><div class="notification success">ការបង្ហោះត្រូវបានកំណត់ពេលដោយជោគជ័យ!</div><?php endif; ?>
        <?php endif; ?>
        <div style="overflow-x:auto;">
        <table>
            <thead><tr><th>ID</th><th>អ្នកបង្កើត</th><th>Caption</th><th>ឯកសារ</th><th>ពេលវេលា</th><th>ស្ថានភាព</th><th>សកម្មភាព</th></tr></thead>
            <tbody>
                <?php if (isset($posts) && count($posts) > 0): foreach ($posts as $post): ?>
                    <tr>
                        <td><?php echo $post['id']; ?></td><td><?php echo htmlspecialchars($post['posted_by']); ?></td>
                        <td><?php echo htmlspecialchars(mb_substr($post['caption'], 0, 50)) . '...'; ?></td>
                        <td><?php $media = json_decode($post['media_paths']); echo is_array($media) ? count($media) . ' ឯកសារ' : '0 ឯកសារ'; ?></td>
                        <td><?php echo date('d-M-Y H:i', strtotime($post['schedule_time'])); ?></td>
                        <td><span class="status-badge status-<?php echo $post['status']; ?>"><?php echo $post['status']; ?></span></td>
                        <td class="actions">
                            <a href="?view=edit&id=<?php echo $post['id']; ?>" class="edit-btn" title="កែសម្រួល"><i class="fas fa-edit"></i></a>
                            <a href="?action=delete&id=<?php echo $post['id']; ?>" class="delete-btn" title="លុប" onclick="return confirm('តើអ្នកពិតជាចង់លុបការបង្ហោះនេះមែនទេ?')"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="7" style="text-align: center;">មិនមានការបង្ហោះដែលបានកំណត់ពេលទេ។</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php elseif ($view === 'edit' && isset($post_to_edit)): ?>
    <div class="container form-container">
        <h1><i class="fas fa-edit"></i> កែសម្រួលការបង្ហោះ ID: <?php echo $post_to_edit['id']; ?></h1>
        <form action="" method="POST">
            <input type="hidden" name="form_action" value="update_existing">
            <input type="hidden" name="post_id" value="<?php echo $post_to_edit['id']; ?>">
            <label for="posted_by" class="label-title"><i class="fas fa-user"></i> អ្នកបង្កើត:</label>
            <input type="text" id="posted_by" name="posted_by" value="<?php echo htmlspecialchars($post_to_edit['posted_by']); ?>" required>
            <label for="caption" class="label-title"><i class="fas fa-comment-alt"></i> Caption:</label>
            <textarea id="caption" name="caption" rows="6" required><?php echo htmlspecialchars($post_to_edit['caption']); ?></textarea>
            <label for="schedule_time" class="label-title"><i class="fas fa-clock"></i> ពេលវេលាកំណត់:</label>
            <input type="datetime-local" id="schedule_time" name="schedule_time" value="<?php echo date('Y-m-d\TH:i', strtotime($post_to_edit['schedule_time'])); ?>" required>
            <div class="media-info">
                <strong><i class="fas fa-photo-film"></i> ឯកសារមេឌៀ</strong>
                <p>ការកែប្រែរូបភាព/វីដេអូមិនត្រូវបានគាំទ្រទេ។ បើត្រូវការផ្លាស់ប្ដូរ សូមលុបការបង្ហោះនេះ ហើយបង្កើតថ្មី។</p>
                <ul><?php $media = json_decode($post_to_edit['media_paths']); if(is_array($media)){ foreach($media as $file) { echo '<li>' . basename($file) . '</li>'; } } ?></ul>
            </div>
            <button type="submit" style="width:100%;"><i class="fas fa-save"></i> រក្សាទុកការកែប្រែ</button>
        </form>
    </div>
    <?php else: // Default 'create' view ?>
    <div class="container form-container">
        <h1><i class="fas fa-share-square"></i> បង្កើតការបង្ហោះថ្មី</h1>
        <form id="poster-form" action="" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="form_action" value="submit_new">
            <label for="posted_by" class="label-title"><i class="fas fa-user-edit"></i> បង្ហោះដោយ:</label>
            <input type="text" id="posted_by" name="posted_by" placeholder="បញ្ចូលឈ្មោះរបស់អ្នក" required>
            <label for="caption" class="label-title"><i class="fas fa-comment-dots"></i> Caption:</label>
            <textarea id="caption" name="caption" placeholder="បញ្ចូល Caption របស់អ្នកនៅទីនេះ..." required></textarea>
            <div class="caption-history-group" style="margin-bottom: 20px;">
                <select id="caption-history"><option value="" disabled selected>-- ជ្រើសរើសពីប្រវត្តិ Caption --</option></select>
            </div>
            <div class="file-upload-area" style="margin-bottom: 25px;">
                <label class="label-title"><i class="fas fa-photo-video"></i> ផ្ទុកឡើង និង រៀបចំមេឌៀ:</label>
                <label for="media" class="file-upload-label"><i class="fas fa-cloud-upload-alt"></i><span>ចុចដើម្បីជ្រើសរើសរូបភាព ឬ វីដេអូ</span></label>
                <input type="file" id="media" name="media[]" accept="image/*,video/*" multiple>
                <div id="preview-container"></div>
            </div>
            <label class="label-title"><i class="fas fa-bullhorn"></i> ជ្រើសរើស Platform:</label>
            <div class="platform-group">
                <label class="checkbox-label"><input type="checkbox" name="platforms[]" value="facebook"><span class="checkbox-custom"></span> <i class="fab fa-facebook"></i> Facebook</label>
                <label class="checkbox-label"><input type="checkbox" name="platforms[]" value="telegram"><span class="checkbox-custom"></span> <i class="fab fa-telegram"></i> Telegram</label>
                <label class="checkbox-label"><input type="checkbox" name="platforms[]" value="instagram"><span class="checkbox-custom"></span> <i class="fab fa-instagram"></i> Instagram</label>
            </div>
            <label class="label-title"><i class="fas fa-link"></i> ប៊ូតុង Telegram (ស្រេចចិត្ត):</label>
            <div id="buttons-container" style="margin-bottom: 15px;"></div>
            <button type="button" class="add-button" onclick="addTelegramButton(); this.blur();"><i class="fas fa-plus"></i> បន្ថែមប៊ូតុង</button>
            <div class="schedule-group">
                <label class="checkbox-label"><input type="checkbox" id="schedule-toggle"><span class="checkbox-custom"></span> <span>កំណត់ពេលបង្ហោះសម្រាប់ពេលក្រោយ</span></label>
                <div id="schedule-time-container" style="display:none; margin-top:20px;">
                    <label for="schedule-time" class="label-title">ជ្រើសរើសកាលបរិច្ឆេទ និង ពេលវេលា:</label>
                    <input type="datetime-local" id="schedule-time" name="schedule_time">
                </div>
            </div>
            <button type="submit" id="submit-button" style="width:100%;"><i class="fas fa-paper-plane"></i> បង្ហោះឥឡូវនេះ</button>
        </form>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const posterForm = document.getElementById('poster-form');
    if (posterForm) {
        const captionTextarea = document.getElementById('caption');
        const captionHistorySelect = document.getElementById('caption-history');
        const scheduleToggle = document.getElementById('schedule-toggle');
        const scheduleContainer = document.getElementById('schedule-time-container');
        const scheduleInput = document.getElementById('schedule-time');
        const submitButton = document.getElementById('submit-button');
        const fileInput = document.getElementById('media');
        const previewContainer = document.getElementById('preview-container');
        let fileStore = [];
        const MAX_CAPTION_HISTORY = 20;

        const saveCaptionToHistory = (caption) => {
            if (!caption || caption.trim() === '') return;
            let history = JSON.parse(localStorage.getItem('captionHistory')) || [];
            history = history.filter(item => item !== caption);
            history.unshift(caption);
            history = history.slice(0, MAX_CAPTION_HISTORY);
            localStorage.setItem('captionHistory', JSON.stringify(history));
            loadCaptionHistory();
        };

        const loadCaptionHistory = () => {
            const history = JSON.parse(localStorage.getItem('captionHistory')) || [];
            captionHistorySelect.innerHTML = '<option value="" disabled selected>-- ជ្រើសរើសពីប្រវត្តិ Caption --</option>';
            history.forEach(caption => {
                const option = document.createElement('option');
                option.value = caption;
                option.textContent = caption.length > 80 ? caption.substring(0, 80) + '...' : caption;
                captionHistorySelect.appendChild(option);
            });
        };

        captionHistorySelect.addEventListener('change', () => {
            if (captionHistorySelect.value) {
                captionTextarea.value = captionHistorySelect.value;
                captionHistorySelect.selectedIndex = 0;
            }
        });
        
        const saveButtonsToStorage = () => {
            const buttonsContainer = document.getElementById('buttons-container');
            const buttonGroups = buttonsContainer.querySelectorAll('.button-group');
            const buttonsData = [];
            buttonGroups.forEach(group => {
                const textInput = group.querySelector('input[name="button_text[]"]');
                const urlInput = group.querySelector('input[name="button_url[]"]');
                if (textInput && urlInput) { buttonsData.push({ text: textInput.value, url: urlInput.value }); }
            });
            localStorage.setItem('telegramButtons', JSON.stringify(buttonsData));
        };

        const addTelegramButton = (text = '', url = '') => {
            const container = document.getElementById('buttons-container');
            const div = document.createElement('div');
            div.className = 'button-group';
            div.innerHTML = `<input type="text" name="button_text[]" placeholder="អក្សរលើប៊ូតុង" value="${text}" oninput="saveButtonsToStorage()"><input type="url" name="button_url[]" placeholder="URL របស់ប៊ូតុង" value="${url}" oninput="saveButtonsToStorage()"><button type="button" class="remove-btn" onclick="this.parentElement.remove(); saveButtonsToStorage();"><i class="fas fa-trash"></i></button>`;
            container.appendChild(div);
        };
        
        const loadButtonsFromStorage = () => {
            const savedButtons = localStorage.getItem('telegramButtons');
            if (savedButtons && JSON.parse(savedButtons).length > 0) {
                const buttonsData = JSON.parse(savedButtons);
                buttonsData.forEach(button => addTelegramButton(button.text, button.url));
            } else {
                addTelegramButton('TikTok'); addTelegramButton('Facebook'); addTelegramButton('Instagram');
                saveButtonsToStorage();
            }
        };

        window.addTelegramButton = addTelegramButton;
        window.saveButtonsToStorage = saveButtonsToStorage;

        scheduleToggle.addEventListener('change', () => {
            const isScheduling = scheduleToggle.checked;
            scheduleContainer.style.display = isScheduling ? 'block' : 'none';
            scheduleInput.required = isScheduling;
            if (!isScheduling) scheduleInput.value = '';
            submitButton.innerHTML = isScheduling ? '<i class="fas fa-calendar-check"></i> កំណត់ពេលបង្ហោះ' : '<i class="fas fa-paper-plane"></i> បង្ហោះឥឡូវនេះ';
            submitButton.style.backgroundColor = isScheduling ? 'var(--warning-color)' : 'var(--primary-color)';
        });

        new Sortable(previewContainer, { animation: 150, ghostClass: 'sortable-ghost', onEnd: reorderFileStore });
        fileInput.addEventListener('change', handleFileSelection);

        function handleFileSelection(event) {
            const files = Array.from(event.target.files);
            files.forEach(file => { if (!fileStore.some(f => f.file.name === file.name && f.file.size === file.size)) { const fileObject = { id: Date.now() + Math.random(), file: file }; fileStore.push(fileObject); createPreviewElement(fileObject); } });
            event.target.value = null;
        }

        function createPreviewElement({ id, file }) {
            const reader = new FileReader();
            reader.readAsDataURL(file);
            reader.onload = () => {
                const previewItem = document.createElement('div');
                previewItem.className = 'preview-item';
                previewItem.dataset.id = id;
                let mediaElement = file.type.startsWith('image/') ? document.createElement('img') : document.createElement('video');
                mediaElement.src = reader.result;
                const overlay = document.createElement('div');
                overlay.className = 'overlay';
                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'overlay-btn';
                removeBtn.innerHTML = '<i class="fas fa-trash-alt"></i>';
                removeBtn.onclick = (e) => { e.stopPropagation(); handleRemove(id); };
                overlay.appendChild(removeBtn);
                previewItem.appendChild(mediaElement);
                previewItem.appendChild(overlay);
                previewContainer.appendChild(previewItem);
            };
        }

        function handleRemove(id) {
            const itemToRemove = previewContainer.querySelector(`.preview-item[data-id="${id}"]`);
            if (itemToRemove) itemToRemove.remove();
            fileStore = fileStore.filter(f => f.id != id);
            reorderFileStore();
        }
        
        function reorderFileStore() {
            const newFileStore = [];
            previewContainer.querySelectorAll('.preview-item').forEach(item => { const id = item.dataset.id; const fileObject = fileStore.find(f => f.id == id); if (fileObject) newFileStore.push(fileObject); });
            fileStore = newFileStore;
            const dataTransfer = new DataTransfer();
            fileStore.forEach(fileObject => dataTransfer.items.add(fileObject.file));
            fileInput.files = dataTransfer.files;
        }

        posterForm.addEventListener('submit', () => {
            document.getElementById('loading-overlay').style.display = 'flex';
            saveCaptionToHistory(captionTextarea.value.trim());
            reorderFileStore();
        });

        loadButtonsFromStorage();
        loadCaptionHistory();
    }
});
</script>
</body>
</html>