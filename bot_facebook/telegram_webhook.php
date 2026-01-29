<?php
require_once 'helpers.php';

$update = json_decode(file_get_contents('php://input'), true);
if (!$update) { http_response_code(200); exit; }
$message = $update['message'] ?? null; if (!$message) { http_response_code(200); exit; }

// Avoid loops
if (($message['from']['is_bot'] ?? false) === true) { http_response_code(200); exit; }

$chat_id        = $message['chat']['id'] ?? null;
$reply_to_id    = $message['reply_to_message']['message_id'] ?? null;
$media_group_id = $message['media_group_id'] ?? null; // present when user sends multiple media as an album

// --- Resolve target Facebook PSID ---
$psid = resolve_target_psid($reply_to_id, $media_group_id, $chat_id);
if (!$psid) { http_response_code(200); exit; }

// Text
if (isset($message['text'])) {
    fb_send_text($psid, trim($message['text']));
    http_response_code(200); exit;
}

// Photo (Telegram → Facebook): send image first, then caption
if (isset($message['photo'])) {
    $photo_sizes = $message['photo'];
    $file_id = end($photo_sizes)['file_id'];
    $caption = trim($message['caption'] ?? '');

    if ($url = tg_get_file_url($file_id)) {
        // 1) Send the image
        fb_send_image_from_url_with_fallback($psid, $url);

        // (optional) tiny delay to preserve ordering under network jitter
        // usleep(250000); // 250ms

        // 2) Send the caption as a text message (if present)
        if ($caption !== '') {
            fb_send_text($psid, $caption);
        }
    } else {
        error_log('tg_get_file_url failed for photo');
    }
    http_response_code(200); exit;
}

// Video (Telegram → Facebook): send video first, then caption
if (isset($message['video'])) {
    $file_id = $message['video']['file_id'];
    $caption = trim($message['caption'] ?? '');

    if ($url = tg_get_file_url($file_id)) {
        // 1) Send the video
        fb_send_video_url($psid, $url);
        // usleep(250000); // optional small delay

        // 2) Send the caption (if present)
        if ($caption !== '') {
            fb_send_text($psid, $caption);
        }
    }
    http_response_code(200); exit;
}


http_response_code(200);

// ================= Helpers =================
function resolve_target_psid(?int $reply_to_id, ?string $media_group_id, ?int $chat_id): ?string {
    global $conn;

    // 1) If this message is a direct reply, use conversation_mapping immediately
    if ($reply_to_id) {
        $stmt = $conn->prepare('SELECT facebook_sender_id FROM conversation_mapping WHERE telegram_message_id = ? ORDER BY timestamp DESC LIMIT 1');
        $stmt->bind_param('i', $reply_to_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $psid = $res->fetch_assoc()['facebook_sender_id'] ?? null;
        $stmt->close();

        // If it's the first item of an album, persist mapping for media_group_id
        if ($psid && $media_group_id) {
            $stmt = $conn->prepare('INSERT INTO media_group_mapping (media_group_id, chat_id, facebook_sender_id, created_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE facebook_sender_id = VALUES(facebook_sender_id), created_at = NOW()');
            $stmt->bind_param('sis', $media_group_id, $chat_id, $psid);
            $stmt->execute();
            $stmt->close();
        }
        if ($psid) return $psid;
    }

    // 2) If album part without reply, try media_group_id mapping
    if ($media_group_id) {
        $stmt = $conn->prepare('SELECT facebook_sender_id FROM media_group_mapping WHERE media_group_id = ? ORDER BY created_at DESC LIMIT 1');
        $stmt->bind_param('s', $media_group_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $psid = $res->fetch_assoc()['facebook_sender_id'] ?? null;
        $stmt->close();
        if ($psid) return $psid;
    }

    // 3) Fallback: use most recent mapping in this chat within last 10 minutes (optional table recent_reply)
    if ($chat_id) {
        if ($stmt = $conn->prepare('SELECT cm.facebook_sender_id FROM recent_reply rr JOIN conversation_mapping cm ON cm.telegram_message_id = rr.telegram_message_id WHERE rr.chat_id = ? AND rr.created_at >= (NOW() - INTERVAL 10 MINUTE) ORDER BY rr.created_at DESC LIMIT 1')) {
            $stmt->bind_param('i', $chat_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $psid = $res->fetch_assoc()['facebook_sender_id'] ?? null;
            $stmt->close();
            if ($psid) return $psid;
        }
    }

    return null;
}