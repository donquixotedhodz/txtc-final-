<?php
session_start();
require_once '../config/database.php';

// Authentication check
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

// Fetch admin info for header
$admin = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT name, profile_picture FROM admins WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fetch technicians for dropdown
$tech_stmt = $pdo->query("SELECT id, name FROM technicians ORDER BY name ASC");
$technicians = $tech_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get filter parameters
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$filter = $_GET['filter'] ?? '';
$custom_from = $_GET['from'] ?? '';
$custom_to = $_GET['to'] ?? '';
$selected_technician = $_GET['technician'] ?? '';
$search_customer = $_GET['search_customer'] ?? '';

$where = '1';
$params = [];

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

// Technician filter
if (!empty($selected_technician)) {
    $where .= " AND assigned_technician_id = ?";
    $params[] = $selected_technician;
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
$sql = "SELECT job_orders.*, aircon_models.brand, aircon_models.model_name 
        FROM job_orders 
        LEFT JOIN aircon_models ON job_orders.aircon_model_id = aircon_models.id 
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
                                    <a class="dropdown-item d-flex align-items-center py-2" href="view/profile.php">
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
    <div id="content" class="flex-grow-1">
        <div class="container-fluid py-4">
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

            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-0">Job Orders Report</h4>
                    <p class="text-muted mb-0">All job orders including cancelled, with filters and pagination</p>
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
                    <div class="col-md-2">
                        <label for="technician" class="form-label">Technician</label>
                        <select name="technician" id="technician" class="form-select" onchange="this.form.submit()">
                            <option value="">All</option>
                            <?php foreach ($technicians as $tech): ?>
                                <option value="<?= $tech['id'] ?>" <?= $selected_technician == $tech['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tech['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
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
                <div class="col-md-4">
                    <div class="card text-bg-primary mb-3">
                        <div class="card-body">
                            <h5 class="card-title mb-2">Total Job Orders</h5>
                            <h3 class="card-text"><?= $total ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-bg-info mb-3">
                        <div class="card-body">
                            <h5 class="card-title mb-2">Current Page</h5>
                            <h3 class="card-text"><?= $page ?></h3>
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
                                        <th>Brand</th>
                                        <th>Model</th>
                                        <th>Price</th>
                                        <th>Status</th>
                                        <th>Technician</th>
                                        <th>Created At</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($order['job_order_number'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($order['customer_name'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($order['brand'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($order['model_name'] ?? '') ?></td>
                                            <td>₱<?= number_format($order['price'] ?? 0,2) ?></td>
                                            <td><?= htmlspecialchars($order['status'] ?? '') ?></td>
                                            <td>
                                                <?php
                                                $tech_name = '';
                                                if (!empty($order['assigned_technician_id'])) {
                                                    foreach ($technicians as $tech) {
                                                        if ($tech['id'] == $order['assigned_technician_id']) {
                                                            $tech_name = $tech['name'];
                                                            break;
                                                        }
                                                    }
                                                }
                                                echo htmlspecialchars($tech_name);
                                                ?>
                                            </td>
                                            <td><?= htmlspecialchars($order['created_at'] ?? '') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($orders)): ?>
                                        <tr><td colspan="8" class="text-center">No job orders found.</td></tr>
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
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

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
        font-size: 9px !important;
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
}
</style>
</body>
</html>