-- ============================================================
-- Script xóa các cột sale khỏi bảng product
-- Chạy trong phpMyAdmin hoặc MySQL command line
-- ============================================================
-- ⚠️ BACKUP DATABASE TRƯỚC KHI CHẠY! ⚠️

-- Kiểm tra xem cột có tồn tại không (chạy trước)
-- SELECT COLUMN_NAME 
-- FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_SCHEMA = DATABASE()
--   AND TABLE_NAME = 'product' 
--   AND COLUMN_NAME IN ('price_sale', 'discount_percent');

-- Xóa cột price_sale (nếu có lỗi "Unknown column", bỏ qua vì cột đã bị xóa)
ALTER TABLE `product` DROP COLUMN `price_sale`;

-- Xóa cột discount_percent (nếu có lỗi "Unknown column", bỏ qua vì cột đã bị xóa)
ALTER TABLE `product` DROP COLUMN `discount_percent`;

-- ============================================================
-- Kiểm tra sau khi chạy (chạy để xác nhận)
-- ============================================================
-- SELECT COLUMN_NAME 
-- FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_SCHEMA = DATABASE()
--   AND TABLE_NAME = 'product' 
--   AND COLUMN_NAME IN ('price_sale', 'discount_percent');
-- Kết quả phải là: Empty set (không có cột nào)

