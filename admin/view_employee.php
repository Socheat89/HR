<?php
include 'includes/auth.php'; // Include authentication logic

// include 'includes/db.php'; // Assuming this returns a PDO connection
$conn = include 'includes/db.php';

// Validate the ID parameter
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Error: Invalid or missing employee ID.");
}
$id = (int)$_GET['id'];

// Fetch the employee details
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id AND role = 'employee'");
    $stmt->execute(['id' => $id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$employee) {
        error_log("Employee not found for ID: " . $id);
        die("Error: Employee not found.");
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    die("An error occurred while fetching the employee details.");
}

// Handle rating submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $rating = (int)$_POST['rating'];
        $adminId = $_SESSION['user_id']; // ID of the logged-in admin

        // Validate rating
        if ($rating < 1 || $rating > 5) {
            throw new Exception("Invalid rating value. Please choose a rating between 1 and 5.");
        }

        // Insert the rating into the database
        $stmt = $conn->prepare("INSERT INTO ratings (user_id, rater_id, rating) VALUES (:user_id, :rater_id, :rating)");
        $stmt->bindParam(':user_id', $id);
        $stmt->bindParam(':rater_id', $adminId);
        $stmt->bindParam(':rating', $rating);
        $stmt->execute();

        $success_message = "Rating submitted successfully!";
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Fetch average rating for the employee
try {
    $stmt = $conn->prepare("SELECT AVG(rating) AS avg_rating FROM ratings WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $id]);
    $ratingData = $stmt->fetch(PDO::FETCH_ASSOC);
    $avgRating = $ratingData['avg_rating'] ? round($ratingData['avg_rating'], 1) : null;
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $avgRating = null;
}

// Check if the current user is an admin
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Details - <?php echo htmlspecialchars($employee['username']); ?></title>
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #007bff; /* A more standard, professional blue */
            --accent-color: #6f42c1; /* A nice purple for accents */
            --text-color: #343a40;
            --text-color-muted: #6c757d;
            --bg-color: #f8f9fa;
            --card-bg: #ffffff;
            --border-color: #dee2e6;
            --star-color: #ffc107;
            --star-color-inactive: #e9ecef;
        }

        body {
            background-color: var(--bg-color);
            font-family: 'Inter', sans-serif;
            color: var(--text-color);
            padding: 20px 0;
        }

        .main-container {
            max-width: 900px;
            margin: auto;
        }

        .card {
            border: 1px solid var(--border-color);
            border-radius: 1rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .card-header {
            background-color: var(--primary-color);
            color: white;
            padding: 1.5rem;
            font-size: 1.5rem;
            font-weight: 600;
            border-bottom: none;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .card-body {
            padding: 2rem;
        }

        .employee-image {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
            border: 4px solid var(--card-bg);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .employee-image:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
        }

        .employee-details p {
            font-size: 1rem;
            margin-bottom: 0.75rem;
            color: var(--text-color-muted);
        }

        .employee-details strong {
            color: var(--text-color);
            min-width: 80px;
            display: inline-block;
        }

        .document-container {
            margin-top: 1.5rem;
            padding: 1.5rem;
            border-radius: 0.75rem;
            background: #f8f9fa;
            border: 1px solid var(--border-color);
        }

        .document-container h4 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }

        .document-container iframe {
            width: 100%;
            height: 450px;
            border: none;
            border-radius: 0.5rem;
        }

        .unsupported-message {
            color: #dc3545;
            font-size: 0.9em;
            text-align: center;
            padding: 2rem;
        }

        /* Rating Section */
        .rating-section {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
        }

        .rating-stars {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .rating-stars i {
            font-size: 2.25rem;
            color: var(--star-color-inactive);
            cursor: pointer;
            transition: color 0.2s ease, transform 0.2s ease;
        }

        .rating-stars i:hover {
            transform: scale(1.15);
        }

        .rating-stars i.active,
        .rating-stars:hover i:hover ~ i {
            color: var(--star-color-inactive);
        }
        
        .rating-stars:hover i:hover,
        .rating-stars:hover i:hover ~ i.active {
            color: var(--star-color);
        }

        .rating-stars i.active {
            color: var(--star-color);
        }

        .average-rating {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .average-rating .stars i {
            font-size: 1.5rem;
            color: var(--star-color-inactive);
        }

        .average-rating .stars i.active {
            color: var(--star-color);
        }

        .average-rating .score {
            font-size: 1.1rem;
            font-weight: 500;
            color: var(--text-color-muted);
        }
        
        /* Back Button */
        .back-btn {
            display: inline-block;
            text-decoration: none;
            color: var(--primary-color);
            background-color: transparent;
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 500;
            transition: background-color 0.3s ease, color 0.3s ease;
            border: 2px solid var(--primary-color);
        }

        .back-btn:hover {
            background-color: var(--primary-color);
            color: white;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-user-circle"></i> Employee Details
            </div>
            <div class="card-body">
                <!-- Success/Error Messages -->
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php elseif (isset($error_message)): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <div class="row align-items-center">
                    <div class="col-md-4 text-center mb-4 mb-md-0">
                        <?php if (!empty($employee['image_url'])): ?>
                            <img src="<?php echo htmlspecialchars($employee['image_url']); ?>" alt="Employee Image" class="employee-image">
                        <?php else: ?>
                            <!-- Placeholder image -->
                            <div class="employee-image bg-light d-flex align-items-center justify-content-center">
                                <i class="fas fa-user fa-3x text-muted"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-8 employee-details">
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($employee['username']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($employee['email']); ?></p>
                        <p><strong>Role:</strong> <?php echo htmlspecialchars(ucfirst($employee['role'])); ?></p>
                    </div>
                </div>

                <!-- Rating Section -->
                <div class="rating-section">
                    <h5 class="text-center mb-3">Rating</h5>
                    <?php if ($is_admin): ?>
                        <form method="POST" action="" id="ratingForm" class="text-center">
                            <div class="rating-stars" data-rating="0">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star" data-value="<?php echo $i; ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <input type="hidden" name="rating" id="ratingInput" value="0">
                            <button type="submit" class="btn btn-primary mt-2">Submit Rating</button>
                        </form>
                    <?php endif; ?>
                    
                    <div class="mt-3">
                        <?php if ($avgRating): ?>
                            <div class="average-rating">
                                <span class="stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?php echo $i <= $avgRating ? 'active' : ''; ?>"></i>
                                    <?php endfor; ?>
                                </span>
                                <span class="score">(<?php echo $avgRating; ?> / 5.0)</span>
                            </div>
                        <?php else: ?>
                            <p class="text-center text-muted">No ratings available yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Job Description Card -->
        <div class="card">
             <div class="card-body document-container">
                <h4><i class="fas fa-file-alt me-2"></i>Job Description</h4>
                <?php 
                if (!empty($employee['jd_pdf'])) {
                    $jd_pdf_url = htmlspecialchars($employee['jd_pdf']);
                    echo '<iframe src="' . $jd_pdf_url . '" title="Job Description PDF"></iframe>';
                    echo '<p class="mt-2"><a href="' . $jd_pdf_url . '" target="_blank">Download PDF</a></p>';
                } else {
                    echo '<p class="text-muted">No job description available.</p>';
                }
                ?>
            </div>
        </div>

        <!-- Workflow Card -->
        <div class="card">
            <div class="card-body document-container">
                <h4><i class="fas fa-project-diagram me-2"></i>Workflow</h4>
                 <?php 
                if (!empty($employee['workflow_pdf'])) {
                    $workflow_pdf_url = htmlspecialchars($employee['workflow_pdf']);
                    echo '<iframe src="' . $workflow_pdf_url . '" title="Workflow PDF"></iframe>';
                    echo '<p class="mt-2"><a href="' . $workflow_pdf_url . '" target="_blank">Download PDF</a></p>';
                } else {
                    echo '<p class="text-muted">No workflow available.</p>';
                }
                ?>
            </div>
        </div>

        <div class="text-center mt-4">
            <a href="employee_view.php" class="back-btn"><i class="fas fa-arrow-left me-2"></i>Back to Dashboard</a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const stars = document.querySelectorAll('.rating-stars i');
            const ratingInput = document.getElementById('ratingInput');
            const ratingContainer = document.querySelector('.rating-stars');

            if (stars.length > 0) {
                // Function to highlight stars
                const highlightStars = (value) => {
                    stars.forEach((star, index) => {
                        star.classList.toggle('active', index < value);
                    });
                };

                // Mouseover event for live highlighting
                ratingContainer.addEventListener('mouseover', (e) => {
                    if (e.target.matches('i')) {
                        const value = e.target.getAttribute('data-value');
                        highlightStars(value);
                    }
                });

                // Mouseout event to revert to the selected rating
                ratingContainer.addEventListener('mouseout', () => {
                    highlightStars(ratingInput.value);
                });

                // Click event to set the rating
                ratingContainer.addEventListener('click', (e) => {
                    if (e.target.matches('i')) {
                        const value = e.target.getAttribute('data-value');
                        // Allow deselecting by clicking the same star again
                        if (ratingInput.value === value) {
                            ratingInput.value = 0;
                        } else {
                            ratingInput.value = value;
                        }
                        highlightStars(ratingInput.value);
                    }
                });
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>