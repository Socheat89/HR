<?php
/**
 * FB Messenger webhook: verifies, handles postbacks, text, images, videos.
 * Forwards to Telegram with user's Khmer name; stores mapping for replies.
 */
require_once 'helpers.php';

// Verify webhook (GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['hub_mode'], $_GET['hub_verify_token'])) {
    if ($_GET['hub_mode'] === 'subscribe' && $_GET['hub_verify_token'] === VERIFY_TOKEN) {
        echo $_GET['hub_challenge'];
        exit;
    }
}

// Only POST carries events
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(200); exit; }
$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['entry'][0]['messaging'][0])) { error_log('No messaging event: ' . json_encode($input)); http_response_code(200); exit; }
$evt = $input['entry'][0]['messaging'][0];
$psid = $evt['sender']['id'] ?? null; if (!$psid) { http_response_code(200); exit; }

// ignore echoes from our page
if (($evt['message']['is_echo'] ?? false) === true) { http_response_code(200); exit; }

$user_name = fb_get_user_name_cached($psid);

// A) Postbacks
if (isset($evt['postback']['payload'])) {
    $payload = $evt['postback']['payload'];
    touch_interaction($psid);

    if ($tg_id = tg_send_text("សារប៊ូតុងពីអ្នកប្រើប្រាស់ {$user_name}:\nប៊ូតុង: {$payload}")) {
        map_store($psid, $tg_id);
    }

    // Optional: DB keyword-based reply
    $stmt = $conn->prepare('SELECT reply_text, reply_type, buttons_json, carousel_data_json FROM auto_replies WHERE keyword != "*" AND keyword = ? LIMIT 1');
    $stmt->bind_param('s', $payload);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows) {
        $r = $res->fetch_assoc();
        sendConfiguredResponse($psid, $r);
    } else {
        switch ($payload) {
            case 'USER_TAPPED_GET_STARTED':
                fb_send_buttons($psid, 'សូមស្វាគមន៍! តើខ្ញុំអាចជួយអ្វីបាន?', [
                    ['type'=>'postback','title'=>'មើលសេវាកម្ម','payload'=>'VIEW_SERVICES'],
                    ['type'=>'postback','title'=>'ទាក់ទងមកយើង','payload'=>'CONTACT_US'],
                    ['type'=>'web_url','title'=>'ចូលគេហទំព័រ','url'=>'https://app.vvc.asia/','webview_height_ratio'=>'full'],
                ]);
                break;
            case 'VIEW_SERVICES':
                fb_send_text($psid, "សេវាកម្មរបស់យើងរួមមាន:\n- បង្កើត Website\n- បង្កើត Mobile App\n- ប្រឹក្សាយោបល់ផ្នែក Digital Marketing");
                break;
            case 'CONTACT_US':
                fb_send_text($psid, "សូមទាក់ទងមកកាន់លេខ: 012 345 678\nឬតាមរយៈអ៊ីម៉ែល: contact@vvc.asia");
                break;
        }
    }
    $stmt->close();
    http_response_code(200); exit;
}



// B) Messages
if (isset($evt['message'])) {
    // Text
    if (isset($evt['message']['text'])) {
        $txt = trim($evt['message']['text']);
        if ($tg_id = tg_send_text("សារថ្មី {$user_name}:\n\nអត្ថបទ: {$txt}")) {
            map_store($psid, $tg_id);
        }

        $stmt = $conn->prepare('SELECT reply_text, reply_type, buttons_json, carousel_data_json FROM auto_replies WHERE keyword != "*" AND keyword = ? LIMIT 1');
        $stmt->bind_param('s', $txt);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows) {
            touch_interaction($psid);
            $r = $res->fetch_assoc();
            sendConfiguredResponse($psid, $r);
            $stmt->close();
            http_response_code(200); exit;
        }
        $stmt->close();
    }

// Attachments (image/video)
if (isset($evt['message']['attachments']) && is_array($evt['message']['attachments'])) {
    $photos = [];
    foreach ($evt['message']['attachments'] as $att) {
        $type = $att['type'] ?? '';
        if ($type === 'image') {
            $att_payload_url = $att['payload']['url'] ?? '';
            $resolved_url = fb_get_attachment_url($att_payload_url, $psid);
            if ($resolved_url) {
                $photos[] = [
                    'type' => 'photo',
                    'media' => $resolved_url,
                    'caption' => "ឯកសារពីអ្នកប្រើប្រាស់ {$user_name}:\nប្រភេទ: រូបភាព"
                ];
            }
        }
    }
    if (!empty($photos)) {
        // Send as media group
        $url = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendMediaGroup';
        $data = [
            'chat_id' => TELEGRAM_CHAT_ID,
            'media' => json_encode($photos, JSON_UNESCAPED_UNICODE)
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        $resp = curl_exec($ch);
        if ($resp === false) {
            error_log('Telegram sendMediaGroup error: ' . curl_error($ch));
        } else {
            $j = json_decode($resp, true);
            if (!($j['ok'] ?? false)) {
                error_log('Telegram sendMediaGroup API error: ' . $resp);
            } else {
                // store mapping for the first message in the group
                if (isset($j['result'][0]['message_id'])) {
                    map_store($psid, $j['result'][0]['message_id']);
                }
            }
        }
        curl_close($ch);
    }
    touch_interaction($psid);
}

    // One-time per 24h welcome
    $stmt = $conn->prepare('SELECT last_interaction FROM conversation_tracker WHERE user_psid = ? LIMIT 1');
    $stmt->bind_param('s', $psid);
    $stmt->execute();
    $res = $stmt->get_result();
    $send_welcome = true;
    if ($res->num_rows) {
        $row = $res->fetch_assoc();
        if (time() - strtotime($row['last_interaction']) < 86400) $send_welcome = false;
    }
    $stmt->close();

    if ($send_welcome) {
        $kw = '*';
        $stmt = $conn->prepare('SELECT reply_text, reply_type, buttons_json, carousel_data_json FROM auto_replies WHERE keyword = ? LIMIT 1');
        $stmt->bind_param('s', $kw);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows) {
            $r = $res->fetch_assoc();
            sendConfiguredResponse($psid, $r);
            touch_interaction($psid);
        }
        $stmt->close();
    }
}

http_response_code(200);

// ===== Local functions =====
function fb_get_user_name(string $psid): string {
    $url = 'https://graph.facebook.com/v19.0/' . $psid . '?fields=first_name,last_name&access_token=' . PAGE_ACCESS_TOKEN;
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>['Content-Type: application/json'], CURLOPT_TIMEOUT=>10]);
    $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
    if ($resp === false || $code !== 200) { error_log("FB get name error: $code - " . ($err ?: $resp)); return 'អ្នកប្រើប្រាស់មិនស្គាល់'; }
    $d = json_decode($resp, true);
    if (isset($d['first_name'], $d['last_name'])) return $d['first_name'].' '.$d['last_name'];
    if (isset($d['first_name'])) return $d['first_name'];
    return 'អ្នកប្រើប្រាស់មិនស្គាល់';
}

function fb_get_attachment_url(string $attachment_url, string $psid): ?string {
    // If payload already contains full URL (lookaside/scontent), use it.
    if (preg_match('~^https?://~i', $attachment_url)) return $attachment_url;
    // Otherwise resolve by Graph (legacy attachment id)
    $url = 'https://graph.facebook.com/v19.0/' . $attachment_url . '?access_token=' . PAGE_ACCESS_TOKEN;
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>['Content-Type: application/json'], CURLOPT_TIMEOUT=>10]);
    $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
    if ($resp === false || $code !== 200) { error_log("FB get attachment error ($psid): HTTP $code - " . ($err ?: $resp)); return null; }
    $d = json_decode($resp, true);
    return $d['url'] ?? null;
}

function sendConfiguredResponse(string $psid, array $reply_data) {
    $reply_text = $reply_data['reply_text'];
    $reply_type = $reply_data['reply_type'];
    $buttons_json = $reply_data['buttons_json'];
    $carousel_json = $reply_data['carousel_data_json'];

    if (!empty($reply_text) || !empty($buttons_json)) {
        $buttons = !empty($buttons_json) ? json_decode($buttons_json, true) : [];
        if (is_array($buttons) && $buttons) {
            fb_send_buttons($psid, $reply_text ?: 'សូមជ្រើសរើស៖', $buttons);
        } elseif (!empty($reply_text)) {
            fb_send_text($psid, $reply_text);
        }
    }

    if ($reply_type === 'carousel' && !empty($carousel_json)) {
        $elements = json_decode($carousel_json, true);
        if (is_array($elements) && $elements) {
            $base = 'https://app.vvc.asia/bot_facebook';
            foreach ($elements as &$el) {
                if (!empty($el['image_url']) && strpos($el['image_url'], 'http') !== 0) {
                    $el['image_url'] = rtrim($base,'/').'/'.ltrim($el['image_url'],'/');
                }
            }
            unset($el);
            fb_send_carousel($psid, $elements);
        }
    } elseif ($reply_type === 'image') {
        $path = $reply_text;
        if (!empty($path) && file_exists($path)) fb_send_image_file($psid, $path); else fb_send_text($psid, 'សូមអភ័យទោស រកមិនឃើញរូបភាពដែលចង់ផ្ញើទេ');
    }
}