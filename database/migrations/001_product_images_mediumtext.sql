-- Las imágenes se guardan como data URLs base64; VARCHAR(255) truncaba/fallaba el INSERT.
ALTER TABLE product_images MODIFY COLUMN image_url MEDIUMTEXT NOT NULL;
