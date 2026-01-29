<?php
/**
 * File: dashboard.php
 * Version: 7.1 - Fixed product duplication issue in edit modal.
 * Description: Manage simple replies and product carousels with image URL or upload options.
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';

// --- Configuration for Uploads ---
define('UPLOAD_DIR', __DIR__ . '/uploads/');
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$base_url = $protocol . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . '/';

// Ensure upload directory exists and is writable, and secure it.
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0775, true);
}
if (!is_writable(UPLOAD_DIR)) {
    die("Error: Upload directory is not writable. Please check permissions for the 'uploads' folder.");
}
if (!file_exists(UPLOAD_DIR . '.htaccess')) {
    file_put_contents(UPLOAD_DIR . '.htaccess', "Options -ExecCGI\nSetHandler none");
}

// --- LOGIC FOR HANDLING FORM SUBMISSIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // --- CREATE/UPDATE LOGIC ---
    if ($_POST['action'] === 'create' || $_POST['action'] === 'update') {
        $keyword = ($_POST['keyword'] === '*') ? '*' : strtolower(trim($_POST['keyword']));
        $reply_text = trim($_POST['reply_text']);
        $reply_type = $_POST['reply_type'] ?? 'simple';
        
        $buttons_json = null;
        $carousel_data_json = null;

        // Process Buttons
        $buttons = [];
        if (isset($_POST['button_types']) && is_array($_POST['button_types'])) {
            foreach ($_POST['button_types'] as $index => $type) {
                $title = trim($_POST['button_titles'][$index] ?? '');
                $payload_value = trim($_POST['button_payloads'][$index] ?? '');
                if (empty($title) || empty($payload_value)) continue;

                if ($type === 'web_url') {
                    $buttons[] = ['type' => 'web_url', 'title' => $title, 'url' => $payload_value];
                } elseif ($type === 'phone_number') {
                    $buttons[] = ['type' => 'phone_number', 'title' => $title, 'payload' => '+' . ltrim($payload_value, '+')];
                }
            }
        }
        $buttons_json = !empty($buttons) ? json_encode($buttons, JSON_UNESCAPED_UNICODE) : null;

        // Process Carousel Products
        if ($reply_type === 'carousel') {
            $carousel_elements = [];
            if (isset($_POST['product_titles']) && is_array($_POST['product_titles'])) {
                foreach ($_POST['product_titles'] as $index => $title) {
                    $title = trim($title);
                    if (empty($title)) continue; // Skip if title is empty

                    $subtitle = trim($_POST['product_subtitles'][$index] ?? '');
                    $button_title = trim($_POST['product_btn_titles'][$index] ?? '');
                    $button_url = trim($_POST['product_btn_urls'][$index] ?? '');

                    $image_url_to_save = '';
                    $image_source_type = $_POST['product_image_source'][$index] ?? 'url';

                    if ($image_source_type === 'upload' && isset($_FILES['product_image_uploads']) && $_FILES['product_image_uploads']['error'][$index] === UPLOAD_ERR_OK) {
                        $file_tmp_name = $_FILES['product_image_uploads']['tmp_name'][$index];
                        $file_name = $_FILES['product_image_uploads']['name'][$index];
                        
                        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
                        if (in_array($file_ext, $allowed_exts)) {
                            $new_file_name = uniqid('', true) . '.' . $file_ext;
                            $target_path = UPLOAD_DIR . $new_file_name;
                            if (move_uploaded_file($file_tmp_name, $target_path)) {
                                $image_url_to_save = $base_url . 'uploads/' . $new_file_name;
                            }
                        }
                    } 
                    elseif ($image_source_type === 'url') {
                        $image_url_to_save = trim($_POST['product_images_url'][$index] ?? '');
                    }
                    elseif ($_POST['action'] === 'update' && !empty($_POST['existing_image_paths'][$index])) {
                         $image_url_to_save = trim($_POST['existing_image_paths'][$index]);
                    }
                    
                    if (empty($image_url_to_save)) continue; // Also skip if there's no image

                    $element = [
                        'title' => $title,
                        'image_url' => $image_url_to_save,
                        'subtitle' => $subtitle,
                    ];

                    if (!empty($button_title) && !empty($button_url)) {
                        $element['buttons'] = [['type' => 'web_url', 'title' => $button_title, 'url' => $button_url]];
                    }
                    $carousel_elements[] = $element;
                }
            }
            $carousel_data_json = !empty($carousel_elements) ? json_encode($carousel_elements, JSON_UNESCAPED_UNICODE) : null;
        }

        if ($_POST['action'] === 'create') {
            $stmt = $conn->prepare("INSERT INTO auto_replies (keyword, reply_text, reply_type, buttons_json, carousel_data_json) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $keyword, $reply_text, $reply_type, $buttons_json, $carousel_data_json);
        } else { // Update
            $id = intval($_POST['id']);
            $stmt = $conn->prepare("UPDATE auto_replies SET keyword=?, reply_text=?, reply_type=?, buttons_json=?, carousel_data_json=? WHERE id=?");
            $stmt->bind_param("sssssi", $keyword, $reply_text, $reply_type, $buttons_json, $carousel_data_json, $id);
        }
        $stmt->execute();
    }
    
    header("Location: dashboard.php");
    exit();
}

// --- LOGIC FOR DELETING A RULE ---
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt_select = $conn->prepare("SELECT carousel_data_json FROM auto_replies WHERE id = ?");
    $stmt_select->bind_param("i", $id);
    $stmt_select->execute();
    $result_select = $stmt_select->get_result();
    if ($row = $result_select->fetch_assoc()) {
        if (!empty($row['carousel_data_json'])) {
            $products = json_decode($row['carousel_data_json'], true);
            foreach($products as $product) {
                if (strpos($product['image_url'], $base_url . 'uploads/') === 0) {
                    $filename = basename($product['image_url']);
                    $filepath = UPLOAD_DIR . $filename;
                    if (file_exists($filepath)) {
                        @unlink($filepath);
                    }
                }
            }
        }
    }
    
    $stmt = $conn->prepare("DELETE FROM auto_replies WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    header("Location: dashboard.php");
    exit();
}

// --- READ data from the database to display ---
$result = $conn->query("SELECT id, keyword, reply_text, reply_type, buttons_json, carousel_data_json FROM auto_replies ORDER BY keyword='*' DESC, id DESC");
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Auto Reply Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        /* CSS is unchanged */
        :root { --primary-color: #007bff; --success-color: #28a745; --danger-color: #dc3545; --info-color: #17a2b8; --dark-color: #2c3e50; --light-color: #f4f7f9; --white-color: #fff; --border-color: #dee2e6; --sidebar-width: 260px; }
        body { font-family: 'Kantumruy Pro', sans-serif; background-color: var(--light-color); margin: 0; color: #333; }
        .app-container { display: flex; }
        .app-sidebar { width: var(--sidebar-width); position: fixed; top: 0; left: 0; height: 100vh; background-color: var(--dark-color); padding: 20px 0; color: var(--white-color); box-shadow: 2px 0 10px rgba(0,0,0,0.1); }
        .app-sidebar .logo { text-align: center; padding: 0 20px 20px 20px; font-size: 1.5rem; font-weight: 700; color: var(--white-color); border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 20px; }
        .app-sidebar .logo i { margin-right: 10px; color: var(--primary-color); }
        .sidebar-nav a { display: flex; align-items: center; padding: 15px 25px; color: #bdc3c7; text-decoration: none; font-weight: 600; transition: all 0.3s ease; border-left: 4px solid transparent; }
        .sidebar-nav a:hover { background-color: rgba(255,255,255,0.05); color: var(--white-color); }
        .sidebar-nav a.active { background-color: rgba(0,123,255,0.1); color: var(--white-color); border-left-color: var(--primary-color); }
        .sidebar-nav a i { margin-right: 15px; width: 20px; text-align: center; }
        .app-content { margin-left: var(--sidebar-width); width: calc(100% - var(--sidebar-width)); padding: 30px; }
        .view { display: none; }
        .view.active { display: block; }
        .card { background: var(--white-color); border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); padding: 30px; margin-bottom: 30px; }
        h1, h2 { margin-top: 0; color: var(--dark-color); border-bottom: 2px solid var(--border-color); padding-bottom: 15px; margin-bottom: 25px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; border-bottom: 1px solid var(--border-color); text-align: left; vertical-align: top; }
        th { background-color: #f8f9fa; font-weight: 700; color: var(--dark-color); }
        .action-btn { padding: 6px 12px; font-size: 0.9em; margin-right: 5px; color: white !important; text-decoration: none; display: inline-block; border-radius: 5px; cursor: pointer; border: none; font-family: 'Kantumruy Pro', sans-serif;}
        .edit-btn { background-color: var(--success-color); }
        .delete-btn { background-color: var(--danger-color); }
        form label { display: block; margin-bottom: 8px; font-weight: 600; color: #495057; }
        form input[type="text"], form input[type="url"], form input[type="tel"], form input[type="file"], form textarea, form select { width: 100%; padding: 12px; border: 1px solid #ced4da; background-color: #fff; color: #333; border-radius: 5px; box-sizing: border-box; margin-bottom: 15px; font-family: 'Kantumruy Pro', sans-serif; transition: border-color 0.2s; }
        form input:focus, form textarea:focus, form select:focus { border-color: var(--primary-color); outline: none; }
        input[type="submit"] { width: auto; min-width: 200px; margin-top: 15px; padding: 15px 25px; background: var(--primary-color); color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 1rem; font-family: 'Kantumruy Pro', sans-serif; font-weight: 600; transition: background-color 0.2s ease; }
        .add-btn-dynamic { background: var(--info-color); color: white; padding: 12px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 1rem; text-decoration: none; display: inline-block; font-family: 'Kantumruy Pro', sans-serif; font-weight: 600; margin-top: 10px; }
        .remove-btn { background-color: var(--danger-color); color: white; padding: 8px 12px; border: none; border-radius: 5px; cursor: pointer;}
        .button-section { border: 1px dashed var(--border-color); padding: 20px; margin-top: 20px; border-radius: 5px; background-color: #f8f9fa; }
        .reply-type-chooser { display: flex; gap: 10px; background-color: #e9ecef; border-radius: 8px; padding: 5px; margin-bottom: 20px; }
        .reply-type-chooser label { flex: 1; text-align: center; padding: 10px; border-radius: 6px; cursor: pointer; transition: all 0.3s ease; color: #495057; font-weight: 600; }
        .reply-type-chooser input[type="radio"] { display: none; }
        .reply-type-chooser input[type="radio"]:checked + label { background-color: var(--primary-color); color: var(--white-color); }
        .form-section { display: none; }
        .form-section.active { display: block; }
        .product-card { background: #f8f9fa; padding: 20px; border-radius: 6px; margin-bottom: 15px; border-left: 4px solid var(--info-color); }
        .product-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .product-card-header h4 { margin: 0; color: var(--dark-color); }
        .carousel-preview { display: flex; gap: 10px; padding-top: 10px; overflow-x: auto; border-top: 1px solid #eee; margin-top: 10px; }
        .carousel-item-preview { flex-shrink: 0; width: 120px; text-align: center; font-size: 0.9em; }
        .carousel-item-preview img { width: 100%; height: 80px; object-fit: cover; border-radius: 5px; border: 2px solid #ddd; }
        .carousel-item-preview p { margin: 5px 0 0 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6); }
        .modal-content { background-color: var(--white-color); color: var(--dark-color); margin: 5% auto; padding: 30px; border-radius: 10px; width: 90%; max-width: 650px; position: relative; box-shadow: 0 5px 20px rgba(0,0,0,0.3); }
        .modal-content h2 { color: var(--dark-color); }
        .close-btn { color: #aaa; position: absolute; top: 15px; right: 25px; font-size: 28px; font-weight: bold; cursor: pointer; transition: color 0.2s; }
        .close-btn:hover, .close-btn:focus { color: var(--dark-color); text-decoration: none; }
        .modal-body { max-height: 70vh; overflow-y: auto; padding-right: 15px; margin-right: -15px;}
        .modal-body::-webkit-scrollbar { width: 8px; }
        .modal-body::-webkit-scrollbar-track { background: #f1f1f1; }
        .modal-body::-webkit-scrollbar-thumb { background: #ccc; border-radius: 4px; }
        .image-source-chooser { display: flex; gap: 10px; margin-bottom: 10px; }
        .image-source-chooser label { border: 1px solid #ced4da; padding: 8px; border-radius: 5px; cursor: pointer; flex: 1; text-align: center; font-size: 0.9em;}
        .image-source-chooser input[type="radio"]:checked + label { background-color: var(--info-color); border-color: var(--info-color); color: white;}
        .image-input-container { display: none; }
        .image-input-container.active { display: block; }
        .current-image-info { color: #6c757d; font-size: 0.85em; margin-bottom: 10px; word-break: break-all; }
        #create-view .card, #edit-modal .modal-content { background-color: #fff; color: #333; }
        #create-view form label, #edit-modal form label { color: #495057; }
        #create-view .product-card, #edit-modal .product-card { background-color: #f8f9fa; border-left-color: var(--info-color); }
        #create-view .product-card h4, #edit-modal .product-card h4 { color: var(--dark-color); }
        #create-view .button-section, #edit-modal .button-section { border-color: #dee2e6; background-color: #f8f9fa;}
        #create-view small, #edit-modal small { color: #6c757d; }
    </style>
</head>
<body>
    <div class="app-container">
        <nav class="app-sidebar">
            <div class="logo">
                <i class="fa-solid fa-robot"></i> Bot Manager
            </div>
            <div class="sidebar-nav">
                <a href="#" id="nav-dashboard" class="nav-link active"><i class="fa-solid fa-table-list"></i> បញ្ជី Flow</a>
                <a href="#" id="nav-create" class="nav-link"><i class="fa-solid fa-plus"></i> បង្កើត Flow ថ្មី</a>
                
            </div>
        </nav>

        <main class="app-content">
            <!-- HTML Views are unchanged -->
            <div id="dashboard-view" class="view active">
                 <div class="card">
                    <h2><i class="fa-solid fa-table-list"></i> បញ្ជី Flow ដែលមាន</h2>
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Keyword</th>
                                    <th>ប្រភេទ</th>
                                    <th>ខ្លឹមសារឆ្លើយតប</th>
                                    <th style="width: 160px;">សកម្មភាព</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result && $result->num_rows > 0): ?>
                                    <?php while($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <?php if($row['keyword'] == '*'): ?>
                                                    <strong style="color: var(--primary-color);">* (គ្រប់សារដំបូង)</strong>
                                                <?php else: ?>
                                                    <strong><?php echo htmlspecialchars($row['keyword']); ?></strong>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if($row['reply_type'] == 'carousel'): ?>
                                                    <span style="background: #e8f4ff; color: #007bff; padding: 3px 8px; border-radius: 12px; font-weight: 600;">🛍️ បញ្ជីផលិតផល</span>
                                                <?php else: ?>
                                                    <span style="background: #f0f0f0; color: #555; padding: 3px 8px; border-radius: 12px; font-weight: 600;">💬 សារធម្មតា</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if(!empty($row['reply_text'])) echo '<blockquote>' . nl2br(htmlspecialchars($row['reply_text'])) . '</blockquote>'; ?>
                                                
                                                <?php if(!empty($row['buttons_json'])): 
                                                    $buttons = json_decode($row['buttons_json'], true);
                                                    echo '<ul style="margin-bottom: 10px; padding-left: 20px;">';
                                                    foreach($buttons as $button) {
                                                        echo '<li><strong>' . htmlspecialchars($button['title']) . '</strong> <small>(' . str_replace('_', ' ', $button['type']) . ')</small></li>';
                                                    }
                                                    echo '</ul>';
                                                endif; ?>

                                                <?php if($row['reply_type'] == 'carousel' && !empty($row['carousel_data_json'])): 
                                                    $products = json_decode($row['carousel_data_json'], true);
                                                ?>
                                                    <div class="carousel-preview">
                                                    <?php foreach($products as $product): ?>
                                                        <div class="carousel-item-preview">
                                                            <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="Product Image">
                                                            <p><strong><?php echo htmlspecialchars($product['title']); ?></strong></p>
                                                            <small><?php echo htmlspecialchars($product['subtitle']); ?></small>
                                                        </div>
                                                    <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="action-btn edit-btn" onclick='openEditModal(<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>)'>កែប្រែ</button>
                                                <a href="dashboard.php?action=delete&id=<?php echo $row['id']; ?>" class="action-btn delete-btn" onclick="return confirm('តើអ្នកពិតជាចង់លុប Flow នេះមែនទេ? រូបភាពដែលបាន Upload ក៏នឹងត្រូវលុបដែរ។')">លុប</a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" style="text-align: center; padding: 30px;">មិនទាន់មាន Flow នៅឡើយទេ</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div id="create-view" class="view">
                <div class="card">
                    <h1><i class="fa-solid fa-gear"></i> បង្កើត Flow ឆ្លើយតប</h1>
                    <form action="dashboard.php" method="POST" id="create-form" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="create">
                        <div class="reply-type-chooser">
                            <input type="radio" id="create_type_simple" name="reply_type" value="simple" checked onchange="toggleFormSection('create')">
                            <label for="create_type_simple"><i class="fa-solid fa-comment-dots"></i> សារធម្មតា</label>
                            <input type="radio" id="create_type_carousel" name="reply_type" value="carousel" onchange="toggleFormSection('create')">
                            <label for="create_type_carousel"><i class="fa-solid fa-store"></i>️ បញ្ជីផលិតផល</label>
                        </div>
                        <label for="create-keyword">Keyword (ពាក្យគន្លឹះ):</label>
                        <input type="text" id="create-keyword" name="keyword" required placeholder="ឧ. P001, សួរតម្លៃ, hello">
                        <small style="display: block; margin-top: -10px; margin-bottom: 15px;">ប្រើសញ្ញា `*` ដើម្បីបង្កើតការឆ្លើយតបសម្រាប់គ្រប់សារដំបូង។</small>
                        <label for="create-reply_text">សារនាំមុខ (ផ្ញើមុនគេ):</label>
                        <textarea id="create-reply_text" name="reply_text" rows="3" placeholder="សរសេរសារដែលត្រូវបង្ហាញមុនបញ្ជីផលិតផល ឬ ប៊ូតុង"></textarea>
                        <div class="button-section">
                            <label>ប៊ូតុងភ្ជាប់សារនាំមុខ (ស្រេចចិត្ត)</label>
                            <div id="create-button-container"></div>
                            <button type="button" class="add-btn-dynamic" onclick="addButton('create')"> <i class="fa-solid fa-plus"></i> បន្ថែមប៊ូតុង</button>
                        </div>
                        <div id="create-carousel-reply-section" class="form-section" style="margin-top: 20px;">
                            <label>បញ្ជីផលិតផល (បន្ថែមបាន 10)</label>
                            <div id="create-product-container"></div>
                            <button type="button" class="add-btn-dynamic" onclick="addProduct('create')"> <i class="fa-solid fa-plus"></i> បន្ថែមផលិតផល</button>
                        </div>
                        <input type="submit" value="រក្សាទុក Flow">
                    </form>
                </div>
            </div>
        </main>
    </div>

    <div id="edit-modal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeEditModal()">×</span>
            <h2>✏️ កែប្រែ Flow</h2>
            <div class="modal-body">
                <form action="dashboard.php" method="POST" id="edit-form" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="edit-id">
                    <div class="reply-type-chooser">
                        <input type="radio" id="edit_type_simple" name="reply_type" value="simple" onchange="toggleFormSection('edit')">
                        <label for="edit_type_simple">💬 សារធម្មតា</label>
                        <input type="radio" id="edit_type_carousel" name="reply_type" value="carousel" onchange="toggleFormSection('edit')">
                        <label for="edit_type_carousel">🛍️ បញ្ជីផលិតផល</label>
                    </div>
                    <label for="edit-keyword">Keyword (ពាក្យគន្លឹះ):</label>
                    <input type="text" id="edit-keyword" name="keyword" required>
                    <small style="display: block; margin-top: -10px; margin-bottom: 15px;">ប្រើសញ្ញា `*` ដើម្បីបង្កើតការឆ្លើយតបសម្រាប់គ្រប់សារដំបូង។</small>
                    <label for="edit-reply_text">សារនាំមុខ:</label>
                    <textarea id="edit-reply_text" name="reply_text" rows="3"></textarea>
                    <div class="button-section">
                        <label>ប៊ូតុងភ្ជាប់សារនាំមុខ (ស្រេចចិត្ត)</label>
                        <div id="edit-button-container"></div>
                        <button type="button" class="add-btn-dynamic" onclick="addButton('edit')">➕ បន្ថែមប៊ូតុង</button>
                    </div>
                    <div id="edit-carousel-reply-section" class="form-section" style="margin-top: 20px;">
                        <label>បញ្ជីផលិតផល</label>
                        <div id="edit-product-container"></div>
                        <button type="button" class="add-btn-dynamic" onclick="addProduct('edit')">➕ បន្ថែមផលិតផល</button>
                    </div>
                    <input type="submit" value="រក្សាទុកការកែប្រែ">
                </form>
            </div>
        </div>
    </div>


<script>
const base_url = '<?php echo $base_url; ?>';

// --- START: កូដដែលបានកែប្រែ ---

// បង្កើតตัวแปรสำหรับนับ (counter) នៅខាងក្រៅ Function ដើម្បីកុំឲ្យវា reset រាល់ពេលเรียกใช้
let productCreationCounter = 0;

document.addEventListener('DOMContentLoaded', function() {
    const navLinks = document.querySelectorAll('.nav-link');
    const views = document.querySelectorAll('.view');
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            navLinks.forEach(item => item.classList.remove('active'));
            this.classList.add('active');
            const targetId = this.id.replace('nav-', '') + '-view';
            views.forEach(view => {
                view.classList.add('active');
                if (view.id === targetId) {
                    view.classList.add('active');
                } else {
                    view.classList.remove('active');
                }
            });
        });
    });
    toggleFormSection('create');
});

function toggleFormSection(formPrefix) {
    const carouselSection = document.getElementById(`${formPrefix}-carousel-reply-section`);
    if (document.getElementById(`${formPrefix}_type_carousel`).checked) {
        carouselSection.classList.add('active');
    } else {
        carouselSection.classList.remove('active');
    }
}

function addButton(formPrefix, data = null) {
    const container = document.getElementById(`${formPrefix}-button-container`);
    const buttonDiv = document.createElement('div');
    buttonDiv.style.marginBottom = '15px';
    buttonDiv.innerHTML = `
        <select name="button_types[]" style="margin-bottom: 5px;">
            <option value="web_url" ${data && data.type === 'web_url' ? 'selected' : ''}>Link URL</option>
            <option value="phone_number" ${data && data.type === 'phone_number' ? 'selected' : ''}>Phone Number</option>
        </select>
        <input type="text" name="button_titles[]" placeholder="Button Title (e.g., Visit Website)" required value="${data ? (data.title || '') : ''}">
        <input type="text" name="button_payloads[]" placeholder="URL or Phone Number" required value="${data ? (data.url || data.payload || '') : ''}">
        <button type="button" class="remove-btn" onclick="this.parentElement.remove()">Remove</button>
    `;
    container.appendChild(buttonDiv);
}

function toggleImageInput(formPrefix, index, source) {
    const urlContainer = document.getElementById(`${formPrefix}-image-url-container-${index}`);
    const uploadContainer = document.getElementById(`${formPrefix}-image-upload-container-${index}`);
    if (source === 'url') {
        urlContainer.classList.add('active');
        uploadContainer.classList.remove('active');
    } else {
        urlContainer.classList.remove('active');
        uploadContainer.classList.add('active');
    }
}

function addProduct(formPrefix, data = null) {
    const container = document.getElementById(`${formPrefix}-product-container`);
    if (container.children.length >= 10) {
        alert('អ្នកអាចបន្ថែមផលិតផលបានត្រឹមតែ 10 ប៉ុណ្ណោះក្នុងមួយ Carousel។');
        return;
    }
    
    // CHANGE #1: បង្កើត ID សម្គាល់ (index) ដែលមិនซ้ำគ្នាเด็ดขาด
    const index = `${Date.now()}-${productCreationCounter++}`;
    
    const cardDiv = document.createElement('div');
    cardDiv.className = 'product-card';

    let isUpload = false;
    let existingImageUrl = '';
    if (data && data.image_url) {
        existingImageUrl = data.image_url;
        if (existingImageUrl.startsWith(base_url + 'uploads/')) {
            isUpload = true;
        }
    }

    cardDiv.innerHTML = `
        <div class="product-card-header">
            <h4>ផលិតផល #${container.children.length + 1}</h4>
            <button type="button" class="remove-btn" onclick="this.parentElement.parentElement.remove()">លុប</button>
        </div>
        <label>ប្រភពរូបភាព:</label>
        <div class="image-source-chooser">
            <input type="radio" id="${formPrefix}_image_source_url_${index}" name="product_image_source[${index}]" value="url" ${!isUpload ? 'checked' : ''} onchange="toggleImageInput('${formPrefix}', '${index}', 'url')" style="display:none;">
            <label for="${formPrefix}_image_source_url_${index}">ប្រើ URL</label>
            <input type="radio" id="${formPrefix}_image_source_upload_${index}" name="product_image_source[${index}]" value="upload" ${isUpload ? 'checked' : ''} onchange="toggleImageInput('${formPrefix}', '${index}', 'upload')" style="display:none;">
            <label for="${formPrefix}_image_source_upload_${index}">Upload រូបភាព</label>
        </div>
        <div id="${formPrefix}-image-url-container-${index}" class="image-input-container ${!isUpload ? 'active' : ''}">
             <label>រូបភាព (Image URL):</label>
             <input type="url" name="product_images_url[${index}]" placeholder="https://example.com/image.jpg" value="${!isUpload ? existingImageUrl : ''}">
        </div>
        <div id="${formPrefix}-image-upload-container-${index}" class="image-input-container ${isUpload ? 'active' : ''}">
            <label>Upload រូបភាពថ្មី (ទុក​ចោល​បើ​មិន​ប្តូរ):</label>
            ${isUpload ? `<div class="current-image-info">រូបភាពបច្ចុប្បន្ន: ${existingImageUrl.split('/').pop()}</div>` : ''}
            <input type="file" name="product_image_uploads[${index}]" accept="image/png, image/jpeg, image/gif">
            <input type="hidden" name="existing_image_paths[${index}]" value="${existingImageUrl}">
        </div>
        <label>ចំណងជើង (Title):</label>
        <input type="text" name="product_titles[${index}]" placeholder="ឧ. កាបូបម៉ូតថ្មី" required value="${data ? (data.title || '') : ''}">
        <label>ចំណងជើងរង (Subtitle - ឧ. តម្លៃ):</label>
        <input type="text" name="product_subtitles[${index}]" placeholder="ឧ. តម្លៃ: $25 | Free Delivery" value="${data ? (data.subtitle || '') : ''}">
        <label>ឈ្មោះប៊ូតុង (ស្រេចចិត្ត):</label>
        <input type="text" name="product_btn_titles[${index}]" placeholder="ឧ. ទិញឥឡូវនេះ" value="${data && data.buttons ? (data.buttons[0].title || '') : ''}">
        <label>Link ពេលចុចប៊ូតុង (URL):</label>
        <input type="url" name="product_btn_urls[${index}]" placeholder="https://example.com/product/123" value="${data && data.buttons ? (data.buttons[0].url || '') : ''}">
    `;
    container.appendChild(cardDiv);
}

const modal = document.getElementById('edit-modal');

function openEditModal(rowData) {
    // CHANGE #2: Reset ตัวเลขរាប់រាល់ពេលเปิด Modal
    productCreationCounter = 0; 
    
    document.getElementById('edit-form').reset();
    document.getElementById('edit-button-container').innerHTML = '';
    document.getElementById('edit-product-container').innerHTML = '';

    document.getElementById('edit-id').value = rowData.id;
    document.getElementById('edit-keyword').value = rowData.keyword;
    document.getElementById('edit-reply_text').value = rowData.reply_text;
    
    if (rowData.reply_type === 'carousel') {
        document.getElementById('edit_type_carousel').checked = true;
    } else {
        document.getElementById('edit_type_simple').checked = true;
    }
    toggleFormSection('edit');

    if (rowData.buttons_json) {
        const buttons = JSON.parse(rowData.buttons_json);
        buttons.forEach(buttonData => addButton('edit', buttonData));
    }
    
    if (rowData.reply_type === 'carousel' && rowData.carousel_data_json) {
        const products = JSON.parse(rowData.carousel_data_json);
        products.forEach(productData => addProduct('edit', productData));
    }

    modal.style.display = 'block';
}

function closeEditModal() {
    modal.style.display = 'none';
}

window.onclick = function(event) {
    if (event.target == modal) {
        closeEditModal();
    }
}

// --- END: កូដដែលបានកែប្រែ ---
</script>
</body>
</html>
<?php
$conn->close();
?>