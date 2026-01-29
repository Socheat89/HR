<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>it_telegram_bot</title>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .chat-container {
      max-width: 600px;
      margin: 20px auto;
      padding: 20px;
      border-radius: 10px;
      background-color: #f9f9f9;
      box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
    }
    .chat-box {
      height: 400px;
      overflow-y: auto;
      border: 1px solid #0e0049;
      border-radius: 5px;
      padding: 10px;
      background: #fff;
      margin-bottom: 15px;
    }
  </style>
</head>
<body>
<div class="container">
  <div class="chat-container">
    <h3 class="text-center">Bot_Chat</h3>
    <div id="chatBox" class="chat-box"></div>
    <form id="chatForm" class="d-flex mb-3" method="POST">
      <input type="text" id="userInput" class="form-control me-2" placeholder="សរសេរបញ្ហារបស់អ្នក......" required>
      <button type="submit" class="btn btn-primary">ផ្ញើរ</button>
    </form>
    <!-- <button id="clearChat" class="btn btn-danger w-100">Clear Chat History</button> -->
  </div>
</div>

<!-- Bootstrap Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>

<!-- JavaScript -->
<script>
  const chatBox = document.getElementById('chatBox');
  const chatForm = document.getElementById('chatForm');
  const userInput = document.getElementById('userInput');
  const clearChatButton = document.getElementById('clearChat');

  const BOT_TOKEN = '7205851039:AAHBOJmY40GvNl7M0X_FN9Ml0Fg2T_KQpb8'; // Replace with your bot token
  const CHAT_ID = '-1002282814819'; // Replace with the Telegram chat ID

  // Function to send a message to Telegram
  async function sendMessageToTelegram(message) {
    const url = `https://api.telegram.org/bot7205851039:AAHBOJmY40GvNl7M0X_FN9Ml0Fg2T_KQpb8/sendMessage`;
    const payload = { chat_id: CHAT_ID, text: message };

    try {
      await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });

      addMessageToChat('You', message);
    } catch (error) {
      console.error('Error sending message to Telegram:', error);
    }
  }
  // Function to delete a message on Telegram
  async function deleteMessageFromTelegram(messageId) {
    const urlDelete = `https://api.telegram.org/bot7205851039:AAHBOJmY40GvNl7M0X_FN9Ml0Fg2T_KQpb8/deleteMessage`;
    const payload = { chat_id: CHAT_ID, message_id: messageId };

    try {
      await fetch(urlDelete, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
    } catch (error) {
      console.error('Error deleting message:', error);
    }
  }

  // Function to fetch replies from Telegram
  async function fetchReplies() {
    const url = `https://api.telegram.org/bot7205851039:AAHBOJmY40GvNl7M0X_FN9Ml0Fg2T_KQpb8/getUpdates`;

    try {
      const response = await fetch(url);
      const data = await response.json();

      if (data.ok) {
        const messages = data.result;

        // Clear the chat box
        chatBox.innerHTML = '';

        // Display messages
        messages.forEach((msg) => {
          if (msg.message && msg.message.text) {
            const sender = msg.message.from.username || msg.message.from.first_name || 'Unknown';
            const messageText = msg.message.text;
            addMessageToChat(sender, messageText);
          }
        });

        chatBox.scrollTop = chatBox.scrollHeight; // Auto-scroll
      } else {
        console.error('Failed to fetch updates:', data.description);
      }
    } catch (error) {
      console.error('Error fetching Telegram replies:', error);
    }
  }

  // Add a message to the chat box
  function addMessageToChat(sender, message) {
    const messageHtml = `<div><strong>${sender}:</strong> ${message}</div>`;
    chatBox.innerHTML += messageHtml;
  }

  // Handle form submission
  chatForm.addEventListener('submit', function (e) {
    e.preventDefault(); // Prevent form from refreshing the page
    const userMessage = userInput.value.trim();
    if (!userMessage) return;

    // Send the user's message to Telegram
    sendMessageToTelegram(userMessage);

    // Clear the input field
    userInput.value = '';
  });

  // Clear chat history
  clearChatButton.addEventListener('click', function () {
    // Clear the chat box
    chatBox.innerHTML = '';

    // Optionally reset the Telegram session (delete messages via API or update the state)
    alert('Chat history cleared!');
  });

  // Fetch replies every 3 seconds
  setInterval(fetchReplies, 1000);
</script>
</body>
</html>
