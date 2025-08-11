# Job Order System - Customer Linking Fix

## Problem Description

The job order system had issues where:
1. Customer details were being saved to the database, but job orders were not being inserted into the job_orders table
2. There was no proper connection between customers and job orders
3. The "View Order" functionality was not displaying orders properly for each customer

## Root Cause Analysis

1. **Database Schema Inconsistencies**: Multiple database schema files with different structures
2. **Missing Foreign Key Relationships**: No proper link between customers and job orders
3. **Incomplete Job Order Insertion**: The process_order.php file was missing some required fields
4. **Query Issues**: Customer orders view was not properly joining with customer data

## Solutions Implemented

### 1. Database Schema Update (`sql/update_database_schema.sql`)

This script ensures:
- Customers table exists with proper structure
- Job_orders table has customer_id column
- Foreign key constraints are properly established
- Existing data is linked correctly
- Performance indexes are added

**To run the database update:**
```sql
mysql -u your_username -p job_order_system < sql/update_database_schema.sql
```

### 2. Fixed Job Order Creation Process (`admin/controller/process_order.php`)

**Changes made:**
- Added proper customer lookup and creation logic
- Updated customer address if customer already exists
- Added missing fields (additional_fee, discount) to job order insertion
- Ensured proper parameter binding for all fields

**Key improvements:**
```php
// Customer lookup/creation with address update
if ($customer) {
    $customer_id = $customer['id'];
    // Update customer address if it has changed
    $stmt = $pdo->prepare("UPDATE customers SET address = ? WHERE id = ?");
    $stmt->execute([$customer_address, $customer_id]);
} else {
    // Insert new customer
    $stmt = $pdo->prepare("INSERT INTO customers (name, phone, address) VALUES (?, ?, ?)");
    $stmt->execute([$customer_name, $customer_phone, $customer_address]);
    $customer_id = $pdo->lastInsertId();
}
```

### 3. Enhanced Customer Orders View (`admin/customer_orders.php`)

**Changes made:**
- Updated query to properly join with aircon_models and technicians
- Added model_name, brand, and technician_name to the display
- Ensured proper filtering by customer_id

**Improved query:**
```sql
SELECT 
    jo.*,
    COALESCE(am.model_name, 'Not Specified') as model_name,
    COALESCE(am.brand, 'Not Specified') as brand,
    t.name as technician_name
FROM job_orders jo 
LEFT JOIN aircon_models am ON jo.aircon_model_id = am.id 
LEFT JOIN technicians t ON jo.assigned_technician_id = t.id
WHERE jo.customer_id = ? 
ORDER BY jo.created_at DESC
```

### 4. Updated Orders Display (`admin/orders.php`)

**Changes made:**
- Added customer information to the main orders query
- Improved customer summary section with proper JOINs
- Enhanced data display with customer details

## How the System Now Works

### 1. Job Order Creation Process

1. **Customer Input**: Admin enters customer name, phone, and address
2. **Customer Lookup**: System checks if customer already exists by name and phone
3. **Customer Creation/Update**: 
   - If customer exists: Updates address if changed
   - If customer doesn't exist: Creates new customer record
4. **Job Order Creation**: Creates job order with proper customer_id link
5. **Success Redirect**: Redirects to appropriate page with success message

### 2. Customer-Order Relationship

- Each job order is linked to a customer via `customer_id` foreign key
- Customer information is stored in the `customers` table
- Job order details are stored in the `job_orders` table
- The relationship allows for proper data integrity and efficient queries

### 3. View Order Functionality

- **Customer Orders Page**: Shows all orders for a specific customer
- **Main Orders Page**: Shows all orders with customer information
- **Individual Order View**: Shows detailed order information with customer data

## Testing the Fix

### 1. Run the Database Update
```bash
mysql -u your_username -p job_order_system < sql/update_database_schema.sql
```

### 2. Test Job Order Creation
1. Go to Admin Dashboard
2. Click "Add Job Order"
3. Fill in customer details and order information
4. Submit the form
5. Verify that both customer and job order are created
6. Check that the job order appears in the orders list

### 3. Test Customer Order View
1. Go to Orders page
2. Find a customer in the sidebar
3. Click "View Orders" for that customer
4. Verify that all orders for that customer are displayed

### 4. Run the Test Script
```bash
php test_job_order_creation.php
```

This will verify that:
- Database schema is correct
- Job order creation works
- Customer linking works
- Data integrity is maintained

## Database Schema Overview

### Customers Table
```sql
CREATE TABLE customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Job Orders Table
```sql
CREATE TABLE job_orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    job_order_number VARCHAR(20) UNIQUE NOT NULL,
    customer_name VARCHAR(100) NOT NULL,
    customer_address TEXT NOT NULL,
    customer_phone VARCHAR(20) NOT NULL,
    service_type ENUM('installation', 'repair') NOT NULL,
    aircon_model_id INT,
    assigned_technician_id INT,
    status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    price DECIMAL(10,2) DEFAULT NULL,
    additional_fee DECIMAL(10,2) DEFAULT 0.00,
    discount DECIMAL(10,2) DEFAULT 0.00,
    created_by INT,
    due_date DATE DEFAULT NULL,
    customer_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (aircon_model_id) REFERENCES aircon_models(id),
    FOREIGN KEY (assigned_technician_id) REFERENCES technicians(id),
    FOREIGN KEY (created_by) REFERENCES admins(id)
);
```

## Troubleshooting

### Common Issues and Solutions

1. **Job orders not being created**
   - Check database connection in config/database.php
   - Verify that all required tables exist
   - Check PHP error logs for specific errors

2. **Customer orders not displaying**
   - Ensure customer_id is properly set in job_orders table
   - Verify that the customer exists in customers table
   - Check that the JOIN queries are working correctly

3. **Foreign key constraint errors**
   - Run the database update script to fix schema issues
   - Ensure all referenced tables exist
   - Check that data types match between tables

4. **Performance issues**
   - The update script adds indexes for better performance
   - Monitor query execution times
   - Consider adding more indexes if needed

## Files Modified

1. `sql/update_database_schema.sql` - Database schema update script
2. `admin/controller/process_order.php` - Fixed job order creation
3. `admin/customer_orders.php` - Enhanced customer orders view
4. `admin/orders.php` - Updated orders display
5. `test_job_order_creation.php` - Test script for verification

## Next Steps

1. **Backup your database** before running the update script
2. **Run the database update script** to fix schema issues
3. **Test the job order creation** process
4. **Verify customer order views** work correctly
5. **Monitor the system** for any remaining issues

## Support

If you encounter any issues after implementing these fixes:
1. Check the PHP error logs
2. Run the test script to identify specific problems
3. Verify database schema matches the expected structure
4. Ensure all file permissions are correct
