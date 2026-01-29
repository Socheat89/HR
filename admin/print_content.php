<?php
// Start the session (optional, kept for consistency with other pages if needed)
session_start();

// Include database connection
include 'includes/db.php';
$conn = include 'includes/db.php';

// Fetch all PDFs (no user restriction since login is removed)
try {
    $stmt = $conn->query("
        SELECT p.id, p.title, p.file_path, p.created_at, u.username AS author
        FROM pdf_posts p
        JOIN users u ON p.user_id = u.id
        ORDER BY p.created_at DESC
    ");
    $pdfs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Debug output removed
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $pdfs = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ឯកសារព្រីន 📄</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    <!-- Swiper CSS -->
    <link href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" rel="stylesheet">
   <style>
    /* --- Modern & Premium Gradient Theme --- */
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
    @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;500;700&display=swap');

    :root {
        --bg-gradient: linear-gradient(135deg, #f5f7fa 0%, #eef2f7 100%);
        --card-bg-color: #ffffff;
        --primary-gradient: linear-gradient(45deg, #3b82f6, #8b5cf6);
        --primary-gradient-hover: linear-gradient(45deg, #2563eb, #7c3aed);
        --text-color-primary: #1f2937;
        --text-color-secondary: #6b7280;
        --border-color: #e5e7eb;
        --card-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        --card-shadow-hover: 0 10px 25px rgba(0, 0, 0, 0.1);
        --font-family-en: 'Poppins', sans-serif;
        --font-family-km: 'Noto Sans Khmer', sans-serif;
    }

    body {
        background-image: var(--bg-gradient);
        font-family: var(--font-family-en), var(--font-family-km);
        color: var(--text-color-primary);
        margin: 0;
        padding: 0;
        line-height: 1.7;
    }

    .main-content {
        padding: 2.5rem;
        max-width: 1280px;
        margin: 0 auto;
    }

    /* --- Header Styling --- */
    .header {
        text-align: center;
        margin-bottom: 3rem;
        position: relative;
    }

    .header h1 {
        font-size: 2.8rem;
        font-weight: 700;
        color: var(--text-color-primary);
        letter-spacing: -1px;
    }

    .back-btn {
        display: inline-flex;
        align-items: center;
        position: absolute;
        top: 50%;
        left: 0;
        transform: translateY(-50%);
        background-color: var(--card-bg-color);
        color: var(--text-color-secondary);
        padding: 0.6rem 1.2rem;
        border-radius: 10px;
        font-weight: 500;
        text-decoration: none;
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
        box-shadow: var(--card-shadow);
    }

    .back-btn:hover {
        color: #3b82f6;
        border-color: #3b82f6;
        transform: translateY(-52%) scale(1.05);
        box-shadow: 0 2px 10px rgba(59, 130, 246, 0.2);
    }

    .back-btn i {
        margin-right: 0.6rem;
    }

    /* --- Search and Filter Styling --- */
    .search-filter-container {
        margin-bottom: 3rem;
        text-align: center;
    }

    .search-input {
        width: 100%;
        max-width: 600px;
        padding: 0.9rem 1.5rem;
        border: 1px solid var(--border-color);
        border-radius: 12px;
        font-size: 1rem;
        background-color: var(--card-bg-color);
        box-shadow: var(--card-shadow);
        transition: all 0.3s ease;
    }

    .search-input:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
    }
    
    .filter-select {
         padding: 0.9rem 1.2rem;
         border-radius: 12px;
         border: 1px solid var(--border-color);
         background-color: var(--card-bg-color);
         font-size: 1rem;
         box-shadow: var(--card-shadow);
    }
     .filter-select:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
    }

    /* --- Swiper Carousel --- */
    .swiper-slide {
        background: var(--card-bg-color);
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: var(--card-shadow);
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
    }
     .swiper-slide:hover {
        transform: translateY(-5px);
        box-shadow: var(--card-shadow-hover);
     }
    .swiper-slide h3 {
        font-size: 1.1rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
    }
    .swiper-slide p {
        font-size: 0.9rem;
        color: var(--text-color-secondary);
    }
    .swiper-button-next, .swiper-button-prev {
        color: #3b82f6;
    }

    /* --- PDF Grid & Card Styling --- */
    .pdf-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 2rem;
    }

    .pdf-card {
        background: var(--card-bg-color);
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: var(--card-shadow);
        position: relative;
        overflow: hidden;
        border: 1px solid var(--border-color);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        display: flex;
        flex-direction: column;
    }
    
    /* The magic gradient border on hover */
    .pdf-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background: var(--primary-gradient);
        border-radius: 16px;
        opacity: 0;
        z-index: 0;
        transition: opacity 0.3s ease;
    }
    
    .pdf-card:hover {
        transform: translateY(-8px);
        box-shadow: var(--card-shadow-hover);
    }

    .pdf-card:hover::before {
        opacity: 1;
    }
    
    /* Create a container inside to sit on top of the gradient background */
    .pdf-card > * {
        position: relative;
        z-index: 1;
    }
    
    /* Create a white layer to mask the gradient, leaving a 2px border */
    .pdf-card::after {
        content: '';
        position: absolute;
        top: 2px; left: 2px; right: 2px; bottom: 2px;
        background: var(--card-bg-color);
        border-radius: 14px; /* Slightly smaller than parent */
        z-index: 0;
    }

    .pdf-card h2 {
        font-size: 1.3rem;
        font-weight: 600;
        color: var(--text-color-primary);
        margin-bottom: 0.5rem;
    }

    .pdf-card .meta {
        color: var(--text-color-secondary);
        font-size: 0.9rem;
        margin-bottom: 1.5rem;
        flex-grow: 1; /* Pushes button to bottom */
    }

    /* --- Button Styling --- */
    .print-btn {
        background-image: var(--primary-gradient);
        color: #fff;
        padding: 0.7rem 1.5rem;
        border: none;
        border-radius: 10px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        text-decoration: none;
        box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
        align-self: flex-start;
    }

    .print-btn:hover {
        background-image: var(--primary-gradient-hover);
        transform: scale(1.05);
        box-shadow: 0 6px 15px rgba(99, 102, 241, 0.4);
    }

    .print-btn i {
        margin-right: 0.6rem;
    }
    
    /* --- Fallback Message --- */
    .no-pdfs {
        text-align: center;
        padding: 3rem;
        background: var(--card-bg-color);
        border: 2px dashed var(--border-color);
        border-radius: 16px;
        color: var(--text-color-secondary);
        font-size: 1.1rem;
    }

    /* --- Responsive Adjustments --- */
    @media (max-width: 768px) {
        .main-content {
            padding: 1.5rem;
        }
        .header h1 {
            font-size: 2.2rem;
        }
        .back-btn {
            position: static;
            transform: none;
            margin: 0 auto 1.5rem auto;
            display: table;
        }
        .pdf-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
</head>
<body>
    <div class="main-content">
        <div class="header">
            <a href="javascript:history.back()" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <h1>ឯកសារព្រីន 📄</h1>
        </div>
        
        <div class="search-filter-container flex justify-center items-center gap-4">
            <input type="text" id="searchInput" class="search-input" placeholder="Search PDFs by title or author...">
            <select id="filterSelect" class="filter-select">
                <option value="all">All PDFs</option>
                <option value="recent">Recent</option>
                <option value="author">By Author</option>
            </select>
        </div>

        <div class="featured-carousel mb-4">
            <div class="swiper mySwiper">
                <div class="swiper-wrapper">
                    <?php if (!empty($pdfs)): ?>
                        <?php foreach (array_slice($pdfs, 0, 5) as $pdf): ?>
                            <div class="swiper-slide">
                                <h3><?php echo htmlspecialchars($pdf['title']); ?></h3>
                                <p>By <?php echo htmlspecialchars($pdf['author']); ?> on <?php echo date('F j, Y', strtotime($pdf['created_at'])); ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="swiper-button-next text-ffd700"></div>
                <div class="swiper-button-prev text-ffd700"></div>
            </div>
        </div>

        <?php if (empty($pdfs)): ?>
            <p class="no-pdfs">No PDFs available in the vault.</p>
        <?php else: ?>
            <div class="pdf-grid">
                <?php foreach ($pdfs as $pdf): ?>
                    <div class="pdf-card" id="pdf-<?php echo $pdf['id']; ?>" data-title="<?php echo htmlspecialchars(strtolower($pdf['title'])); ?>" data-author="<?php echo htmlspecialchars(strtolower($pdf['author'])); ?>" data-date="<?php echo strtotime($pdf['created_at']); ?>">
                        <h2><?php echo htmlspecialchars($pdf['title']); ?></h2>
                        <div class="meta">
                            By <?php echo htmlspecialchars($pdf['author']); ?> on 
                            <?php echo date('F j, Y, g:i a', strtotime($pdf['created_at'])); ?>
                        </div>
                        <button class="print-btn" onclick="printPDF('<?php echo htmlspecialchars($pdf['file_path']); ?>')">
                            <i class="fas fa-print"></i> Print
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Updated Swiper JS -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script>
        function printPDF(filePath) {
            const printWindow = window.open(filePath, '_blank');
            printWindow.onload = function() {
                printWindow.print();
            };
            setTimeout(() => {
                printWindow.print();
            }, 1000);
        }

        document.addEventListener('DOMContentLoaded', () => {
            const searchInput = document.getElementById('searchInput');
            const filterSelect = document.getElementById('filterSelect');
            const pdfCards = document.querySelectorAll('.pdf-card');

            function filterCards(searchTerm, filterType) {
                pdfCards.forEach(card => {
                    const title = card.getAttribute('data-title');
                    const author = card.getAttribute('data-author');
                    const date = parseInt(card.getAttribute('data-date'));
                    let matches = false;

                    if (title.includes(searchTerm) || author.includes(searchTerm)) {
                        matches = true;
                    }

                    if (filterType === 'recent' && matches) {
                        const recentThreshold = new Date().getTime() - (30 * 24 * 60 * 60 * 1000);
                        matches = date > recentThreshold;
                    } else if (filterType === 'author' && matches) {
                        matches = author.includes(searchTerm);
                    }

                    card.style.display = matches ? '' : 'none';
                });
            }

            searchInput.addEventListener('input', (event) => {
                const searchTerm = event.target.value.toLowerCase().trim();
                const filterType = filterSelect.value;
                filterCards(searchTerm, filterType);
            });

            filterSelect.addEventListener('change', () => {
                const searchTerm = searchInput.value.toLowerCase().trim();
                const filterType = filterSelect.value;
                filterCards(searchTerm, filterType);
            });

            const swiper = new Swiper('.mySwiper', {
                slidesPerView: 3,
                spaceBetween: 20,
                navigation: {
                    nextEl: '.swiper-button-next',
                    prevEl: '.swiper-button-prev',
                },
                breakpoints: {
                    768: {
                        slidesPerView: 2,
                    },
                    480: {
                        slidesPerView: 1,
                    },
                },
            });
        });
    </script>
</body>
</html>