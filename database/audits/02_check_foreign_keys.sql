-- ============================================
-- Database Audit: Foreign Keys Verification
-- ============================================
-- Purpose: Verify all foreign keys, their rules, and integrity
-- Run this script to audit foreign key constraints
-- ============================================

SET @db_name = DATABASE();

-- Check all foreign keys in the database
SELECT 
    CONSTRAINT_NAME,
    TABLE_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME,
    UPDATE_RULE,
    DELETE_RULE,
    CASE 
        WHEN DELETE_RULE = 'RESTRICT' OR DELETE_RULE = 'NO ACTION' THEN '✓ SAFE'
        WHEN DELETE_RULE = 'CASCADE' THEN '⚠ WARNING: Auto-delete'
        WHEN DELETE_RULE = 'SET NULL' THEN '⚠ WARNING: Sets NULL'
        ELSE DELETE_RULE
    END AS delete_safety,
    CASE 
        WHEN UPDATE_RULE = 'CASCADE' THEN '✓ OPTIMAL'
        ELSE UPDATE_RULE
    END AS update_optimal
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
WHERE REFERENCED_TABLE_SCHEMA = @db_name 
AND REFERENCED_TABLE_NAME IS NOT NULL
ORDER BY TABLE_NAME, CONSTRAINT_NAME;

-- Tables without any foreign keys (potential data integrity risk)
SELECT 
    t.TABLE_NAME,
    t.TABLE_ROWS,
    '⚠ NO FOREIGN KEYS' AS status
FROM INFORMATION_SCHEMA.TABLES t
WHERE t.TABLE_SCHEMA = @db_name
AND t.TABLE_TYPE = 'BASE TABLE'
AND t.TABLE_NAME NOT IN (
    SELECT DISTINCT TABLE_NAME 
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE REFERENCED_TABLE_SCHEMA = @db_name 
    AND REFERENCED_TABLE_NAME IS NOT NULL
)
ORDER BY t.TABLE_NAME;

-- Foreign keys that should use RESTRICT but don't (for inventory tables)
SELECT 
    CONSTRAINT_NAME,
    TABLE_NAME,
    DELETE_RULE,
    '⚠ SHOULD BE RESTRICT' AS recommendation
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
WHERE REFERENCED_TABLE_SCHEMA = @db_name 
AND REFERENCED_TABLE_NAME IS NOT NULL
AND TABLE_NAME IN ('warehouse_inventory', 'stock_movements', 'product_warehouse_assignments', 'bin_assignments')
AND DELETE_RULE != 'RESTRICT'
AND DELETE_RULE != 'NO ACTION';

-- ============================================
-- Critical Inventory Tables Foreign Key Audit
-- ============================================

-- Check warehouse_inventory foreign keys
SELECT 
    'warehouse_inventory' AS table_name,
    COUNT(*) AS fk_count,
    GROUP_CONCAT(CONSTRAINT_NAME) AS foreign_keys
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
WHERE TABLE_SCHEMA = @db_name 
AND TABLE_NAME = 'warehouse_inventory'
AND REFERENCED_TABLE_NAME IS NOT NULL;

-- Check product_warehouse_assignments foreign keys
SELECT 
    'product_warehouse_assignments' AS table_name,
    COUNT(*) AS fk_count,
    GROUP_CONCAT(CONSTRAINT_NAME) AS foreign_keys
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
WHERE TABLE_SCHEMA = @db_name 
AND TABLE_NAME = 'product_warehouse_assignments'
AND REFERENCED_TABLE_NAME IS NOT NULL;

-- ============================================
-- Orphaned Records Check
-- ============================================

-- Products without warehouse assignments
SELECT 
    COUNT(*) AS orphaned_products_count
FROM products p
LEFT JOIN product_warehouse_assignments pwa ON p.id = pwa.product_id
WHERE pwa.id IS NULL;

-- Warehouse inventory without valid warehouse
SELECT 
    COUNT(*) AS orphaned_inventory_count
FROM warehouse_inventory wi
LEFT JOIN warehouses w ON wi.warehouse_id = w.id
WHERE w.id IS NULL;

-- Warehouse inventory without valid product
SELECT 
    COUNT(*) AS orphaned_inventory_product_count
FROM warehouse_inventory wi
LEFT JOIN products p ON wi.product_id = p.id
WHERE p.id IS NULL;

-- ============================================
-- Recommended Foreign Key Changes
-- ============================================

-- Tables that should have ON DELETE RESTRICT instead of CASCADE
-- This prevents accidental deletion of products/warehouses with inventory
SELECT 
    CONCAT('ALTER TABLE ', TABLE_NAME, ' DROP FOREIGN KEY ', CONSTRAINT_NAME, ';') AS drop_command,
    CONCAT('ALTER TABLE ', TABLE_NAME, ' ADD CONSTRAINT ', CONSTRAINT_NAME, '_new FOREIGN KEY (', COLUMN_NAME, ') REFERENCES ', REFERENCED_TABLE_NAME, '(', REFERENCED_COLUMN_NAME, ') ON DELETE RESTRICT ON UPDATE CASCADE;') AS add_command
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
WHERE REFERENCED_TABLE_SCHEMA = @db_name 
AND REFERENCED_TABLE_NAME IS NOT NULL
AND TABLE_NAME IN ('warehouse_inventory', 'product_warehouse_assignments', 'bin_assignments')
AND DELETE_RULE = 'CASCADE';
