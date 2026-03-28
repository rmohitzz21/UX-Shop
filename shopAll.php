<?php require_once 'includes/config.php'; ?>
<?php
// Fetch distinct categories for filter tabs
$catResult = $conn->query("SELECT DISTINCT category FROM products WHERE is_active = 1 ORDER BY category");
$categories = [];
while ($cat = $catResult->fetch_assoc()) {
    $categories[] = $cat['category'];
}

// Get price range for slider
$priceRange = $conn->query("SELECT MIN(price) as min_price, MAX(price) as max_price FROM products WHERE is_active = 1")->fetch_assoc();
$minPrice = floor($priceRange['min_price'] ?? 0);
$maxPrice = ceil($priceRange['max_price'] ?? 500);
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <title>UX Pacific - Shop All Products</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="style.css" />
  </head>

  <body class="shopAll">
    <div class="page">
      <!-- NAVBAR -->
      <header class="site-header" id="navbar">
        <nav class="nav-bar">
          <div class="nav-logo">
            <a href="index.php"><img src="img/logo1.webp" alt="UX Pacific" /></a>
          </div>
          <ul class="nav-links">
            <li><a href="index.php" class="nav-link">Home</a></li>
            <li><a href="index.php#story" class="nav-link">About Us</a></li>
            <li><a href="index.php#products" class="nav-link">New</a></li>
            <li><a href="shopAll.php" class="nav-link active">Buy Now</a></li>
          </ul>
          <div class="nav-actions">
            <a href="cart.php" class="nav-cart">
              <img src="img/cart-icon.webp" alt="Shopping cart" />
              <span id="cart-count">0</span>
            </a>
            <a href="signin.php" class="nav-cta">Sign in</a>
            <div class="nav-user">
              <div class="user-avatar"></div>
              <div class="user-info">
                <span class="user-name">User</span>
                <span class="user-role">Customer</span>
              </div>
              <div class="user-dropdown">
                <a href="account.php" class="user-dropdown-item">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                  </svg>
                  <span>My Account</span>
                </a>
                <a href="cart.php" class="user-dropdown-item">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="9" cy="21" r="1"></circle>
                    <circle cx="20" cy="21" r="1"></circle>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                  </svg>
                  <span>My Cart</span>
                </a>
                <a href="orders.php" class="user-dropdown-item">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
                    <rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>
                  </svg>
                  <span>My Orders</span>
                </a>
                <div class="user-dropdown-divider"></div>
                <a href="#" onclick="handleSignOut(); return false;" class="user-dropdown-item logout">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                  </svg>
                  <span>Sign Out</span>
                </a>
              </div>
            </div>
          </div>
          <button id="mobile-menu-btn" class="nav-toggle" aria-label="Toggle navigation">
            <span></span><span></span><span></span>
          </button>
        </nav>
        <div id="mobile-menu" class="nav-mobile-menu">
          <a href="index.php" class="nav-mobile-link">Home</a>
          <a href="index.php#story" class="nav-mobile-link">About Us</a>
          <a href="index.php#products" class="nav-mobile-link">New</a>
          <a href="shopAll.php" class="nav-mobile-link">Buy Now</a>
          <a href="cart.php" class="nav-mobile-link">Cart</a>
          <a href="signin.php" class="nav-mobile-link nav-mobile-cta">Sign in</a>
        </div>
      </header>

      <!-- MAIN CONTENT -->
      <main class="main shop-all-main">
        <!-- Page Header -->
        <section class="shop-all-header">
          <h1 class="shop-all-title">Design <span>Resources & Products</span></h1>
          <p class="shop-all-subtitle">
            Explore premium UX/UI design resources including digital assets and physical products.
          </p>
        </section>

        <!-- Category Tabs + Sort -->
        <div class="shop-controls">
          <div class="category-tabs">
            <button class="category-tab active" data-filter="all">All</button>
            <?php foreach ($categories as $cat): ?>
              <button class="category-tab" data-filter="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></button>
            <?php endforeach; ?>
          </div>
          <div class="sort-control">
            <label for="sort-select">Sort By:</label>
            <select id="sort-select">
              <option value="newest">Newest</option>
              <option value="price-low">Price: Low to High</option>
              <option value="price-high">Price: High to Low</option>
              <option value="rating">Top Rated</option>
            </select>
          </div>
        </div>

        <!-- Main Layout: Sidebar + Grid -->
        <section class="shop-layout">
          <!-- Left Sidebar -->
          <aside class="shop-sidebar">
            <!-- Product Type Filter -->
            <div class="filter-section">
              <h3 class="filter-title">Product Type</h3>
              <div class="filter-options">
                <label class="filter-checkbox">
                  <input type="checkbox" name="type" value="physical" checked />
                  <span class="checkmark"></span>
                  Physical
                </label>
                <label class="filter-checkbox">
                  <input type="checkbox" name="type" value="digital" checked />
                  <span class="checkmark"></span>
                  Digital
                </label>
                <label class="filter-checkbox">
                  <input type="checkbox" name="type" value="both" checked />
                  <span class="checkmark"></span>
                  Both
                </label>
              </div>
            </div>

            <!-- Price Range Filter -->
            <div class="filter-section">
              <h3 class="filter-title">Price Range</h3>
              <div class="price-range-wrapper">
                <div class="price-inputs">
                  <span class="price-label">$<span id="price-min-val"><?= $minPrice ?></span></span>
                  <span class="price-separator">-</span>
                  <span class="price-label">$<span id="price-max-val"><?= $maxPrice ?></span>+</span>
                </div>
                <div class="range-slider">
                  <input type="range" id="price-range" min="<?= $minPrice ?>" max="<?= $maxPrice ?>" value="<?= $maxPrice ?>" />
                </div>
              </div>
            </div>

            <!-- Promo Card -->
            <div class="sidebar-promo">
              <div class="promo-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                  <path d="M2 17l10 5 10-5"></path>
                  <path d="M2 12l10 5 10-5"></path>
                </svg>
              </div>
              <h4>Premium Resources</h4>
              <p>Get 20% off on all digital products this month!</p>
              <a href="#" class="promo-btn">Learn More</a>
            </div>
          </aside>

          <!-- Product Grid -->
          <div class="shop-products">
            <div class="product-grid shop-grid" id="product-grid">
              <?php
              // Pagination Settings
              $limit = 9;
              $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
              if ($page < 1) $page = 1;
              $offset = ($page - 1) * $limit;

              // Count total products
              $countSql = "SELECT COUNT(*) as total FROM products WHERE is_active = 1 AND (stock > 0 OR available_type != 'physical')";
              $countResult = $conn->query($countSql);
              $totalProducts = $countResult->fetch_assoc()['total'];
              $totalPages = ceil($totalProducts / $limit);

              // Fetch products
              $sql = "SELECT * FROM products WHERE is_active = 1 AND (stock > 0 OR available_type != 'physical') ORDER BY created_at DESC LIMIT ? OFFSET ?";
              $stmt = $conn->prepare($sql);
              $stmt->bind_param("ii", $limit, $offset);
              $stmt->execute();
              $result = $stmt->get_result();

              if ($result && $result->num_rows > 0) {
                  while($row = $result->fetch_assoc()) {
                      $id = $row['id'];
                      $name = htmlspecialchars($row['name']);
                      $jsName = htmlspecialchars(addslashes($row['name']), ENT_QUOTES, 'UTF-8');
                      $jsImage = htmlspecialchars(addslashes($row['image']), ENT_QUOTES, 'UTF-8');
                      $jsCategory = htmlspecialchars(addslashes($row['category']), ENT_QUOTES, 'UTF-8');
                      $jsAvailableType = htmlspecialchars(addslashes($row['available_type'] ?? 'physical'), ENT_QUOTES, 'UTF-8');

                      $price = number_format($row['price'], 2);
                      $old_price = !empty($row['old_price']) ? number_format($row['old_price'], 2) : '';
                      $imgSrc = !empty($row['image']) ? htmlspecialchars($row['image']) : 'img/sticker.webp';
                      $category = htmlspecialchars($row['category']);
                      $rating = number_format($row['rating'] ?: 4.5, 1);
                      $availableType = $row['available_type'] ?? 'physical';

                      // Truncate description
                      $description = htmlspecialchars($row['description']);
                      if (strlen($description) > 100) {
                          $description = substr($description, 0, 100) . '...';
                      }

                      echo "
                      <article class='product-card' data-category='$category' data-type='$availableType' data-price='{$row['price']}' data-rating='$rating'>
                        <div class='product-img'>
                          <img src='$imgSrc' alt='$name' loading='lazy' onerror=\"this.src='img/sticker.webp'\" />
                          <span class='product-tag'>$category</span>
                        </div>
                        <div class='product-body'>
                          <h3>$name</h3>
                          <p style='margin-bottom: 0.5rem; font-size: 0.95rem;'>$description</p>
                          <div class='product-meta'>
                            <div class='product-price'>$$price " . ($old_price ? "<span>$$old_price</span>" : "") . "</div>
                            <div class='product-rating'>★ $rating</div>
                          </div>
                          <div class='product-actions'>
                            <button onclick=\"addToCart('$id', null, 1, {name: '$jsName', price: {$row['price']}, image: '$jsImage', category: '$jsCategory'}, '$jsAvailableType')\" class='btn-primary small' aria-label='Add to cart' " . ($row['stock'] <= 0 && $availableType === 'physical' ? 'disabled' : '') . ">Add to Cart</button>
                            <a href='product.php?id=$id' class='btn-ghost small'>View Details</a>
                          </div>
                        </div>
                      </article>
                      ";
                  }
              } else {
                  echo "<p class='no-products'>No products found.</p>";
              }
              ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <nav class="pagination">
              <a href="?page=<?= max(1, $page - 1) ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
              </a>
              <?php
              $startPage = max(1, $page - 2);
              $endPage = min($totalPages, $page + 2);

              if ($startPage > 1) {
                  echo '<a href="?page=1" class="page-num">1</a>';
                  if ($startPage > 2) echo '<span class="page-ellipsis">...</span>';
              }

              for ($i = $startPage; $i <= $endPage; $i++) {
                  $activeClass = $i === $page ? 'active' : '';
                  echo "<a href='?page=$i' class='page-num $activeClass'>$i</a>";
              }

              if ($endPage < $totalPages) {
                  if ($endPage < $totalPages - 1) echo '<span class="page-ellipsis">...</span>';
                  echo "<a href='?page=$totalPages' class='page-num'>$totalPages</a>";
              }
              ?>
              <a href="?page=<?= min($totalPages, $page + 1) ?>" class="page-btn <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
              </a>
            </nav>
            <?php endif; ?>
          </div>
        </section>
      </main>

      <!-- FOOTER -->
      <footer class="site-footer">
        <div class="footer-main">
          <div class="footer-top">
            <div class="footer-brand">
              <img src="img/logo1.webp" alt="UX Pacific" />
              <p>Design resources and merchandise trusted by creators worldwide  built to be used, worn, and valued.</p>
              <div class="footer-socials">
                <a href="https://dribbble.com/social-ux-pacific" target="_blank" rel="noopener"><img src="img/bl.webp" alt="Dribbble" /></a>
                <a href="https://www.instagram.com/official_uxpacific/" target="_blank" rel="noopener"><img src="img/i.webp" alt="Instagram" /></a>
                <a href="https://www.linkedin.com/company/uxpacific/" target="_blank" rel="noopener"><img src="img/in1.png" alt="LinkedIn" /></a>
                <a href="https://in.pinterest.com/uxpacific/" target="_blank" rel="noopener"><img src="img/p.webp" alt="Pinterest" /></a>
                <a href="https://www.behance.net/ux_pacific" target="_blank" rel="noopener"><img src="img/be.webp" alt="Behance" /></a>
              </div>
            </div>
            <div class="footer-contact">
              <p>Support : +91 9274061063&nbsp;&nbsp;|</p>
              <p>Email : <a href="mailto:hello@uxpacific.com" style="text-decoration: none; color: inherit">hello@uxpacific.com</a></p>
            </div>
          </div>
        </div>
        <div class="footer-bottom">
          <p>2026 UXPacific. All rights reserved.</p>
          <div class="footer-links">
            <a href="policies.php">Our Policies</a>
            <span></span>
            <a href="contact.php">Contact Us</a>
          </div>
        </div>
      </footer>
    </div>

    <script src="script.js"></script>
    <script>
    // Shop page specific filtering and sorting
    document.addEventListener('DOMContentLoaded', function() {
      const categoryTabs = document.querySelectorAll('.category-tab');
      const productCards = document.querySelectorAll('.product-card');
      const typeCheckboxes = document.querySelectorAll('input[name="type"]');
      const priceRange = document.getElementById('price-range');
      const sortSelect = document.getElementById('sort-select');

      function filterProducts() {
        const activeCategory = document.querySelector('.category-tab.active')?.dataset.filter || 'all';
        const checkedTypes = Array.from(typeCheckboxes).filter(cb => cb.checked).map(cb => cb.value);
        const maxPrice = parseFloat(priceRange?.value || 9999);

        productCards.forEach(card => {
          const cardCategory = card.dataset.category;
          const cardType = card.dataset.type || 'physical';
          const cardPrice = parseFloat(card.dataset.price || 0);

          const categoryMatch = activeCategory === 'all' || cardCategory === activeCategory;
          const typeMatch = checkedTypes.includes(cardType);
          const priceMatch = cardPrice <= maxPrice;

          card.style.display = (categoryMatch && typeMatch && priceMatch) ? '' : 'none';
        });
      }

      function sortProducts() {
        const sortBy = sortSelect?.value || 'newest';
        const grid = document.getElementById('product-grid');
        const cards = Array.from(productCards);

        cards.sort((a, b) => {
          switch(sortBy) {
            case 'price-low':
              return parseFloat(a.dataset.price) - parseFloat(b.dataset.price);
            case 'price-high':
              return parseFloat(b.dataset.price) - parseFloat(a.dataset.price);
            case 'rating':
              return parseFloat(b.dataset.rating) - parseFloat(a.dataset.rating);
            default:
              return 0;
          }
        });

        cards.forEach(card => grid.appendChild(card));
      }

      // Category tabs
      categoryTabs.forEach(tab => {
        tab.addEventListener('click', function() {
          categoryTabs.forEach(t => t.classList.remove('active'));
          this.classList.add('active');
          filterProducts();
        });
      });

      // Type checkboxes
      typeCheckboxes.forEach(cb => {
        cb.addEventListener('change', filterProducts);
      });

      // Price range
      if (priceRange) {
        priceRange.addEventListener('input', function() {
          document.getElementById('price-max-val').textContent = this.value;
          filterProducts();
        });
      }

      // Sort
      if (sortSelect) {
        sortSelect.addEventListener('change', sortProducts);
      }
    });
    </script>
  </body>
</html>
