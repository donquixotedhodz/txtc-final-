# Bulk Order Feature for Installation Job Orders

## Overview
This feature automatically converts installation job orders to bulk orders, allowing administrators to create multiple installation orders for the same customer efficiently. The system automatically detects single orders and handles them appropriately without requiring additional clicks.

## How It Works

### 1. Installation Order Selection
- When clicking on "Installation" in the service type selection modal, the system automatically shows the bulk order interface
- This is designed to handle multiple aircon installations for the same customer

### 2. Dynamic Order Interface
The bulk order modal includes:
- **Customer Information**: Name, phone, and address fields with autocomplete functionality
- **Order Details**: Technician assignment, due date
- **Dynamic Order Management**: 
  - Start with one order by default
  - "Add Another Order" button to add more orders dynamically
  - "Remove Order" button to remove orders (minimum 1 order)
- **Individual Aircon Selection**: Each order can have its own aircon model
- **Individual Pricing**: Each order has its own base price and additional fees
- **Pricing Summary**: Clear breakdown of total costs

### 3. Automatic Single Order Detection
- **Single Order**: When only one order is present, the submit button automatically shows "Create Single Order"
- **Multiple Orders**: When multiple orders are present, the submit button shows "Create Orders"
- **No Manual Switching**: No need to click additional buttons to switch between single and bulk modes

### 4. Dynamic Calculations
- Base price is automatically populated when an aircon model is selected for each order
- Total price is calculated as: `Sum of all (Base Price + Additional Fee) - Total Discount`
- Real-time updates as values change
- Price summary updates automatically

## Features

### Customer Management
- Customer autocomplete functionality
- Automatic customer creation if not exists
- Consistent customer information across all orders

### Dynamic Order Management
- **Add Orders**: Click "Add Another Order" to add more orders
- **Remove Orders**: Click "Remove Order" to remove orders (prevents removing the last order)
- **Individual Configuration**: Each order can have different aircon models and pricing
- **Visual Feedback**: Clear order separation with borders and spacing

### Pricing System
- Automatic price calculation based on aircon model selection
- Support for additional fees per order
- Total discount distribution across all orders
- Visual price summary with breakdown

### Order Management
- Multiple orders created with sequential job order numbers
- Each order maintains individual pricing and aircon model
- Proper customer association
- Technician assignment for all orders

## Technical Implementation

### Files Modified
1. `admin/orders.php` - Main interface and JavaScript functionality
2. `admin/controller/process_bulk_order.php` - Backend processing

### Key Features
- **Database Transactions**: Ensures all orders are created successfully or none
- **Error Handling**: Comprehensive validation and error reporting
- **Customer Lookup**: Efficient customer search and creation
- **Schema Compatibility**: Works with both old and new database schemas
- **Dynamic UI**: Real-time order addition/removal with proper event handling

### JavaScript Functionality
- Real-time price calculations
- Customer autocomplete
- Dynamic order management
- Form validation
- Modal management
- Automatic button text updates

## Usage Instructions

1. **Access**: Click "Add Job Order" button on the orders page
2. **Select Service**: Choose "Installation" from the service type modal
3. **Fill Customer Details**: Enter customer information (autocomplete available)
4. **Configure First Order**: Select aircon model and set additional fees
5. **Add More Orders**: Click "Add Another Order" if needed
6. **Set Common Details**: Assign technician and due date
7. **Review**: Check the price summary before submitting
8. **Submit**: 
   - Single order: Button shows "Create Single Order"
   - Multiple orders: Button shows "Create Orders"

## Benefits

- **Efficiency**: Create multiple orders quickly with dynamic addition
- **Accuracy**: Automatic calculations reduce errors
- **Flexibility**: Support for both single and bulk orders automatically
- **User-Friendly**: Intuitive interface with clear visual feedback
- **Consistency**: Uniform customer and pricing information
- **No Manual Switching**: Automatic detection of single vs bulk orders

## Future Enhancements

- Support for different aircon models per order
- Bulk order templates
- Advanced pricing options
- Order scheduling features
- Integration with inventory management
- Order preview before submission
