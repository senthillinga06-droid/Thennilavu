<?php
// review-management.php
require_once 'header.php';

// Calculate statistics
$stats_sql = "SELECT 
    COUNT(*) as total_reviews, 
    AVG(rating) as average_rating 
    FROM reviews";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();
$total_reviews = $stats['total_reviews'];
$average_rating = round($stats['average_rating'], 1);

// Handle search/filter
$search = isset($_GET['search']) ? $_GET['search'] : '';
$where_clause = '';
if (!empty($search)) {
    $search_term = $conn->real_escape_string($search);
    $where_clause = "WHERE name LIKE '%$search_term%' OR comment LIKE '%$search_term%'";
}

// Get reviews
$sql = "SELECT * FROM reviews $where_clause ORDER BY review_date DESC";
$result = $conn->query($sql);
?>

<style>
/* Additional styles for the review management page */
.star-rating {
  color: #ffc107;
}

.search-form {
  display: flex;
  margin-bottom: 20px;
}

.search-input {
  flex: 1;
  padding: 10px 15px 10px 40px;
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  font-size: 14px;
}

.search-icon {
  position: absolute;
  left: 15px;
  top: 50%;
  transform: translateY(-50%);
  color: #888;
}

.search-container {
  position: relative;
  width: 100%;
}

/* Review Statistics */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
  margin-bottom: 20px;
}

.stat-card {
  background: white;
  border-radius: 8px;
  padding: 20px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.05);
  display: flex;
  align-items: center;
  gap: 15px;
}

.stat-icon {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  background: linear-gradient(135deg, #4a90e2, #67b26f);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 24px;
}

.stat-info h3 {
  margin: 0 0 5px 0;
  color: #666;
  font-size: 14px;
  font-weight: 500;
}

.stat-number {
  margin: 0 0 5px 0;
  font-size: 28px;
  font-weight: 700;
  color: #333;
}

.stat-change {
  font-size: 12px;
  font-weight: 600;
}

.stat-change.positive {
  color: #28a745;
}

/* Table Styles */
.table-responsive {
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 20px;
  background-color: #fff;
  box-shadow: 0 4px 8px rgba(0,0,0,0.05);
  border-radius: 8px;
  overflow: hidden;
}

.data-table th, .data-table td {
  padding: 15px 12px;
  text-align: left;
  vertical-align: top;
}

.data-table thead th {
  background-color: #f8f9fa;
  font-weight: 600;
  color: #333;
  text-transform: uppercase;
  font-size: 12px;
  border-bottom: 2px solid #e9ecef;
}

.data-table tbody tr {
  border-bottom: 1px solid #e9ecef;
  transition: background-color 0.2s ease;
}

.data-table tbody tr:last-child {
  border-bottom: none;
}

.data-table tbody tr:hover {
  background-color: #f1f3f5;
}

/* Comment column styling */
.comment-cell {
  max-width: 300px;
  word-wrap: break-word;
}

/* Main Content Fixes */
.main-content1 {
  margin-top: 40px;
  width: 100%;
  padding: 20px;
  transition: margin-left 0.3s ease;
}

/* Mobile Navigation Improvements */
@media (max-width: 992px) {
  .main-content1 {
    margin-left: 0 !important;
    padding: 15px;
  }
  
  .sidebar {
    transform: translateX(-100%);
    transition: transform 0.3s ease;
  }
  
  .sidebar.active {
    transform: translateX(0);
  }
  
  /* Hover effect for sidebar on mobile */
  .sidebar:hover {
    transform: translateX(0);
  }
  
  /* Mobile menu toggle button */
  .menu-toggle {
    display: block;
    position: fixed;
    top: 15px;
    left: 15px;
    z-index: 110;
    background: var(--primary);
    color: white;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 10px rgba(0,0,0,0.2);
  }
  
  /* Mobile hover navigation */
  .sidebar {
    width: 280px;
    z-index: 105;
  }
  
  .overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 100;
  }
  
  .overlay.active {
    display: block;
  }
  
  /* Adjust top nav for mobile */
  .top-nav {
    padding-left: 70px;
  }
}

/* Enhanced mobile sidebar with hover support */
@media (min-width: 993px) {
  .menu-toggle {
    display: none;
  }
}

/* Mobile-specific table improvements */
@media (max-width: 768px) {
  .main-content1 {
    padding: 15px;
    margin-left: 0 !important;
  }
  
  .content-section {
    padding: 15px;
  }
  
  .stats-grid {
    grid-template-columns: 1fr;
  }
  
  .stat-card {
    padding: 15px;
  }
  
  .stat-icon {
    width: 50px;
    height: 50px;
    font-size: 20px;
  }
  
  .stat-number {
    font-size: 24px;
  }
  
  .data-table {
    font-size: 14px;
    min-width: 650px; /* Ensure all 4 columns are visible with horizontal scroll */
  }
  
  .data-table th, 
  .data-table td {
    padding: 12px 10px;
    min-width: 120px; /* Minimum width for each column */
  }
  
  /* Specific column widths for mobile */
  .data-table th:nth-child(1), /* Name */
  .data-table td:nth-child(1) {
    min-width: 130px;
    max-width: 130px;
  }
  
  .data-table th:nth-child(2), /* Date */
  .data-table td:nth-child(2) {
    min-width: 110px;
    max-width: 110px;
  }
  
  .data-table th:nth-child(3), /* Comment */
  .data-table td:nth-child(3) {
    min-width: 200px;
    max-width: 200px;
  }
  
  .data-table th:nth-child(4), /* Rating */
  .data-table td:nth-child(4) {
    min-width: 120px;
    max-width: 120px;
  }
  
  .comment-cell {
    max-width: 200px;
  }
  
  /* Mobile table improvements */
  .table-responsive {
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    position: relative;
  }
  
  /* Scroll indicator for mobile */
  .table-responsive::after {
    content: '← Scroll →';
    position: absolute;
    top: 5px;
    right: 10px;
    font-size: 11px;
    color: #666;
    background: rgba(255,255,255,0.8);
    padding: 2px 6px;
    border-radius: 4px;
    z-index: 2;
  }
}

@media (max-width: 576px) {
  .main-content1 {
    padding: 10px;
    margin-left: 0 !important;
  }
  
  .content-section {
    padding: 12px;
    margin: 0;
  }
  
  .content-section h2 {
    font-size: 18px;
  }
  
  .data-table {
    font-size: 13px;
    min-width: 600px;
  }
  
  .data-table th, 
  .data-table td {
    padding: 10px 8px;
  }
  
  .star-rating {
    font-size: 12px;
  }
  
  .star-rating i {
    font-size: 10px;
  }
  
  .search-input {
    padding: 8px 8px 8px 35px;
    font-size: 14px;
  }
  
  .search-icon {
    left: 12px;
  }
  
  .comment-cell {
    max-width: 180px;
  }
  
  /* Enhanced mobile hover effects */
  .data-table tbody tr {
    position: relative;
  }
  
  .data-table tbody tr:hover::before {
    content: 'Hovering - All columns visible';
    position: absolute;
    top: -20px;
    left: 50%;
    transform: translateX(-50%);
    background: var(--primary);
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 10px;
    white-space: nowrap;
    z-index: 10;
  }
}

/* Ensure all 4 columns are always visible on mobile with horizontal scroll */
@media (max-width: 768px) {
  .data-table th,
  .data-table td {
    display: table-cell !important; /* Force all columns to show */
  }
}

/* Ensure full width on mobile */
@media (max-width: 992px) {
  .main-content1 {
    width: 100%;
    margin-left: 0 !important;
    padding: 15px;
  }
}

/* Fix for when sidebar is active on mobile */
.sidebar.active + .main-content1 {
  margin-left: 0 !important;
}

/* Touch-friendly improvements */
@media (max-width: 768px) {
  .sidebar-link {
    padding: 1rem;
    font-size: 16px; /* Larger touch targets */
  }
  
  .stat-card, .content-section {
    border-radius: 12px;
  }
  
  .search-input {
    font-size: 16px; /* Prevent zoom on iOS */
  }
}

/* Hover navigation improvements for devices that support hover */
@media (hover: hover) and (max-width: 992px) {
  .sidebar {
    transform: translateX(-90%);
    transition: transform 0.3s ease;
  }
  
  .sidebar:hover {
    transform: translateX(0);
    box-shadow: 5px 0 15px rgba(0,0,0,0.1);
  }
}

/* For touch devices without hover */
@media (hover: none) and (max-width: 992px) {
  .sidebar {
    transform: translateX(-100%);
  }
  
  .sidebar.active {
    transform: translateX(0);
  }
}

/* Enhanced mobile table row hover effects */
@media (max-width: 768px) {
  .data-table tbody tr {
    transition: all 0.3s ease;
  }
  
  .data-table tbody tr:hover {
    transform: scale(1.01);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    z-index: 5;
    position: relative;
  }
  
  /* Highlight all columns on hover */
  .data-table tbody tr:hover td {
    background-color: #e8f4fd;
    border-color: #b8daff;
  }
}

/* Mobile optimization for very small screens */
@media (max-width: 380px) {
  .data-table {
    min-width: 580px;
    font-size: 12px;
  }
  
  .data-table th, 
  .data-table td {
    padding: 8px 6px;
    min-width: 110px;
  }
  
  .data-table th:nth-child(1),
  .data-table td:nth-child(1) {
    min-width: 120px;
    max-width: 120px;
  }
  
  .data-table th:nth-child(2),
  .data-table td:nth-child(2) {
    min-width: 100px;
    max-width: 100px;
  }
  
  .data-table th:nth-child(3),
  .data-table td:nth-child(3) {
    min-width: 180px;
    max-width: 180px;
  }
  
  .data-table th:nth-child(4),
  .data-table td:nth-child(4) {
    min-width: 110px;
    max-width: 110px;
  }
}
</style>

<main class="main-content1" style="margin-top: 40px;">
  <h1>Review Management</h1>

  <!-- Review Statistics -->
  <div class="content-section">
    <br>
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon">
          <i class="fas fa-star"></i>
        </div>
        <div class="stat-info">
          <h3>Total Reviews</h3>
          <p class="stat-number"><?php echo $total_reviews; ?></p>
          <span class="stat-change positive">
            <i class="fas fa-arrow-up"></i>
            12.5%
          </span>
        </div>
      </div>
      
      <div class="stat-card">
        <div class="stat-icon">
          <i class="fas fa-star-half-alt"></i>
        </div>
        <div class="stat-info">
          <h3>Average Rating</h3>
          <p class="stat-number"><?php echo $average_rating; ?></p>
          <span class="stat-change positive">
            <i class="fas fa-arrow-up"></i>
            0.3
          </span>
        </div>
      </div>
    </div>
  </div>

  <!-- Search Bar -->
  <div class="search-container">
    <form method="GET" class="search-form">
      <div style="position: relative; width: 100%;">
        <i class="fas fa-search search-icon"></i>
        <input 
          type="text" 
          name="search" 
          class="search-input" 
          placeholder="Search reviews " 
          value="<?php echo htmlspecialchars($search); ?>"
        />
      </div>
    </form>
  </div>
  

  <!-- Review Table - All 4 columns always visible with horizontal scroll -->
  <div class="content-section">
    <h2>All Reviews</h2>
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Date</th>
            <th>Comment</th>
            <th>Rating</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($row['name']); ?></td>
                <td><?= date('M j, Y', strtotime($row['review_date'])); ?></td>
                <td class="comment-cell">
                  <?= htmlspecialchars($row['comment']); ?>
                </td>
                <td>
                  <div class="star-rating">
                    <?php
                      $rating = (int)$row['rating'];
                      for ($i=1; $i<=5; $i++) {
                        if ($i <= $rating) {
                          echo '<i class="fas fa-star"></i>';
                        } else {
                          echo '<i class="far fa-star"></i>';
                        }
                      }
                    ?>
                    <span>(<?= $rating; ?>/5)</span>
                  </div>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="4" style="text-align: center;">
                No reviews found
                <?php if (!empty($search)): ?>
                  for search term "<?php echo htmlspecialchars($search); ?>"
                <?php endif; ?>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

<script>
// Check authentication on page load
document.addEventListener('DOMContentLoaded', function() {
  const isLoggedIn = localStorage.getItem('isLoggedIn');
  const userType = localStorage.getItem('userType');
  const username = localStorage.getItem('username');
  
  if (!isLoggedIn) {
    window.location.href = 'login.php';
    return;
  }
  
  // Display user info
  if (username && userType) {
    console.log(`Welcome ${username} (${userType})`);
    const userDisplay = document.getElementById('userDisplay');
    if (userDisplay) {
      userDisplay.textContent = `${userType.toUpperCase()}`;
    }
  }

  const staffLink = document.getElementById('staffLink');
  if (staffLink && userType !== 'admin') {
    staffLink.style.display = 'none';
  }
  
  // Add mobile table enhancements
  enhanceMobileTable();
});

// Enhanced menu toggle functionality with hover support
const menuToggle = document.getElementById('menuToggle');
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('overlay');

if (menuToggle && sidebar) {
  // Toggle sidebar on button click
  menuToggle.addEventListener('click', () => {
    sidebar.classList.toggle('active');
    if (overlay) overlay.classList.toggle('active');
  });
  
  // Close sidebar when clicking on overlay
  if (overlay) {
    overlay.addEventListener('click', () => {
      sidebar.classList.remove('active');
      overlay.classList.remove('active');
    });
  }
  
  // Enhanced hover functionality for devices that support hover
  if (window.matchMedia("(hover: hover)").matches) {
    // Show sidebar on hover
    sidebar.addEventListener('mouseenter', () => {
      sidebar.classList.add('active');
    });
    
    // Keep sidebar open when hovering over it
    sidebar.addEventListener('mouseleave', () => {
      // Only auto-close if it wasn't explicitly opened via click
      if (!sidebar.classList.contains('user-activated')) {
        sidebar.classList.remove('active');
      }
    });
    
    // Track if user explicitly opened the sidebar
    menuToggle.addEventListener('click', () => {
      if (sidebar.classList.contains('active')) {
        sidebar.classList.add('user-activated');
      } else {
        sidebar.classList.remove('user-activated');
      }
    });
  }
}

// Auto-submit search form when typing stops
let searchTimeout;
const searchInput = document.querySelector('.search-input');
if (searchInput) {
  searchInput.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
      this.form.submit();
    }, 800);
  });
}

// Handle window resize for responsive behavior
window.addEventListener('resize', function() {
  // Close sidebar on resize to larger screens if it was open
  if (window.innerWidth > 992 && sidebar) {
    sidebar.classList.remove('active');
    if (overlay) overlay.classList.remove('active');
  }
});

// Touch gesture support for mobile navigation
let touchStartX = 0;
let touchEndX = 0;

document.addEventListener('touchstart', e => {
  touchStartX = e.changedTouches[0].screenX;
});

document.addEventListener('touchend', e => {
  touchEndX = e.changedTouches[0].screenX;
  handleSwipe();
});

function handleSwipe() {
  const swipeThreshold = 50;
  
  // Swipe right to open sidebar (only from left edge)
  if (touchEndX > touchStartX && (touchEndX - touchStartX) > swipeThreshold && touchStartX < 50) {
    if (sidebar && window.innerWidth <= 992) {
      sidebar.classList.add('active');
      if (overlay) overlay.classList.add('active');
    }
  }
  
  // Swipe left to close sidebar
  if (touchStartX > touchEndX && (touchStartX - touchEndX) > swipeThreshold) {
    if (sidebar && sidebar.classList.contains('active') && window.innerWidth <= 992) {
      sidebar.classList.remove('active');
      if (overlay) overlay.classList.remove('active');
    }
  }
}

// Enhanced mobile table functionality
function enhanceMobileTable() {
  const tableRows = document.querySelectorAll('.data-table tbody tr');
  const tableContainer = document.querySelector('.table-responsive');
  
  if (tableRows.length > 0 && window.innerWidth <= 768) {
    // Add hover effects for mobile
    tableRows.forEach(row => {
      row.addEventListener('touchstart', function() {
        this.classList.add('mobile-hover');
      });
      
      row.addEventListener('touchend', function() {
        setTimeout(() => {
          this.classList.remove('mobile-hover');
        }, 2000);
      });
    });
    
    // Add scroll indicator
    if (tableContainer) {
      const scrollIndicator = document.createElement('div');
      scrollIndicator.className = 'mobile-scroll-indicator';
      scrollIndicator.innerHTML = '← Scroll horizontally to view all columns →';
      scrollIndicator.style.cssText = `
        position: sticky;
        left: 0;
        bottom: 5px;
        background: rgba(79, 70, 229, 0.9);
        color: white;
        padding: 8px 12px;
        border-radius: 20px;
        font-size: 12px;
        text-align: center;
        margin: 10px auto;
        width: fit-content;
        max-width: 90%;
        z-index: 10;
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
      `;
      
      tableContainer.parentNode.insertBefore(scrollIndicator, tableContainer.nextSibling);
      
      // Hide indicator after 5 seconds
      setTimeout(() => {
        scrollIndicator.style.opacity = '0';
        scrollIndicator.style.transition = 'opacity 1s ease';
        setTimeout(() => {
          scrollIndicator.remove();
        }, 1000);
      }, 5000);
    }
  }
}

// Re-initialize table enhancements on resize
window.addEventListener('resize', enhanceMobileTable);
</script>

</body>
</html>
<?php $conn->close(); ?>