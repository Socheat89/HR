<?php
// =========================================================================
// == SECTION 1: CONFIGURATION & SETUP
// =========================================================================

// --- ⚙️ DATABASE CONFIGURATION (សូមបំពេញព័ត៌មាននេះ!) ---
$DB_SERVER = "localhost";
$DB_USERNAME = "samann1_facebook-bot";
$DB_PASSWORD = "facebook-bot!@#";
$DB_NAME = "samann1_facebook-bot";

// --- Social Media API Keys (from your original file) ---
$FB_ACCESS_TOKEN = 'EAAb4Bh6lZCg4BPR8KgozfVcXugdnT61dEd86FZAmh2repJ4kavFq3ohP6eM7iZAAKmPdJWqkFkFRxZBYbrL6N197oVB37yZBiJ49c8etZCNpDObD5gP3waDZBAAqT5C4iCWoxkSNYEu9l5FjB3r60FmX6KvXsjhLpFXegZBZC72KK7w9Xw5U6k53IEUDfRdz6iWSqVYl7RJfj3QZDZD'; // Page access token
$FB_PAGE_ID = '702125832991384'; // Page ID

$TG_BOT_TOKEN = '8178885681:AAGg694AoxzeiOmPIScwve9JXIqrdSCkmpw'; // Bot token from BotFather
$TG_CHANNEL_ID = '-1002670098598'; // e.g., @channelusername or chat_id

$IG_ACCESS_TOKEN = 'EAAb4Bh6lZCg4BPEZBLUnzBZACiOayO2sodkFmnZB9a1fzGs9mwNl8bhhm7VFc75fqGg8Ea76QP2Tip7TBdQqruzV2GldJ82cIm4ZBQMZCaiZCQS0rQdWrrUyUZBnXcrudlNfpEqmRdg44iiSfbVYGmNjXJDgILwsy62soTd2rCl0NcZALp8hKNpLyucrlS9ZAeqxxyTd7grmtjqxXe9Ek6kLLee5PJ4KYrf6UkvLL1zTEZD'; // Long-lived access token
$IG_USER_ID = '17841476105797400'; // Business account ID

// --- PHP Settings ---
ini_set('memory_limit', '256M');
ini_set('max_execution_time', 300);
error_reporting(E_ALL);
ini_set('display_errors', 0); // Disable in production
ini_set('log_errors', 1);
ini_set('error_log', 'errors.log');


// =========================================================================
// == SECTION 2: CORE LOGIC (Form Handling & Routing)
// =========================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // --- 1. Process Form Data & Files ---
        $posted_by = htmlspecialchars($_POST['posted_by'] ?? 'Unknown', ENT_QUOTES, 'UTF-8');
        $caption = htmlspecialchars($_POST['caption'] ?? '', ENT_QUOTES, 'UTF-8');
        $platforms = $_POST['platforms'] ?? [];
        $button_texts = $_POST['button_text'] ?? [];
        $button_urls = $_POST['button_url'] ?? [];

        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                throw new Exception("Failed to create uploads directory.");
            }
        }

        $media_files = [];
        $max_file_size = 50 * 1024 * 1024; // 50MB
        if (isset($_FILES['media']) && !empty($_FILES['media']['name'][0])) {
            foreach ($_FILES['media']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['media']['error'][$key] === UPLOAD_ERR_OK) {
                    $file_path = $tmp_name;
                    $file_name = basename($_FILES['media']['name'][$key]);
                    $file_size = $_FILES['media']['size'][$key];
                    $mime_type = mime_content_type($file_path);
                    
                    if ($file_size > $max_file_size) continue; // Skip if too large

                    $new_file_path = $upload_dir . uniqid() . '_' . $file_name;
                    if (move_uploaded_file($file_path, $new_file_path)) {
                        chmod($new_file_path, 0644);
                        $media_files[] = [
                            'path' => $new_file_path,
                            'url' => 'https://app.vvc.asia/' . $new_file_path, // Update with your domain
                            'is_image' => strpos($mime_type, 'image/') === 0,
                            'name' => $file_name
                        ];
                    }
                }
            }
        }
        
        // --- 2. Decide whether to Post Now or Schedule ---
        if (isset($_POST['schedule_time']) && !empty($_POST['schedule_time'])) {
            // --- ACTION: SCHEDULE FOR LATER ---
            if (empty($media_files)) {
                throw new Exception("Cannot schedule a post without media files.");
            }

            $conn = new mysqli($DB_SERVER, $DB_USERNAME, $DB_PASSWORD, $DB_NAME);
            if ($conn->connect_error) {
                throw new Exception("Database connection failed: " . $conn->connect_error);
            }
            $conn->set_charset("utf8mb4");

            $schedule_time = $_POST['schedule_time'];
            $media_paths_json = json_encode(array_column($media_files, 'path'));
            $platforms_json = json_encode($platforms);

            $telegram_buttons = [];
            foreach ($button_texts as $key => $text) {
                if (!empty(trim($text)) && !empty(trim($button_urls[$key]))) {
                    $telegram_buttons[] = ['text' => $text, 'url' => $button_urls[$key]];
                }
            }
            $telegram_buttons_json = json_encode($telegram_buttons);

            $sql = "INSERT INTO scheduled_posts (posted_by, caption, media_paths, platforms, telegram_buttons, schedule_time) VALUES (?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssss", $posted_by, $caption, $media_paths_json, $platforms_json, $telegram_buttons_json, $schedule_time);

            if ($stmt->execute()) {
                echo "Post has been scheduled successfully.";
            } else {
                throw new Exception("Failed to schedule post: " . $stmt->error);
            }
            $stmt->close();
            $conn->close();

        } else {
            // --- ACTION: POST IMMEDIATELY ---
            if (empty($media_files)) {
                throw new Exception("No valid files to post immediately.");
            }

            if (in_array('telegram', $platforms)) {
                $tg_validation = validateTelegram($TG_BOT_TOKEN, $TG_CHANNEL_ID);
                if ($tg_validation !== true) {
                    echo "Telegram validation error: $tg_validation<br>";
                    $platforms = array_diff($platforms, ['telegram']);
                }
            }

            foreach ($platforms as $platform) {
                if ($platform === 'facebook') {
                    postToFacebook($media_files, $caption, $FB_ACCESS_TOKEN, $FB_PAGE_ID);
                } elseif ($platform === 'telegram') {
                    postToTelegram($media_files, $caption, $button_texts, $button_urls, $TG_BOT_TOKEN, $TG_CHANNEL_ID);
                } elseif ($platform === 'instagram') {
                    postToInstagram($media_files, $caption, $IG_ACCESS_TOKEN, $IG_USER_ID);
                }
            }
            echo 'Immediate posting completed.';
        }
    } catch (Exception $e) {
        error_log("Main script exception: " . $e->getMessage());
        http_response_code(500);
        echo "An error occurred: " . $e->getMessage();
    }
}

// =========================================================================
// == SECTION 3: SOCIAL MEDIA FUNCTIONS (Your original uncut code)
// =========================================================================

function validateTelegram($bot_token, $channel_id) {
    try {
        $url = "https://api.telegram.org/bot$bot_token/getMe";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception("cURL error: " . curl_error($ch));
        }
        curl_close($ch);
        $result = json_decode($response, true);
        if (!$result || !isset($result['ok']) || !$result['ok']) {
            error_log("Telegram bot validation failed: $response");
            return "Invalid Telegram bot token: $response";
        }

        $url = "https://api.telegram.org/bot$bot_token/getChat?chat_id=$channel_id";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception("cURL error: " . curl_error($ch));
        }
        curl_close($ch);
        $result = json_decode($response, true);
        if (!$result || !isset($result['ok']) || !$result['ok']) {
            error_log("Telegram channel validation failed: $response");
            return "Invalid Telegram channel ID or bot not admin: $response";
        }
        return true;
    } catch (Exception $e) {
        error_log("Telegram validation exception: " . $e->getMessage());
        return "Telegram validation error: " . $e->getMessage();
    }
}

function postToFacebook($media_files, $caption, $access_token, $page_id) {
    try {
        $images = array_filter($media_files, fn($file) => $file['is_image']);
        $videos = array_filter($media_files, fn($file) => !$file['is_image']);

        if (!empty($images)) {
            $url = "https://graph.facebook.com/v20.0/$page_id/photos";
            $photo_ids = [];
            foreach ($images as $image) {
                $data = [
                    'access_token' => $access_token,
                    'message' => $caption,
                    'published' => false,
                    'source' => new CURLFile($image['path'])
                ];

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                $response = curl_exec($ch);
                if (curl_errno($ch)) {
                    throw new Exception("cURL error: " . curl_error($ch));
                }
                curl_close($ch);

                $response_json = json_decode($response, true);
                if (isset($response_json['id'])) {
                    $photo_ids[] = $response_json['id'];
                } else {
                    error_log("Facebook photo upload failed for {$image['name']}: $response");
                    echo "Facebook photo upload failed for {$image['name']}: $response<br>";
                }
            }

            if (!empty($photo_ids)) {
                $url = "https://graph.facebook.com/v20.0/$page_id/feed";
                $data = [
                    'access_token' => $access_token,
                    'message' => $caption,
                    'attached_media' => json_encode(array_map(fn($id) => ['media_fbid' => $id], $photo_ids))
                ];

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                $response = curl_exec($ch);
                if (curl_errno($ch)) {
                    throw new Exception("cURL error: " . curl_error($ch));
                }
                curl_close($ch);

                echo "Facebook album response: $response<br>";
                error_log("Facebook album response: $response");
            }
        }

        foreach ($videos as $video) {
            $url = "https://graph-video.facebook.com/v20.0/$page_id/videos";
            $data = [
                'access_token' => $access_token,
                'description' => $caption,
                'file' => new CURLFile($video['path'])
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                throw new Exception("cURL error: " . curl_error($ch));
            }
            curl_close($ch);

            echo "Facebook video response for {$video['name']}: $response<br>";
            error_log("Facebook video response for {$video['name']}: $response");
        }
    } catch (Exception $e) {
        error_log("Facebook posting exception: " . $e->getMessage());
        echo "Facebook posting error: " . $e->getMessage() . "<br>";
    }
}

function postToTelegram($media_files, $caption, $button_texts, $button_urls, $bot_token, $channel_id) {
    try {
        $inline_keyboard = [];
        for ($i = 0; $i < count($button_texts); $i++) {
            $text = trim($button_texts[$i]);
            $url = trim($button_urls[$i]);
            if (!empty($text) && !empty($url) && filter_var($url, FILTER_VALIDATE_URL)) {
                $inline_keyboard[] = [['text' => $text, 'url' => $url]];
            } else {
                error_log("Invalid button data at index $i: Text='$text', URL='$url'");
            }
        }
        $reply_markup = !empty($inline_keyboard) ? json_encode(['inline_keyboard' => $inline_keyboard]) : '';
        error_log("Reply markup: $reply_markup");

        $media_groups = array_chunk($media_files, 10);

        foreach ($media_groups as $group) {
            if (count($group) > 1) {
                // Media Group without Caption/Buttons
                $url = "https://api.telegram.org/bot$bot_token/sendMediaGroup";
                $media = [];
                foreach ($group as $index => $file) {
                    $media[] = [
                        'type' => $file['is_image'] ? 'photo' : 'video',
                        'media' => 'attach://file' . $index,
                    ];
                }

                $data = ['chat_id' => $channel_id, 'media' => json_encode($media)];
                foreach ($group as $index => $file) {
                    $data["file$index"] = new CURLFile($file['path']);
                }

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 60);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                $response = curl_exec($ch);
                if (curl_errno($ch)) {
                    throw new Exception("cURL error (sendMediaGroup): " . curl_error($ch));
                }
                curl_close($ch);

                $response_json = json_decode($response, true);
                if ($response_json && isset($response_json['ok']) && $response_json['ok']) {
                    echo "Telegram media group sent successfully.<br>";
                    error_log("Telegram media group response: $response");

                    // Separate message with Caption/Buttons
                    if (!empty($caption) || !empty($reply_markup)) {
                        $message_url = "https://api.telegram.org/bot$bot_token/sendMessage";
                        $message_data = [
                            'chat_id' => $channel_id,
                            'text' => $caption,
                            'reply_markup' => $reply_markup,
                            'parse_mode' => 'HTML'
                        ];

                        $ch_msg = curl_init($message_url);
                        curl_setopt($ch_msg, CURLOPT_POST, true);
                        curl_setopt($ch_msg, CURLOPT_POSTFIELDS, http_build_query($message_data));
                        curl_setopt($ch_msg, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch_msg, CURLOPT_TIMEOUT, 30);
                        curl_setopt($ch_msg, CURLOPT_FOLLOWLOCATION, true);
                        $message_response = curl_exec($ch_msg);
                        if (curl_errno($ch_msg)) {
                            throw new Exception("cURL error (sendMessage): " . curl_error($ch_msg));
                        }
                        curl_close($ch_msg);

                        echo "Telegram caption and buttons sent successfully: $message_response<br>";
                        error_log("Telegram caption/buttons response: $message_response");
                    }

                } else {
                    error_log("Telegram media group failed: $response");
                    echo "Telegram media group failed: $response<br>";
                }
            } else {
                // Single Photo/Video
                $file = reset($group);
                $url = "https://api.telegram.org/bot$bot_token/" . ($file['is_image'] ? 'sendPhoto' : 'sendVideo');
                $data = [
                    'chat_id' => $channel_id,
                    'caption' => $caption,
                    ($file['is_image'] ? 'photo' : 'video') => new CURLFile($file['path']),
                    'reply_markup' => $reply_markup
                ];
                error_log("Single post data: " . json_encode($data));

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                $response = curl_exec($ch);
                if (curl_errno($ch)) {
                    throw new Exception("cURL error: " . curl_error($ch));
                }
                curl_close($ch);

                echo "Telegram single file response for {$file['name']}: $response<br>";
                error_log("Telegram single file response for {$file['name']}: $response");
            }
        }
    } catch (Exception $e) {
        error_log("Telegram posting exception: " . $e->getMessage());
        echo "Telegram posting error: " . $e->getMessage() . "<br>";
    }
}

function postToInstagram($media_files, $caption, $access_token, $user_id) {
    try {
        if (count($media_files) > 1) {
            $children = [];
            foreach ($media_files as $file) {
                // Verify URL accessibility
                $ch = curl_init($file['url']);
                curl_setopt($ch, CURLOPT_NOBODY, true);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_USERAGENT, 'facebookexternalhit/1.1');
                curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($http_code !== 200) {
                    error_log("Inaccessible URL: {$file['url']}, HTTP Code: $http_code");
                    echo "Cannot access URL for {$file['name']}: {$file['url']} (HTTP $http_code)<br>";
                    continue;
                }

                $create_url = "https://graph.facebook.com/v20.0/$user_id/media";
                $create_data = [
                    'access_token' => $access_token,
                    'is_carousel_item' => true
                ];
                if ($file['is_image']) {
                    $create_data['image_url'] = $file['url'];
                } else {
                    $create_data['media_type'] = 'VIDEO';
                    $create_data['video_url'] = $file['url'];
                }

                $ch = curl_init($create_url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($create_data));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                $create_response = curl_exec($ch);
                if (curl_errno($ch)) {
                    throw new Exception("cURL error: " . curl_error($ch));
                }
                curl_close($ch);

                $create_json = json_decode($create_response, true);
                if (isset($create_json['id'])) {
                    $children[] = $create_json['id'];
                } else {
                    error_log("Instagram carousel item creation failed for {$file['name']}: $create_response");
                    echo "Instagram carousel item creation failed for {$file['name']}: $create_response<br>";
                }
            }

            if (!empty($children)) {
                $carousel_url = "https://graph.facebook.com/v20.0/$user_id/media";
                $carousel_data = [
                    'access_token' => $access_token,
                    'caption' => $caption,
                    'media_type' => 'CAROUSEL',
                    'children' => implode(',', $children)
                ];

                $ch = curl_init($carousel_url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($carousel_data));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                $carousel_response = curl_exec($ch);
                if (curl_errno($ch)) {
                    throw new Exception("cURL error: " . curl_error($ch));
                }
                curl_close($ch);

                $carousel_json = json_decode($carousel_response, true);
                if (isset($carousel_json['id'])) {
                    $publish_url = "https://graph.facebook.com/v20.0/$user_id/media_publish";
                    $publish_data = [
                        'access_token' => $access_token,
                        'creation_id' => $carousel_json['id']
                    ];

                    $ch = curl_init($publish_url);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($publish_data));
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    $publish_response = curl_exec($ch);
                    if (curl_errno($ch)) {
                        throw new Exception("cURL error: " . curl_error($ch));
                    }
                    curl_close($ch);

                    echo "Instagram carousel response: $publish_response<br>";
                    error_log("Instagram carousel response: $publish_response");
                } else {
                    error_log("Instagram carousel creation failed: $carousel_response");
                    echo "Instagram carousel creation failed: $carousel_response<br>";
                }
            } else {
                echo "No valid carousel items created.<br>";
            }
        } else {
            $file = reset($media_files);
            $ch = curl_init($file['url']);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'facebookexternalhit/1.1');
            curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code !== 200) {
                error_log("Inaccessible URL: {$file['url']}, HTTP Code: $http_code");
                echo "Cannot access URL for {$file['name']}: {$file['url']} (HTTP $http_code)<br>";
                return;
            }
            
            $create_url = "https://graph.facebook.com/v20.0/$user_id/media";
            $create_data = [
                'access_token' => $access_token,
                'caption' => $caption
            ];
            if ($file['is_image']) {
                $create_data['image_url'] = $file['url'];
            } else {
                $create_data['media_type'] = 'VIDEO';
                $create_data['video_url'] = $file['url'];
            }

            $ch = curl_init($create_url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($create_data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            $create_response = curl_exec($ch);
            if (curl_errno($ch)) {
                throw new Exception("cURL error: " . curl_error($ch));
            }
            curl_close($ch);

            $create_json = json_decode($create_response, true);
            if (isset($create_json['id'])) {
                $publish_url = "https://graph.facebook.com/v20.0/$user_id/media_publish";
                $publish_data = [
                    'access_token' => $access_token,
                    'creation_id' => $create_json['id']
                ];

                $ch = curl_init($publish_url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($publish_data));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                $publish_response = curl_exec($ch);
                if (curl_errno($ch)) {
                    throw new Exception("cURL error: " . curl_error($ch));
                }
                curl_close($ch);

                echo "Instagram single file response for {$file['name']}: $publish_response<br>";
                error_log("Instagram single file response for {$file['name']}: $publish_response");
            } else {
                error_log("Instagram single file creation failed for {$file['name']}: $create_response");
                echo "Instagram single file creation failed for {$file['name']}: $create_response<br>";
            }
        }
    } catch (Exception $e) {
        error_log("Instagram posting exception: " . $e->getMessage());
        echo "Instagram posting error: " . $e->getMessage() . "<br>";
    }
}
?>