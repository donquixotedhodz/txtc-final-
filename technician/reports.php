<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a technician
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'technician') {
    header('Location: ../index.php');
    exit();
}

// Database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Fetch technician info for header
$technician = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT name, profile_picture FROM technicians WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $technician = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get filter parameters
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$filter = $_GET['filter'] ?? '';
$custom_from = $_GET['from'] ?? '';
$custom_to = $_GET['to'] ?? '';
$search_customer = $_GET['search_customer'] ?? '';

$where = 'assigned_technician_id = ?';
$params = [$_SESSION['user_id']];

// Date filters
switch ($filter) {
    case 'day': $where .= " AND DATE(created_at) = CURDATE()"; break;
    case 'week': $where .= " AND YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)"; break;
    case 'month': $where .= " AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())"; break;
    case 'year': $where .= " AND YEAR(created_at) = YEAR(CURDATE())"; break;
    case 'custom':
        if ($custom_from && $custom_to) {
            $where .= " AND DATE(created_at) BETWEEN ? AND ?";
            $params[] = $custom_from;
            $params[] = $custom_to;
        }
        break;
}

// Customer name filter
if (!empty($search_customer)) {
    $where .= " AND customer_name LIKE ?";
    $params[] = '%' . $search_customer . '%';
}

// Get total count for pagination
$count_sql = "SELECT COUNT(*) FROM job_orders WHERE $where";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total = $count_stmt->fetchColumn();

// Get job orders (including cancelled)
$sql = "SELECT job_orders.*, 
               aircon_models.brand, 
               aircon_models.model_name,
               ac_parts.part_name,
               ac_parts.part_code,
               ac_parts.part_category
        FROM job_orders 
        LEFT JOIN aircon_models ON job_orders.aircon_model_id = aircon_models.id 
        LEFT JOIN ac_parts ON job_orders.part_id = ac_parts.id
        WHERE $where 
        ORDER BY job_orders.created_at DESC 
        LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once 'includes/header.php';
?>
<body>
    
<div class="wrapper d-flex">
    <?php require_once 'includes/sidebar.php'; ?>

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
                                <img src="<?= !empty($technician['profile_picture']) ? '../' . htmlspecialchars($technician['profile_picture']) : 'https://ui-avatars.com/api/?name=' . urlencode($technician['name'] ?: 'Technician') . '&background=1a237e&color=fff' ?>" 
                                     alt="Technician" 
                                     class="rounded-circle me-2" 
                                     width="32" 
                                     height="32"
                                     style="object-fit: cover; border: 2px solid #4A90E2;">
                                <span class="me-3">Welcome, <?= htmlspecialchars($technician['name'] ?: 'Technician') ?></span>
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
                                    <a class="dropdown-item d-flex align-items-center py-2 text-danger" href="../admin/logout.php">
                                        <i class="fas fa-sign-out-alt me-2"></i>
                                        <span>Logout</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </nav>
            
            <div class="container-fluid py-4">
            <!-- Print Header (hidden by default, shown only when printing) -->
            <div class="print-header" style="display: none;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <img src="../images/logo.png" alt="Company Logo" style="height: 60px; width: auto;">
                    </div>
                    <div class="text-end">
                        <div style="font-size: 14px; font-weight: bold; color: #2c3e50;">My Job Orders Report</div>
                        <div style="font-size: 12px; color: #7f8c8d;">Technician: <?= htmlspecialchars($technician['name'] ?: 'Technician') ?></div>
                        <div style="font-size: 12px; color: #7f8c8d;">Date Generated: <?= date('F j, Y \a\t g:i A') ?></div>
                    </div>
                </div>
            </div>

            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-0">My Job Orders Report</h4>
                    <p class="text-muted mb-0">All your assigned job orders including cancelled, with filters and pagination</p>
                </div>
                <button class="btn btn-success" onclick="window.print()">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>

            <!-- Filter Form -->
            <form method="get" class="mb-4">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label for="filter" class="form-label">Filter By Date</label>
                        <select name="filter" id="filter" onchange="this.form.submit()" class="form-select">
                            <option value="">All</option>
                            <option value="day" <?= $filter=='day'?'selected':'' ?>>Today</option>
                            <option value="week" <?= $filter=='week'?'selected':'' ?>>This Week</option>
                            <option value="month" <?= $filter=='month'?'selected':'' ?>>This Month</option>
                            <option value="year" <?= $filter=='year'?'selected':'' ?>>This Year</option>
                            <option value="custom" <?= $filter=='custom'?'selected':'' ?>>Custom</option>
                        </select>
                    </div>
                    <?php if ($filter == 'custom'): ?>
                    <div class="col-md-2">
                        <label for="from" class="form-label">From</label>
                        <input type="date" name="from" id="from" value="<?= htmlspecialchars($custom_from) ?>" class="form-control" required>
                    </div>
                    <div class="col-md-2">
                        <label for="to" class="form-label">To</label>
                        <input type="date" name="to" id="to" value="<?= htmlspecialchars($custom_to) ?>" class="form-control" required>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-3">
                        <label for="search_customer" class="form-label">Customer Name</label>
                        <input type="text" name="search_customer" id="search_customer" value="<?= htmlspecialchars($search_customer) ?>" class="form-control" placeholder="Search customer...">
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-primary w-100">Apply</button>
                    </div>
                </div>
            </form>

            <!-- Cards Summary -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card text-bg-primary mb-3">
                        <div class="card-body">
                            <h5 class="card-title mb-2">Total Job Orders</h5>
                            <h3 class="card-text"><?= $total ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
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

            <!-- Job Orders Table -->
            <div class="card">
                <div class="card-body">
                    <div id="job-orders-report-print">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Order #</th>
                                        <th>Customer</th>
                                        <th>Service Type</th>
                                        <th>Brand</th>
                                        <th>Model</th>
                                        <th>Part Code</th>
                                        <th>Part Name</th>
                                        <th>Price</th>
                                        <th>Status</th>
                                        <th>Created At</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($order['job_order_number'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($order['customer_name'] ?? '') ?></td>
                                            <td>
                                                <span class="badge 
                                                    <?php 
                                                    switch($order['service_type']) {
                                                        case 'installation': echo 'bg-primary'; break;
                                                        case 'repair': echo 'bg-warning text-dark'; break;
                                                        case 'maintenance': echo 'bg-success'; break;
                                                        case 'survey': echo 'bg-info'; break;
                                                        default: echo 'bg-secondary';
                                                    }
                                                    ?>
                                                ">
                                                    <?= ucfirst(htmlspecialchars($order['service_type'] ?? '')) ?>
                                                </span>
                                            </td>
                                            <!-- Brand Column -->
                                            <td>
                                                <?php if ($order['service_type'] == 'installation'): ?>
                                                    <?= htmlspecialchars($order['brand'] ?? 'N/A') ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <!-- Model Column -->
                                            <td>
                                                <?php if ($order['service_type'] == 'installation'): ?>
                                                    <?= htmlspecialchars($order['model_name'] ?? 'N/A') ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <!-- Part Code Column -->
                                            <td>
                                                <?php if ($order['service_type'] == 'repair'): ?>
                                                    <?php if (!empty($order['part_name'])): ?>
                                                        <?= htmlspecialchars($order['part_code'] ?? 'N/A') ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not specified</span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <!-- Part Name Column -->
                                            <td>
                                                <?php if ($order['service_type'] == 'repair'): ?>
                                                    <?php if (!empty($order['part_name'])): ?>
                                                        <?= htmlspecialchars($order['part_name'] ?? 'N/A') ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not specified</span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>₱<?= number_format($order['price'] ?? 0,2) ?></td>
                                            <td>
                                                <?php
                                                $status_class = '';
                                                switch($order['status']) {
                                                    case 'pending': $status_class = 'bg-secondary'; break;
                                                    case 'in_progress': $status_class = 'bg-info'; break;
                                                    case 'completed': $status_class = 'bg-success'; break;
                                                    case 'cancelled': $status_class = 'bg-danger'; break;
                                                    default: $status_class = 'bg-secondary';
                                                }
                                                ?>
                                                <span class="badge <?= $status_class ?>">
                                                    <?= ucfirst(htmlspecialchars($order['status'] ?? '')) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($order['created_at'] ?? '') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($orders)): ?>
                                        <tr><td colspan="10" class="text-center">No job orders found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <!-- Pagination -->
                        <nav>
                            <ul class="pagination justify-content-center">
                                <?php for ($i = 1; $i <= ceil($total / $limit); $i++): ?>
                                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                        
                        <!-- Summary Section for Print -->
                        <div class="print-summary mt-4" style="border-top: 2px solid #34495e; padding-top: 20px; display: none;">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="summary-item">
                                        <h5 class="mb-2" style="color: #2c3e50; font-weight: bold;">Summary</h5>
                                        <div class="d-flex justify-content-between mb-2">
                            <span style="font-weight: 600;">Total Job Orders:</span>
                            <span style="font-weight: bold; color: #27ae60;"><?= $total ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span style="font-weight: 600;">Repair Orders:</span>
                            <span style="font-weight: bold; color: #f39c12;"><?= count(array_filter($orders, function($order) { return $order['service_type'] == 'repair'; })) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span style="font-weight: 600;">Installation Orders:</span>
                            <span style="font-weight: bold; color: #3498db;"><?= count(array_filter($orders, function($order) { return $order['service_type'] == 'installation'; })) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span style="font-weight: 600;">Maintenance Orders:</span>
                            <span style="font-weight: bold; color: #27ae60;"><?= count(array_filter($orders, function($order) { return $order['service_type'] == 'maintenance'; })) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span style="font-weight: 600;">Survey Orders:</span>
                            <span style="font-weight: bold; color: #17a2b8;"><?= count(array_filter($orders, function($order) { return $order['service_type'] == 'survey'; })) ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span style="font-weight: 600;">Filter Applied:</span>
                            <span style="font-weight: bold; color: #e74c3c; font-size: 1.1em;"><?= htmlspecialchars($filter ?: 'All') ?></span>
                        </div>
                                        <?php if ($filter == 'custom'): ?>
                                        <div class="d-flex justify-content-between mt-2">
                                            <span style="font-weight: 600;">Date Range:</span>
                                            <span style="font-weight: bold; color: #9b59b6;"><?= htmlspecialchars($custom_from) ?> to <?= htmlspecialchars($custom_to) ?></span>
                                        </div>
                                        <?php endif; ?>
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
    <script src="../js/dashboard.js"></script>
<!-- Print Script -->
<script>
function printJobOrdersReport() {
    var printContents = document.getElementById('job-orders-report-print').innerHTML;
    var originalContents = document.body.innerHTML;
    document.body.innerHTML = printContents;
    window.print();
    document.body.innerHTML = originalContents;
    location.reload();
}
</script>

<style>
/* Table font size for screen */
.table {
    font-size: 14px !important;
}

/* Table font size for print */
@media print {
    .table {
        font-size: 14px !important;
    }
    .table th {
        font-size: 16px !important;
    }
    /* Hide screen elements */
    .navbar, .sidebar, .btn, .card-header, .modal, .d-flex.gap-2, .pagination, form, .dropdown, #sidebarCollapse,
    .row.mb-4, /* This hides the summary cards row */
    .card.text-bg-primary, .card.text-bg-info, .card.text-bg-success /* Extra safety for summary cards */
    {
        display: none !important;
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

    .print-summary span[style*="color: #3498db"] {
        color: #3498db !important;
    }

    .print-summary span[style*="color: #e74c3c"] {
        color: #e74c3c !important;
        font-size: 13px !important;
    }

    .print-summary span[style*="color: #9b59b6"] {
        color: #9b59b6 !important;
    }
}
</style>
</body>
</html>