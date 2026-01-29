<?php
//======================================================================
// SECTION 1: AJAX REQUEST HANDLER & BACKEND LOGIC
//======================================================================
session_start();
include 'db_connect.php'; 

// Function សម្រាប់បង្កើត Table Row HTML ថ្មី (ដើម្បីបញ្ជូនกลับទៅ JavaScript)
function create_link_row_html($conn, $id) {
    $result = $conn->query("SELECT * FROM social_links WHERE id = $id");
    if ($row = $result->fetch_assoc()) {
        $name = htmlspecialchars($row['name']);
        $url = htmlspecialchars($row['url']);
        $icon = htmlspecialchars($row['icon_class']);
        return "<tr data-row-id='{$row['id']}'>
                    <td>{$name}</td>
                    <td>{$url}</td>
                    <td><img src='{$icon}' alt='{$name}' class='icon-img'></td>
                    <td class='actions'>
                        <button class='edit-btn' data-id='{$row['id']}'>កែប្រែ</button>
                        <button class='delete-btn' data-id='{$row['id']}'>លុប</button>
                    </td>
                </tr>";
    }
    return '';
}

// Router សម្រាប់ AJAX Requests: បើមាន Request ណាមួយខាងក្រោមនេះ កូដនឹងដំណើរការហើយ exit
if (
    isset($_POST['update_profile']) || 
    isset($_POST['add_link']) || 
    (isset($_POST['action']) && $_POST['action'] == 'delete') || 
    isset($_POST['update_link']) || 
    (isset($_GET['action']) && $_GET['action'] == 'get_link_details')
) {
    
    header('Content-Type: application/json');
    $response = ['status' => 'error', 'message' => 'An unknown error occurred.'];
    $upload_dir = 'uploads/';

    // LOGIC សម្រាប់គ្រប់គ្រង PROFILE (AJAX)
    if (isset($_POST['update_profile'])) {
        $name = $conn->real_escape_string($_POST['profile_name']);
        $description = $conn->real_escape_string($_POST['profile_description']);
        $old_profile_image = $conn->real_escape_string($_POST['old_profile_image']);
        $new_image_path = $old_profile_image;

        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0 && !empty($_FILES['profile_image']['name'])) {
            $file_name = 'profile_' . time() . '_' . basename($_FILES["profile_image"]["name"]);
            $target_file = $upload_dir . $file_name;
            $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

            if (in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_file)) {
                    if (file_exists($old_profile_image) && strpos($old_profile_image, 'placeholder') === false) {
                        unlink($old_profile_image);
                    }
                    $new_image_path = $target_file;
                }
            }
        }

        $sql = "UPDATE site_profile SET name='$name', description='$description', profile_image_url='$new_image_path' WHERE id=1";
        if ($conn->query($sql)) {
            $response['status'] = 'success';
            $response['message'] = 'ការអាប់ដេត Profile បានជោគជ័យ!';
            $response['newImageUrl'] = $new_image_path;
        } else {
            $response['message'] = 'Query Error: ' . $conn->error;
        }
        echo json_encode($response);
        exit();
    }

    // LOGIC សម្រាប់បន្ថែម LINK (AJAX)
    if (isset($_POST['add_link'])) {
        $name = $conn->real_escape_string($_POST['name']);
        $url = $conn->real_escape_string($_POST['url']);
        $image_path = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $file_name = time() . '_' . basename($_FILES["image"]["name"]);
            $target_file = $upload_dir . $file_name;
            $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
            if (in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'])) {
                if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                    $image_path = $target_file;
                }
            }
        }
        if (!empty($image_path)) {
            $sql = "INSERT INTO social_links (name, url, icon_class) VALUES ('$name', '$url', '$image_path')";
            if ($conn->query($sql)) {
                $new_id = $conn->insert_id;
                $response['status'] = 'success';
                $response['message'] = 'ការបន្ថែម Link បានជោគជ័យ!';
                $response['newRow'] = create_link_row_html($conn, $new_id);
            } else { $response['message'] = "Error: " . $conn->error; }
        } else { $response['message'] = "ការបន្ថែម Link បរាជ័យ! សូមពិនិត្យមើលរូបភាព។"; }
        echo json_encode($response);
        exit();
    }

    // LOGIC សម្រាប់ទាញយក LINK DETAILS (AJAX)
    if (isset($_GET['action']) && $_GET['action'] == 'get_link_details' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $result = $conn->query("SELECT id, name, url, icon_class FROM social_links WHERE id=$id");
        if ($row = $result->fetch_assoc()) {
            $response['status'] = 'success';
            $response['data'] = $row;
        } else { $response['message'] = 'Link not found.'; }
        echo json_encode($response);
        exit();
    }

    // LOGIC សម្រាប់កែប្រែ LINK (AJAX)
    if (isset($_POST['update_link'])) {
        $id = (int)$_POST['id'];
        $name = $conn->real_escape_string($_POST['name']);
        $url = $conn->real_escape_string($_POST['url']);
        $old_image = $conn->real_escape_string($_POST['old_image']);
        $new_image_path = $old_image;

        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0 && !empty($_FILES['image']['name'])) {
            $file_name = time() . '_' . basename($_FILES["image"]["name"]);
            $target_file = $upload_dir . $file_name;
            $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
            if (in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'])) {
                if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                    if (file_exists($old_image)) { unlink($old_image); }
                    $new_image_path = $target_file;
                }
            }
        }
        $sql = "UPDATE social_links SET name='$name', url='$url', icon_class='$new_image_path' WHERE id=$id";
        if ($conn->query($sql)) {
            $response['status'] = "success";
            $response['message'] = "ការកែប្រែ Link បានជោគជ័យ!";
            $response['updatedData'] = ['id' => $id, 'name' => htmlspecialchars($name), 'url' => htmlspecialchars($url), 'icon_class' => $new_image_path];
        } else { $response['message'] = "Query Error: " . $conn->error; }
        echo json_encode($response);
        exit();
    }

    // LOGIC សម្រាប់លុប LINK (AJAX)
    if (isset($_POST['action']) && $_POST['action'] == 'delete' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $result = $conn->query("SELECT icon_class FROM social_links WHERE id=$id");
        if ($row = $result->fetch_assoc()) {
            if (file_exists($row['icon_class'])) { unlink($row['icon_class']); }
        }
        $sql = "DELETE FROM social_links WHERE id=$id";
        if ($conn->query($sql)) {
            $response['status'] = "success";
            $response['message'] = "ការលុប Link បានជោគជ័យ!";
        } else { $response['message'] = "Error deleting record: " . $conn->error; }
        echo json_encode($response);
        exit();
    }
}

//======================================================================
// SECTION 2: HTML PAGE RENDERING LOGIC (Runs only on normal page load)
//======================================================================
$page = $_GET['page'] ?? 'profile';
$profile_result = $conn->query("SELECT * FROM site_profile WHERE id = 1");
$profile = $profile_result->fetch_assoc();
$links_result = $conn->query("SELECT * FROM social_links ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ប្រព័ន្ធគ្រប់គ្រងទិន្នន័យ</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;500;700&display=swap');
        :root {
            --primary-color: #007bff; --primary-hover-color: #0056b3; --success-color: #28a745; --danger-color: #dc3545;
            --warning-color: #ffc107; --sidebar-bg: #212529; --content-bg: #f8f9fa; --card-bg: #ffffff;
            --text-color: #343a40; --border-color: #dee2e6; --border-radius: 8px; --shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --transition-speed: 0.3s;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Kantumruy Pro', sans-serif; background-color: var(--content-bg); color: var(--text-color); display: flex; }
        .sidebar { width: 250px; background-color: var(--sidebar-bg); color: #fff; height: 100vh; position: fixed; top: 0; left: 0; display: flex; flex-direction: column; padding: 20px 0; transition: width var(--transition-speed) ease; z-index: 1000; }
        .sidebar-header { padding: 0 20px 20px 20px; text-align: center; border-bottom: 1px solid #343a40; font-size: 1.3rem; font-weight: 700; color: #fff; }
        .sidebar-nav { list-style-type: none; flex-grow: 1; margin-top: 20px; }
        .sidebar-nav a { display: flex; align-items: center; gap: 10px; color: #adb5bd; text-decoration: none; padding: 15px 25px; font-size: 1rem; font-weight: 500; transition: background-color var(--transition-speed), color var(--transition-speed); border-left: 4px solid transparent; }
        .sidebar-nav a:hover, .sidebar-nav a.active { background-color: rgba(255, 255, 255, 0.05); color: #fff; border-left-color: var(--primary-color); }
        .main-content { margin-left: 250px; width: calc(100% - 250px); padding: 40px; transition: margin-left var(--transition-speed) ease; }
        h1 { font-size: 2rem; font-weight: 700; color: var(--text-color); margin-bottom: 30px; }
        .content-card { background: var(--card-bg); padding: 30px; border-radius: var(--border-radius); box-shadow: var(--shadow); margin-bottom: 30px; }
        h2 { font-size: 1.6rem; color: var(--primary-color); padding-bottom: 15px; margin-bottom: 25px; border-bottom: 1px solid var(--border-color); }
        h3 { font-size: 1.3rem; margin-bottom: 20px; color: var(--text-color); font-weight: 500; }
        form label { display: block; margin-bottom: 8px; font-weight: 500; color: #495057; }
        form input[type="text"], form input[type="url"], form input[type="file"], form textarea { width: 100%; padding: 12px 15px; margin-bottom: 20px; border: 1px solid var(--border-color); border-radius: var(--border-radius); box-sizing: border-box; font-family: 'Kantumruy Pro', sans-serif; font-size: 1rem; transition: border-color var(--transition-speed), box-shadow var(--transition-speed); }
        form input:focus, form textarea:focus { border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.2); outline: none; }
        form textarea { min-height: 120px; resize: vertical; }
        .actions button { text-decoration: none; padding: 8px 16px; border: none; border-radius: var(--border-radius); cursor: pointer; font-size: 0.9rem; font-weight: 700; transition: background-color var(--transition-speed), transform 0.2s; display: inline-block; text-align: center; color: #fff; }
        form button, .cancel-btn { text-decoration: none; padding: 12px 24px; border: none; border-radius: var(--border-radius); cursor: pointer; font-size: 1rem; font-weight: 700; transition: background-color var(--transition-speed), transform 0.2s; display: inline-block; text-align: center; color: #fff; }
        form button:hover, .actions button:hover, .cancel-btn:hover { transform: translateY(-2px); }
        .add-btn { background-color: var(--success-color); }
        .update-btn { background-color: var(--primary-color); }
        .edit-btn { background-color: var(--warning-color); }
        .delete-btn { background-color: var(--danger-color); }
        .cancel-btn { background-color: #6c757d; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid var(--border-color); padding: 15px; text-align: left; vertical-align: middle; }
        thead th { background-color: #f2f2f2; font-weight: 700; color: var(--text-color); }
        tbody tr:nth-child(even) { background-color: #f9f9f9; }
        tbody tr { transition: background-color 0.3s ease; }
        .icon-img, .profile-img-preview { object-fit: cover; border: 2px solid var(--border-color); }
        .icon-img { width: 50px; height: 50px; border-radius: var(--border-radius); }
        .profile-img-preview { width: 100px; height: 100px; border-radius: 50%; }
        .message-ajax { padding: 1rem; margin-bottom: 2rem; border-radius: var(--border-radius); border: 1px solid; font-weight: 500; display: none; }
        .success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        .info { background-color: #fff3cd; color: #856404; border-color: #ffeeba; }
        .modal { display: none; position: fixed; z-index: 1001; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); animation: fadeIn 0.3s; }
        .modal-content { background-color: #fefefe; margin: 10% auto; padding: 30px; border: 1px solid #888; width: 80%; max-width: 600px; border-radius: var(--border-radius); box-shadow: var(--shadow); position: relative; animation: slideIn 0.3s; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 15px; margin-bottom: 20px; }
        .modal-header h2 { margin: 0; padding: 0; border: none; }
        .close-button { color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close-button:hover, .close-button:focus { color: black; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideIn { from { transform: translateY(-50px); } to { transform: translateY(0); } }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">ផ្ទាំងគ្រប់គ្រង</div>
        <ul class="sidebar-nav">
            <li><a href="admin_manage_group_link.php?page=profile" class="<?php echo ($page == 'profile') ? 'active' : ''; ?>">គ្រប់គ្រង Profile</a></li>
            <li><a href="admin_manage_group_link.php?page=links" class="<?php echo ($page == 'links') ? 'active' : ''; ?>">គ្រប់គ្រង Social Links</a></li>
            <li><a href="index.php" target="_blank">មើលគេហទំព័រ</a></li>
        </ul>
    </div>

    <div class="main-content">
        <h1>ប្រព័ន្ធគ្រប់គ្រងទិន្នន័យគេហទំព័រ</h1>
        <div id="ajax-message" class="message-ajax"></div>
        <?php if ($page == 'profile'): ?>
            <div id="profile-section" class="content-card">
                <h2>គ្រប់គ្រងព័ត៌មាន Profile</h2>
                <form id="profile-form" method="POST" enctype="multipart/form-data">
                    <label>ឈ្មោះ Profile:</label>
                    <input type="text" name="profile_name" value="<?php echo htmlspecialchars($profile['name']); ?>" required>
                    <label>ការពិពណ៌នា:</label>
                    <textarea name="profile_description" required><?php echo htmlspecialchars($profile['description']); ?></textarea>
                    <label>រូបភាព Profile បច្ចុប្បន្ន:</label>
                    <img src="<?php echo htmlspecialchars($profile['profile_image_url']); ?>" alt="Profile Icon" class="profile-img-preview" style="margin-bottom: 15px;">
                    <label>ផ្លាស់ប្តូររូបភាព Profile (ទុកឱ្យនៅទំនេរ បើមិនចង់ប្តូរ):</label>
                    <input type="file" name="profile_image" accept="image/*">
                    <input type="hidden" name="old_profile_image" value="<?php echo htmlspecialchars($profile['profile_image_url']); ?>">
                    <button type="submit" name="update_profile" class="update-btn">អាប់ដេត Profile</button>
                </form>
            </div>
        <?php elseif ($page == 'links'): ?>
            <div id="links-section" class="content-card">
                <h2>គ្រប់គ្រង Social Links</h2>
                <form id="add-link-form" method="POST" enctype="multipart/form-data" style="margin-bottom: 40px; border-bottom: 1px solid #eee; padding-bottom: 30px;">
                    <h3>បន្ថែម Link ថ្មី</h3>
                    <label>ឈ្មោះ (ឧទាហរណ៍ Facebook):</label>
                    <input type="text" name="name" required>
                    <label>URL Link:</label>
                    <input type="url" name="url" placeholder="https://..." required>
                    <label>រូបភាព Icon:</label>
                    <input type="file" name="image" accept="image/*" required>
                    <button type="submit" name="add_link" class="add-btn">បន្ថែម Link</button>
                </form>
                <h3>Links ដែលមានស្រាប់</h3>
                <table>
                    <thead><tr><th>ឈ្មោះ</th><th>URL</th><th>រូប Icon</th><th>សកម្មភាព</th></tr></thead>
                    <tbody id="links-table-body">
                        <?php while($row = $links_result->fetch_assoc()): ?>
                        <tr data-row-id="<?php echo $row['id']; ?>">
                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                            <td><?php echo htmlspecialchars($row['url']); ?></td>
                            <td><img src="<?php echo htmlspecialchars($row['icon_class']); ?>" alt="<?php echo htmlspecialchars($row['name']); ?>" class="icon-img"></td>
                            <td class="actions">
                                <button class="edit-btn" data-id="<?php echo $row['id']; ?>">កែប្រែ</button>
                                <button class="delete-btn" data-id="<?php echo $row['id']; ?>">លុប</button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <div id="edit-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>កែប្រែ Social Link</h2>
                <span class="close-button">&times;</span>
            </div>
            <form id="edit-link-form" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" id="edit-id">
                <input type="hidden" name="old_image" id="edit-old-image">
                <label>ឈ្មោះ:</label>
                <input type="text" name="name" id="edit-name" required>
                <label>URL Link:</label>
                <input type="url" name="url" id="edit-url" required>
                <label>រូប Icon បច្ចុប្បន្ន:</label>
                <img src="" alt="Icon" class="icon-img" id="edit-image-preview" style="margin-bottom: 15px;">
                <label>ផ្លាស់ប្តូររូប Icon (ទុកឱ្យនៅទំនេរ បើមិនចង់ប្តូរ):</label>
                <input type="file" name="image" accept="image/*">
                <button type="submit" name="update_link" class="update-btn">អាប់ដេត Link</button>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // *** បានកែប្រែ ***: URL សម្រាប់ fetch គឺឯកសារនេះផ្ទាល់
        const API_URL = 'admin_manage_group_link.php';
        const ajaxMessageDiv = document.getElementById('ajax-message');
        function showMessage(message, type = 'success') {
            ajaxMessageDiv.className = `message-ajax ${type}`;
            ajaxMessageDiv.textContent = message;
            ajaxMessageDiv.style.display = 'block';
            setTimeout(() => { ajaxMessageDiv.style.display = 'none'; }, 5000);
        }
        const profileForm = document.getElementById('profile-form');
        if (profileForm) {
            profileForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                formData.append('update_profile', '1');
                const submitButton = this.querySelector('button[type="submit"]');
                submitButton.textContent = 'កំពុងដំណើរการ...';
                submitButton.disabled = true;
                fetch(API_URL, { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        showMessage(data.message, 'success');
                        if (data.newImageUrl) {
                            document.querySelector('.profile-img-preview').src = data.newImageUrl + '?t=' + new Date().getTime();
                        }
                    } else { showMessage(data.message, 'error'); }
                })
                .catch(error => { showMessage('An error occurred.', 'error'); console.error('Error:', error); })
                .finally(() => { submitButton.textContent = 'អាប់ដេត Profile'; submitButton.disabled = false; });
            });
        }
        const addLinkForm = document.getElementById('add-link-form');
        if (addLinkForm) {
            addLinkForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                formData.append('add_link', '1');
                const submitButton = this.querySelector('button[type="submit"]');
                submitButton.textContent = 'កំពុងបន្ថែម...';
                submitButton.disabled = true;
                fetch(API_URL, { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        showMessage(data.message, 'success');
                        document.getElementById('links-table-body').insertAdjacentHTML('afterbegin', data.newRow);
                        addLinkForm.reset();
                    } else { showMessage(data.message, 'error'); }
                })
                .catch(error => { showMessage('An error occurred.', 'error'); console.error('Error:', error); })
                .finally(() => { submitButton.textContent = 'បន្ថែម Link'; submitButton.disabled = false; });
            });
        }
        const tableBody = document.getElementById('links-table-body');
        const editModal = document.getElementById('edit-modal');
        const editForm = document.getElementById('edit-link-form');
        const closeModalButton = editModal ? editModal.querySelector('.close-button') : null;
        function closeModal() { if (editModal) editModal.style.display = 'none'; }
        if(closeModalButton) closeModalButton.addEventListener('click', closeModal);
        window.addEventListener('click', function(event) { if (event.target == editModal) { closeModal(); } });
        if (tableBody) {
            tableBody.addEventListener('click', function(e) {
                const target = e.target.closest('button');
                if (!target) return;
                if (target.classList.contains('edit-btn')) {
                    e.preventDefault();
                    const linkId = target.getAttribute('data-id');
                    fetch(`${API_URL}?action=get_link_details&id=${linkId}`)
                    .then(response => response.json())
                    .then(res => {
                        if (res.status === 'success' && editForm) {
                            const data = res.data;
                            editForm.querySelector('#edit-id').value = data.id;
                            editForm.querySelector('#edit-old-image').value = data.icon_class;
                            editForm.querySelector('#edit-name').value = data.name;
                            editForm.querySelector('#edit-url').value = data.url;
                            editForm.querySelector('#edit-image-preview').src = data.icon_class;
                            if (editModal) editModal.style.display = 'block';
                        } else { showMessage(res.message, 'error'); }
                    })
                    .catch(error => { showMessage('Error fetching details.', 'error'); console.error('Error:', error); });
                }
                if (target.classList.contains('delete-btn')) {
                    e.preventDefault();
                    if (confirm('តើអ្នកពិតជាចង់លុប Link នេះមែនទេ?')) {
                        const linkId = target.getAttribute('data-id');
                        const rowToDelete = target.closest('tr');
                        const formData = new FormData();
                        formData.append('action', 'delete');
                        formData.append('id', linkId);
                        fetch(API_URL, { method: 'POST', body: formData })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success') {
                                showMessage(data.message, 'success');
                                rowToDelete.style.transition = 'opacity 0.5s';
                                rowToDelete.style.opacity = '0';
                                setTimeout(() => { rowToDelete.remove(); }, 500);
                            } else { showMessage(data.message, 'error'); }
                        })
                        .catch(error => { showMessage('An error occurred.', 'error'); console.error('Error:', error); });
                    }
                }
            });
        }
        if (editForm) {
            editForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                formData.append('update_link', '1');
                const submitButton = this.querySelector('button[type="submit"]');
                submitButton.textContent = 'កំពុងអាប់ដេត...';
                submitButton.disabled = true;
                fetch(API_URL, { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        closeModal();
                        showMessage(data.message, 'success');
                        const updatedData = data.updatedData;
                        const rowToUpdate = tableBody.querySelector(`tr[data-row-id='${updatedData.id}']`);
                        if (rowToUpdate) {
                            rowToUpdate.cells[0].textContent = updatedData.name;
                            rowToUpdate.cells[1].textContent = updatedData.url;
                            const img = rowToUpdate.cells[2].querySelector('img');
                            img.src = updatedData.icon_class + '?t=' + new Date().getTime();
                            img.alt = updatedData.name;
                        }
                    } else { alert('Error: ' + data.message); }
                })
                .catch(error => { showMessage('Error updating link.', 'error'); console.error('Error:', error); })
                .finally(() => { submitButton.textContent = 'អាប់ដេត Link'; submitButton.disabled = false; });
            });
        }
    });
    </script>
</body>
</html>
<?php $conn->close(); ?>