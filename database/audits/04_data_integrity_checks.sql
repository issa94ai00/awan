-- ============================================
-- Database Audit: Data Integrity Checks
-- ============================================
-- Purpose: Check for orphaned records and data inconsistencies
-- Run this script to audit data integrity
-- ============================================

SET @db_name = DATABASE();

-- ============================================
-- Orphaned Records Detection
-- ============================================

-- 1. Products without any warehouse assignment
SELECT 
    'Products without warehouse assignment' AS issue_type,
    COUNT(*) AS record_count,
    GROUP_CONCAT(CONCAT('ID:', p.id, ' - ', p.name) SEPARATOR '; ') AS sample_records
FROM products p
LEFT JOIN product_warehouse_assignments pwa ON p.id = pwa.product_id
WHERE pwa.id IS NULL
LIMIT 5;

-- 2. Warehouse inventory without valid warehouse
SELECT 
    'Warehouse inventory without valid warehouse' AS issue_type,
    COUNT(*) AS record_count,
    GROUP_CONCAT(CONCAT('WI ID:', wi.id, ' - WH ID:', wi.warehouse_id) SEPARATOR '; ') AS sample_records
FROM warehouse_inventory wi
LEFT JOIN warehouses w ON wi.warehouse_id = w.id
WHERE w.id IS NULL
LIMIT 5;

-- 3. Warehouse inventory without valid product
SELECT 
    'Warehouse inventory without valid product' AS issue_type,
    COUNT(*) AS record_count,
    GROUP_CONCAT(CONCAT('WI ID:', wi.id, ' - Product ID:', wi.product_id) SEPARATOR '; ') AS sample_records
FROM warehouse_inventory wi
LEFT JOIN products p ON wi.product_id = p.id
WHERE p.id IS NULL
LIMIT 5;

-- 4. Warehouse inventory without valid variant (when variant_id is set)
SELECT 
    'Warehouse inventory without valid variant' AS issue_type,
    COUNT(*) AS record_count,
    GROUP_CONCAT(CONCAT('WI ID:', wi.id, ' - Variant ID:', wi.product_variant_id) SEPARATOR '; ') AS sample_records
FROM warehouse_inventory wi
LEFT JOIN product_variants pv ON wi.product_variant_id = pv.id
WHERE wi.product_variant_id IS NOT NULL
AND pv.id IS NULL
LIMIT 5;

-- 5. Product warehouse assignments without valid product
SELECT 
    'Assignment without valid product' AS issue_type,
    COUNT(*) AS record_count,
    GROUP_CONCAT(CONCAT('Assignment ID:', pwa.id, ' - Product ID:', pwa.product_id) SEPARATOR '; ') AS sample_records
FROM product_warehouse_assignments pwa
LEFT JOIN products p ON pwa.product_id = p.id
WHERE p.id IS NULL
LIMIT 5;

-- 6. Product warehouse assignments without valid warehouse
SELECT 
    'Assignment without valid warehouse' AS issue_type,
    COUNT(*) AS record_count,
    GROUP_CONCAT(CONCAT('Assignment ID:', pwa.id, ' - Warehouse ID:', pwa.warehouse_id) SEPARATOR '; ') AS sample_records
FROM product_warehouse_assignments pwa
LEFT JOIN warehouses w ON pwa.warehouse_id = w.id
WHERE w.id IS NULL
LIMIT 5;

-- 7. Bin assignments without valid assignment
SELECT 
    'Bin assignment without valid assignment' AS issue_type,
    COUNT(*) AS record_count,
    GROUP_CONCAT(CONCAT('Bin Assignment ID:', ba.id, ' - Assignment ID:', ba.product_warehouse_assignment_id) SEPARATOR '; ') AS sample_records
FROM bin_assignments ba
LEFT JOIN product_warehouse_assignments pwa ON ba.product_warehouse_assignment_id = pwa.id
WHERE pwa.id IS NULL
LIMIT 5;

-- 8. Bin assignments without valid bin
SELECT 
    'Bin assignment without valid bin' AS issue_type,
    COUNT(*) AS record_count,
    GROUP_CONCAT(CONCAT('Bin Assignment ID:', ba.id, ' - Bin ID:', ba.bin_id) SEPARATOR '; ') AS sample_records
FROM bin_assignments ba
LEFT JOIN warehouse_bins wb ON ba.bin_id = wb.id
WHERE wb.id IS NULL
LIMIT 5;

-- 9. Product components without valid parent product
SELECT 
    'Component without valid parent product' AS issue_type,
    COUNT(*) AS record_count,
    GROUP_CONCAT(CONCAT('Component ID:', pc.id, ' - Parent ID:', pc.parent_product_id) SEPARATOR '; ') AS sample_records
FROM product_components pc
LEFT JOIN products p ON pc.parent_product_id = p.id
WHERE p.id IS NULL
LIMIT 5;

-- 10. Product components without valid component product
SELECT 
    'Component without valid component product' AS issue_type,
    COUNT(*) AS record_count,
    GROUP_CONCAT(CONCAT('Component ID:', pc.id, ' - Component ID:', pc.component_product_id) SEPARATOR '; ') AS sample_records
FROM product_components pc
LEFT JOIN products p ON pc.component_product_id = p.id
WHERE p.id IS NULL
LIMIT 5;

-- ============================================
-- Data Consistency Checks
-- ============================================

-- 1. Negative quantities in warehouse_inventory
SELECT 
    'Negative quantity in warehouse_inventory' AS issue_type,
    COUNT(*) AS record_count,
    GROUP_CONCAT(CONCAT('WI ID:', id, ' - Qty:', quantity) SEPARATOR '; ') AS sample_records
FROM warehouse_inventory
WHERE quantity < 0
LIMIT 5;

-- 2. Negative reserved quantities
SELECT 
    'Negative reserved quantity' AS issue_type,
    COUNT(*) AS record_count,
    GROUP_CONCAT(CONCAT('WI ID:', id, ' - Reserved:', reserved_quantity) SEPARATOR '; ') AS sample_records
FROM warehouse_inventory
WHERE reserved_quantity < 0
LIMIT 5;

-- 3. Reserved quantity exceeding total quantity
SELECT 
    'Reserved quantity exceeds total quantity' AS issue_type,
    COUNT(*) AS record_count,
    GROUP_CONCAT(CONCAT('WI ID:', id, ' - Total:', quantity, ' - Reserved:', reserved_quantity) SEPARATOR '; ') AS sample_records
FROM warehouse_inventory
WHERE reserved_quantity > quantity
LIMIT 5;

-- 4. Inconsistent available quantity calculation
SELECT 
    'Inconsistent available quantity calculation' AS issue_type,
    COUNT(*) AS record_count,
    GROUP_CONCAT(CONCAT('WI ID:', id, ' - Qty:', quantity, ' - Reserved:', reserved_quantity, ' - Available:', available_quantity) SEPARATOR '; ') AS sample_records
FROM warehouse_inventory
WHERE available_quantity != (quantity - reserved_quantity)
LIMIT 5;

-- 5. Duplicate product-warehouse assignments (same product, same warehouse, same effective date)
SELECT 
    'Duplicate product-warehouse assignments' AS issue_type,
    COUNT(*) AS record_count,
    GROUP_CONCAT(CONCAT('ID:', id, ' - Product:', product_id, ' - Warehouse:', warehouse_id, ' - Date:', effective_date) SEPARATOR '; ') AS sample_records
FROM product_warehouse_assignments
WHERE (product_id, warehouse_id, effective_date) IN (
    SELECT product_id, warehouse_id, effective_date
    FROM product_warehouse_assignments
    GROUP BY product_id, warehouse_id, effective_date
    HAVING COUNT(*) > 1
)
LIMIT 5;

-- 6. Expired active assignments
SELECT 
    'Expired assignments still marked as active' AS issue_type,
    COUNT(*) AS record_count,
    GROUP_CONCAT(CONCAT('ID:', id, ' - Expiry:', expiry_date) SEPARATOR '; ') AS sample_records
FROM product_warehouse_assignments
WHERE is_active = TRUE
AND expiry_date IS NOT NULL
AND expiry_date < CURDATE()
LIMIT 5;

-- 7. Future effective dates marked as active
SELECT 
    'Future effective dates marked as active' AS issue_type,
    COUNT(*) AS record_count,
    GROUP_CONCAT(CONCAT('ID:', id, ' - Effective:', effective_date) SEPARATOR '; ') AS sample_records
FROM product_warehouse_assignments
WHERE is_active = TRUE
AND effective_date > CURDATE()
LIMIT 5;

-- 8. Self-referencing product components (product is component of itself)
SELECT 
    'Self-referencing product components' AS issue_type,
    COUNT(*) AS record_count,
    GROUP_CONCAT(CONCAT('ID:', id, ' - Product:', parent_product_id) SEPARATOR '; ') AS sample_records
FROM product_components
WHERE parent_product_id = component_product_id
LIMIT 5;

-- 9. Circular component references (A -> B -> A)
SELECT 
    'Circular component references' AS issue_type,
    COUNT(*) AS record_count,
    GROUP_CONCAT(CONCAT('Parent:', pc1.parent_product_id, ' -> Component:', pc1.component_product_id, ' -> Back to:', pc2.component_product_id) SEPARATOR '; ') AS sample_records
FROM product_components pc1
JOIN product_components pc2 ON pc1.component_product_id = pc2.parent_product_id
WHERE pc1.parent_product_id = pc2.component_product_id
LIMIT 5;

-- ============================================
-- Stock Movement Integrity
-- ============================================

-- 1. Stock movements without valid warehouse
SELECT 
    'Stock movement without valid warehouse' AS issue_type,
    COUNT(*) AS record_count,
    GROUP_CONCAT(CONCAT('Movement ID:', id, ' - Warehouse ID:', warehouse_id) SEPARATOR '; ') AS sample_records
FROM stock_movements
LEFT JOIN warehouses w ON stock_movements.warehouse_id = w.id
WHERE w.id IS NULL
LIMIT 5;

-- 2. Stock movements without valid product
SELECT 
    'Stock movement without valid product' AS issue_type,
    COUNT(*) AS record_count,
    GROUP_CONCAT(CONCAT('Movement ID:', id, ' - Product ID:', product_id) SEPARATOR '; ') AS sample_records
FROM stock_movements
LEFT JOIN products p ON stock_movements.product_id = p.id
WHERE p.id IS NULL
LIMIT 5;

-- 3. Stock movements with zero quantity
SELECT 
    'Stock movements with zero quantity' AS issue_type,
    COUNT(*) AS record_count,
    GROUP_CONCAT(CONCAT('Movement ID:', id, ' - Type:', movement_type) SEPARATOR '; ') AS sample_records
FROM stock_movements
WHERE quantity = 0
LIMIT 5;

-- ============================================
-- Cleanup Commands (if issues found)
-- ============================================

-- Delete orphaned warehouse inventory records
-- DELETE FROM warehouse_inventory
-- WHERE warehouse_id NOT IN (SELECT id FROM warehouses)
-- OR product_id NOT IN (SELECT id FROM products);

-- Delete orphaned product warehouse assignments
-- DELETE FROM product_warehouse_assignments
-- WHERE product_id NOT IN (SELECT id FROM products)
-- OR warehouse_id NOT IN (SELECT id FROM warehouses);

-- Delete orphaned bin assignments
-- DELETE FROM bin_assignments
-- WHERE product_warehouse_assignment_id NOT IN (SELECT id FROM product_warehouse_assignments)
-- OR bin_id NOT IN (SELECT id FROM warehouse_bins);

-- Delete orphaned product components
-- DELETE FROM product_components
-- WHERE parent_product_id NOT IN (SELECT id FROM products)
-- OR component_product_id NOT IN (SELECT id FROM products);

-- Fix inconsistent available quantities
-- UPDATE warehouse_inventory
-- SET available_quantity = quantity - reserved_quantity
-- WHERE available_quantity != (quantity - reserved_quantity);

-- Deactivate expired assignments
-- UPDATE product_warehouse_assignments
-- SET is_active = FALSE
-- WHERE is_active = TRUE
-- AND expiry_date IS NOT NULL
-- AND expiry_date < CURDATE();
