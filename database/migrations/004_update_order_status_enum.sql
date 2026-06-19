ALTER TABLE orders MODIFY COLUMN status ENUM('pending','paid','cancelled','shipped','delivered','preparing','ready') NOT NULL;
