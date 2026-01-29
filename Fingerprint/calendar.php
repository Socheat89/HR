<?php
header('Content-Type: text/html; charset=utf-8');

// Get year and month from GET parameters or use current date
$year = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT) ?: date('Y');
$month = filter_input(INPUT_GET, 'month', FILTER_VALIDATE_INT) ?: date('n');
if ($month < 1 || $month > 12) $month = date('n');
if ($year < 1970 || $year > 9999) $year = date('Y');

// Cache setup
$cacheKey = "calendar_{$year}_{$month}";
$cacheDir = 'cache';
$cacheFile = "$cacheDir/{$cacheKey}.html";
if (!is_dir($cacheDir)) mkdir($cacheDir, 0755, true);

// Check cache
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
    echo file_get_contents($cacheFile);
    exit;
}
ob_start();

// Calculate calendar details
$today = ($year == date('Y') && $month == date('n')) ? date('j') : 0;
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$firstDayOfWeek = (new DateTime("$year-$month-01"))->format('N');

// Khmer month names
$khmerMonths = ['មករា', 'កុម្ភៈ', 'មីនា', 'មេសា', 'ឧសភា', 'មិថុនា', 'កក្កដា', 'សីហា', 'កញ្ញា', 'តុលា', 'វិច្ឆិកា', 'ធ្នូ'];

// Calculate previous and next month/year
$prevMonth = $month == 1 ? 12 : $month - 1;
$prevYear = $month == 1 ? $year - 1 : $year;
$nextMonth = $month == 12 ? 1 : $month + 1;
$nextYear = $month == 12 ? $year + 1 : $year;
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <title>ប្រតិទិន</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;700&display=swap">
    <link rel="manifest" href="/manifest.json">
    <style>
        body{font-family:'Noto Sans Khmer',Arial,sans-serif;background:#f8fbff;margin:0}.calendar-container{max-width:420px;margin:1.5rem auto;background:#fff;border-radius:1.2rem;box-shadow:0 4px 16px #0001;padding:1.5rem;touch-action:pan-y;transition:transform .3s ease-in-out}h2{color:#2980b9;text-align:center;margin-bottom:1.2rem}.nav{text-align:center;margin-bottom:1rem}.nav a{color:#2980b9;text-decoration:none;margin:0 1rem;font-weight:700}.nav a:hover{text-decoration:underline}table{width:100%;border-collapse:collapse;margin-top:1rem}th,td{text-align:center;padding:.5rem;border-radius:.5rem}th{background:#eaf0fa;color:#2980b9}td.today{background:#ffe066;color:#222;font-weight:700}td{color:#333}td.empty{background:#f5f5f5}td:hover:not(.empty){background:#e0f0ff;transform:scale(1.1);transition:all .2s ease;cursor:pointer}.loading{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);border:4px solid #2980b9;border-top-color:transparent;border-radius:50%;width:30px;height:30px;animation:spin 1s linear infinite;display:none}@keyframes spin{to{transform:translate(-50%,-50%) rotate(360deg)}}@media (max-width:400px){.calendar-container{padding:1rem}h2{font-size:1.2rem}th,td{font-size:.9rem;padding:.4rem}}
    </style>
</head>
<body>
<div class="calendar-container" id="calendar">
    <h2>ប្រតិទិន - <?php echo $khmerMonths[$month-1] . ' ' . $year; ?></h2>
    <div class="nav">
        <a href="?year=<?php echo $prevYear; ?>&month=<?php echo $prevMonth; ?>" title="ខែមុន">« មុន</a> | 
        <a href="?year=<?php echo $nextYear; ?>&month=<?php echo $nextMonth; ?>" title="ខែបន្ទាប់">បន្ទាប់ »</a>
    </div>
    <table role="grid" aria-label="ប្រតិទិនសម្រាប់ <?php echo $khmerMonths[$month-1] . ' ' . $year; ?>">
        <tr>
            <th>ច</th><th>អ</th><th>ព</th><th>ព្រ</th><th>សុ</th><th>ស</th><th>អា</th>
        </tr>
        <tr>
        <?php
        $dow = 1;
        $cells = [];
        // Empty cells before first day
        for ($i = 1; $i < $firstDayOfWeek; $i++, $dow++) {
            $cells[] = "<td class='empty'></td>";
        }
        // Calendar days
        for ($d = 1; $d <= $daysInMonth; $d++, $dow++) {
            $class = ($d == $today) ? 'today" aria-current="date"' : '';
            $cells[] = "<td class='$class'>$d</td>";
            if ($dow % 7 == 0 && $d != $daysInMonth) {
                $cells[] = "</tr><tr>";
            }
        }
        // Empty cells after last day
        while ($dow % 7 != 1) {
            $cells[] = "<td class='empty'></td>";
            $dow++;
        }
        echo implode('', $cells);
        ?>
        </tr>
    </table>
    <div class="loading" id="loading"></div>
</div>
<script>
    const calendar = document.getElementById('calendar');
    const loading = document.getElementById('loading');
    let startX = 0, isSwiping = false;

    calendar.addEventListener('touchstart', (e) => {
        startX = e.touches[0].clientX;
    });

    calendar.addEventListener('touchend', (e) => {
        if (isSwiping) return;
        isSwiping = true;
        endX = e.changedTouches[0].clientX;
        const threshold = 50;
        if (startX - endX > threshold) {
            loading.style.display = 'block';
            calendar.style.transform = 'translateX(-20%)';
            setTimeout(() => {
                window.location.href = '?year=<?php echo $nextYear; ?>&month=<?php echo $nextMonth; ?>';
            }, 300);
        } else if (endX - startX > threshold) {
            loading.style.display = 'block';
            calendar.style.transform = 'translateX(20%)';
            setTimeout(() => {
                window.location.href = '?year=<?php echo $prevYear; ?>&month=<?php echo $prevMonth; ?>';
            }, 300);
        }
        setTimeout(() => isSwiping = false, 300);
    });

    // Preload next and previous months
    function preloadCalendar(year, month) {
        fetch(`?year=${year}&month=${month}`, { method: 'GET' })
            .then(response => response.text())
            .catch(err => console.log('Preload failed:', err));
    }
    preloadCalendar(<?php echo $nextYear; ?>, <?php echo $nextMonth; ?>);
    preloadCalendar(<?php echo $prevYear; ?>, <?php echo $prevMonth; ?>);
</script>
</body>
</html>
<?php
// Save to cache
file_put_contents($cacheFile, ob_get_contents());
ob_end_flush();
?>