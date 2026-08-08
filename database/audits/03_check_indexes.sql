-- ============================================
-- Database Audit: Index Analysis and Optimization
-- ============================================
-- Purpose: Analyze current indexes and recommend optimizations
-- Run this script to audit index performance
-- ============================================

SET @db_name = DATABASE();

-- ============================================
-- Current Index Analysis
-- ============================================

-- Show all indexes on key inventory tables
SHOW INDEX FROM warehouse_inventory;
SHOW INDEX FROM product_warehouse_assignments;
SHOW INDEX FROM bin_assignments;
SHOW INDEX FROM product_components;
SHOW INDEX FROM stock_movements;
SHOW INDEX FROM warehouses;
SHOW INDEX FROM products;

-- ============================================
-- Duplicate Index Detection
-- ============================================

-- Find potentially duplicate indexes
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columns,
    CARDINALITY,
    INDEX_TYPE
FROM INFORMATION_SCHEMA.STATISTICS
WHERE TABLE_SCHEMA = @db_name
AND TABLE_NAME IN ('warehouse_inventory', 'product_warehouse_assignments', 'stock_movements')
GROUP BY TABLE_NAME, INDEX_NAME, CARDINALITY, INDEX_TYPE
ORDER BY TABLE_NAME, INDEX_NAME;

-- ============================================
-- Unused Index Detection
-- ============================================

-- Note: This requires MySQL Performance Schema to be enabled
-- Run this to identify indexes that haven't been used

SELECT 
    object_schema AS table_schema,
    object_name AS table_name,
    index_name,
    count_star AS usage_count,
    count_read AS read_count,
    count_write AS write_count
FROM performance_schema.table_io_waits_summary_by_index_usage
WHERE object_schema = @db_name
AND index_name IS NOT NULL
AND index_name != 'PRIMARY'
ORDER BY count_star ASC;

-- ============================================
-- Index Cardinality Check
-- ============================================

-- Low cardinality indexes may not be effective
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    COLUMN_NAME,
    CARDINALITY,
    TABLE_ROWS,
    ROUND((CARDINALITY / TABLE_ROWS) * 100, 2) AS selectivity_percentage,
    CASE 
        WHEN CARDINALITY < 10 THEN '⚠ LOW CARDINALITY'
        WHEN (CARDINALITY / TABLE_ROWS) < 0.01 THEN '⚠ LOW SELECTIVITY'
        ELSE '✓ GOOD'
    END AS cardinality_status
FROM INFORMATION_SCHEMA.STATISTICS s
JOIN INFORMATION_SCHEMA.TABLES t ON s.TABLE_SCHEMA = t.TABLE_SCHEMA AND s.TABLE_NAME = t.TABLE_NAME
WHERE s.TABLE_SCHEMA = @db_name
AND s.TABLE_NAME IN ('warehouse_inventory', 'product_warehouse_assignments', 'stock_movements')
AND s.INDEX_NAME != 'PRIMARY'
ORDER BY (CARDINALITY / TABLE_ROWS) ASC;

-- ============================================
-- Missing Index Recommendations
-- ============================================

-- Based on common query patterns for ERP systems

-- 1. Product lookup by warehouse
SELECT 
    'CREATE INDEX idx_warehouse_inventory_warehouse_product ON warehouse_inventory(warehouse_id, product_id);' AS recommendation,
    'Speed up product queries by warehouse' AS purpose
WHERE NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'warehouse_inventory'
    AND INDEX_NAME = 'idx_warehouse_inventory_warehouse_product'
);

-- 2. Stock movement date range queries
SELECT 
    'CREATE INDEX idx_stock_movements_date_type ON stock_movements(created_at, movement_type);' AS recommendation,
    'Speed up stock movement reports by date' AS purpose
WHERE NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'stock_movements'
    AND INDEX_NAME = 'idx_stock_movements_date_type'
);

-- 3. Active assignments lookup
SELECT 
    'CREATE INDEX idx_pwa_active_date ON product_warehouse_assignments(is_active, effective_date);' AS recommendation,
    'Speed up active assignment queries' AS purpose
WHERE NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'product_warehouse_assignments'
    AND INDEX_NAME = 'idx_pwa_active_date'
);

-- 4. Geographic proximity queries
SELECT 
    'CREATE INDEX idx_warehouse_geo ON warehouses(latitude, longitude);' AS recommendation,
    'Speed up proximity calculations for picking' AS purpose
WHERE NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'warehouses'
    AND INDEX_NAME = 'idx_warehouse_geo'
);

-- 5. Bin assignment priority
SELECT 
    'CREATE INDEX idx_bin_assignment_priority ON bin_assignments(is_primary, priority_order);' AS recommendation,
    'Speed up putaway bin selection' AS purpose
WHERE NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'bin_assignments'
    AND INDEX_NAME = 'idx_bin_assignment_priority'
);

-- ============================================
-- Index Size Analysis
-- ============================================

-- Check index sizes to identify bloated indexes
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    ROUND(STAT_VALUE * @@innodb_page_size / 1024 / 1024, 2) AS size_mb,
    ROUND(STAT_VALUE * @@innodb_page_size / 1024 / 1024 / 1024, 2) AS size_gb
FROM mysql.innodb_index_stats
WHERE database_name = @db_name
AND stat_name = 'size'
AND table_name IN ('warehouse_inventory', 'product_warehouse_assignments', 'stock_movements')
ORDER BY STAT_VALUE DESC;

-- ============================================
-- Fragmentation Check
-- ============================================

-- Check for table fragmentation
SELECT 
    TABLE_NAME,
    ROUND(DATA_LENGTH / 1024 / 1024, 2) AS data_mb,
    ROUND(INDEX_LENGTH / 1024 / 1024, 2) AS index_mb,
    ROUND(DATA_FREE / 1024 / 1024, 2) AS free_mb,
    ROUND((DATA_FREE / (DATA_LENGTH + INDEX_LENGTH)) * 100, 2) AS fragmentation_percentage,
    CASE 
        WHEN (DATA_FREE / (DATA_LENGTH + INDEX_LENGTH)) > 0.1 THEN '⚠ HIGH FRAGMENTATION'
        ELSE '✓ OK'
    END AS fragmentation_status
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = @db_name
AND TABLE_NAME IN ('warehouse_inventory', 'product_warehouse_assignments', 'stock_movements')
ORDER BY fragmentation_percentage DESC;

-- ============================================
-- Recommended Optimization Commands
-- ============================================

-- Analyze tables to update statistics
SELECT CONCAT('ANALYZE TABLE ', TABLE_NAME, ';') AS analyze_command
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = @db_name
AND TABLE_NAME IN ('warehouse_inventory', 'product_warehouse_assignments', 'stock_movements');

-- Optimize fragmented tables
SELECT CONCAT('OPTIMIZE TABLE ', TABLE_NAME, ';') AS optimize_command
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = @db_name
AND TABLE_NAME IN ('warehouse_inventory', 'product_warehouse_assignments', 'stock_movements')
AND (DATA_FREE / (DATA_LENGTH + INDEX_LENGTH)) > 0.1;
