START TRANSACTION;

ALTER TABLE products
    ADD COLUMN product_type ENUM('service','digital_code','wallet','capacity') NULL AFTER price,
    ADD COLUMN stock_qty INT NULL AFTER product_type,
    ADD COLUMN reorder_point INT NULL AFTER stock_qty,
    ADD COLUMN supplier_name VARCHAR(160) NULL AFTER reorder_point,
    ADD COLUMN lead_time_days INT NULL AFTER supplier_name,
    ADD COLUMN cost_price DECIMAL(15,2) NULL AFTER lead_time_days,
    ADD COLUMN min_margin_percent DECIMAL(6,2) NULL AFTER cost_price,
    ADD COLUMN platform_fee_percent DECIMAL(6,2) NULL AFTER min_margin_percent,
    ADD COLUMN payment_fee_percent DECIMAL(6,2) NULL AFTER platform_fee_percent,
    ADD COLUMN ads_cost_per_order DECIMAL(15,2) NULL AFTER payment_fee_percent,
    ADD COLUMN delivery_cost DECIMAL(15,2) NULL AFTER ads_cost_per_order,
    ADD COLUMN capacity_limit INT NULL AFTER delivery_cost,
    ADD COLUMN capacity_used INT NULL AFTER capacity_limit;

COMMIT;
