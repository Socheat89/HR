<?php
session_start();

// Include database connection
include 'db.php'; // Adjust path if needed, e.g., '../db.php'

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Verify database connection
if (!isset($conn) || !$conn || $conn->connect_error) {
    die("Database connection failed: " . ($conn->connect_error ?? "Connection object not initialized. Check db.php."));
}

// Set default category_id
$default_category_id = 1;

// Get category_id from URL or use default, cast to integer for safety
$category_filter = isset($_GET['category_id']) ? (int)$_GET['category_id'] : $default_category_id;

// Securely query products with all fields from the admin dashboard structure
$sql = "SELECT id, name, category_id, image_url, section, menu, submenu, description, active 
        FROM products 
        WHERE category_id = ? AND active = 1"; // Only fetch active products
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("i", $category_filter);
$stmt->execute();
$product_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Syrup - Classic</title>
    <link rel="icon" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABwAAAAcCAMAAABF0y+mAAAAeFBMVEWXWhdHcEx9TBI5MBu1XxuYYiK+ZBvKcyLGdyHpgyPxiyb0kyfmiCfLeyXegCP8kyj1kyjviSXnhSVALxLxiCXJdCQeKBTskCn6jSXlgCPQciHyhyQ8KQ4AAAAAABD+mSnsgySzciL5jCa4ax/rgSPdgCX0iyanaRpYyeWHAAAAKHRSTlM8ADIECRYsSo7f9NSLV7z//rltIeV4C6b/05T/LBVB//5J6GrCfvtnjd1Y2AAAAKBJREFUeAHVUjUWwzAMtcLMzOb737BLuVbmRpue8AMhgIZF4CSuUbQd1yOusej6QRjFiWcoOmmWF2VVm9a6SZQ3bWe8affFMPrIQ1M+j+TtW7te1ke27UdD4b2YxvveTMQB6Ngc8S+cNs1YvpcUeD6X9i8JVBxDU4mDLSaGXNnM6hgmhL40nI+8Rop22gwZTnyba7zYlR5eBNf5Aw+dVJcbUwYK5uv5IWgAAAAASUVORK5CYII=">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSV7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js" integrity="sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVT8EE2iyI61OV8e6M8PP2/4hpQINQ/g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <link rel="stylesheet" href="syrup.css" />
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Koulen&family=Montserrat:ital,wght@0,100..900;1,100..900&family=Roboto+Condensed:ital,wght@0,100..900;1,100..900&family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&display=swap');
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { scroll-behavior: smooth; font-family: Montserrat; background-color: #faf3e3; }
        /* Existing media queries and styles remain unchanged */
        .sub-product a { 
            text-decoration: none; 
            color: black; 
            font-weight: bold; 
            display: block; 
        }
        .sub-product .description { 
            font-size: 12px; 
            color: #666; 
            margin-top: 5px; 
        }
        /* Add other styles as needed */
    </style>
</head>
<body>
    <div class="bg-header"></div>
    <section id="main-header" class="py-3" style="background-color: #f26923; position: fixed; justify-content: space-between; width: 100%; z-index: 120;">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-3">
                    <div class="main-logo">
                        <a href="index.html"><img src="https://www.longbeachsyrup.com/images/logo%20longbeach-08.png?crc=377204701" alt="Longbeach Logo" class="img-fluid" /></a>
                    </div>
                </div>
                <div class="col-md-9">
                    <nav class="navbar navbar-expand-lg">
                        <div class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                            <i class="fa-solid fa-bars"></i>
                        </div>
                        <div class="collapse navbar-collapse" id="navbarNav">
                            <ul class="navbar-nav ms-auto">
                                <li class="nav-item"><a class="nav-link" href="About.html">About Us</a></li>
                                <li class="nav-item"><a class="nav-link active" href="Product.html">Products</a></li>
                                <li class="nav-item"><a class="nav-link" href="recipes.html">Recipes</a></li>
                                <li class="nav-item"><a class="nav-link" href="events.html">Events</a></li>
                                <li class="nav-item"><a class="nav-link" href="stores.html">Stores</a></li>
                                <li class="nav-item"><a class="nav-link" href="contact.html">Contact</a></li>
                                <li class="nav-item"><a class="nav-link" href="careers.html">Careers</a></li>
                                <!-- Accordion remains unchanged -->
                                <div class="accordion new-bg" id="productAccordion">
                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="headingOne">
                                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                                                Main Categories
                                            </button>
                                        </h2>
                                        <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#productAccordion">
                                            <a class="nav-link" href="best-sellers.html">Best Sellers</a>
                                            <a class="nav-link active-menu" href="product.php?category_id=1">Syrup</a>
                                            <a class="nav-link" href="zero.php?category_id=14">Zero Sugar Zero Calories Syrup</a>
                                            <a class="nav-link" href="puree.php?category_id=18">Puree</a>
                                            <a class="nav-link" href="sauce.php?category_id=22">Sauce</a>
                                            <a class="nav-link" href="power.php?category_id=24">Powder</a>
                                            <a class="nav-link" href="coffee.php?category_id=27">Coffee & Tea</a>
                                            <a class="nav-link" href="topping.php?category_id=32">Toppings</a>
                                            <a class="nav-link" href="kawami.php?category_id=40">Kawami Japanese Tea</a>
                                            <a class="nav-link" href="gofresh.php?category_id=43">GoFresh Premixed Beverage</a>
                                        </div>
                                    </div>
                                    <!-- Other accordion items remain unchanged -->
                                </div>
                            </ul>
                        </div>
                    </nav>
                </div>
            </div>
        </section>

        <div id="carouselExampleIndicators" class="carousel slide" data-bs-ride="carousel">
            <div class="carousel-inner">
                <div class="carousel-item active">
                    <a href="#"><img src="https://www.longbeachsyrup.com/images/_head3.jpg?crc=3995169363" class="d-block w-100" alt="Featured Syrup Product Banner"></a>
                </div>
            </div>
        </div>

        <div class="sub-menu">
            <ul>
                <li><a class="nav-link" href="best-sellers.html">Best Sellers <span style="color: gold;">|</span></a></li>
                <li><a class="nav-link active-menu" href="product.php">Syrup <span style="color: gold;">|</span></a></li>
                <li><a class="nav-link" href="zero.php">Zero Sugar Zero Calories Syrup <span style="color: gold;">|</span></a></li>
                <li><a class="nav-link" href="puree.php">Puree <span style="color: gold;"></span></a></li>
                <li><a class="nav-link" href="sauce.php">Sauce <span style="color: gold;">|</span></a></li>
                <li><a class="nav-link" href="power.php">Powder</a></li>
                <br>
                <li><a class="nav-link" href="coffee.php">Coffee & Tea <span style="color: gold;">|</span></a></li>
                <li><a class="nav-link" href="topping.php">Toppings <span style="color: gold;">|</span></a></li>
                <li><a class="nav-link" href="kawami.php">Kawami Japanese Tea <span style="color: gold;">|</span></a></li>
                <li><a class="nav-link" href="gofresh.php">GoFresh Premixed Beverage</a></li>
            </ul>
        </div>

        <div class="main-product" style="display: flex; justify-content: center; margin-top: 2rem;">
            <div class="main-content-prodeuct">
                <div class="sub-content-product">
                    <div class="main-content-1"><a data-category-id="1" href="#"><span>Coffeehouse Favourites Syrup</span></a><hr></div>
                    <div class="main-content-2"><a data-category-id="1" href="#" class="nav-link"><span>Classic</span></a></div>
                    <div class="main-content-3"><a data-category-id="2" href="#"><span>Nuts</span></a></div>
                    <div class="main-content-4"><a data-category-id="3" href="#"><span>Dessert</span></a></div>
                    <div class="Fruit">
                        <div class="main-content-01"><a data-category-id="4" href="#"><span>Fruit Syrup</span></a><hr></div>
                        <div class="main-content-02"><a data-category-id="4" href="#"><span>Refreshing</span></a></div>
                        <div class="main-content-03"><a data-category-id="5" href="#"><span>Citrus</span></a></div>
                        <div class="main-content-04"><a data-category-id="6" href="#"><span>Sweet and Creamy</span></a></div>
                    </div>
                    <div class="Fruit">
                        <div class="main-content-01"><a data-category-id="7" href="#"><span>Flower, Herb and Spice Syrup</span></a><hr></div>
                        <div class="main-content-02"><a data-category-id="7" href="#"><span>Floral</span></a></div>
                        <div class="main-content-03"><a data-category-id="8" href="#"><span>Herb and Spice</span></a></div>
                        <div class="coktail">
                            <div class="main-content-06"><a data-category-id="9" href="#"><span>Cocktail Mix Syrup</span></a></div>
                            <div class="main-content-07"><a data-category-id="10" href="#"><span>Tea Syrup</span></a></div>
                            <div class="main-content-08"><a data-category-id="11" href="#"><span>Thai Flavoured Syrup</span></a></div>
                            <div class="main-content-09"><a data-category-id="12" href="#"><span>Japanese Flavoured Syrup</span></a></div>
                            <div class="main-content-10"><a data-category-id="13" href="#"><span>LongBeach for home use</span></a></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="title-syrup" style="text-align: center; font-size: 26px; color: black; font-weight: bold; position: relative; top: -3rem;">
                Syrup<hr style="border: 1px solid #f26923; opacity: 100%; width: 30%; margin: auto;">
            </div>
            <div class="sub-product">
                <?php if ($product_result->num_rows > 0): ?>
                    <?php while ($row = $product_result->fetch_assoc()): ?>
                        <a href="product_details.php?id=<?= htmlspecialchars($row['id']) ?>">
                            <img src="<?= htmlspecialchars($row['image_url'] ?: 'https://via.placeholder.com/150') ?>" class="card-img-top" alt="<?= htmlspecialchars($row['name']) ?>" loading="lazy">
                            <div><span><?= htmlspecialchars($row['name']) ?></span></div>
                            <?php if (!empty($row['description'])): ?>
                                <div class="description"><?= htmlspecialchars($row['description']) ?></div>
                            <?php endif; ?>
                        </a>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>No active products found in this category.</p>
                <?php endif; ?>
            </div>
        </div>

        <button id="scrollToTopBtn" style="display: none; position: fixed; bottom: 20px; right: 20px; z-index: 100; background-color: #f26923; color: white; border: none; border-radius: 50%; padding: 10px 15px; cursor: pointer;">
            <i class="fa-solid fa-caret-up"></i>
        </button>

        <script>
            $(document).ready(function() {
                $('.accordion-item a').click(function(e) {
                    e.preventDefault();
                    const categoryId = $(this).data('category-id');
                    window.location.href = '?category_id=' + categoryId;
                });
                $('.sub-content-product a').click(function(e) {
                    e.preventDefault();
                    const categoryId = $(this).data('category-id');
                    window.location.href = '?category_id=' + categoryId;
                });
            });

            const scrollToTopBtn = document.getElementById("scrollToTopBtn");
            window.addEventListener("scroll", () => {
                if (window.scrollY > 300) {
                    scrollToTopBtn.style.display = "block";
                } else {
                    scrollToTopBtn.style.display = "none";
                }
            });
            scrollToTopBtn.addEventListener("click", () => {
                window.scrollTo({ top: 0, behavior: "smooth" });
            });

            document.addEventListener("DOMContentLoaded", function () {
                let currentPage = window.location.pathname.split("/").pop();
                let navLinks = document.querySelectorAll(".nav-link");
                navLinks.forEach((link) => {
                    if (link.getAttribute("href") === currentPage) {
                        link.classList.add("active");
                    }
                });

                const products = document.querySelectorAll(".sub-product a");
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add("visible");
                            observer.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.5 });
                products.forEach((product) => observer.observe(product));
            });
        </script>
    </body>
</html>
<?php
// Close the database connection after all output
if (isset($conn) && $conn) {
    $conn->close();
}
?>