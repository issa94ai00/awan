-- ============================================
-- Database Audit: Engine and Collation Check
-- ============================================
-- Purpose: Verify all tables use InnoDB and utf8mb4
-- Run this script to audit your current database state
-- ============================================

-- Replace 'your_database_name' with your actual database name
SET @db_name = DATABASE();

-- Check engine and collation for all tables
SELECT 
    TABLE_NAME,
    ENGINE,
    TABLE_COLLATION,
    CASE 
        WHEN ENGINE = 'InnoDB' THEN '✓ OK'
        ELSE '⚠ WARNING: Should be InnoDB'
    END AS engine_status,
    CASE 
        WHEN TABLE_COLLATION LIKE 'utf8mb4%' THEN '✓ OK'
        ELSE '⚠ WARNING: Should be utf8mb4'
    END AS collation_status
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = @db_name
ORDER BY TABLE_NAME;

-- Count tables by engine
SELECT 
    ENGINE,
    COUNT(*) AS table_count,
    GROUP_CONCAT(TABLE_NAME ORDER BY TABLE_NAME SEPARATOR ', ') AS tables
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = @db_name
GROUP BY ENGINE;

-- Tables that need engine conversion (MyISAM to InnoDB)
SELECT 
    TABLE_NAME,
    ENGINE,
    TABLE_COLLATION
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = @db_name 
AND ENGINE != 'InnoDB';

-- ============================================
-- Conversion Commands (if needed)
-- ============================================
-- If any tables show as MyISAM, run these commands:
-- ALTER TABLE table_name ENGINE=InnoDB;
-- ALTER TABLE table_name CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
