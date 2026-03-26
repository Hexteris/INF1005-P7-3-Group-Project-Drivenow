-- ============================================================
-- DriveNow Migration: Add lat/lng to cars table
-- Run once: mysql -u root -p car_rental < migration_add_latlng.sql
-- ============================================================

ALTER TABLE cars
    ADD COLUMN lat DECIMAL(10,7) DEFAULT NULL AFTER location,
    ADD COLUMN lng DECIMAL(10,7) DEFAULT NULL AFTER lat;

-- Update seed cars with real Singapore pickup coordinates
UPDATE cars SET lat = 1.3533,  lng = 103.9440 WHERE plate_no = 'SBA1001A'; -- Tampines Hub
UPDATE cars SET lat = 1.4043,  lng = 103.9024 WHERE plate_no = 'SBB2002B'; -- Punggol Drive
UPDATE cars SET lat = 1.3510,  lng = 103.8481 WHERE plate_no = 'SBC3003C'; -- Bishan MRT
UPDATE cars SET lat = 1.3329,  lng = 103.7422 WHERE plate_no = 'SBD4004D'; -- Jurong East
UPDATE cars SET lat = 1.4370,  lng = 103.7867 WHERE plate_no = 'SBE5005E'; -- Woodlands
UPDATE cars SET lat = 1.3500,  lng = 103.8737 WHERE plate_no = 'SBF6006F'; -- Serangoon
UPDATE cars SET lat = 1.3048,  lng = 103.8318 WHERE plate_no = 'SBG7007G'; -- Orchard Road
UPDATE cars SET lat = 1.2817,  lng = 103.8636 WHERE plate_no = 'SBH8008H'; -- Marina Bay
