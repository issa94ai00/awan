# Product-Warehouse Assignment ERD

## Entity Relationship Diagram

```
┌─────────────────────────┐
│       Products         │
├─────────────────────────┤
│ - id (PK)              │
│ - name                 │
│ - name_ar              │
│ - name_en              │
│ - description          │
│ - price                │
│ - sku                  │
│ - barcode              │
│ - cost_price           │
│ - weight               │
│ - dimensions           │
│ - category_id          │
│ - is_active            │
│ - ... (commercial data)│
└───────────┬─────────────┘
            │
            │ 1
            │
            │ *
┌───────────▼─────────────┐
│   Product_Variants      │
├─────────────────────────┤
│ - id (PK)              │
│ - product_id (FK)       │
│ - sku                  │
│ - barcode               │
│ - price                │
│ - cost_price            │
│ - color/size/material   │
│ - stock_quantity        │
└───────────┬─────────────┘
            │
            │ 1
            │
            │ *
┌───────────▼─────────────────────────────────────┐
│  Product_Warehouse_Assignment (NEW)             │
├─────────────────────────────────────────────────┤
│ - id (PK)                                      │
│ - product_id (FK)                              │
│ - product_variant_id (FK, nullable)            │
│ - warehouse_id (FK)                            │
│ - effective_date (Future-dated support)       │
│ - expiry_date (nullable)                       │
│ - is_active                                    │
│ - replenishment_method (ENUM)                  │
│ - planning_method (ENUM)                       │
│ - min_stock_level                              │
│ - max_stock_level                              │
│ - safety_stock                                 │
│ - supplier_id (FK)                             │
│ - lead_time_days                              │
│ - primary_bin_id (FK)                          │
│ - putaway_strategy (ENUM)                     │
│ - auto_reorder_enabled                         │
│ - notes                                        │
└───────────┬─────────────────────────────────────┘
            │
            │ 1
            │
            │ *
┌───────────▼─────────────┐       ┌──────────────────┐
│   Warehouse_Inventory   │       │   Warehouse_Bins │
├─────────────────────────┤       ├──────────────────┤
│ - id (PK)              │◄──────│ - id (PK)        │
│ - warehouse_id (FK)     │       │ - warehouse_id   │
│ - product_id (FK)       │       │ - bin_code       │
│ - product_variant_id    │       │ - zone           │
│ - bin_id (FK)           │       │ - rack           │
│ - quantity              │       │ - shelf          │
│ - reserved_quantity     │       │ - type           │
│ - available_quantity    │       │ - capacity       │
│ - damaged_quantity      │       │ - is_primary     │
│ - quarantined_quantity  │       └──────────────────┘
│ - batch_number          │
│ - expiry_date           │
│ - cost_basis (FIFO/FEFO)│
│ - last_counted_at       │
└───────────┬─────────────┘
            │
            │ 1
            │
            │ *
┌───────────▼─────────────┐
│  Bin_Assignment (NEW)   │
├─────────────────────────┤
│ - id (PK)              │
│ - assignment_id (FK)    │
│ - bin_id (FK)          │
│ - is_primary (boolean)  │
│ - priority_order       │
│ - capacity_percentage   │
└─────────────────────────┘

┌─────────────────────────┐
│      Warehouses         │
├─────────────────────────┤
│ - id (PK)              │
│ - name                 │
│ - code                 │
│ - address              │
│ - city                 │
│ - country              │
│ - location_type        │
│ - latitude             │
│ - longitude            │
│ - capacity             │
│ - is_primary           │
│ - manager_id           │
└───────────┬─────────────┘
            │
            │ 1
            │
            │ *
┌───────────▼─────────────┐
│      Suppliers         │
├─────────────────────────┤
│ - id (PK)              │
│ - name                 │
│ - address              │
│ - city                 │
│ - country              │
│ - latitude             │
│ - longitude            │
│ - lead_time_default    │
└─────────────────────────┘

┌─────────────────────────┐
│   Inventory_Transfers   │
├─────────────────────────┤
│ - id (PK)              │
│ - from_warehouse_id    │
│ - to_warehouse_id      │
│ - status               │
│ - transfer_number      │
│ - requested_at         │
│ - shipped_at           │
│ - received_at          │
└─────────────────────────┘

┌─────────────────────────┐
│  Product_Components    │ (NEW - for Kitted Products)
├─────────────────────────┤
│ - id (PK)              │
│ - parent_product_id     │
│ - component_product_id  │
│ - quantity_required    │
│ - is_optional          │
└─────────────────────────┘
```

## Key Relationships

1. **Product → Product_Warehouse_Assignment (1:N)**
   - Each product can be assigned to multiple warehouses
   - Supports future-dated assignments via `effective_date`

2. **Product_Warehouse_Assignment → Warehouse_Inventory (1:N)**
   - Each assignment can have multiple inventory records (different bins/batches)
   - Planning data stored at assignment level, inventory at bin level

3. **Product_Warehouse_Assignment → Warehouse_Bins (1:N via Bin_Assignment)**
   - Primary bin and secondary bins defined per assignment
   - Putaway logic uses this relationship

4. **Warehouse → Product_Warehouse_Assignment (1:N)**
   - Each warehouse has many product assignments
   - Geographic data for proximity calculations

5. **Supplier → Product_Warehouse_Assignment (1:N)**
   - Lead time calculated based on supplier-to-warehouse distance
   - Different suppliers can serve different warehouses for same product

## Enums

### Replenishment Method
- PURCHASE (شراء)
- MANUFACTURE (تصنيع)
- INTERNAL_DISTRIBUTION (توزيع داخلي)
- WAREHOUSE_TRANSFER (نقل مخزني)

### Planning Method
- ROP (نقطة إعادة طلب)
- MRP (تخطيط متطلبات المواد)

### Putaway Strategy
- FIFO (First In First Out)
- FEFO (First Expired First Out)
- SIMILARITY (منتجات مشابهة)
- WEIGHT_BASED (بناءً على الوزن)
- VOLUME_BASED (بناءً على الحجم)

## Indexes

- Unique index on (product_id, warehouse_id, effective_date) for assignments
- Index on effective_date for future-dated queries
- Index on (warehouse_id, is_active) for active assignments
- Composite index on (warehouse_id, product_id) for inventory lookups
- Geographic index on (latitude, longitude) for proximity calculations
