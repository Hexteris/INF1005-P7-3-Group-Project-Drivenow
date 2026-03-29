-- ================================================
-- DriveNow: Car image_url update
-- Run this after cloning if uploads/cars/ images
-- are already in the repo.
-- ================================================

USE car_rental;

UPDATE cars SET image_url = 'toyota-corolla.jpg'  WHERE car_id = 1;
UPDATE cars SET image_url = 'honda-jazz.jpg'       WHERE car_id = 2;
UPDATE cars SET image_url = 'mazda-cx30.jpg'       WHERE car_id = 3;
UPDATE cars SET image_url = 'toyota-camry.jpg'     WHERE car_id = 4;
UPDATE cars SET image_url = 'hyundai-tucson.jpg'   WHERE car_id = 5;
UPDATE cars SET image_url = 'kia-sorento.jpg'      WHERE car_id = 6;
UPDATE cars SET image_url = 'bmw-3-series.jpg'     WHERE car_id = 7;
UPDATE cars SET image_url = 'mercedes-c-class.jpg' WHERE car_id = 8;

-- Verify
SELECT car_id, make, model, image_url FROM cars ORDER BY car_id;