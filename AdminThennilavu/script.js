// Admin Dashboard Script
document.addEventListener('DOMContentLoaded', function() {
    // Check authentication
    checkAuthentication();
    
    // Initialize charts if they exist
    initializeCharts();
    
    // Setup search functionality
    setupSearch();
    
    // Setup form submissions
    setupForms();
    
    // Setup logout functionality
    setupLogout();
    
    // Setup user-specific content
    setupUserContent();
});

// Check if user is authenticated
function checkAuthentication() {
    const isLoggedIn = localStorage.getItem('isLoggedIn');
    const userType = localStorage.getItem('userType');
    
    if (!isLoggedIn || !userType) {
    window.location.href = 'login.php';
        return;
    }
    
    // Update UI to show user info
    updateUserInfo();
}

// Update user information in the UI
function updateUserInfo() {
    const userType = localStorage.getItem('userType');
    const username = localStorage.getItem('username');
    
    // Add user info to the header if it doesn't exist
    const topNav = document.querySelector('.top-nav');
    if (topNav && !document.querySelector('.user-info')) {
        const userInfo = document.createElement('div');
        userInfo.className = 'user-info';
        userInfo.innerHTML = `
            <span class="user-type">${userType.charAt(0).toUpperCase() + userType.slice(1)}</span>
            <span class="username">${username}</span>
        `;
        topNav.appendChild(userInfo);
    }
}

// Force update user info on all pages
function forceUpdateUserInfo() {
    const userType = localStorage.getItem('userType');
    const username = localStorage.getItem('username');
    
    if (userType && username) {
        const topNav = document.querySelector('.top-nav');
        if (topNav) {
            // Remove existing user info if any
            const existingUserInfo = document.querySelector('.user-info');
            if (existingUserInfo) {
                existingUserInfo.remove();
            }
            
            // Add new user info
            const userInfo = document.createElement('div');
            userInfo.className = 'user-info';
            userInfo.innerHTML = `
                <span class="user-type">${userType.charAt(0).toUpperCase() + userType.slice(1)}</span>
                <span class="username">${username}</span>
            `;
            topNav.appendChild(userInfo);
        }
    }
}

// Setup user-specific content (hide/show based on user type)
function setupUserContent() {
    const userType = localStorage.getItem('userType');
    
    if (userType === 'staff') {
        // Hide total earnings for staff
        hideTotalEarnings();
    }
}

// Hide total earnings section for staff
function hideTotalEarnings() {
    // Hide total earnings from sidebar
    const totalEarningsLink = document.querySelector('a[href="total-earnings.html"]');
    if (totalEarningsLink) {
        totalEarningsLink.parentElement.style.display = 'none';
    }
    
    // Hide revenue stats from dashboard if on dashboard page
    const revenueStat = document.querySelector('.revenue-stat');
    if (revenueStat) {
        revenueStat.style.display = 'none';
    }
    
    // Hide revenue-related content from other pages
    const revenueElements = document.querySelectorAll('.revenue-content, .earnings-content');
    revenueElements.forEach(element => {
        element.style.display = 'none';
    });
}

// Initialize charts if they exist
function initializeCharts() {
    // Check if Chart.js is loaded and charts exist
    if (typeof Chart !== 'undefined') {
        // Initialize any charts that might be on the page
        const chartElements = document.querySelectorAll('canvas[data-chart]');
        chartElements.forEach(canvas => {
            const chartType = canvas.getAttribute('data-chart');
            const chartData = JSON.parse(canvas.getAttribute('data-chart-data'));
            
            new Chart(canvas.getContext('2d'), {
                type: chartType,
                data: chartData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        });
    }
}

// Setup search functionality
function setupSearch() {
    const searchInputs = document.querySelectorAll('.search-bar input');
    searchInputs.forEach(input => {
        input.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const table = this.closest('.main-content').querySelector('.data-table');
            
            if (table) {
                const rows = table.querySelectorAll('tbody tr');
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    if (text.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            }
        });
    });
}

// Setup form submissions
function setupForms() {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Show success message
            showNotification('Form submitted successfully!', 'success');
            
            // Reset form
            form.reset();
        });
    });
}

// Setup logout functionality
function setupLogout() {
    const logoutBtn = document.querySelector('.logout');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            handleLogout();
        });
    }
}

// Handle logout
function handleLogout() {
    if (confirm('Are you sure you want to logout?')) {
        // Clear stored data
        localStorage.clear();
        
        // Show logout message
        showNotification('Logging out...', 'info');
        
        // Redirect to login page
        setTimeout(() => {
            window.location.href = 'login.php';
        }, 1000);
    }
}

// Show notification
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
        <span>${message}</span>
        <button class="notification-close">&times;</button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
    }, 3000);
    
    // Close button
    const closeBtn = notification.querySelector('.notification-close');
    closeBtn.addEventListener('click', () => {
        if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
    });
}

// Add CSS for notifications
const style = document.createElement('style');
style.textContent = `
    .notification {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 12px 16px;
        border-radius: 8px;
        color: white;
        display: flex;
        align-items: center;
        gap: 8px;
        z-index: 10000;
        animation: slideIn 0.3s ease;
    }
    
    .notification-success {
        background: #10b981;
    }
    
    .notification-error {
        background: #ef4444;
    }
    
    .notification-info {
        background: #3b82f6;
    }
    
    .notification-close {
        background: none;
        border: none;
        color: white;
        font-size: 1.2rem;
        cursor: pointer;
        margin-left: 8px;
    }
    
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    .welcome-section {
        padding: 2rem;
        text-align: center;
    }
    
    .welcome-section h1 {
        color: #1f2937;
        margin-bottom: 1rem;
    }
    
    .welcome-section p {
        color: #6b7280;
        margin-bottom: 2rem;
    }
    
    .quick-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-top: 2rem;
    }
    
    .stat-card {
        background: white;
        padding: 1.5rem;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        text-align: center;
    }
    
    .stat-card h3 {
        color: #6b7280;
        font-size: 0.875rem;
        margin-bottom: 0.5rem;
    }
    
    .stat-card p {
        color: #1f2937;
        font-size: 1.5rem;
        font-weight: 600;
        margin: 0;
    }
    
    /* Dashboard Overview Styles */
    .dashboard-overview {
        margin-bottom: 2rem;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .stat-card {
        background: white;
        padding: 1.5rem;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        display: flex;
        align-items: center;
        gap: 1rem;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    
    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    
    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: white;
    }
    
    .stat-card:nth-child(1) .stat-icon {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    
    .stat-card:nth-child(2) .stat-icon {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    }
    
    .stat-card:nth-child(3) .stat-icon {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    }
    
    .stat-card:nth-child(4) .stat-icon {
        background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
    }
    
    .stat-content h3 {
        color: #6b7280;
        font-size: 0.875rem;
        margin: 0 0 0.5rem 0;
        font-weight: 500;
    }
    
    .stat-number {
        color: #1f2937;
        font-size: 1.75rem;
        font-weight: 700;
        margin: 0 0 0.25rem 0;
    }
    
    .stat-change {
        font-size: 0.75rem;
        font-weight: 500;
    }
    
    .stat-change.positive {
        color: #10b981;
    }
    
    .stat-change.negative {
        color: #ef4444;
    }
    
    /* Quick Actions Section */
    .quick-actions-section {
        margin-bottom: 2rem;
    }
    
    .quick-actions-section h2 {
        color: #1f2937;
        margin-bottom: 1rem;
        font-size: 1.25rem;
    }
    
    .top-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .action-btn {
        background: #3b82f6;
        color: white;
        border: none;
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 500;
        transition: background-color 0.2s ease;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .action-btn:hover {
        background: #2563eb;
    }
    
    .action-btn i {
        font-size: 0.875rem;
    }
    
    /* Search Section */
    .search-section {
        margin-bottom: 2rem;
    }
    
    /* Recent Activity Section */
    .recent-activity-section {
        margin-bottom: 2rem;
    }
    
    .recent-activity-section h2 {
        color: #1f2937;
        margin-bottom: 1rem;
        font-size: 1.25rem;
    }
    
    .activity-tables-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 2rem;
    }
    
    .activity-table-section {
        background: white;
        padding: 1.5rem;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }
    
    .activity-table-section h3 {
        color: #1f2937;
        margin-bottom: 1rem;
        font-size: 1.125rem;
        font-weight: 600;
    }
    
    .activity-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 0.5rem;
    }
    
    .activity-table th {
        background: #f8fafc;
        color: #374151;
        font-weight: 600;
        text-align: left;
        padding: 0.75rem;
        border-bottom: 2px solid #e5e7eb;
        font-size: 0.875rem;
    }
    
    .activity-table td {
        padding: 0.75rem;
        border-bottom: 1px solid #f1f5f9;
        color: #374151;
        font-size: 0.875rem;
    }
    
    .activity-table tbody tr:hover {
        background: #f8fafc;
    }
    
    .activity-table tbody tr:last-child td {
        border-bottom: none;
    }
    
    /* Package Charts Styles */
    .charts-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 2rem;
        margin-bottom: 2rem;
    }
    
    .charts-row canvas {
        background: white;
        padding: 1.5rem;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        max-width: 100%;
        height: 300px !important;
    }
    
    /* User Info Styles */
    .user-info {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-left: auto;
        padding: 0.5rem 1rem;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 8px;
        color: white;
    }
    
    .user-type {
        background: #10b981;
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }
    
    .username {
        font-weight: 500;
    }

    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 10000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        animation: fadeIn 0.3s ease;
    }

    .modal-content {
        background-color: white;
        margin: 2% auto;
        padding: 0;
        border-radius: 12px;
        width: 90%;
        max-width: 1200px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        animation: slideIn 0.3s ease;
    }

    .modal-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 1.5rem 2rem;
        border-radius: 12px 12px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-header h2 {
        margin: 0;
        font-size: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .close {
        color: white;
        font-size: 2rem;
        font-weight: bold;
        cursor: pointer;
        transition: opacity 0.2s ease;
    }

    .close:hover {
        opacity: 0.7;
    }

    .modal-body {
        padding: 2rem;
    }

    .modal-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
        padding: 1.5rem;
        background: #f8fafc;
        border-radius: 8px;
    }

    .stat-item {
        text-align: center;
        padding: 1rem;
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .stat-item .stat-number {
        display: block;
        font-size: 2rem;
        font-weight: 700;
        color: #667eea;
        margin-bottom: 0.5rem;
    }

    .stat-item .stat-label {
        font-size: 0.875rem;
        color: #6b7280;
        font-weight: 500;
    }

    .members-table-container {
        overflow-x: auto;
    }

    .members-table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .members-table th {
        background: #f8fafc;
        color: #374151;
        font-weight: 600;
        text-align: left;
        padding: 1rem;
        border-bottom: 2px solid #e5e7eb;
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .members-table td {
        padding: 1rem;
        border-bottom: 1px solid #f1f5f9;
        color: #374151;
        font-size: 0.875rem;
    }

    .members-table tbody tr:hover {
        background: #f8fafc;
    }

    .members-table tbody tr:last-child td {
        border-bottom: none;
    }

    .membership-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .membership-badge.premium {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        color: white;
    }

    .membership-badge.free {
        background: #e5e7eb;
        color: #374151;
    }

    .status-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .status-badge.active {
        background: #10b981;
        color: white;
    }

    .status-badge.expired {
        background: #ef4444;
        color: white;
    }

    .profile-status-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .profile-status-badge.complete {
        background: #10b981;
        color: white;
    }

    .profile-status-badge.incomplete {
        background: #f59e0b;
        color: white;
    }

    .package-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .package-badge.premium {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        color: white;
    }

    .package-badge.gold {
        background: linear-gradient(135deg, #ffd700 0%, #ffb347 100%);
        color: white;
    }

    .package-badge.silver {
        background: linear-gradient(135deg, #c0c0c0 0%, #a9a9a9 100%);
        color: white;
    }

    .package-badge.free {
        background: #e5e7eb;
        color: #374151;
    }

    .action-btn.small-btn {
        padding: 0.25rem 0.75rem;
        font-size: 0.75rem;
        background: #3b82f6;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        transition: background-color 0.2s ease;
    }

    .action-btn.small-btn:hover {
        background: #2563eb;
    }

    /* Reports Grid Styles */
    .reports-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
        margin-top: 2rem;
    }

    .report-card {
        background: white;
        padding: 1.5rem;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        border: 1px solid #e5e7eb;
    }

    .report-card h3 {
        color: #1f2937;
        margin-bottom: 1rem;
        font-size: 1.125rem;
        font-weight: 600;
    }

    .chart-placeholder {
        height: 200px;
        background: #f8fafc;
        border: 2px dashed #d1d5db;
        border-radius: 8px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: #6b7280;
    }

    .chart-placeholder i {
        font-size: 3rem;
        margin-bottom: 1rem;
        color: #9ca3af;
    }

    .chart-placeholder p {
        margin: 0;
        font-size: 0.875rem;
        text-align: center;
    }

    /* Settings Grid Styles */
    .settings-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 2rem;
        margin-bottom: 2rem;
    }

    .settings-section {
        background: white;
        padding: 1.5rem;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        border: 1px solid #e5e7eb;
    }

    .settings-section h3 {
        color: #1f2937;
        margin-bottom: 1.5rem;
        font-size: 1.125rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .setting-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem 0;
        border-bottom: 1px solid #f1f5f9;
    }

    .setting-item:last-child {
        border-bottom: none;
    }

    .setting-item label {
        color: #374151;
        font-weight: 500;
        font-size: 0.875rem;
    }

    .setting-item input[type="email"],
    .setting-item select {
        padding: 0.5rem;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 0.875rem;
        background: white;
    }

    .setting-item input[type="email"]:read-only {
        background: #f8fafc;
        color: #6b7280;
    }

    .setting-item span {
        color: #6b7280;
        font-size: 0.875rem;
    }

    /* Switch Toggle Styles */
    .switch {
        position: relative;
        display: inline-block;
        width: 50px;
        height: 24px;
    }

    .switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: 0.4s;
        border-radius: 24px;
    }

    .slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: 0.4s;
        border-radius: 50%;
    }

    input:checked + .slider {
        background-color: #10b981;
    }

    input:checked + .slider:before {
        transform: translateX(26px);
    }

    /* Settings Actions */
    .settings-actions {
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
        padding-top: 2rem;
        border-top: 1px solid #e5e7eb;
    }

    .btn-primary {
        background: #3b82f6;
        color: white;
        border: none;
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 500;
        transition: background-color 0.2s ease;
    }

    .btn-primary:hover {
        background: #2563eb;
    }

    .btn-secondary {
        background: #6b7280;
        color: white;
        border: none;
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 500;
        transition: background-color 0.2s ease;
    }

    .btn-secondary:hover {
        background: #4b5563;
    }

    /* Member Details Modal Styles */
    .member-profile {
        padding: 1rem;
    }

    .profile-header {
        display: flex;
        align-items: center;
        gap: 1.5rem;
        margin-bottom: 2rem;
        padding-bottom: 1.5rem;
        border-bottom: 1px solid #e5e7eb;
    }

    .profile-avatar {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 2.5rem;
    }

    .profile-info h3 {
        margin: 0 0 0.5rem 0;
        color: #1f2937;
        font-size: 1.5rem;
        font-weight: 600;
    }

    .profile-info p {
        margin: 0 0 0.5rem 0;
        color: #6b7280;
        font-size: 0.875rem;
    }

    .member-details-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 2rem;
        margin-bottom: 2rem;
    }

    .detail-section {
        background: #f8fafc;
        padding: 1.5rem;
        border-radius: 8px;
        border: 1px solid #e5e7eb;
    }

    .detail-section h4 {
        margin: 0 0 1rem 0;
        color: #1f2937;
        font-size: 1.125rem;
        font-weight: 600;
    }

    .detail-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem 0;
        border-bottom: 1px solid #e5e7eb;
    }

    .detail-item:last-child {
        border-bottom: none;
    }

    .detail-item label {
        color: #374151;
        font-weight: 500;
        font-size: 0.875rem;
    }

    .detail-item span {
        color: #1f2937;
        font-size: 0.875rem;
    }

    .member-actions {
        display: flex;
        gap: 1rem;
        justify-content: center;
        padding-top: 1.5rem;
        border-top: 1px solid #e5e7eb;
    }

    .btn-danger {
        background: #ef4444;
        color: white;
        border: none;
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 500;
        transition: background-color 0.2s ease;
    }

    .btn-danger:hover {
        background: #dc2626;
    }

    /* View Details Button */
    .view-details-btn {
        background: #3b82f6;
        color: white;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.875rem;
        font-weight: 500;
        transition: background-color 0.2s ease;
    }

    .view-details-btn:hover {
        background: #2563eb;
    }

    /* Package Badge Styles for Members Page */
    .package-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        display: inline-block;
    }

    .package-badge.premium {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        color: white;
    }

    .package-badge.gold {
        background: linear-gradient(135deg, #ffd700 0%, #ffb347 100%);
        color: white;
    }

    .package-badge.silver {
        background: linear-gradient(135deg, #c0c0c0 0%, #a9a9a9 100%);
        color: white;
    }

    .package-badge.free {
        background: #e5e7eb;
        color: #374151;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }

    @keyframes slideIn {
        from {
            transform: translateY(-50px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .modal-content {
            width: 95%;
            margin: 5% auto;
        }

        .modal-header {
            padding: 1rem;
        }

        .modal-header h2 {
            font-size: 1.25rem;
        }

        .modal-body {
            padding: 1rem;
        }

        .modal-stats {
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        .members-table th,
        .members-table td {
            padding: 0.75rem 0.5rem;
            font-size: 0.8rem;
        }
    }

    /* Transaction Status Badge Styles */
    .status-badge.completed {
        background: #10b981;
        color: white;
    }

    .status-badge.pending {
        background: #f59e0b;
        color: white;
    }

    .status-badge.failed {
        background: #ef4444;
        color: white;
    }

    .status-badge.refunded {
        background: #6b7280;
        color: white;
    }

    /* Transaction Card Styles */
    .card-list {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
        margin-top: 2rem;
    }

    .member-card {
        background: white;
        padding: 1.5rem;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        border: 1px solid #e5e7eb;
        display: flex;
        align-items: flex-start;
        gap: 1rem;
    }

    .member-card .avatar {
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
        flex-shrink: 0;
    }

    .member-card > div:last-child {
        flex: 1;
    }

    .member-card > div:last-child > div {
        margin-bottom: 0.5rem;
        color: #374151;
        font-size: 0.875rem;
    }

    .member-card > div:last-child > div:first-child {
        font-weight: 600;
        color: #1f2937;
        font-size: 1rem;
    }

    /* Transaction Details Modal Styles */
    .transaction-info {
        padding: 1rem;
    }

    .transaction-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        padding-bottom: 1.5rem;
        border-bottom: 1px solid #e5e7eb;
    }

    .transaction-id h3 {
        margin: 0 0 0.5rem 0;
        color: #1f2937;
        font-size: 1.5rem;
        font-weight: 600;
    }

    .transaction-amount {
        font-size: 2rem;
        font-weight: 700;
        color: #10b981;
    }

    .transaction-details-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 2rem;
        margin-bottom: 2rem;
    }

    .transaction-actions {
        display: flex;
        gap: 1rem;
        justify-content: center;
        padding-top: 1.5rem;
        border-top: 1px solid #e5e7eb;
    }

    /* Package Badge Styles for Additional Packages */
    .package-badge.basic {
        background: #6b7280;
        color: white;
    }

    .package-badge.platinum {
        background: linear-gradient(135deg, #e5e7eb 0%, #9ca3af 100%);
        color: white;
    }

    .package-badge.diamond {
        background: linear-gradient(135deg, #b4e7ff 0%, #4facfe 100%);
        color: white;
    }

    .status-badge.inactive {
        background: #6b7280;
        color: white;
    }

    /* Package Details Modal Styles */
    .package-info {
        padding: 1rem;
    }

    .package-header {
        display: flex;
        align-items: center;
        gap: 1.5rem;
        margin-bottom: 2rem;
        padding-bottom: 1.5rem;
        border-bottom: 1px solid #e5e7eb;
    }

    .package-icon {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 2.5rem;
        flex-shrink: 0;
    }

    .package-info-main h3 {
        margin: 0 0 0.5rem 0;
        color: #1f2937;
        font-size: 1.5rem;
        font-weight: 600;
    }

    .package-info-main p {
        margin: 0 0 0.5rem 0;
        color: #6b7280;
        font-size: 0.875rem;
    }

    .package-price {
        margin-left: auto;
        text-align: right;
    }

    .package-price span:first-child {
        display: block;
        font-size: 2rem;
        font-weight: 700;
        color: #10b981;
    }

    .package-price span:last-child {
        color: #6b7280;
        font-size: 0.875rem;
    }

    .package-details-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 2rem;
        margin-bottom: 2rem;
    }

    .package-actions {
        display: flex;
        gap: 1rem;
        justify-content: center;
        padding-top: 1.5rem;
        border-top: 1px solid #e5e7eb;
    }

    /* Video Style Modal */
    .modal-content.video-style {
        max-width: 900px;
        background: #1a1a1a;
        color: white;
    }

    .modal-content.video-style .modal-header {
        background: #2d2d2d;
        border-bottom: 1px solid #404040;
    }

    .modal-content.video-style .modal-header h2 {
        color: white;
    }

    .modal-content.video-style .close {
        color: white;
    }

    .video-container {
        position: relative;
        background: #000;
        border-radius: 8px;
        overflow: hidden;
        margin-bottom: 1.5rem;
    }

    .video-placeholder {
        height: 300px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .video-overlay {
        text-align: center;
        color: white;
        z-index: 2;
    }

    .video-overlay i {
        font-size: 4rem;
        margin-bottom: 1rem;
        opacity: 0.8;
    }

    .video-overlay h3 {
        margin: 0 0 0.5rem 0;
        font-size: 1.5rem;
        font-weight: 600;
    }

    .video-overlay p {
        margin: 0;
        opacity: 0.8;
        font-size: 0.875rem;
    }

    .video-controls {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: rgba(0, 0, 0, 0.8);
        padding: 1rem;
    }

    .progress-bar {
        width: 100%;
        height: 4px;
        background: #404040;
        border-radius: 2px;
        margin-bottom: 0.5rem;
        overflow: hidden;
    }

    .progress-fill {
        width: 35%;
        height: 100%;
        background: #3b82f6;
        border-radius: 2px;
        transition: width 0.3s ease;
    }

    .control-buttons {
        display: flex;
        gap: 0.5rem;
        align-items: center;
    }

    .control-btn {
        background: none;
        border: none;
        color: white;
        font-size: 1rem;
        cursor: pointer;
        padding: 0.5rem;
        border-radius: 4px;
        transition: background-color 0.2s ease;
    }

    .control-btn:hover {
        background: rgba(255, 255, 255, 0.1);
    }

    .video-info {
        padding: 1.5rem;
        background: #2d2d2d;
        border-radius: 8px;
    }

    .video-title {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }

    .video-title h3 {
        margin: 0;
        color: white;
        font-size: 1.5rem;
        font-weight: 600;
    }

    .video-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .video-stats .stat-item {
        text-align: center;
        padding: 1rem;
        background: #404040;
        border-radius: 8px;
    }

    .video-stats .stat-item i {
        font-size: 1.5rem;
        color: #3b82f6;
        margin-bottom: 0.5rem;
    }

    .video-stats .stat-item span {
        display: block;
        font-size: 1.25rem;
        font-weight: 600;
        color: white;
        margin-bottom: 0.25rem;
    }

    .video-stats .stat-item label {
        font-size: 0.75rem;
        color: #9ca3af;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .package-features {
        margin-bottom: 1.5rem;
    }

    .package-features h4 {
        color: white;
        margin-bottom: 1rem;
        font-size: 1.125rem;
    }

    .features-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 0.75rem;
    }

    .feature-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem;
        background: #404040;
        border-radius: 6px;
        color: white;
    }

    .feature-item i {
        color: #10b981;
        font-size: 1rem;
    }

    .feature-item span {
        font-size: 0.875rem;
    }

    .video-actions {
        display: flex;
        gap: 1rem;
        justify-content: center;
    }

    .video-actions .btn-primary,
    .video-actions .btn-secondary,
    .video-actions .btn-danger {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1.5rem;
        border: none;
        border-radius: 6px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .video-actions .btn-primary {
        background: #3b82f6;
        color: white;
    }

    .video-actions .btn-primary:hover {
        background: #2563eb;
    }

    .video-actions .btn-secondary {
        background: #6b7280;
        color: white;
    }

    .video-actions .btn-secondary:hover {
        background: #4b5563;
    }

    .video-actions .btn-danger {
        background: #ef4444;
        color: white;
    }

    .video-actions .btn-danger:hover {
        background: #dc2626;
    }

    /* Package Details Table Styles */
    .package-details-container {
        margin-bottom: 2rem;
    }

    .package-details-table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .package-details-table th {
        background: #f8fafc;
        color: #374151;
        font-weight: 600;
        text-align: left;
        padding: 1rem;
        border-bottom: 2px solid #e5e7eb;
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .package-details-table td {
        padding: 1rem;
        border-bottom: 1px solid #f1f5f9;
        color: #374151;
        font-size: 0.875rem;
    }

    .package-details-table tbody tr:hover {
        background: #f8fafc;
    }

    .package-details-table tbody tr:last-child td {
        border-bottom: none;
    }

    /* Call Management Styles */
    .call-btn, .export-btn, .history-btn {
        padding: 0.5rem 1rem;
        border: none;
        border-radius: 6px;
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .call-btn {
        background: #10b981;
        color: white;
    }

    .call-btn:hover {
        background: #059669;
    }

    .export-btn {
        background: #3b82f6;
        color: white;
    }

    .export-btn:hover {
        background: #2563eb;
    }

    .history-btn {
        background: #8b5cf6;
        color: white;
    }

    .history-btn:hover {
        background: #7c3aed;
    }

    /* Call History Modal Styles */
    .call-history-container {
        margin-bottom: 2rem;
    }

    .call-history-table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .call-history-table th {
        background: #f8fafc;
        color: #374151;
        font-weight: 600;
        text-align: left;
        padding: 1rem;
        border-bottom: 2px solid #e5e7eb;
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .call-history-table td {
        padding: 1rem;
        border-bottom: 1px solid #f1f5f9;
        color: #374151;
        font-size: 0.875rem;
    }

    .call-history-table tbody tr:hover {
        background: #f8fafc;
    }

    .call-history-table tbody tr:last-child td {
        border-bottom: none;
    }

    .call-actions {
        display: flex;
        gap: 1rem;
        justify-content: center;
        margin-top: 2rem;
    }

    .call-actions .btn-primary {
        background: #10b981;
        color: white;
        border: none;
        padding: 0.75rem 1.5rem;
        border-radius: 6px;
        font-weight: 500;
        cursor: pointer;
        transition: background 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .call-actions .btn-primary:hover {
        background: #059669;
    }

    .call-actions .btn-secondary {
        background: #6b7280;
        color: white;
        border: none;
        padding: 0.75rem 1.5rem;
        border-radius: 6px;
        font-weight: 500;
        cursor: pointer;
        transition: background 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .call-actions .btn-secondary:hover {
        background: #4b5563;
    }

    .call-actions .btn-danger {
        background: #ef4444;
        color: white;
        border: none;
        padding: 0.75rem 1.5rem;
        border-radius: 6px;
        font-weight: 500;
        cursor: pointer;
        transition: background 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .call-actions .btn-danger:hover {
        background: #dc2626;
    }
`;
document.head.appendChild(style); 