<?php
session_start();
require_once 'config.php';

// Set timezone to Phnom Penh
date_default_timezone_set('Asia/Phnom_Penh');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user info
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>បណ្តាញសង្គម - ឆាត</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#2563eb">
    <link rel="icon" href="https://static-00.iconduck.com/assets.00/fb-messenger-icon-512x512-qimhvycc.png" type="image/png">
    <style>
        .chat-container {
            height: 500px;
            overflow-y: auto;
            scrollbar-width: thin;
            display: flex;
            flex-direction: column;
            gap: 14px;
            padding-bottom: 16px;
            background: linear-gradient(135deg, #e0e7ef 0%, #f8fafc 100%);
            border-radius: 20px;
            box-shadow: 0 6px 32px rgba(59,130,246,0.08);
        }
        .message {
            max-width: 68%;
            word-break: break-word;
            padding: 16px 24px;
            border-radius: 28px;
            margin-bottom: 2px;
            font-size: 1.1rem;
            line-height: 1.7;
            box-shadow: 0 2px 12px rgba(59,130,246,0.10);
            position: relative;
            transition: background 0.2s;
            display: flex;
            flex-direction: column;
        }
        .sent {
            margin-left: auto;
            background: linear-gradient(135deg, #2563eb 0%, #60a5fa 100%);
            color: #fff;
            border-bottom-right-radius: 12px;
            border-bottom-left-radius: 28px;
            border-top-left-radius: 28px;
            border-top-right-radius: 28px;
            box-shadow: 0 2px 16px rgba(59,130,246,0.22);
            align-items: flex-end;
            cursor: pointer;
        }
        .sent:after {
            content: "";
            position: absolute;
            right: -12px;
            bottom: 10px;
            width: 0;
            height: 0;
            border-left: 14px solid transparent;
        }
        .received {
            margin-right: auto;
            background: linear-gradient(135deg, #f1f5f9 0%, #e0e7ef 100%);
            color: #1e293b;
            border-bottom-left-radius: 12px;
            border-bottom-right-radius: 28px;
            border-top-left-radius: 28px;
            border-top-right-radius: 28px;
            box-shadow: 0 2px 12px rgba(59,130,246,0.09);
            align-items: flex-start;
        }
        .received:after {
            content: "";
            position: absolute;
            left: -12px;
            bottom: 10px;
            width: 0;
            height: 0;
            border-right: 14px solid transparent;
        }
        .message-meta {
            font-size: 0.82rem;
            color: #64748b;
            margin-top: 6px;
            text-align: right;
            opacity: 0.85;
        }
        .chat-container::-webkit-scrollbar {
            width: 8px;
            background: #e0e7ef;
            border-radius: 10px;
        }
        .chat-container::-webkit-scrollbar-thumb {
            background: #a5b4fc;
            border-radius: 10px;
        }
        #install-prompt {
            display: none;
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: linear-gradient(135deg, #2563eb 0%, #60a5fa 100%);
            color: white;
            padding: 16px 24px;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.2);
            z-index: 1000;
        }
        #install-prompt button {
            background: #fff;
            color: #2563eb;
            padding: 8px 16px;
            border-radius: 8px;
            margin-left: 10px;
            font-weight: bold;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-100 via-white to-blue-200 min-h-screen font-sans leading-normal tracking-normal">
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                <svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.77 9.77 0 01-4-.8L3 21l1.8-4A7.96 7.96 0 013 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                សូមស្វាគមន៍, <?php echo htmlspecialchars($user['username']); ?>!
            </h2>
            <a href="logout.php" class="bg-red-500 hover:bg-red-600 text-white font-semibold py-2 px-4 rounded-lg shadow transition duration-300">ចាកចេញ</a>
        </div>
        
        <div class="flex flex-col md:flex-row gap-6">
            <div class="md:w-1/3 bg-white rounded-2xl shadow-lg p-4 border border-blue-100">
                <h4 class="text-lg font-semibold text-blue-700 mb-3 flex items-center gap-2">
                    <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87M16 3.13a4 4 0 010 7.75M8 3.13a4 4 0 000 7.75"></path></svg>
                    អ្នកប្រើ
                </h4>
                <ul class="space-y-2" id="user-list">
                    <?php
                    $users = $conn->query("SELECT id, username FROM users WHERE id != $user_id");
                    while ($row = $users->fetch_assoc()) {
                        echo "<li class='cursor-pointer p-3 rounded-xl hover:bg-blue-100 transition duration-200 flex items-center gap-2' data-user-id='{$row['id']}'>
                                <span class='w-3 h-3 bg-green-400 rounded-full inline-block'></span>
                                <span>{$row['username']}</span>
                              </li>";
                    }
                    ?>
                </ul>
            </div>
            <div class="md:w-2/3 bg-white rounded-2xl shadow-lg p-4 border border-blue-100 flex flex-col">
                <div class="flex items-center gap-2 mb-2">
                    <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h12a2 2 0 012 2z"></path></svg>
                    <h3 class="text-xl font-semibold text-blue-700">ឆាតជាមួយអ្នកប្រើផ្សេង</h3>
                </div>
                <div class="chat-container flex-1 p-4 bg-gradient-to-br from-blue-50 via-white to-blue-100 rounded-xl border border-blue-50 shadow-inner" id="chat-box"></div>
                <form id="chat-form" class="mt-4">
                    <input type="hidden" id="receiver_id" name="receiver_id">
                    <input type="hidden" id="edit_message_id" name="edit_message_id">
                    <div class="flex gap-2">
                        <input type="text" id="message" name="message" class="flex-1 p-3 border-2 border-blue-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-400 bg-blue-50" placeholder="វាយសាររបស់អ្នក..." required autocomplete="off">
                        <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-6 rounded-xl shadow transition duration-300">ផ្ញើ</button>
                    </div>
                </form>
            </div>
        </div>
        <div id="install-prompt" class="flex items-center">
            <span>ដំឡើងកម្មវិធីឆាតនេះទៅកាន់ឧបករណ៍របស់អ្នក!</span>
            <button id="install-button">ដំឡើង</button>
            <button id="dismiss-button">បិទ</button>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            let receiverId = null;
            let editingMessageId = null;
            let installPrompt = null;

            // Register service worker
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('/serviceworker.js')
                    .then(reg => console.log('Service Worker registered', reg))
                    .catch(err => console.error('Service Worker registration failed', err));
            }

            // Handle install prompt
            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                installPrompt = e;
                // Show install prompt after 10 seconds if not dismissed
                setTimeout(() => {
                    if (localStorage.getItem('pwaInstallDismissed') !== 'true') {
                        $('#install-prompt').fadeIn();
                    }
                }, 10000);
            });

            // Install button click
            $('#install-button').click(() => {
                if (installPrompt) {
                    installPrompt.prompt();
                    installPrompt.userChoice.then(choiceResult => {
                        if (choiceResult.outcome === 'accepted') {
                            console.log('PWA installed');
                        } else {
                            console.log('PWA installation dismissed');
                        }
                        installPrompt = null;
                        $('#install-prompt').fadeOut();
                    });
                }
            });

            // Dismiss button click
            $('#dismiss-button').click(() => {
                localStorage.setItem('pwaInstallDismissed', 'true');
                $('#install-prompt').fadeOut();
            });

            // Check if app is installed
            window.addEventListener('appinstalled', () => {
                console.log('PWA was installed');
                $('#install-prompt').fadeOut();
            });

            // Existing chat functionality
            $('#user-list li').click(function() {
                receiverId = $(this).data('user-id');
                $('#receiver_id').val(receiverId);
                $('#user-list li').removeClass('bg-blue-100 font-bold');
                $(this).addClass('bg-blue-100 font-bold');
                $('#edit_message_id').val('');
                $('#message').val('');
                editingMessageId = null;
                loadMessages();
            });

            $('#chat-box').on('click', '.sent', function() {
                const messageId = $(this).data('message-id');
                const messageContent = $(this).find('.message-content').text().trim();
                editingMessageId = messageId;
                $('#edit_message_id').val(messageId);
                $('#message').val(messageContent).focus();
            });

            $('#chat-form').submit(function(e) {
                e.preventDefault();
                if (!receiverId) {
                    alert('សូមជ្រើសរើសអ្នកប្រើដើម្បីឆាត!');
                    return;
                }

                $.ajax({
                    url: 'send_message.php',
                    type: 'POST',
                    data: {
                        receiver_id: receiverId,
                        message: $('#message').val(),
                        edit_message_id: editingMessageId
                    },
                    success: function() {
                        $('#message').val('');
                        $('#edit_message_id').val('');
                        editingMessageId = null;
                        loadMessages();
                    }
                });
            });

            function loadMessages() {
                if (receiverId) {
                    $.ajax({
                        url: 'get_messages.php',
                        type: 'GET',
                        data: { receiver_id: receiverId },
                        success: function(data) {
                            $('#chat-box').html(data);
                            $('#chat-box')[0].scrollTop = $('#chat-box')[0].scrollHeight;
                        }
                    });
                }
            }

            setInterval(loadMessages, 2000);
        });
    </script>
</body>
</html>