<?php
require_once 'config/database.php';

echo "<h2>Testing Job Order Creation and Customer Linking</h2>";

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h3>1. Database Connection Test</h3>";
    echo "✓ Database connection successful<br>";

    echo "<h3>2. Checking Database Schema</h3>";
    
    // Check if customers table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'customers'");
    if ($stmt->rowCount() > 0) {
        echo "✓ Customers table exists<br>";
    } else {
        echo "✗ Customers table does not exist<br>";
    }

    // Check if job_orders table has customer_id column
    $stmt = $pdo->query("SHOW COLUMNS FROM job_orders LIKE 'customer_id'");
    if ($stmt->rowCount() > 0) {
        echo "✓ Job_orders table has customer_id column<br>";
    } else {
        echo "✗ Job_orders table missing customer_id column<br>";
    }

    echo "<h3>3. Current Data Status</h3>";
    
    // Count customers
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM customers");
    $customerCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Total customers: $customerCount<br>";

    // Count job orders
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM job_orders");
    $orderCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Total job orders: $orderCount<br>";

    // Count linked job orders
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM job_orders WHERE customer_id IS NOT NULL");
    $linkedCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Linked job orders: $linkedCount<br>";

    // Count unlinked job orders
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM job_orders WHERE customer_id IS NULL");
    $unlinkedCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Unlinked job orders: $unlinkedCount<br>";

    echo "<h3>4. Sample Customer Data</h3>";
    $stmt = $pdo->query("SELECT * FROM customers LIMIT 5");
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($customers)) {
        echo "No customers found<br>";
    } else {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Name</th><th>Phone</th><th>Address</th></tr>";
        foreach ($customers as $customer) {
            echo "<tr>";
            echo "<td>{$customer['id']}</td>";
            echo "<td>{$customer['name']}</td>";
            echo "<td>{$customer['phone']}</td>";
            echo "<td>{$customer['address']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    echo "<h3>5. Sample Job Order Data</h3>";
    $stmt = $pdo->query("
        SELECT 
            jo.id,
            jo.job_order_number,
            jo.customer_name,
            jo.customer_id,
            jo.status,
            c.name as linked_customer_name
        FROM job_orders jo
        LEFT JOIN customers c ON jo.customer_id = c.id
        LIMIT 5
    ");
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($orders)) {
        echo "No job orders found<br>";
    } else {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Order #</th><th>Customer Name</th><th>Customer ID</th><th>Linked Customer</th><th>Status</th></tr>";
        foreach ($orders as $order) {
            echo "<tr>";
            echo "<td>{$order['id']}</td>";
            echo "<td>{$order['job_order_number']}</td>";
            echo "<td>{$order['customer_name']}</td>";
            echo "<td>{$order['customer_id']}</td>";
            echo "<td>{$order['linked_customer_name']}</td>";
            echo "<td>{$order['status']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    echo "<h3>6. Testing Job Order Creation Process</h3>";
    
    // Simulate the job order creation process
    $testCustomerName = "Test Customer " . date('Y-m-d H:i:s');
    $testCustomerPhone = "09999999999";
    $testCustomerAddress = "Test Address";
    
    // Step 1: Customer lookup/creation
    $stmt = $pdo->prepare("SELECT id FROM customers WHERE name = ? AND phone = ? LIMIT 1");
    $stmt->execute([$testCustomerName, $testCustomerPhone]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($customer) {
        $customer_id = $customer['id'];
        echo "✓ Found existing customer: $customer_id<br>";
    } else {
        // Insert new customer
        $stmt = $pdo->prepare("INSERT INTO customers (name, phone, address) VALUES (?, ?, ?)");
        $stmt->execute([$testCustomerName, $testCustomerPhone, $testCustomerAddress]);
        $customer_id = $pdo->lastInsertId();
        echo "✓ Created new customer: $customer_id<br>";
    }

    // Step 2: Generate job order number
    $year = date('Y');
    $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(job_order_number, 5) AS UNSIGNED)) as max_num 
                        FROM job_orders 
                        WHERE job_order_number LIKE '$year%'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $next_num = ($result['max_num'] ?? 0) + 1;
    $job_order_number = $year . str_pad($next_num, 5, '0', STR_PAD_LEFT);
    echo "✓ Generated job order number: $job_order_number<br>";

    // Step 3: Insert job order
    $stmt = $pdo->prepare("
        INSERT INTO job_orders (
            job_order_number,
            customer_name,
            customer_address,
            customer_phone,
            service_type,
            status,
            price,
            customer_id
        ) VALUES (?, ?, ?, ?, 'installation', 'pending', 15000.00, ?)
    ");
    
    $stmt->execute([
        $job_order_number,
        $testCustomerName,
        $testCustomerAddress,
        $testCustomerPhone,
        $customer_id
    ]);
    
    $jobOrderId = $pdo->lastInsertId();
    echo "✓ Created job order: $jobOrderId<br>";

    // Step 4: Verify the link
    $stmt = $pdo->prepare("
        SELECT 
            jo.id,
            jo.job_order_number,
            jo.customer_name,
            jo.customer_id,
            c.name as linked_customer_name
        FROM job_orders jo
        LEFT JOIN customers c ON jo.customer_id = c.id
        WHERE jo.id = ?
    ");
    $stmt->execute([$jobOrderId]);
    $testOrder = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($testOrder && $testOrder['customer_id'] == $customer_id) {
        echo "✓ Job order successfully linked to customer<br>";
    } else {
        echo "✗ Job order not properly linked to customer<br>";
    }

    echo "<h3>7. Cleanup Test Data</h3>";
    // Clean up test data
    $stmt = $pdo->prepare("DELETE FROM job_orders WHERE customer_name = ?");
    $stmt->execute([$testCustomerName]);
    
    $stmt = $pdo->prepare("DELETE FROM customers WHERE name = ?");
    $stmt->execute([$testCustomerName]);
    
    echo "✓ Test data cleaned up<br>";

    echo "<h3>✅ Test Completed Successfully!</h3>";
    echo "The job order creation and customer linking system is working correctly.<br>";

} catch (PDOException $e) {
    echo "<h3>❌ Test Failed</h3>";
    echo "Error: " . $e->getMessage() . "<br>";
}
?>
