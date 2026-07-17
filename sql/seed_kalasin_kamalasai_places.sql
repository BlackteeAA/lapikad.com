-- Seed data: real tourist attractions in Kalasin province, focused on Kamalasai
-- district, with quests for each. Written against the live (drifted) schema —
-- places.category/image_url/lat/lng/district/province and quests.title/quest_type
-- (see sql/add_shop_role.sql for why init.sql doesn't have these columns).
--
-- Coordinates: only Phra That Yaku's lat/lng are verified against a named source
-- (Fine Arts Dept. archaeology database). The rest are best-effort estimates from
-- district/village-level knowledge and are flagged below — verify and correct them
-- in admin_places.php (which has lat/lng fields) before relying on in-app navigation.
--
-- Run this on the target database (e.g. via phpMyAdmin or `mysql < seed_....sql`).
-- Each place+quest block is independent; LAST_INSERT_ID() chains the quests to the
-- place inserted immediately before it, so keep each block's statements together.

-- ============================================================
-- 1) พระธาตุยาคู — Kamalasai district (verified coordinates)
-- ============================================================
INSERT INTO places (name, description, location_text, category, image_url, lat, lng, district, province, is_active)
VALUES (
  'พระธาตุยาคู',
  'เจดีย์โบราณสมัยทวารวดี (พุทธศตวรรษที่ 13-15) ขนาดใหญ่และสมบูรณ์ที่สุดที่พบในเมืองโบราณฟ้าแดดสงยาง ต่อมาในสมัยอยุธยาและรัตนโกสินทร์ได้มีการก่อเจดีย์ทรงแปดเหลี่ยมซ้อนทับฐานเดิม เป็นโบราณสถานสำคัญของจังหวัดกาฬสินธุ์',
  'บ้านเสมา ต.หนองแปน อ.กมลาไสย จ.กาฬสินธุ์',
  'วัด/ศาสนา',
  '',
  16.3191762,
  103.5180786,
  'กมลาไสย',
  'กาฬสินธุ์',
  1
);
SET @place_yaku = LAST_INSERT_ID();

INSERT INTO quests (place_id, title, description, quest_type, reward_points, target_code) VALUES
(@place_yaku, 'เช็คอินหน้าพระธาตุยาคู', 'เดินทางไปยังพระธาตุยาคูแล้วกดเช็คอินยืนยันว่าคุณมาถึงจริง', 'checkin', 10, 'LAPIKAD-YAKU-CHECKIN'),
(@place_yaku, 'ตอบคำถามเมืองฟ้าแดดสงยาง', 'อ่านประวัติเมืองโบราณฟ้าแดดสงยางแล้วตอบคำถามให้ถูกต้อง', 'quiz', 15, 'LAPIKAD-YAKU-QUIZ'),
(@place_yaku, 'สแกน QR ภารกิจพระธาตุยาคู', 'ค้นหา QR Code บริเวณจุดแลนด์มาร์กของพระธาตุยาคู', 'qr', 20, 'LAPIKAD-YAKU-QR');


-- ============================================================
-- 2) วัดโพธิ์ชัยเสมาราม (วัดบ้านก้อม) — Kamalasai district
-- NOTE: lat/lng are an estimate (same village as Phra That Yaku, ~200m away) —
-- please confirm the exact pin before publishing.
-- ============================================================
INSERT INTO places (name, description, location_text, category, image_url, lat, lng, district, province, is_active)
VALUES (
  'วัดโพธิ์ชัยเสมาราม (วัดบ้านก้อม)',
  'วัดเก่าแก่เชื่อกันว่าเป็นวัดประจำเมืองโบราณฟ้าแดดสงยาง เป็นที่รวบรวมใบเสมาหินทรายสมัยทวารวดีจำนวนมาก บางใบสลักลวดลายเรื่องชาดกและพุทธประวัติ ตั้งอยู่ตรงข้ามทางเข้าเมืองฟ้าแดดสงยาง',
  'บ้านเสมา ต.หนองแปน อ.กมลาไสย จ.กาฬสินธุ์',
  'วัด/ศาสนา',
  '',
  16.3183,
  103.5192,
  'กมลาไสย',
  'กาฬสินธุ์',
  1
);
SET @place_semaram = LAST_INSERT_ID();

INSERT INTO quests (place_id, title, description, quest_type, reward_points, target_code) VALUES
(@place_semaram, 'ชมใบเสมาทวารวดี', 'สำรวจใบเสมาหินทรายโบราณรอบวัด แล้วเช็คอินยืนยันภารกิจ', 'checkin', 10, 'LAPIKAD-SEMARAM-CHECKIN'),
(@place_semaram, 'สแกน QR วัดโพธิ์ชัยเสมาราม', 'ค้นหา QR Code บริเวณจุดแลนด์มาร์กของวัด', 'qr', 20, 'LAPIKAD-SEMARAM-QR');


-- ============================================================
-- 3) พิพิธภัณฑ์สิรินธร (ภูกุ้มข้าว) — Sahatsakhan district
-- NOTE: lat/lng are an estimate for Phu Kum Khao / Sahatsakhan — please confirm
-- the exact pin before publishing.
-- ============================================================
INSERT INTO places (name, description, location_text, category, image_url, lat, lng, district, province, is_active)
VALUES (
  'พิพิธภัณฑ์สิรินธร (ภูกุ้มข้าว)',
  'พิพิธภัณฑ์ไดโนเสาร์แห่งแรกของไทยและใหญ่ที่สุดในเอเชียตะวันออกเฉียงใต้ จัดแสดงซากดึกดำบรรพ์ไดโนเสาร์ที่ขุดพบ ณ ภูกุ้มข้าว รวมถึงสยามโมไทรันนัส อิสานเอนซิส ที่ค้นพบเมื่อปี 2537',
  'ต.โนนบุรี อ.สหัสขันธ์ จ.กาฬสินธุ์',
  'พิพิธภัณฑ์',
  '',
  16.5410,
  103.5390,
  'สหัสขันธ์',
  'กาฬสินธุ์',
  1
);
SET @place_sirindhorn = LAST_INSERT_ID();

INSERT INTO quests (place_id, title, description, quest_type, reward_points, target_code) VALUES
(@place_sirindhorn, 'สำรวจนิทรรศการไดโนเสาร์', 'เดินชมนิทรรศการซากดึกดำบรรพ์ในพิพิธภัณฑ์สิรินธร แล้วเช็คอิน', 'checkin', 15, 'LAPIKAD-SIRINDHORN-CHECKIN'),
(@place_sirindhorn, 'ตอบคำถามสยามโมไทรันนัส', 'อ่านข้อมูลไดโนเสาร์สายพันธุ์ใหม่ของโลกที่พบในภูกุ้มข้าว แล้วตอบคำถาม', 'quiz', 20, 'LAPIKAD-SIRINDHORN-QUIZ'),
(@place_sirindhorn, 'สแกน QR พิพิธภัณฑ์สิรินธร', 'ค้นหา QR Code บริเวณจุดกิจกรรมของพิพิธภัณฑ์', 'qr', 20, 'LAPIKAD-SIRINDHORN-QR');


-- ============================================================
-- 4) สะพานเทพสุดา (เขื่อนลำปาว) — Sahatsakhan / Nong Kung Si districts
-- NOTE: lat/lng are a rough estimate for the Lam Pao Dam crossing — please
-- confirm the exact pin before publishing (a web search returned a clearly
-- invalid longitude for this one, so it was NOT used).
-- ============================================================
INSERT INTO places (name, description, location_text, category, image_url, lat, lng, district, province, is_active)
VALUES (
  'สะพานเทพสุดา',
  'สะพานคอนกรีตเสริมเหล็กข้ามเขื่อนลำปาว ความยาวกว่า 2 กิโลเมตร ได้ชื่อว่าเป็นสะพานข้ามน้ำจืดที่ยาวที่สุดในประเทศไทย เป็นจุดชมวิวและถ่ายรูปยอดนิยม',
  'ต.โนนบุรี อ.สหัสขันธ์ - ต.หนองบัว อ.หนองกุงศรี จ.กาฬสินธุ์',
  'ธรรมชาติ',
  '',
  16.6990,
  103.4400,
  'สหัสขันธ์',
  'กาฬสินธุ์',
  1
);
SET @place_thepsuda = LAST_INSERT_ID();

INSERT INTO quests (place_id, title, description, quest_type, reward_points, target_code) VALUES
(@place_thepsuda, 'เช็คอินชมวิวเขื่อนลำปาว', 'เดินทางไปยังสะพานเทพสุดาแล้วเช็คอินชมวิวเขื่อนลำปาว', 'checkin', 10, 'LAPIKAD-THEPSUDA-CHECKIN'),
(@place_thepsuda, 'สแกน QR สะพานเทพสุดา', 'ค้นหา QR Code บริเวณจุดชมวิวของสะพาน', 'qr', 20, 'LAPIKAD-THEPSUDA-QR');


-- ============================================================
-- 5) ตลาดโรงสี กาฬสินธุ์ — Mueang Kalasin district
-- NOTE: lat/lng are a rough estimate for Kalasin town center — please confirm
-- the exact pin before publishing.
-- ============================================================
INSERT INTO places (name, description, location_text, category, image_url, lat, lng, district, province, is_active)
VALUES (
  'ตลาดโรงสี กาฬสินธุ์',
  'ตลาดชุมชนที่ดัดแปลงจากโรงสีข้าวเก่าอายุกว่า 50 ปี ริมลำน้ำปาว จำหน่ายอาหารพื้นถิ่นและของฝากจากชุมชน',
  'เขตเทศบาลเมืองกาฬสินธุ์ จ.กาฬสินธุ์',
  'ชุมชน',
  '',
  16.4322,
  103.5060,
  'เมืองกาฬสินธุ์',
  'กาฬสินธุ์',
  1
);
SET @place_rongsi = LAST_INSERT_ID();

INSERT INTO quests (place_id, title, description, quest_type, reward_points, target_code) VALUES
(@place_rongsi, 'เดินชมตลาดโรงสีริมน้ำปาว', 'เดินสำรวจตลาดโรงสีเก่าและวิวริมลำน้ำปาว แล้วเช็คอินยืนยันภารกิจ', 'checkin', 10, 'LAPIKAD-RONGSI-CHECKIN'),
(@place_rongsi, 'สแกน QR ตลาดโรงสี', 'ค้นหา QR Code บริเวณจุดกิจกรรมของตลาด', 'qr', 20, 'LAPIKAD-RONGSI-QR');
