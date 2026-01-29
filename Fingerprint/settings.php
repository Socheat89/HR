<?php
// settings.php (Per-User Time Settings)

// IMPORTANT: This admin panel needs to be in the same directory as api.php and data.json
// or you need to adjust the path to data.json.
define('DATA_FILE', 'data.json'); // Assumes admin folder is one level down

// --- Helper Functions ---

// Load data from the main JSON file
function load_user_data() {
    if (!file_exists(DATA_FILE)) {
        return [];
    }
    $json_data = file_get_contents(DATA_FILE);
    $data = json_decode($json_data, true);
    return $data['users'] ?? [];
}

// Save all user data back to the main JSON file
function save_user_data($all_users) {
    if (!file_exists(DATA_FILE)) {
        return false;
    }
    $json_data = file_get_contents(DATA_FILE);
    $data = json_decode($json_data, true);
    $data['users'] = $all_users;
    return file_put_contents(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// --- Handle Form Submission ---

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $all_users = load_user_data();
    $user_id_to_update = $_POST['user_id'];
    $user_index = -1;

    // Find the index of the user to update
    foreach ($all_users as $index => $user) {
        if ($user['id'] === $user_id_to_update) {
            $user_index = $index;
            break;
        }
    }

    if ($user_index !== -1) {
        // *** START: MODIFIED CODE BLOCK ***
        // ជំហានទី១៖ យកការកំណត់ដែលមានស្រាប់សម្រាប់អ្នកប្រើប្រាស់ ឬបង្កើត array ទទេប្រសិនបើគ្មាន។
        $existing_settings = $all_users[$user_index]['timeSettings'] ?? [];

        // ជំហានទី២៖ បង្កើតរចនាសម្ព័ន្ធសម្រាប់ការកំណត់ថ្មីដែលបានមកពីฟอร์ม។
        $new_period_settings = [
            'morning'        => ['start' => '', 'end' => '', 'action' => 'Check-In'],
            'afternoon_out'  => ['start' => '', 'end' => '', 'action' => 'Check-Out'],
            'afternoon_in'   => ['start' => '', 'end' => '', 'action' => 'Check-In'],
            'evening'        => ['start' => '', 'end' => '', 'action' => 'Check-Out']
        ];

        // បញ្ចូលទិន្នន័យពី $_POST ទៅក្នុងរចនាសម្ព័ន្ធនៃការកំណត់ថ្មី។
        foreach ($new_period_settings as $period => &$details) {
            $details['start']  = $_POST[$period . '_start'] ?? '';
            $details['end']    = $_POST[$period . '_end'] ?? '';
            $details['action'] = $_POST[$period . '_action'] ?? $details['action'];
        }

        // ជំហានទី៣៖ បញ្ចូលការកំណត់ថ្មីទៅក្នុងការកំណត់ដែលមានស្រាប់។
        // វានឹងបន្ថែម/អាប់ដេតការកំណត់ពេលថ្មី ខណៈពេលដែលរក្សាទុក key ចាស់ៗ
        // ដូចជា 'check_in_ranges' និង 'check_out_ranges' មិនឱ្យបាត់បង់។
        $all_users[$user_index]['timeSettings'] = array_merge($existing_settings, $new_period_settings);
        // *** END: MODIFIED CODE BLOCK ***


        // រក្សាទុកបញ្ជីអ្នកប្រើប្រាស់ទាំងអស់ត្រឡប់ទៅឯកសារវិញ
        if (save_user_data($all_users)) {
            $message = '<div class="alert alert-success">ការកំណត់សម្រាប់ ' . htmlspecialchars($all_users[$user_index]['username']) . ' ត្រូវបានរក្សាទុក!</div>';
        } else {
            $message = '<div class="alert alert-danger">មានបញ្ហាក្នុងការរក្សាទុកការកំណត់។</div>';
        }
    } else {
        $message = '<div class="alert alert-danger">រកមិនឃើញអ្នកប្រើប្រាស់។</div>';
    }
}

// Load all users to display in the list
$users = load_user_data();

?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>កំណត់ពេលវេលាស្កេនសម្រាប់បុគ្គលិក</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Kantumruy Pro', sans-serif; background-color: #f0f2f5; }
        .container { max-width: 800px; margin-top: 30px; }
        .card-header { background-color: #3b5998; color: white; }
        .modal-header { background-color: #6c757d; color: white; }
        .user-list-item { cursor: pointer; }
        .user-list-item:hover { background-color: #e9ecef; }
        .time-input { width: 130px; display: inline-block; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h3>កំណត់ម៉ោងស្កេនសម្រាប់បុគ្គលិកម្នាក់ៗ</h3>
            </div>
            <div class="card-body">
                <?php echo $message; ?>
                <p class="text-muted">ចុចលើឈ្មោះបុគ្គលិកដើម្បីកំណត់ ឬកែប្រែម៉ោងស្កេនសម្រាប់បុគ្គលនោះ។</p>
                <div class="list-group">
                    <?php if (empty($users)): ?>
                        <p class="text-center">មិនមានទិន្នន័យអ្នកប្រើប្រាស់។</p>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <a href="#" class="list-group-item list-group-item-action user-list-item" data-bs-toggle="modal" data-bs-target="#settingsModal" 
                               data-user-id="<?php echo htmlspecialchars($user['id']); ?>" 
                               data-user-name="<?php echo htmlspecialchars($user['username']); ?>"
                               data-settings='<?php echo json_encode($user['timeSettings'] ?? []); ?>'>
                                <div class="d-flex w-100 justify-content-between">
                                    <h5 class="mb-1"><?php echo htmlspecialchars($user['username']); ?></h5>
                                    <small>ID: <?php echo htmlspecialchars($user['id']); ?></small>
                                </div>
                                <p class="mb-1">ដេប៉ាតឺម៉ង់: <?php echo htmlspecialchars($user['department'] ?? 'N/A'); ?></p>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Settings Modal -->
    <div class="modal fade" id="settingsModal" tabindex="-1" aria-labelledby="settingsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="settingsModalLabel">កំណត់ម៉ោងសម្រាប់: <span id="modalUserName"></span></h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="user_id" id="modalUserId">
                        <table class="table table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>ពេល</th>
                                    <th>ចាប់ពីម៉ោង</th>
                                    <th>ដល់ម៉ោង</th>
                                    <th>ប្រភេទស្កេន</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $periods = [
                                    'morning'       => ['label' => 'ព្រឹក (ចូល)', 'default' => 'Check-In'],
                                    'afternoon_out' => ['label' => 'ថ្ងៃ (ចេញ)', 'default' => 'Check-Out'],
                                    'afternoon_in'  => ['label' => 'ថ្ងៃ (ចូល)', 'default' => 'Check-In'],
                                    'evening'       => ['label' => 'ល្ងាច (ចេញ)', 'default' => 'Check-Out']
                                ];
                                foreach ($periods as $key => $details):
                                ?>
                                <tr>
                                    <td><strong><?php echo $details['label']; ?></strong></td>
                                    <td><input type="time" name="<?php echo $key; ?>_start" id="<?php echo $key; ?>_start" class="form-control time-input"></td>
                                    <td><input type="time" name="<?php echo $key; ?>_end" id="<?php echo $key; ?>_end" class="form-control time-input"></td>
                                    <td>
                                        <select name="<?php echo $key; ?>_action" id="<?php echo $key; ?>_action" class="form-select">
                                            <option value="Check-In">ស្កេនចូល (Check-In)</option>
                                            <option value="Check-Out">ស្កេនចេញ (Check-Out)</option>
                                        </select>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">បោះបង់</button>
                        <button type="submit" class="btn btn-primary">រក្សាទុកការកំណត់</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var settingsModal = document.getElementById('settingsModal');
        settingsModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var userId = button.getAttribute('data-user-id');
            var userName = button.getAttribute('data-user-name');
            var settings = JSON.parse(button.getAttribute('data-settings'));

            var modalTitle = settingsModal.querySelector('.modal-title #modalUserName');
            var modalUserIdInput = settingsModal.querySelector('#modalUserId');

            modalTitle.textContent = userName;
            modalUserIdInput.value = userId;

            // Define periods to populate the form
            const periods = ['morning', 'afternoon_out', 'afternoon_in', 'evening'];
            const defaultActions = {
                morning: 'Check-In',
                afternoon_out: 'Check-Out',
                afternoon_in: 'Check-In',
                evening: 'Check-Out'
            };

            periods.forEach(period => {
                const periodSettings = settings[period] || {};
                document.getElementById(`${period}_start`).value = periodSettings.start || '';
                document.getElementById(`${period}_end`).value = periodSettings.end || '';
                document.getElementById(`${period}_action`).value = periodSettings.action || defaultActions[period];
            });
        });
    });
    </script>
</body>
</html>