-- ============================================
-- Migration: Database Structure Optimization
-- ============================================
-- Purpose: Optimize database structure for ERP inventory system
-- This migration should be run after all other migrations
-- ============================================

-- ============================================
-- Step 1: Ensure InnoDB Engine for All Tables
-- ============================================

-- Convert any MyISAM tables to InnoDB
-- (Run these commands only if audit shows MyISAM tables)
-- ALTER TABLE products ENGINE=InnoDB;
-- ALTER TABLE warehouses ENGINE=InnoDB;
-- ALTER TABLE warehouse_inventory ENGINE=InnoDB;
-- ALTER TABLE product_warehouse_assignments ENGINE=InnoDB;
-- ALTER TABLE bin_assignments ENGINE=InnoDB;
-- ALTER TABLE product_components ENGINE=InnoDB;

-- ============================================
-- Step 2: Fix Foreign Key Delete Rules for Inventory Tables
-- ============================================

-- IMPORTANT: Change CASCADE to RESTRICT for inventory tables to prevent accidental deletion
-- This protects data integrity by preventing deletion of products/warehouses with inventory

-- Drop existing foreign keys (if they use CASCADE)
-- Note: Run these only if the audit shows CASCADE on inventory tables

-- For warehouse_inventory table
-- ALTER TABLE warehouse_inventory DROP FOREIGN KEY warehouse_inventory_warehouse_id_foreign;
-- ALTER TABLE warehouse_inventory DROP FOREIGN KEY warehouse_inventory_product_id_foreign;
-- ALTER TABLE warehouse_inventory DROP FOREIGN KEY warehouse_inventory_product_variant_id_foreign;

-- Re-add with RESTRICT
-- ALTER TABLE warehouse_inventory 
-- ADD CONSTRAINT warehouse_inventory_warehouse_id_foreign 
-- FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE RESTRICT ON UPDATE CASCADE;

-- ALTER TABLE warehouse_inventory 
-- ADD CONSTRAINT warehouse_inventory_product_id_foreign 
-- FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT ON UPDATE CASCADE;

-- ALTER TABLE warehouse_inventory 
-- ADD CONSTRAINT warehouse_inventory_product_variant_id_foreign 
-- FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE RESTRICT ON UPDATE CASCADE;

-- For product_warehouse_assignments table
-- ALTER TABLE product_warehouse_assignments DROP FOREIGN KEY product_warehouse_assignments_product_id_foreign;
-- ALTER TABLE product_warehouse_assignments DROP FOREIGN KEY product_warehouse_assignments_warehouse_id_foreign;

-- ALTER TABLE product_warehouse_assignments 
-- ADD CONSTRAINT product_warehouse_assignments_product_id_foreign 
-- FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT ON UPDATE CASCADE;

-- ALTER TABLE product_warehouse_assignments 
-- ADD CONSTRAINT product_warehouse_assignments_warehouse_id_foreign 
-- FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE RESTRICT ON UPDATE CASCADE;

-- ============================================
-- Step 3: Add Performance Indexes
-- ============================================

-- Indexes for warehouse_inventory table
CREATE INDEX IF NOT EXISTS idx_warehouse_inventory_product 
ON warehouse_inventory(product_id);

CREATE INDEX IF NOT EXISTS idx_warehouse_inventory_warehouse 
ON warehouse_inventory(warehouse_id);

CREATE INDEX IF NOT EXISTS idx_warehouse_inventory_bin 
ON warehouse_inventory(bin_id);

CREATE INDEX IF NOT EXISTS idx_warehouse_inventory_composite 
ON warehouse_inventory(warehouse_id, product_id, product_variant_id);

-- Indexes for product_warehouse_assignments table
CREATE INDEX IF NOT EXISTS idx_pwa_product 
ON product_warehouse_assignments(product_id);

CREATE INDEX IF NOT EXISTS idx_pwa_warehouse 
ON product_warehouse_assignments(warehouse_id);

CREATE INDEX IF NOT EXISTS idx_pwa_active 
ON product_warehouse_assignments(is_active, effective_date);

CREATE INDEX IF NOT EXISTS idx_pwa_replenishment 
ON product_warehouse_assignments(replenishment_method, planning_method);

-- Indexes for bin_assignments table
CREATE INDEX IF NOT EXISTS idx_bin_assignment_pwa 
ON bin_assignments(product_warehouse_assignment_id);

CREATE INDEX IF NOT EXISTS idx_bin_assignment_bin 
ON bin_assignments(bin_id);

CREATE INDEX IF NOT EXISTS idx_bin_assignment_primary 
ON bin_assignments(is_primary, priority_order);

-- Indexes for product_components table
CREATE INDEX IF NOT EXISTS idx_component_parent 
ON product_components(parent_product_id);

CREATE INDEX IF NOT EXISTS idx_component_child 
ON product_components(component_product_id);

CREATE INDEX IF NOT EXISTS idx_component_required 
ON product_components(is_optional);

-- Indexes for stock_movements (if exists)
CREATE INDEX IF NOT EXISTS idx_stock_movement_date 
ON stock_movements(created_at);

CREATE INDEX IF NOT EXISTS idx_stock_movement_product 
ON stock_movements(product_id);

CREATE INDEX IF NOT EXISTS idx_stock_movement_warehouse 
ON stock_movements(warehouse_id);

CREATE INDEX IF NOT EXISTS idx_stock_movement_type 
ON stock_movements(movement_type);

CREATE INDEX IF NOT EXISTS idx_stock_movement_composite 
ON stock_movements(warehouse_id, product_id, created_at);

-- ============================================
-- Step 4: Add Geographic Index for Warehouses
-- ============================================

-- For proximity calculations in picking service
CREATE INDEX IF NOT EXISTS idx_warehouse_location 
ON warehouses(latitude, longitude);

-- ============================================
-- Step 5: Optimize Character Sets
-- ============================================

-- Ensure all tables use utf8mb4 for full Unicode support
-- ALTER TABLE products CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- ALTER TABLE warehouses CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- ALTER TABLE warehouse_inventory CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- ALTER TABLE product_warehouse_assignments CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ============================================
-- Step 6: Add Check Constraints via Triggers
-- ============================================

-- Note: MySQL doesn't support CHECK constraints effectively
-- Triggers will be added in a separate migration file

-- ============================================
-- Verification Queries
-- ============================================

-- Run these after migration to verify changes

-- Check all indexes on warehouse_inventory
-- SHOW INDEX FROM warehouse_inventory;

-- Check foreign key rules
-- SELECT * FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
-- WHERE TABLE_SCHEMA = DATABASE() 
-- AND TABLE_NAME = 'warehouse_inventory';

-- Check table engines
-- SELECT TABLE_NAME, ENGINE FROM INFORMATION_SCHEMA.TABLES 
-- WHERE TABLE_SCHEMA = DATABASE();
