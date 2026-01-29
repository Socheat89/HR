<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Login — Live Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white min-h-screen flex items-center justify-center">
    <div class="w-full max-w-md bg-gray-800 p-6 rounded-lg">
        <h1 class="text-2xl font-bold mb-4 text-center">Login to Live Tracker</h1>

        <p class="text-sm text-gray-400 mb-4">Enter the numeric User ID provided by the Admin (Admin → Users → Copy ID).</p>

        <form id="login-form" class="flex gap-2">
            <input id="login-id" type="number" min="1" placeholder="User ID" required class="flex-1 px-3 py-2 rounded bg-gray-700" />
            <button type="submit" class="bg-blue-600 px-4 py-2 rounded">Login</button>
        </form>

        <div id="info" class="mt-4 text-sm text-gray-300"></div>

        <div class="mt-6 text-center text-sm">
            <a href="admin.php" class="text-blue-300">Go to Admin Panel</a>
        </div>
    </div>

    <script>
        const info = document.getElementById('info');
        const form = document.getElementById('login-form');

        async function lookupName(id) {
            try {
                const res = await fetch('admin_api.php?action=summary');
                const json = await res.json();
                const users = json.users || [];
                const found = users.find(u => String(u.id) === String(id));
                return found ? found.name : null;
            } catch (e) {
                return null;
            }
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('login-id').value.trim();
            if (!id) return;
            info.textContent = 'Checking...';
            const name = await lookupName(id);
            // Save id and name (if found) and go to tracker
            localStorage.setItem('tracker_user', id);
            if (name) localStorage.setItem('tracker_user_name', name);
            else localStorage.removeItem('tracker_user_name');
            window.location.href = 'index.php';
        });
    </script>
</body>
</html>
