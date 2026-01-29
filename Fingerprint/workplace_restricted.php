<?php
header('Content-Type: application/javascript');
session_start();
$adminWorkplace = isset($_SESSION['admin_workplace']) ? json_encode($_SESSION['admin_workplace']) : 'null';
?>
// Workplace restriction
const adminWorkplace = <?php echo $adminWorkplace; ?>;

// Filter data by workplace
function filterByWorkplace(data, key = 'workplace') {
    if (!adminWorkplace) return data; // No restriction for super-admins (if any)
    return data.filter(item => item[key] === adminWorkplace);
}

// Load users
async function loadUsers() {
    try {
        const filteredUsers = filterByWorkplace(users);
        cachedFilteredRows = filteredUsers.map((user, index) => {
            return `
                <tr>
                    <td>${index + 1}</td>
                    <td>${user.id}</td>
                    <td>${user.name}</td>
                    <td>${user.department}</td>
                    <td>${user.workplace}</td>
                    <td>${user.position}</td>
                    <td>${user.folder}</td>
                    <td>
                        <button class="btn btn-sm btn-warning" onclick="editUser('${user.id}')">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="deleteUser('${user.id}')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        });
        filterUsers();
    } catch (error) {
        Swal.fire('កំហុស!', 'មិនអាចផ្ទុកអ្នកប្រើប្រាស់បានទេ!', 'error');
    }
}

// Load folders
async function loadFolders() {
    try {
        const filteredFolders = filterByWorkplace(folders, 'workplace'); // Assuming folders have a workplace field
        const foldersTable = document.getElementById('foldersTable');
        foldersTable.innerHTML = filteredFolders.map((folder, index) => `
            <tr>
                <td>${index + 1}</td>
                <td>${folder.name}</td>
                <td>
                    <button class="btn btn-sm btn-warning" onclick="editFolder('${folder.id}')">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteFolder('${folder.id}')">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `).join('');
    } catch (error) {
        Swal.fire('កំហុស!', 'មិនអាចផ្ទុក Folders បានទេ!', 'error');
    }
}

// Load locations
async function loadLocations() {
    try {
        const filteredLocations = filterByWorkplace(allowedLocations);
        const locationsTable = document.getElementById('locationsTable');
        locationsTable.innerHTML = filteredLocations.map((location, index) => `
            <tr>
                <td>${index + 1}</td>
                <td>${location.name}</td>
                <td>${location.workplace}</td>
                <td>${location.latitude}, ${location.longitude}</td>
                <td>
                    <button class="btn btn-sm btn-warning" onclick="editLocation('${location.id}')">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteLocation('${location.id}')">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `).join('');
    } catch (error) {
        Swal.fire('កំហុស!', 'មិនអាចផ្ទុកទីតាំងបានទេ!', 'error');
    }
}

// Load tokens
async function loadTokens() {
    try {
        const filteredTokens = filterByWorkplace(activeUsers, 'workplace'); // Assuming tokens have a workplace
        const tokensTable = document.getElementById('tokensTable');
        tokensTable.innerHTML = filteredTokens.map((token, index) => `
            <tr>
                <td>${index + 1}</td>
                <td>${token.username}</td>
                <td>${token.token}</td>
                <td>${token.device}</td>
                <td>${token.ip}</td>
                <td>${token.expiry}</td>
                <td>
                    <button class="btn btn-sm btn-danger" onclick="revokeToken('${token.token}')">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `).join('');
    } catch (error) {
        Swal.fire('កំហុស!', 'មិនអាចផ្ទុកតូកែនបានទេ!', 'error');
    }
}

// Filter users
function filterUsers() {
    const searchTerm = document.getElementById('searchUsers').value.toLowerCase();
    const filterFolder = document.getElementById('filterFolder').value;
    const filterWorkplace = document.getElementById('filterWorkplace').value;

    const filteredRows = cachedFilteredRows.filter(row => {
        const text = row.toLowerCase();
        return text.includes(searchTerm) &&
               (!filterFolder || text.includes(filterFolder.toLowerCase())) &&
               (!filterWorkplace || text.includes(filterWorkplace.toLowerCase()));
    });

    displayPage(currentPage, filteredRows);
}

// Display page
function displayPage(page, rows) {
    currentPage = page;
    const start = (page - 1) * rowsPerPage;
    const end = start + rowsPerPage;
    const paginatedRows = rows.slice(start, end);

    document.getElementById('usersTable').innerHTML = paginatedRows.join('');

    const totalPages = Math.ceil(rows.length / rowsPerPage);
    const pagination = document.getElementById('pagination');
    pagination.innerHTML = '';
    for (let i = 1; i <= totalPages; i++) {
        pagination.innerHTML += `
            <li class="page-item ${i === page ? 'active' : ''}">
                <a class="page-link" href="#" onclick="displayPage(${i}, ${JSON.stringify(rows)})">${i}</a>
            </li>
        `;
    }
}

// Add user
document.getElementById('saveUser').addEventListener('click', async () => {
    const userId = document.getElementById('userId').value.trim();
    const userName = document.getElementById('userName').value.trim();
    const userDepartment = document.getElementById('userDepartment').value.trim();
    const userWorkplace = document.getElementById('userWorkplace').value;
    const userPosition = document.getElementById('userPosition').value.trim();
    const userFolder = document.getElementById('userFolder').value;

    if (!userId || !userName || !userDepartment || !userWorkplace || !userPosition || !userFolder) {
        Swal.fire('កំហុស!', 'សូមបញ្ចូលព័ត៌មានគ្រប់ផ្នែក!', 'error');
        return;
    }

    if (userWorkplace !== adminWorkplace) {
        Swal.fire('កំហុស!', 'អ្នកអាចបន្ថែមអ្នកប្រើប្រាស់នៅក្នុង Workplace របស់អ្នកប៉ុណ្ណោះ!', 'error');
        return;
    }

    try {
        const result = await sendRequest('add_user', {
            id: userId,
            name: userName,
            department: userDepartment,
            workplace: userWorkplace,
            position: userPosition,
            folder: userFolder
        });
        users.push(result.data);
        loadUsers();
        document.getElementById('addUserForm').reset();
        bootstrap.Modal.getInstance(document.getElementById('addUserModal')).hide();
        Swal.fire('ជោគជ័យ!', 'អ្នកប្រើប្រាស់ត្រូវបានបន្ថែម!', 'success');
    } catch (error) {}
});

// Edit user
async function editUser(id) {
    const user = users.find(u => u.id === id);
    if (!user) {
        Swal.fire('កំហុស!', 'រកមិនឃើញអ្នកប្រើប្រាស់!', 'error');
        return;
    }

    if (user.workplace !== adminWorkplace) {
        Swal.fire('កំហុស!', 'អ្នកអាចកែសម្រួលអ្នកប្រើប្រ៓ស់នៅក្នុង Workplace របស់អ្នកប៉ុណ្ណោះ!', 'error');
        return;
    }

    document.getElementById('editUserId').value = user.id;
    document.getElementById('editUserName').value = user.name;
    document.getElementById('editUserDepartment').value = user.department;
    document.getElementById('editUserWorkplace').value = user.workplace;
    document.getElementById('editUserPosition').value = user.position;
    document.getElementById('editUserFolder').value = user.folder;

    const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
    modal.show();
}

// Update user
document.getElementById('updateUser').addEventListener('click', async () => {
    const id = document.getElementById('editUserId').value;
    const name = document.getElementById('editUserName').value.trim();
    const department = document.getElementById('editUserDepartment').value.trim();
    const workplace = document.getElementById('editUserWorkplace').value;
    const position = document.getElementById('editUserPosition').value.trim();
    const folder = document.getElementById('editUserFolder').value;

    if (!name || !department || !workplace || !position || !folder) {
        Swal.fire('កំហុស!', 'សូមបញ្ចូលព័ត៌មានគ្រប់ផ្នែក!', 'error');
        return;
    }

    if (workplace !== adminWorkplace) {
        Swal.fire('កំហុស!', 'អ្នកអាចកែសម្រួលអ្នកប្រើប្រាស់នៅក្នុង Workplace របស់អ្នកប៉ុណ្ណោះ!', 'error');
        return;
    }

    try {
        const result = await sendRequest('edit_user', {
            id, name, department, workplace, position, folder
        });
        const index = users.findIndex(u => u.id === id);
        users[index] = result.data;
        loadUsers();
        bootstrap.Modal.getInstance(document.getElementById('editUserModal')).hide();
        Swal.fire('ជោគជ័យ!', 'អ្នកប្រើប្រាស់ត្រូវបានកែសម្រួល!', 'success');
    } catch (error) {}
});

// Delete user
async function deleteUser(id) {
    const user = users.find(u => u.id === id);
    if (user.workplace !== adminWorkplace) {
        Swal.fire('កំហុស!', 'អ្នកអាចលុបអ្នកប្រើប្រាស់នៅក្នុង Workplace របស់អ្នកប៉ុណ្ណោះ!', 'error');
        return;
    }

    const result = await Swal.fire({
        title: 'តើអ្នកប្រាកដទេ?',
        text: 'អ្នកនឹងមិនអាចស្តារអ្នកប្រើប្រាស់នេះមកវិញបានទេ!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'លុប',
        cancelButtonText: 'បោះបង់'
    });

    if (result.isConfirmed) {
        try {
            await sendRequest('delete_user', { id });
            users = users.filter(u => u.id !== id);
            loadUsers();
            Swal.fire('ជោគជ័យ!', 'អ្នកប្រើប្រាស់ត្រូវបានលុប!', 'success');
        } catch (error) {}
    }
}

// Add folder
document.getElementById('saveFolder').addEventListener('click', async () => {
    const folderName = document.getElementById('folderName').value.trim();
    if (!folderName) {
        Swal.fire('កំហុស!', 'សូមបញ្ចូលឈ្មោះ Folder!', 'error');
        return;
    }
    if (folders.some(f => f.name.toLowerCase() === folderName.toLowerCase())) {
        Swal.fire('កំហុស!', 'ឈ្មោះ Folder នេះមានរួចហើយ!', 'error');
        return;
    }

    try {
        const result = await sendRequest('add_folder', {
            name: folderName,
            workplace: adminWorkplace // Restrict folder to admin's workplace
        });
        folders.push(result.data);
        loadFolders();
        document.getElementById('addFolderForm').reset();
        bootstrap.Modal.getInstance(document.getElementById('addFolderModal')).hide();
        Swal.fire('ជោគជ័យ!', 'Folder ត្រូវបានបន្ថែម!', 'success');
    } catch (error) {}
});

// Edit folder
async function editFolder(id) {
    const folder = folders.find(f => f.id === id);
    if (!folder || folder.workplace !== adminWorkplace) {
        Swal.fire('កំហុស!', 'អ្នកអាចកែសម្រួល Folder នៅក្នុង Workplace របស់អ្នកប៉ុណ្ណោះ!', 'error');
        return;
    }

    document.getElementById('editFolderId').value = folder.id;
    document.getElementById('editFolderName').value = folder.name;

    const modal = new bootstrap.Modal(document.getElementById('editFolderModal'));
    modal.show();
}

// Update folder
document.getElementById('updateFolder').addEventListener('click', async () => {
    const id = document.getElementById('editFolderId').value;
    const name = document.getElementById('editFolderName').value.trim();

    if (!name) {
        Swal.fire('កំហុស!', 'សូមបញ្ចូលឈ្មោះ Folder!', 'error');
        return;
    }

    try {
        const result = await sendRequest('edit_folder', {
            id, name, workplace: adminWorkplace
        });
        const index = folders.findIndex(f => f.id === id);
        folders[index] = result.data;
        loadFolders();
        bootstrap.Modal.getInstance(document.getElementById('editFolderModal')).hide();
        Swal.fire('ជោគជ័យ!', 'Folder ត្រូវបានកែសម្រួល!', 'success');
    } catch (error) {}
});

// Delete folder
async function deleteFolder(id) {
    const folder = folders.find(f => f.id === id);
    if (folder.workplace !== adminWorkplace) {
        Swal.fire('កំហុស!', 'អ្នកអាចលុប Folder នៅក្នុង Workplace របស់អ្នកប៉ុណ្ណោះ!', 'error');
        return;
    }

    const result = await Swal.fire({
        title: 'តើអ្នកប្រាកដទេ?',
        text: 'អ្នកនឹងមិនអាចស្តារ Folder នេះមកវិញបានទេ!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'លុប',
        cancelButtonText: 'បោះបង់'
    });

    if (result.isConfirmed) {
        try {
            await sendRequest('delete_folder', { id });
            folders = folders.filter(f => f.id !== id);
            loadFolders();
            Swal.fire('ជោគជ័យ!', 'Folder ត្រូវបានលុប!', 'success');
        } catch (error) {}
    }
}

// Add location
document.getElementById('saveLocation').addEventListener('click', async () => {
    const name = document.getElementById('locationName').value.trim();
    const workplace = document.getElementById('locationWorkplace').value;
    const latitude = document.getElementById('locationLatitude').value;
    const longitude = document.getElementById('locationLongitude').value;

    if (!name || !workplace || !latitude || !longitude) {
        Swal.fire('កំហុស!', 'សូមបញ្ចូលព័ត៌មានគ្រប់ផ្នែក!', 'error');
        return;
    }

    if (workplace !== adminWorkplace) {
        Swal.fire('កំហុស!', 'អ្នកអាចបន្ថែមទីតាំងនៅក្នុង Workplace របស់អ្នកប៉ុណ្ណោះ!', 'error');
        return;
    }

    try {
        const result = await sendRequest('add_location', {
            name, workplace, latitude, longitude
        });
        allowedLocations.push(result.data);
        loadLocations();
        document.getElementById('addLocationForm').reset();
        bootstrap.Modal.getInstance(document.getElementById('addLocationModal')).hide();
        Swal.fire('ជោគជ័យ!', 'ទីតាំងត្រូវបានបន្ថែម!', 'success');
    } catch (error) {}
});

// Edit location
async function editLocation(id) {
    const location = allowedLocations.find(l => l.id === id);
    if (!location || location.workplace !== adminWorkplace) {
        Swal.fire('កំហុស!', 'អ្នកអាចកែសម្រួលទីតាំងនៅក្នុង Workplace �របស់អ្នកប៉ុណ្ណោះ!', 'error');
        return;
    }

    document.getElementById('editLocationId').value = location.id;
    document.getElementById('editLocationName').value = location.name;
    document.getElementById('editLocationWorkplace').value = location.workplace;
    document.getElementById('editLocationLatitude').value = location.latitude;
    document.getElementById('editLocationLongitude').value = location.longitude;

    const modal = new bootstrap.Modal(document.getElementById('editLocationModal'));
    modal.show();
}

// Update location
document.getElementById('updateLocation').addEventListener('click', async () => {
    const id = document.getElementById('editLocationId').value;
    const name = document.getElementById('editLocationName').value.trim();
    const workplace = document.getElementById('editLocationWorkplace').value;
    const latitude = document.getElementById('editLocationLatitude').value;
    const longitude = document.getElementById('editLocationLongitude').value;

    if (!name || !workplace || !latitude || !longitude) {
        Swal.fire('កំហុស!', 'សូមបញ្ចូលព័ត៌មានគ្រប់ផ្នែក!', 'error');
        return;
    }

    if (workplace !== adminWorkplace) {
        Swal.fire('កំហុស!', 'អ្នកអាចកែសម្រួលទីតាំងនៅក្នុង Workplace របស់អ្នកប៉ុណ្ណោះ!', 'error');
        return;
    }

    try {
        const result = await sendRequest('edit_location', {
            id, name, workplace, latitude, longitude
        });
        const index = allowedLocations.findIndex(l => l.id === id);
        allowedLocations[index] = result.data;
        loadLocations();
        bootstrap.Modal.getInstance(document.getElementById('editLocationModal')).hide();
        Swal.fire('ជោគជ័យ!', 'ទីតាំងត្រូវបានកែសម្រួល!', 'success');
    } catch (error) {}
});

// Delete location
async function deleteLocation(id) {
    const location = allowedLocations.find(l => l.id === id);
    if (location.workplace !== adminWorkplace) {
        Swal.fire('កំហុស!', 'អ្នកអាចលុបទីតាំងនៅក្នុង Workplace របស់អ្នកប៉ុណ្ណោះ!', 'error');
        return;
    }

    const result = await Swal.fire({
        title: 'តើអ្នកប្រាកដទេ?',
        text: 'អ្នកនឹងមិនអាចស្តារទីតាំងនេះមកវិញបានទេ!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'លុប',
        cancelButtonText: 'បោះបង់'
    });

    if (result.isConfirmed) {
        try {
            await sendRequest('delete_location', { id });
            allowedLocations = allowedLocations.filter(l => l.id !== id);
            loadLocations();
            Swal.fire('ជោគជ័យ!', 'ទីតាំងត្រូវបានលុប!', 'success');
        } catch (error) {}
    }
}

// Revoke token
async function revokeToken(token) {
    const userToken = activeUsers.find(t => t.token === token);
    if (userToken.workplace !== adminWorkplace) {
        Swal.fire('កំហុស!', 'អ្នកអាចលុបតូកែននៅក្នុង Workplace របស់អ្នកប៉ុណ្ណោះ!', 'error');
        return;
    }

    const result = await Swal.fire({
        title: 'តើអ្នកប្រាកដទេ?',
        text: 'តូកែននេះនឹងត្រូវបានលុប!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'លុប',
        cancelButtonText: 'បោះបង់'
    });

    if (result.isConfirmed) {
        try {
            await sendRequest('revoke_token', { token });
            activeUsers = activeUsers.filter(t => t.token !== token);
            loadTokens();
            Swal.fire('ជោគជ័យ!', 'តូកែនត្រូវបានលុប!', 'success');
        } catch (error) {}
    }
}

// Map setup
let addMap, editMap, addMarker, editMarker;

function setupAddMap() {
    addMap = L.map('addMap').setView([11.562108, 104.916], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(addMap);
    addMarker = L.marker([11.562108, 104.916], { draggable: true }).addTo(addMap);
    addMarker.on('dragend', () => {
        const position = addMarker.getLatLng();
        document.getElementById('locationLatitude').value = position.lat;
        document.getElementById('locationLongitude').value = position.lng;
    });
}

function setupEditMap(latitude, longitude) {
    editMap = L.map('editMap').setView([latitude, longitude], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(editMap);
    editMarker = L.marker([latitude, longitude], { draggable: true }).addTo(editMap);
    editMarker.on('dragend', () => {
        const position = editMarker.getLatLng();
        document.getElementById('editLocationLatitude').value = position.lat;
        document.getElementById('editLocationLongitude').value = position.lng;
    });
}

// Initialize maps on modal show
document.getElementById('addLocationModal').addEventListener('shown.bs.modal', () => {
    setupAddMap();
    setTimeout(() => addMap.invalidateSize(), 100);
});

document.getElementById('editLocationModal').addEventListener('shown.bs.modal', () => {
    const latitude = parseFloat(document.getElementById('editLocationLatitude').value) || 11.562108;
    const longitude = parseFloat(document.getElementById('editLocationLongitude').value) || 104.916;
    setupEditMap(latitude, longitude);
    setTimeout(() => editMap.invalidateSize(), 100);
});

// Dark mode toggle
document.getElementById('toggleDarkMode').addEventListener('click', () => {
    document.body.classList.toggle('dark-mode');
    localStorage.setItem('darkMode', document.body.classList.contains('dark-mode'));
});

if (localStorage.getItem('darkMode') === 'true') {
    document.body.classList.add('dark-mode');
}

// Back to top
const backToTop = document.getElementById('backToTop');
window.addEventListener('scroll', () => {
    backToTop.style.display = window.scrollY > 200 ? 'block' : 'none';
});

backToTop.addEventListener('click', () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
});

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    loadUsers();
    loadFolders();
    loadLocations();
    loadTokens();
});