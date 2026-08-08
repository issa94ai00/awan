-- ============================================
-- Migration: Stock Balance Protection Triggers
-- ============================================
-- Purpose: Add triggers to prevent negative stock balances
-- MySQL doesn't support CHECK constraints effectively, so we use triggers
-- ============================================

-- ============================================
-- Trigger 1: Prevent Negative Balance on Insert
-- ============================================

DELIMITER //

DROP TRIGGER IF EXISTS prevent_negative_stock_balance_insert//

CREATE TRIGGER prevent_negative_stock_balance_insert
BEFORE INSERT ON warehouse_inventory
FOR EACH ROW
BEGIN
    DECLARE current_available INT;
    DECLARE new_available INT;
    
    -- Get current available quantity if record exists
    SELECT available_quantity INTO current_available
    FROM warehouse_inventory
    WHERE warehouse_id = NEW.warehouse_id
    AND product_id = NEW.product_id
    AND (NEW.product_variant_id IS NULL OR product_variant_id = NEW.product_variant_id)
    LIMIT 1;
    
    -- Calculate new available quantity
    IF current_available IS NOT NULL THEN
        SET new_available = current_available + (NEW.quantity - NEW.reserved_quantity);
    ELSE
        SET new_available = NEW.quantity - NEW.reserved_quantity;
    END IF;
    
    -- Prevent negative available quantity
    IF new_available < 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'لا يمكن إنشاء رصيد سالب: الكمية المتاحة يجب أن تكون أكبر من أو تساوي الصفر';
    END IF;
END//

DELIMITER ;

-- ============================================
-- Trigger 2: Prevent Negative Balance on Update
-- ============================================

DELIMITER //

DROP TRIGGER IF EXISTS prevent_negative_stock_balance_update//

CREATE TRIGGER prevent_negative_stock_balance_update
BEFORE UPDATE ON warehouse_inventory
FOR EACH ROW
BEGIN
    DECLARE new_available INT;
    
    -- Calculate new available quantity
    SET new_available = NEW.quantity - NEW.reserved_quantity;
    
    -- Prevent negative available quantity
    IF new_available < 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'لا يمكن تحديث الرصيد إلى قيمة سالبة: الكمية المتاحة يجب أن تكون أكبر من أو تساوي الصفر';
    END IF;
    
    -- Prevent reserved quantity from exceeding total quantity
    IF NEW.reserved_quantity > NEW.quantity THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'الكمية المحجوزة لا يمكن أن تتجاوز الكمية الإجمالية';
    END IF;
END//

DELIMITER ;

-- ============================================
-- Trigger 3: Auto-calculate Available Quantity
-- ============================================

DELIMITER //

DROP TRIGGER IF EXISTS auto_calculate_available_quantity//

CREATE TRIGGER auto_calculate_available_quantity
BEFORE INSERT ON warehouse_inventory
FOR EACH ROW
BEGIN
    -- Auto-calculate available quantity if not provided
    IF NEW.available_quantity IS NULL THEN
        SET NEW.available_quantity = NEW.quantity - NEW.reserved_quantity;
    END IF;
END//

DELIMITER ;

DELIMITER //

DROP TRIGGER IF EXISTS auto_calculate_available_quantity_update//

CREATE TRIGGER auto_calculate_available_quantity_update
BEFORE UPDATE ON warehouse_inventory
FOR EACH ROW
BEGIN
    -- Auto-recalculate available quantity on update
    SET NEW.available_quantity = NEW.quantity - NEW.reserved_quantity;
END//

DELIMITER ;

-- ============================================
-- Trigger 4: Prevent Deletion of Inventory with Stock
-- ============================================

DELIMITER //

DROP TRIGGER IF EXISTS prevent_inventory_deletion_with_stock//

CREATE TRIGGER prevent_inventory_deletion_with_stock
BEFORE DELETE ON warehouse_inventory
FOR EACH ROW
BEGIN
    IF OLD.quantity > 0 OR OLD.reserved_quantity > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'لا يمكن حذف سجل المخزون الذي يحتوي على رصيد أو حجوزات';
    END IF;
END//

DELIMITER ;

-- ============================================
-- Trigger 5: Prevent Negative Stock Movements
-- ============================================

DELIMITER //

DROP TRIGGER IF EXISTS prevent_negative_stock_movement//

CREATE TRIGGER prevent_negative_stock_movement
BEFORE INSERT ON stock_movements
FOR EACH ROW
BEGIN
    DECLARE current_available INT;
    
    -- Only check for outbound movements (negative quantity)
    IF NEW.movement_type = 'out' AND NEW.quantity < 0 THEN
        -- Get current available quantity
        SELECT wi.available_quantity INTO current_available
        FROM warehouse_inventory wi
        WHERE wi.warehouse_id = NEW.warehouse_id
        AND wi.product_id = NEW.product_id
        AND (NEW.product_variant_id IS NULL OR wi.product_variant_id = NEW.product_variant_id)
        LIMIT 1;
        
        -- Check if enough stock available
        IF current_available IS NULL OR current_available < ABS(NEW.quantity) THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'الرصيد المتاح غير كافٍ لإتمام حركة الصرف';
        END IF;
    END IF;
END//

DELIMITER ;

-- ============================================
-- Trigger 6: Update Inventory on Stock Movement
-- ============================================

DELIMITER //

DROP TRIGGER IF EXISTS update_inventory_on_stock_movement//

CREATE TRIGGER update_inventory_on_stock_movement
AFTER INSERT ON stock_movements
FOR EACH ROW
BEGIN
    -- Update warehouse_inventory based on movement type
    IF NEW.movement_type = 'in' THEN
        -- Inbound: increase quantity
        UPDATE warehouse_inventory
        SET quantity = quantity + NEW.quantity,
            available_quantity = available_quantity + NEW.quantity
        WHERE warehouse_id = NEW.warehouse_id
        AND product_id = NEW.product_id
        AND (NEW.product_variant_id IS NULL OR product_variant_id = NEW.product_variant_id);
        
    ELSEIF NEW.movement_type = 'out' THEN
        -- Outbound: decrease quantity
        UPDATE warehouse_inventory
        SET quantity = quantity + NEW.quantity, -- quantity is negative for outbound
            available_quantity = available_quantity + NEW.quantity
        WHERE warehouse_id = NEW.warehouse_id
        AND product_id = NEW.product_id
        AND (NEW.product_variant_id IS NULL OR product_variant_id = NEW.product_variant_id);
        
    ELSEIF NEW.movement_type = 'reserve' THEN
        -- Reserve: increase reserved quantity
        UPDATE warehouse_inventory
        SET reserved_quantity = reserved_quantity + ABS(NEW.quantity),
            available_quantity = available_quantity - ABS(NEW.quantity)
        WHERE warehouse_id = NEW.warehouse_id
        AND product_id = NEW.product_id
        AND (NEW.product_variant_id IS NULL OR product_variant_id = NEW.product_variant_id);
        
    ELSEIF NEW.movement_type = 'release' THEN
        -- Release: decrease reserved quantity
        UPDATE warehouse_inventory
        SET reserved_quantity = reserved_quantity - ABS(NEW.quantity),
            available_quantity = available_quantity + ABS(NEW.quantity)
        WHERE warehouse_id = NEW.warehouse_id
        AND product_id = NEW.product_id
        AND (NEW.product_variant_id IS NULL OR product_variant_id = NEW.product_variant_id);
    END IF;
END//

DELIMITER ;

-- ============================================
-- Verification
-- ============================================

-- List all triggers
-- SHOW TRIGGERS LIKE 'warehouse_inventory';
-- SHOW TRIGGERS LIKE 'stock_movements';

-- Test trigger (should fail with error)
-- INSERT INTO warehouse_inventory (warehouse_id, product_id, quantity, reserved_quantity, available_quantity)
-- VALUES (1, 1, -10, 0, -10);
