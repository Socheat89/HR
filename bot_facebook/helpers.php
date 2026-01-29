<?php
require_once 'config.php';

// ==================== FACEBOOK HELPERS ====================
function fb_send(array $data, bool $is_multipart = false) {
    $url = 'https://graph.facebook.com/v19.0/me/messages?access_token=' . PAGE_ACCESS_TOKEN;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    if ($is_multipart) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: multipart/form-data']);
    } else {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($resp === false) { error_log('fb_send curl error: ' . $err); return; }
    if ($code !== 200) { error_log('fb_send HTTP ' . $code . ' body: ' . $resp); return; }
    $j = json_decode($resp, true);
    if (isset($j['error'])) { error_log('fb_send API error: ' . $resp); }
}

function fb_send_text(string $psid, string $text) {
    fb_send([
        'messaging_type' => 'RESPONSE',
        'recipient' => ['id' => $psid],
        'message' => ['text' => $text],
    ]);
}


/**
 * Fetch with retries from Graph API (returns associative array or null).
 */
function fb_fetch_user_profile(string $psid): ?array {
    $fields = 'name,first_name,last_name,profile_pic';
    $url = 'https://graph.facebook.com/v19.0/' . $psid . '?fields=' . $fields . '&access_token=' . PAGE_ACCESS_TOKEN;

    for ($i = 0; $i < 3; $i++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 12,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($resp !== false && $code === 200) {
            $j = json_decode($resp, true);
            if (is_array($j)) return $j;
        }
        usleep(150000); // 150ms backoff
        if ($err || $code !== 200) {
            error_log("fb_fetch_user_profile retry#$i HTTP $code: " . ($err ?: $resp));
        }
    }
    return null;
}

/**
 * Get user display name for a PSID, using DB cache (<=30 days) with safe fallbacks.
 * - Uses cached name immediately if present.
 * - If cache is stale/missing, tries Graph API (with retries in fb_fetch_user_profile()).
 * - If Graph fails but cache has any non-empty name (even stale), returns that instead of 'អ្នកប្រើប្រាស់'.
 */
function fb_get_user_name_cached(string $psid): string {
    global $conn;

    $cached_full = null;
    $cached_first = null;
    $cached_last  = null;
    $has_cached   = false;

    // 1) Try cache (prefer fresh <=30d, but remember any stale value too)
    if ($conn instanceof mysqli) {
        // Fresh (<=30d)
        if ($stmt = $conn->prepare(
            'SELECT full_name, first_name, last_name FROM fb_user_cache
             WHERE psid = ? AND updated_at >= (NOW() - INTERVAL 30 DAY) LIMIT 1'
        )) {
            $stmt->bind_param('s', $psid);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    $stmt->close();
                    $name = $row['full_name'] ?: trim(($row['first_name'] ?? '').' '.($row['last_name'] ?? ''));
                    if ($name !== '') return $name; // ✅ fresh cache hit
                } else {
                    $stmt->close();
                }
            } else {
                error_log('fb_get_user_name_cached: cache (fresh) stmt->execute() failed');
                $stmt->close();
            }
        } else {
            error_log('fb_get_user_name_cached: cache (fresh) prepare() failed');
        }

        // Stale (any age) — keep as fallback if Graph fails
        if ($stmt = $conn->prepare(
            'SELECT full_name, first_name, last_name FROM fb_user_cache
             WHERE psid = ? LIMIT 1'
        )) {
            $stmt->bind_param('s', $psid);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    $cached_full  = $row['full_name'] ?? null;
                    $cached_first = $row['first_name'] ?? null;
                    $cached_last  = $row['last_name']  ?? null;
                    $has_cached   = true;
                }
                $stmt->close();
            } else {
                error_log('fb_get_user_name_cached: cache (any) stmt->execute() failed');
                $stmt->close();
            }
        } else {
            error_log('fb_get_user_name_cached: cache (any) prepare() failed');
        }
    } else {
        error_log('fb_get_user_name_cached: $conn is not a mysqli instance');
    }

    // 2) Fetch from Graph API (with retries)
    $p = fb_fetch_user_profile($psid); // uses fields: name, first_name, last_name
    if ($p && is_array($p)) {
        $full = $p['name'] ?? trim(($p['first_name'] ?? '').' '.($p['last_name'] ?? ''));
        // Upsert cache
        if ($conn instanceof mysqli) {
            if ($stmt = $conn->prepare(
                'INSERT INTO fb_user_cache (psid, full_name, first_name, last_name, updated_at)
                 VALUES (?,?,?,?,NOW())
                 ON DUPLICATE KEY UPDATE full_name=VALUES(full_name), first_name=VALUES(first_name), last_name=VALUES(last_name), updated_at=NOW()'
            )) {
                $fn = $p['first_name'] ?? null;
                $ln = $p['last_name']  ?? null;
                $fl = $full ?: null;
                $stmt->bind_param('ssss', $psid, $fl, $fn, $ln);
                if (!$stmt->execute()) {
                    error_log('fb_get_user_name_cached: upsert cache execute() failed');
                }
                $stmt->close();
            } else {
                error_log('fb_get_user_name_cached: upsert cache prepare() failed');
            }
        }
        if (!empty($full)) return $full; // ✅ fresh from Graph
    } else {
        error_log('fb_get_user_name_cached: Graph fetch failed for PSID '.$psid);
    }

    // 3) If Graph failed but we have ANY cached value (even stale), use it
    if ($has_cached) {
        $fallback = $cached_full ?: trim(($cached_first ?? '').' '.($cached_last ?? ''));
        if ($fallback !== '') return $fallback; // ✅ better than 'អ្នកប្រើប្រាស់'
    }

    // 4) Ultimate fallback
    return 'Facebook Page';
}


function fb_send_buttons(string $psid, string $text, array $buttons) {
    fb_send([
        'messaging_type' => 'RESPONSE',
        'recipient' => ['id' => $psid],
        'message' => [
            'attachment' => [
                'type' => 'template',
                'payload' => [
                    'template_type' => 'button',
                    'text' => $text,
                    'buttons' => $buttons,
                ],
            ],
        ],
    ]);
}

function fb_send_carousel(string $psid, array $elements) {
    fb_send([
        'messaging_type' => 'RESPONSE',
        'recipient' => ['id' => $psid],
        'message' => [
            'attachment' => [
                'type' => 'template',
                'payload' => [
                    'template_type' => 'generic',
                    'elements' => $elements,
                ],
            ],
        ],
    ]);
}

function fb_send_image_url(string $psid, string $url) {
    fb_send([
        'messaging_type' => 'RESPONSE',
        'recipient' => ['id' => $psid],
        'message' => [
            'attachment' => [
                'type' => 'image',
                'payload' => ['url' => $url, 'is_reusable' => false],
            ],
        ],
    ]);
}

function fb_send_image_file(string $psid, string $local_path) {
    $file = new CURLFile(realpath($local_path));
    fb_send([
        'recipient' => json_encode(['id' => $psid]),
        'messaging_type' => 'RESPONSE',
        'message' => json_encode(['attachment' => ['type' => 'image', 'payload' => ['is_reusable' => false]]]),
        'filedata' => $file,
    ], true);
}

function fb_send_video_url(string $psid, string $url) {
    fb_send([
        'messaging_type' => 'RESPONSE',
        'recipient' => ['id' => $psid],
        'message' => [
            'attachment' => [
                'type' => 'video',
                'payload' => ['url' => $url, 'is_reusable' => false],
            ],
        ],
    ]);
}

// Optional: fallback for images when FB can't fetch Telegram URL directly
function download_to_tmp(string $url, string $prefix = 'fb_img', string $ext = 'jpg'): ?string {
    $tmp = sys_get_temp_dir() . '/' . $prefix . '_' . uniqid('', true) . '.' . $ext;
    $in = @fopen($url, 'rb'); if (!$in) return null;
    $out = @fopen($tmp, 'wb'); if (!$out) { fclose($in); return null; }
    stream_copy_to_stream($in, $out); fclose($in); fclose($out);
    return file_exists($tmp) ? $tmp : null;
}

function fb_send_image_from_url_with_fallback(string $psid, string $url) {
    fb_send_image_url($psid, $url); // try URL method
    $ext = 'jpg';
    if (preg_match('~\.(png|jpeg|jpg|gif)(?:$|\?)~i', $url, $m)) $ext = strtolower($m[1]);
    if ($local = download_to_tmp($url, 'fb_img', $ext)) {
        fb_send_image_file($psid, $local);
        @unlink($local);
    }
}

// ==================== TELEGRAM HELPERS ====================
function tg_send_text(string $text): ?int {
    $url = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendMessage';
    $data = ['chat_id' => TELEGRAM_CHAT_ID, 'text' => mb_substr($text,0,4096), 'parse_mode' => 'Markdown'];
    $resp = http_post_form($url, $data);
    return ($resp['ok'] ?? false) ? ($resp['result']['message_id'] ?? null) : null;
}

function tg_send_photo(string $caption, string $photo_url): ?int {
    $url = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendPhoto';
    $data = ['chat_id' => TELEGRAM_CHAT_ID, 'photo' => $photo_url, 'caption' => mb_substr($caption,0,1024), 'parse_mode' => 'Markdown'];
    $resp = http_post_form($url, $data);
    return ($resp['ok'] ?? false) ? ($resp['result']['message_id'] ?? null) : null;
}

function tg_send_video(string $caption, string $video_url): ?int {
    $url = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendVideo';
    $data = ['chat_id' => TELEGRAM_CHAT_ID, 'video' => $video_url, 'caption' => mb_substr($caption,0,1024), 'parse_mode' => 'Markdown'];
    $resp = http_post_form($url, $data);
    return ($resp['ok'] ?? false) ? ($resp['result']['message_id'] ?? null) : null;
}

function tg_get_file_url(string $file_id): ?string {
    $getFileUrl = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/getFile?file_id=' . urlencode($file_id);
    $resp = json_decode(@file_get_contents($getFileUrl), true);
    if (!($resp['ok'] ?? false)) return null;
    $fp = $resp['result']['file_path'] ?? null;
    if (!$fp) return null;
    return 'https://api.telegram.org/file/bot' . TELEGRAM_BOT_TOKEN . '/' . $fp;
}

function http_post_form(string $url, array $data): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => 1,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT => 20,
    ]);
    $resp = curl_exec($ch);
    if ($resp === false) {
        error_log('curl form error: ' . curl_error($ch));
        curl_close($ch);
        return [];
    }
    curl_close($ch);
    return json_decode($resp, true) ?: [];
}

// ==================== DB HELPERS ====================
function map_store(string $facebook_sender_id, int $telegram_message_id) {
    global $conn;
    $stmt = $conn->prepare('INSERT INTO conversation_mapping (facebook_sender_id, telegram_message_id, timestamp) VALUES (?, ?, NOW())');
    $stmt->bind_param('si', $facebook_sender_id, $telegram_message_id);
    $stmt->execute();
    $stmt->close();
}

function touch_interaction(string $psid) {
    global $conn;
    $stmt = $conn->prepare('INSERT INTO conversation_tracker (user_psid, last_interaction) VALUES (?, NOW()) ON DUPLICATE KEY UPDATE last_interaction = NOW()');
    $stmt->bind_param('s', $psid);
    $stmt->execute();
    $stmt->close();
}