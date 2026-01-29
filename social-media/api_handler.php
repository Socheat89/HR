<?php
// api_handler.php
session_start();
include 'db_connect.php';

// កំណត់ Response Header ជា JSON
header('Content-Type: application/json');

// រៀបចំ Array សម្រាប់ Response
$response = ['status' => 'error', 'message' => 'An unknown error occurred.'];
$upload_dir = 'uploads/';

// Function សម្រាប់បង្កើត Table Row HTML ថ្មី (ដើម្បីបញ្ជូនกลับទៅ JavaScript)
function create_link_row_html($conn, $id) {
    $result = $conn->query("SELECT * FROM social_links WHERE id = $id");
    if ($row = $result->fetch_assoc()) {
        $name = htmlspecialchars($row['name']);
        $url = htmlspecialchars($row['url']);
        $icon = htmlspecialchars($row['icon_class']);
        return "<tr>
                    <td>{$name}</td>
                    <td>{$url}</td>
                    <td><img src='{$icon}' alt='{$name}' class='icon-img'></td>
                    <td class='actions'>
                        <a href='admin_manage_group_link.php?page=links&action=edit&id={$row['id']}' class='edit-btn'>កែប្រែ</a>
                        <a href='#' data-id='{$row['id']}' class='delete-btn'>លុប</a>
                    </td>
                </tr>";
    }
    return '';
}

//================================================
// LOGIC សម្រាប់គ្រប់គ្រង PROFILE
//================================================
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
        $response['newImageUrl'] = $new_image_path; // បញ្ជូន URL រូបភាពថ្មីត្រឡប់ទៅវិញ
    } else {
        $response['message'] = 'Query Error: ' . $conn->error;
    }

    echo json_encode($response);
    exit();
}


//================================================
// LOGIC សម្រាប់គ្រប់គ្រង SOCIAL LINKS
//================================================

// CREATE (Add Link)
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
            $response['newRow'] = create_link_row_html($conn, $new_id); // បង្កើត HTML សម្រាប់ Row ថ្មី
        } else {
            $response['message'] = "Error: " . $conn->error;
        }
    } else {
        $response['message'] = "ការបន្ថែម Link បរាជ័យ! សូមពិនិត្យមើលរូបភាព។";
    }
    echo json_encode($response);
    exit();
}

// UPDATE (Edit Link) - Note: AJAX update is better handled on a separate edit page.
// This example keeps the original logic, which redirects after update.
// For a full AJAX experience, the edit form should also submit via AJAX and update the row in the table.
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
                if (file_exists($old_image)) {
                    unlink($old_image);
                }
                $new_image_path = $target_file;
            }
        }
    }

    $sql = "UPDATE social_links SET name='$name', url='$url', icon_class='$new_image_path' WHERE id=$id";
    if ($conn->query($sql)) {
        $_SESSION['message'] = "ការកែប្រែ Link បានជោគជ័យ!";
        $_SESSION['message_type'] = "success";
    }
     // For this part, we still use redirect as the edit form is on a separate view.
    header('Location: admin_manage_group_link.php?page=links');
    exit();
}


// DELETE (Delete Link)
if (isset($_POST['action']) && $_POST['action'] == 'delete' && isset($_POST['id'])) {
    $id = (int)$_POST['id'];

    // First, get the image path to delete the file
    $result = $conn->query("SELECT icon_class FROM social_links WHERE id=$id");
    if ($row = $result->fetch_assoc()) {
        $image_to_delete = $row['icon_class'];
        if (file_exists($image_to_delete)) {
            unlink($image_to_delete);
        }
    }

    $sql = "DELETE FROM social_links WHERE id=$id";
    if ($conn->query($sql)) {
        $response['status'] = "success";
        $response['message'] = "ការលុប Link បានជោគជ័យ!";
    } else {
         $response['message'] = "Error deleting record: " . $conn->error;
    }
    echo json_encode($response);
    exit();
}

// If no action matched, return the initial error response
echo json_encode($response);
$conn->close();
?>