<?php
// ============================
// Telegram PayWay Bot by Socheat (Final Version)
// ============================

// 1. បញ្ចូល TOKEN Bot របស់អ្នក
$botToken = "7022820650:AAGdMM97-a_FE_wbIWAXMexThKl94nvy6U0"; // 🔴 ប្ដូរ TOKEN របស់អ្នក
$website = "https://api.telegram.org/bot" . $botToken;

// 2. Chat ID នៃ Group ដែលចង់ផ្ញើសារ
$group_chat_id = "-1001234567890"; // 🔴 ប្ដូរ chat_id របស់ group

// 3. Logging ដើម្បី Debug
file_put_contents("log.txt", date("Y-m-d H:i:s") . " | " . file_get_contents("php://input") . "\n", FILE_APPEND);

// 4. ទទួល JSON ពី Telegram និង PayWay
$input = file_get_contents("php://input");
$update = json_decode($input, true);

// 5. បង្កើត file រក្សាទុកទិន្នន័យប្រចាំថ្ងៃ
$today = date("Y-m-d");
$dataFile = "data_{$today}.json";
if (!file_exists($dataFile)) {
    file_put_contents($dataFile, json_encode(["income" => 0, "expense" => 0, "history" => []], JSON_PRETTY_PRINT));
}
$data = json_decode(file_get_contents($dataFile), true);

// 6. Handle PayWay webhook
if (isset($update['amount']) && isset($update['reason'])) {
    $amount = (int)$update['amount'];
    $reason = $update['reason'];

    // បន្ថែមចំណូល
    $data["income"] += $amount;
    $data["history"][] = ["type" => "income", "amount" => $amount, "reason" => $reason];
    file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT));

    // ផ្ញើសារទៅ Group
    sendMessage($group_chat_id, "✅ បានទទួលពី PayWay: +$amount៛ ($reason)");
    exit;
}

// 7. Handle Telegram message (manual commands)
if ($update && isset($update["message"])) {
    $chat_id = $update["message"]["chat"]["id"];
    $text = trim($update["message"]["text"] ?? "");

    if (strpos($text, "/add") === 0) {
        $parts = explode(" ", $text, 3);
        if (count($parts) >= 2 && is_numeric($parts[1])) {
            $amount = (int)$parts[1];
            $reason = $parts[2] ?? "Unknown";
            $data["income"] += $amount;
            $data["history"][] = ["type" => "income", "amount" => $amount, "reason" => $reason];
            file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT));
            sendMessage($chat_id, "✅ បានបន្ថែមចំណូល +$amount៛ ($reason)");
        } else {
            sendMessage($chat_id, "ប្រើបែបនេះ៖ /add 5000 coffee");
        }

    } elseif (strpos($text, "/spend") === 0) {
        $parts = explode(" ", $text, 3);
        if (count($parts) >= 2 && is_numeric($parts[1])) {
            $amount = (int)$parts[1];
            $reason = $parts[2] ?? "Unknown";
            $data["expense"] += $amount;
            $data["history"][] = ["type" => "expense", "amount" => $amount, "reason" => $reason];
            file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT));
            sendMessage($chat_id, "💸 ចំណាយ -$amount៛ ($reason)");
        } else {
            sendMessage($chat_id, "ប្រើបែបនេះ៖ /spend 2000 food");
        }

    } elseif ($text == "/total") {
        $balance = $data["income"] - $data["expense"];
        $msg = "📅 ថ្ងៃទី: $today\n" .
               "💰 ចំណូល: " . $data["income"] . "៛\n" .
               "💸 ចំណាយ: " . $data["expense"] . "៛\n" .
               "📊 សមតុល្យ: $balance៛";
        sendMessage($chat_id, $msg);

    } elseif ($text == "/history") {
        if (empty($data["history"])) {
            sendMessage($chat_id, "មិនទាន់មានប្រវត្តិទេ។");
        } else {
            $msg = "🧾 ប្រវត្តិប្រចាំថ្ងៃ:\n";
            foreach ($data["history"] as $item) {
                $sign = $item["type"] == "income" ? "+" : "-";
                $msg .= "{$sign}{$item["amount"]}៛ ({$item["reason"]})\n";
            }
            sendMessage($chat_id, $msg);
        }

    } else {
        sendMessage($chat_id,
            "សូមប្រើ Command ៖\n" .
            "/add 5000 coffee - បន្ថែមចំណូល\n" .
            "/spend 2000 food - កាត់ចំណាយ\n" .
            "/total - មើលសមតុល្យប្រចាំថ្ងៃ\n" .
            "/history - មើលប្រវត្តិ"
        );
    }
}

// 8. Function ផ្ញើសារ
function sendMessage($chat_id, $message) {
    global $website;
    $url = $website . "/sendMessage";
    $post = [
        'chat_id' => $chat_id,
        'text' => $message
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    $result = curl_exec($ch);
    curl_close($ch);

    file_put_contents("send_log.txt", date("Y-m-d H:i:s") . " | " . $result . "\n", FILE_APPEND);
}
?>
