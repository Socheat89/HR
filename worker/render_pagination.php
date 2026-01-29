<nav aria-label="Page navigation">
    <ul class="pagination">
        <?php if ($page > 1): ?>
            <li class="page-item"><a class="page-link" href="#" onclick="fetchLogs(<?php echo $page - 1; ?>)">មុន</a></li>
        <?php endif; ?>
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                <a class="page-link" href="#" onclick="fetchLogs(<?php echo $i; ?>)"><?php echo $i; ?></a>
            </li>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
            <li class="page-item"><a class="page-link" href="#" onclick="fetchLogs(<?php echo $page + 1; ?>)">បន្ទាប់</a></li>
        <?php endif; ?>
    </ul>
</nav>