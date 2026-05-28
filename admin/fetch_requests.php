<?php
require_once '../system/config.php';

$limit = 20;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$offset = ($page - 1) * $limit;

$sql = "SELECT SQL_CALC_FOUND_ROWS id, request_type, requester_name, department, request_date, reason 
        FROM requests WHERE 1";
$params = [];

if ($search !== '') {
    $sql .= " AND (requester_name LIKE ? OR request_type LIKE ? OR reason LIKE ?)";
    $params = ["%$search%", "%$search%", "%$search%"];
}

$sql .= " ORDER BY request_date DESC LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalRows = $pdo->query("SELECT FOUND_ROWS()")->fetchColumn();
$totalPages = ceil($totalRows / $limit);
?>

<?php if (empty($requests)): ?>
  <p class="text-center text-muted">មិនមានទិន្នន័យសំណើ។</p>
<?php else: ?>
  <div class="table-responsive">
    <table class="table table-bordered table-striped align-middle">
      <thead class="table-light">
        <tr>
          <th>ID</th>
          <th>ប្រភេទសំណើ</th>
          <th>ឈ្មោះអ្នកស្នើសុំ</th>
          <th>ផ្នែក</th>
          <th>កាលបរិច្ឆេទស្នើសុំ</th>
          <th>មូលហេតុ</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($requests as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r['id']) ?></td>
          <td><?= htmlspecialchars($r['request_type']) ?></td>
          <td><?= htmlspecialchars($r['requester_name']) ?></td>
          <td><?= htmlspecialchars($r['department']) ?></td>
          <td><?= htmlspecialchars(date('d-m-Y', strtotime($r['request_date']))) ?></td>
          <td><?= htmlspecialchars($r['reason']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <nav>
    <ul class="pagination justify-content-center mt-3">
      <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
          <a href="#" class="page-link" data-page="<?= $i ?>"><?= $i ?></a>
        </li>
      <?php endfor; ?>
    </ul>
  </nav>
<?php endif; ?>
