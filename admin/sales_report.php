<?php
session_start();
require_once '../config/database.php';

// Example: Authentication check
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Filter logic (simplified for demo)
$filter = $_GET['filter'] ?? 'day';
$custom_from = $_GET['from'] ?? '';
$custom_to = $_GET['to'] ?? '';
$where = '';
$params = [];
switch ($filter) {
    case 'day': $where = "DATE(completed_at) = CURDATE()"; break;
    case 'week': $where = "YEARWEEK(completed_at, 1) = YEARWEEK(CURDATE(), 1)"; break;
    case 'month': $where = "YEAR(completed_at) = YEAR(CURDATE()) AND MONTH(completed_at) = MONTH(CURDATE())"; break;
    case 'year': $where = "YEAR(completed_at) = YEAR(CURDATE())"; break;
    case 'custom':
        if ($custom_from && $custom_to) {
            $where = "DATE(completed_at) BETWEEN ? AND ?";
            $params = [$custom_from, $custom_to];
        }
        break;
}
$sql = "SELECT * FROM job_orders WHERE status = 'completed'";
if ($where) $sql .= " AND $where";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total_sales = 0;
foreach ($sales as $sale) {
    $total_sales += $sale['price'];
}

// Fetch admin info for header
$admin = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT name, profile_picture FROM admins WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Now include the header
require_once 'includes/header.php';
?>
<body>
 <div class="wrapper">
        <?php
        // Include sidebar
        require_once 'includes/sidebar.php';
        ?>

        <!-- Page Content -->
        <div id="content">
            <nav class="navbar navbar-expand-lg navbar-light bg-white">
                <div class="container-fluid">
                    <button type="button" id="sidebarCollapse" class="btn">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="ms-auto d-flex align-items-center">
                        <div class="dropdown">
                            <a class="d-flex align-items-center text-decoration-none dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                                <img src="<?= !empty($admin['profile_picture']) ? '../' . htmlspecialchars($admin['profile_picture']) : 'https://ui-avatars.com/api/?name=' . urlencode($admin['name'] ?: 'Admin') . '&background=1a237e&color=fff' ?>" 
                                     alt="Admin" 
                                     class="rounded-circle me-2" 
                                     width="32" 
                                     height="32"
                                     style="object-fit: cover; border: 2px solid #4A90E2;">
                                <span class="me-3">Welcome, <?= htmlspecialchars($admin['name'] ?: 'Admin') ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" style="min-width: 200px;">
                                <li>
                                    <a class="dropdown-item d-flex align-items-center py-2" href="profile.php">
                                        <i class="fas fa-user me-2 text-primary"></i>
                                        <span>Profile</span>
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider my-2"></li>
                                <li>
                                    <a class="dropdown-item d-flex align-items-center py-2 text-danger" href="logout.php">
                                        <i class="fas fa-sign-out-alt me-2"></i>
                                        <span>Logout</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </nav>

<div class="container mt-4">
    <h3>Sales Report</h3>
    
    <!-- Print Header (hidden by default, shown only when printing) -->
    <div class="print-header" style="display: none;">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <img src="images/logo.png" alt="Company Logo" style="height: 60px; width: auto;">
            </div>
            <div class="text-end">
                <div style="font-size: 14px; font-weight: bold; color: #2c3e50;">Date Generated:</div>
                <div style="font-size: 12px; color: #7f8c8d;"><?= date('F j, Y \a\t g:i A') ?></div>
            </div>
        </div>
    </div>
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <p class="text-muted mb-0">View and print all completed sales with filters</p>
        </div>
        <button class="btn btn-success" onclick="window.print()">
            <i class="fas fa-print"></i> Print
        </button>
    </div>
    
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title mb-3">Filter Sales Report</h5>
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <label for="filter" class="form-label">Filter By</label>
                    <select name="filter" id="filter" onchange="this.form.submit()" class="form-select">
                        <option value="day" <?= $filter=='day'?'selected':'' ?>>Today</option>
                        <option value="week" <?= $filter=='week'?'selected':'' ?>>This Week</option>
                        <option value="month" <?= $filter=='month'?'selected':'' ?>>This Month</option>
                        <option value="year" <?= $filter=='year'?'selected':'' ?>>This Year</option>
                        <option value="custom" <?= $filter=='custom'?'selected':'' ?>>Custom</option>
                    </select>
                </div>
                <?php if ($filter == 'custom'): ?>
                <div class="col-md-3">
                    <label for="from" class="form-label">From</label>
                    <input type="date" name="from" id="from" value="<?= htmlspecialchars($custom_from) ?>" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label for="to" class="form-label">To</label>
                    <input type="date" name="to" id="to" value="<?= htmlspecialchars($custom_to) ?>" class="form-control" required>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Apply</button>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card text-bg-primary mb-3">
                        <div class="card-body">
                            <h5 class="card-title mb-2">Total Sales</h5>
                            <h3 class="card-text">₱<?= number_format($total_sales, 2) ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-bg-info mb-3">
                        <div class="card-body">
                            <h5 class="card-title mb-2">Number of Transactions</h5>
                            <h3 class="card-text"><?= count($sales) ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-bg-success mb-3">
                        <div class="card-body">
                            <h5 class="card-title mb-2">Filter</h5>
                            <h6 class="card-text text-capitalize"><?= htmlspecialchars($filter) ?></h6>
                            <?php if ($filter == 'custom'): ?>
                                <div class="small">From: <?= htmlspecialchars($custom_from) ?><br>To: <?= htmlspecialchars($custom_to) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div id="sales-report-print">
                        <div class="table-wrapper" style="max-height: 500px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 0.375rem;">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Order #</th>
                                        <th>Customer</th>
                                        <th>Price</th>
                                        <th>Completed At</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sales as $sale): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($sale['job_order_number'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($sale['customer_name'] ?? '') ?></td>
                                            <td>₱<?= number_format($sale['price'] ?? 0,2) ?></td>
                                            <td><?= htmlspecialchars($sale['completed_at'] ?? '') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($sales)): ?>
                                        <tr><td colspan="4" class="text-center">No sales found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                                </table>
                            </div>
                        </div>
                        
                       <!-- Summary Section for Print -->
                        <div class="print-summary mt-4" style="border-top: 2px solid #34495e; padding-top: 20px; display: none;">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="summary-item">
                                        <h5 class="mb-2" style="color: #2c3e50; font-weight: bold;">Summary</h5>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span style="font-weight: 600;">Number of Aircons Sold:</span>
                                            <span style="font-weight: bold; color: #27ae60;"><?= count($sales) ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span style="font-weight: 600;">Total Sales Amount:</span>
                                            <span style="font-weight: bold; color: #e74c3c; font-size: 1.1em;">₱<?= number_format($total_sales, 2) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
 <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <!-- <script src="../js/dashboard.js"></script> -->
<script>
function printSalesReport() {
    var printContents = document.getElementById('sales-report-print').innerHTML;
    var originalContents = document.body.innerHTML;
    document.body.innerHTML = printContents;
    window.print();
    document.body.innerHTML = originalContents;
    location.reload();
}

</script>
<style>
@media print {
    /* Hide screen elements */
    .navbar, .sidebar, #sidebar, .wrapper > #sidebar, .btn, .card-header, .modal, .d-flex.gap-2, .pagination, form, .dropdown, #sidebarCollapse, #content nav,
    .row.mb-4, /* This hides the summary cards row */
    .card.text-bg-primary, .card.text-bg-info, .card.text-bg-success /* Extra safety for summary cards */
    {
        display: none !important;
    }
    
    /* Hide wrapper sidebar structure */
    .wrapper {
        display: block !important;
    }
    
    #content {
        margin-left: 0 !important;
        width: 100% !important;
    }

    /* Show print header */
    .print-header {
        display: block !important;
        margin-bottom: 30px !important;
        padding-bottom: 20px !important;
        border-bottom: 2px solid #34495e !important;
        page-break-after: avoid !important;
    }

    .print-header img {
        display: block !important;
        max-height: 60px !important;
        width: auto !important;
    }

    .print-header .text-end {
        text-align: right !important;
    }

    /* Reset page layout */
    body {
        margin: 0 !important;
        padding: 20px !important;
        font-family: 'Arial', sans-serif !important;
        font-size: 12px !important;
        line-height: 1.4 !important;
        color: #000 !important;
        background: white !important;
    }

    /* Header styling */
    .container-fluid {
        max-width: none !important;
        padding: 0 !important;
    }

    /* Table styling for print */
    .table {
        width: 100% !important;
        border-collapse: collapse !important;
        margin-bottom: 20px !important;
        font-size: 11px !important;
    }

    .table th {
        background: #34495e !important;
        color: white !important;
        font-weight: bold !important;
        text-align: left !important;
        padding: 12px 8px !important;
        border: none !important;
        font-size: 12px !important;
    }

    .table td {
        padding: 10px 8px !important;
        border: none !important;
        vertical-align: top !important;
    }

    .table tbody tr:nth-child(even) {
        background: #f8f9fa !important;
    }

    /* Hide action columns in print if any */
    .table th:last-child,
    .table td:last-child {
        display: none !important;
    }

    /* Print summary styling */
    .print-summary {
        display: block !important;
        margin-top: 30px !important;
        padding-top: 20px !important;
        border-top: 2px solid #34495e !important;
        page-break-inside: avoid !important;
    }

    .print-summary h5 {
        color: #2c3e50 !important;
        font-weight: bold !important;
        font-size: 14px !important;
        margin-bottom: 15px !important;
    }

    .print-summary .d-flex {
        display: flex !important;
        justify-content: space-between !important;
        margin-bottom: 8px !important;
        padding: 5px 0 !important;
    }

    .print-summary span {
        font-size: 12px !important;
    }

    .print-summary span[style*="font-weight: bold"] {
        font-weight: bold !important;
    }

    .print-summary span[style*="color: #27ae60"] {
        color: #27ae60 !important;
    }

    .print-summary span[style*="color: #e74c3c"] {
        color: #e74c3c !important;
        font-size: 13px !important;
    }
}
</style>
</body>
</html>