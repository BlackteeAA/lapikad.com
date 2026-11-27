-- DATASET: อ.กุฉินารายณ์ จ.กาฬสินธุ์ — สถานที่จริง (ท่องเที่ยว/วัด/ธรรมชาติ/ร้านอาหาร) พร้อมภารกิจ
-- สำหรับทดลองใช้งานเว็บก่อนเปิดจริง
--
-- แหล่งข้อมูล: lovethailand.org, thai-tour.com, esan108.com, OpenStreetMap, Wongnai (ค้นเมื่อ 27 พ.ย. 2569)
-- * พิกัด = จากแหล่งข้อมูลตามที่ระบุในแต่ละรายการ ไม่ได้เดา
-- * lat/lng = NULL คือยังหาพิกัดไม่ได้ → หน้าเว็บจะไม่ล็อกระยะ 100 ม. จนกว่าจะปักหมุดใน admin_places.php
-- * ร้านอาหารจริงลงเป็น "สถานที่" ไม่มีเจ้าของร้านและไม่มีรางวัล (ให้เจ้าของร้านสมัครเองผ่าน shop_apply.php)
-- * ร้านทดลองระบบ 1 ร้าน บัญชี demo.shop1@lapikad.test รหัสผ่าน Demo@1234
-- * target_code ภารกิจขึ้นต้นด้วย DEMO-KCN-
-- * ลบทั้งชุด: sql/demo_kuchinarai_remove.sql
-- Requires: add_shop_checkins.sql (places.checkin_token)

START TRANSACTION;

-- 1) หมู่บ้านวัฒนธรรมผู้ไทยโคกโก่ง [พิกัด: lovethailand.org (ลิงก์นำทาง Google Maps)]
INSERT INTO places (name, description, location_text, category, image_url, lat, lng, district, province, is_active, owner_user_id, checkin_token)
VALUES ('หมู่บ้านวัฒนธรรมผู้ไทยโคกโก่ง', 'ชุมชนชาวผู้ไทยเชิงเขา มีศูนย์วัฒนธรรมชุมชนและโฮมสเตย์ นักท่องเที่ยวร่วมพิธีบายศรีสู่ขวัญ กินพาแลง ชมการสาธิตทอผ้า และเดินป่าไปน้ำตกใกล้เคียงได้ (กิจกรรมชุมชนต้องจองล่วงหน้า) ศูนย์วัฒนธรรมเปิดทุกวัน 08:30–17:00', 'บ้านโคกโก่ง ม.5 ต.กุดหว้า อ.กุฉินารายณ์ จ.กาฬสินธุ์', 'ชุมชน', '', 16.532567, 104.189388, 'กุฉินารายณ์', 'กาฬสินธุ์', 1, NULL, NULL);
SET @p_kokkong = LAST_INSERT_ID();
INSERT INTO quests (place_id, title, description, quest_type, reward_points, target_code) VALUES
(@p_kokkong, 'เช็คอินศูนย์วัฒนธรรมผู้ไทย', 'ถ่ายรูปที่ศูนย์วัฒนธรรมชุมชนผู้ไทยโคกโก่ง', 'checkin', 15, 'DEMO-KCN-KOKKONG-1'),
(@p_kokkong, 'ชมการสาธิตทอผ้า', 'ถ่ายรูปผ้าทอหรือการสาธิตทอผ้าของชาวผู้ไทย', 'checkin', 15, 'DEMO-KCN-KOKKONG-2');

-- 2) วัดศรีภูขันธุ์ (วัดบ้านโคกโก่ง) [พิกัด: OpenStreetMap]
INSERT INTO places (name, description, location_text, category, image_url, lat, lng, district, province, is_active, owner_user_id, checkin_token)
VALUES ('วัดศรีภูขันธุ์ (วัดบ้านโคกโก่ง)', 'วัดประจำหมู่บ้านโคกโก่ง ตั้งขึ้นราว พ.ศ. 2457 เป็นศูนย์กลางพิธีกรรมทางพุทธศาสนาและงานบุญของชุมชนผู้ไทย', 'บ้านโคกโก่ง ต.กุดหว้า อ.กุฉินารายณ์ จ.กาฬสินธุ์', 'วัด/ศาสนา', '', 16.532746, 104.190018, 'กุฉินารายณ์', 'กาฬสินธุ์', 1, NULL, NULL);
SET @p_sriphukhan = LAST_INSERT_ID();
INSERT INTO quests (place_id, title, description, quest_type, reward_points, target_code) VALUES
(@p_sriphukhan, 'สักการะวัดบ้านโคกโก่ง', 'ถ่ายรูปหน้าวัดศรีภูขันธุ์', 'checkin', 10, 'DEMO-KCN-SRIPHUKHAN-1');

-- 3) ภูผาผึ้ง [พิกัด: lovethailand.org (ลิงก์นำทาง Google Maps)]
INSERT INTO places (name, description, location_text, category, image_url, lat, lng, district, province, is_active, owner_user_id, checkin_token)
VALUES ('ภูผาผึ้ง', 'หน้าผาหินทรายสูงราว 400 เมตร ในเขตรักษาพันธุ์สัตว์ป่าภูสีฐาน มีตำนานผึ้งมาทำรังเต็มหน้าผา มีเส้นทางศึกษาธรรมชาติและธรณีวิทยาราว 2.2 กม. ผ่านถ้ำและลานหิน เปิด 06:00–16:00 ค่าเข้าชมคนไทย ผู้ใหญ่ 30 บาท เด็ก 20 บาท', 'ต.หนองห้าง อ.กุฉินารายณ์ จ.กาฬสินธุ์', 'ธรรมชาติ', '', 16.549025, 104.126738, 'กุฉินารายณ์', 'กาฬสินธุ์', 1, NULL, NULL);
SET @p_phaphueng = LAST_INSERT_ID();
INSERT INTO quests (place_id, title, description, quest_type, reward_points, target_code) VALUES
(@p_phaphueng, 'เช็คอินจุดเริ่มเส้นทาง', 'ถ่ายรูปกับป้ายทางเข้าเส้นทางศึกษาธรรมชาติภูผาผึ้ง', 'checkin', 15, 'DEMO-KCN-PHAPHUENG-1');

-- 4) วนอุทยานภูผาวัว [ข้อมูล: thai-tour.com · ยังไม่มีพิกัด]
INSERT INTO places (name, description, location_text, category, image_url, lat, lng, district, province, is_active, owner_user_id, checkin_token)
VALUES ('วนอุทยานภูผาวัว', 'ผืนป่าราว 4,000 ไร่ในป่าสงวนแห่งชาติดงด่านแย้ ครอบคลุมภูผางัว ภูมะตูม และภูกอง มีน้ำตกและป่าอุดมสมบูรณ์ เป็นที่แรกในกาฬสินธุ์ที่พบฟอสซิลไดโนเสาร์ (พ.ศ. 2531) อยู่ติดเขตรักษาพันธุ์สัตว์ป่าภูสีฐาน', 'บ้านนาไคร้ / บ้านโคกโก่ง ต.กุดหว้า อ.กุฉินารายณ์ จ.กาฬสินธุ์', 'ธรรมชาติ', '', NULL, NULL, 'กุฉินารายณ์', 'กาฬสินธุ์', 1, NULL, NULL);
SET @p_phawua = LAST_INSERT_ID();
INSERT INTO quests (place_id, title, description, quest_type, reward_points, target_code) VALUES
(@p_phawua, 'เช็คอินวนอุทยานภูผาวัว', 'ถ่ายรูปกับป้ายวนอุทยานภูผาวัว', 'checkin', 15, 'DEMO-KCN-PHAWUA-1');

-- 5) น้ำตกตาดสูง และน้ำตกตาดยาว [ข้อมูล: thai-tour.com · ยังไม่มีพิกัด]
INSERT INTO places (name, description, location_text, category, image_url, lat, lng, district, province, is_active, owner_user_id, checkin_token)
VALUES ('น้ำตกตาดสูง และน้ำตกตาดยาว', 'น้ำตกในวนอุทยานภูผาวัว ห่างจากภูผางัวราว 4 กม. ตาดสูงเป็นธารหินที่น้ำไหลลดหลั่นเป็นชั้น เดินต่ออีกราว 100 เมตรถึงตาดยาว ลานหินลาดเอียงที่ชาวบ้านเรียกว่าน้ำตกสไลเดอร์ สวยที่สุดช่วงฤดูฝน', 'วนอุทยานภูผาวัว ต.กุดหว้า อ.กุฉินารายณ์ จ.กาฬสินธุ์', 'ธรรมชาติ', '', NULL, NULL, 'กุฉินารายณ์', 'กาฬสินธุ์', 1, NULL, NULL);
SET @p_tadsung = LAST_INSERT_ID();
INSERT INTO quests (place_id, title, description, quest_type, reward_points, target_code) VALUES
(@p_tadsung, 'ถ่ายรูปน้ำตกตาดสูง', 'ถ่ายรูปธารน้ำตกตาดสูงที่ไหลลดหลั่นเป็นชั้น', 'checkin', 15, 'DEMO-KCN-TADSUNG-1'),
(@p_tadsung, 'ถ่ายรูปน้ำตกตาดยาว', 'เดินต่อไปน้ำตกตาดยาวแล้วถ่ายรูปลานหินสไลเดอร์', 'checkin', 15, 'DEMO-KCN-TADSUNG-2');

-- 6) ถ้ำฝ่ามือแดง [ข้อมูล: thai-tour.com, esan108.com · ยังไม่มีพิกัด]
INSERT INTO places (name, description, location_text, category, image_url, lat, lng, district, province, is_active, owner_user_id, checkin_token)
VALUES ('ถ้ำฝ่ามือแดง', 'แหล่งโบราณคดีก่อนประวัติศาสตร์ในหลืบหินทราย มีภาพเขียนสีรูปฝ่ามือสีแดงที่ทำโดยทาบมือกับผนังหินแล้วพ่นสี สำรวจพบโดยกรมศิลปากร อยู่ในย่านภูผาผึ้ง', 'ย่านภูผาผึ้ง อ.กุฉินารายณ์ จ.กาฬสินธุ์', 'แหล่งท่องเที่ยว', '', NULL, NULL, 'กุฉินารายณ์', 'กาฬสินธุ์', 1, NULL, NULL);
SET @p_famuedaeng = LAST_INSERT_ID();
INSERT INTO quests (place_id, title, description, quest_type, reward_points, target_code) VALUES
(@p_famuedaeng, 'ตามหาภาพฝ่ามือแดง', 'ถ่ายรูปภาพเขียนสีรูปฝ่ามือ (ห้ามสัมผัสผนังถ้ำ)', 'checkin', 20, 'DEMO-KCN-FAMUEDAENG-1');

-- 7) ศูนย์หัตถกรรมผู้ไทยหนองห้าง [ข้อมูล: esan108.com · ยังไม่มีพิกัด]
INSERT INTO places (name, description, location_text, category, image_url, lat, lng, district, province, is_active, owner_user_id, checkin_token)
VALUES ('ศูนย์หัตถกรรมผู้ไทยหนองห้าง', 'กลุ่มชาวบ้านหนองห้างทอผ้าฝ้าย ผ้าไหม และจักสานไม้ไผ่ลายผ้าขิด เช่น กระติ๊บ กระเป๋า และภาชนะต่าง ๆ ห่างจากตัวอำเภอราว 10 กม. ไปตามทางหลวง 2042', 'บ้านหนองห้าง ต.หนองห้าง อ.กุฉินารายณ์ จ.กาฬสินธุ์', 'ชุมชน', '', NULL, NULL, 'กุฉินารายณ์', 'กาฬสินธุ์', 1, NULL, NULL);
SET @p_nonghang = LAST_INSERT_ID();
INSERT INTO quests (place_id, title, description, quest_type, reward_points, target_code) VALUES
(@p_nonghang, 'ชมงานจักสานลายขิด', 'ถ่ายรูปงานจักสานหรือผ้าทอของกลุ่มหัตถกรรม', 'checkin', 15, 'DEMO-KCN-NONGHANG-1');

-- 8) วัดสามัคคีธรรมบัวขาว [ข้อมูล: thai-tour.com · ยังไม่มีพิกัด]
INSERT INTO places (name, description, location_text, category, image_url, lat, lng, district, province, is_active, owner_user_id, checkin_token)
VALUES ('วัดสามัคคีธรรมบัวขาว', 'วัดเก่าแก่อายุนับร้อยปี ประดิษฐาน "พระเจ้าใหญ่" ที่ชาวกุฉินารายณ์เคารพบูชา และมีใบเสมาหินทรายโบราณปักล้อมรอบวิหาร', 'ต.บัวขาว อ.กุฉินารายณ์ จ.กาฬสินธุ์', 'วัด/ศาสนา', '', NULL, NULL, 'กุฉินารายณ์', 'กาฬสินธุ์', 1, NULL, NULL);
SET @p_samakkhi = LAST_INSERT_ID();
INSERT INTO quests (place_id, title, description, quest_type, reward_points, target_code) VALUES
(@p_samakkhi, 'สักการะพระเจ้าใหญ่', 'ถ่ายรูปหน้าวิหารพระเจ้าใหญ่', 'checkin', 10, 'DEMO-KCN-SAMAKKHI-1'),
(@p_samakkhi, 'ชมใบเสมาหินทราย', 'ถ่ายรูปใบเสมาหินทรายโบราณรอบวิหาร', 'checkin', 10, 'DEMO-KCN-SAMAKKHI-2');

-- 9) วัดสิมนาโก [ข้อมูล: thai-tour.com, esan108.com · ยังไม่มีพิกัด]
INSERT INTO places (name, description, location_text, category, image_url, lat, lng, district, province, is_active, owner_user_id, checkin_token)
VALUES ('วัดสิมนาโก', 'วัดเก่าแก่ของบ้านนาโก อายุกว่าร้อยปี มีสิมเก่าล้อมด้วยใบเสมา 28 ใบ ประดิษฐานพระแก้วบุษราคัม และมีพิพิธภัณฑ์ท้องถิ่นเก็บโบราณวัตถุของวัด', 'บ้านนาโก อ.กุฉินารายณ์ จ.กาฬสินธุ์', 'วัด/ศาสนา', '', NULL, NULL, 'กุฉินารายณ์', 'กาฬสินธุ์', 1, NULL, NULL);
SET @p_simnako = LAST_INSERT_ID();
INSERT INTO quests (place_id, title, description, quest_type, reward_points, target_code) VALUES
(@p_simnako, 'ชมสิมเก่า', 'ถ่ายรูปสิมเก่าและใบเสมารอบสิม', 'checkin', 10, 'DEMO-KCN-SIMNAKO-1'),
(@p_simnako, 'ชมพิพิธภัณฑ์ท้องถิ่น', 'ถ่ายรูปโบราณวัตถุที่ชอบในพิพิธภัณฑ์ของวัด', 'checkin', 10, 'DEMO-KCN-SIMNAKO-2');

-- 10) อ่างเลิงซิว [ข้อมูล: esan108.com, lemon8 · ยังไม่มีพิกัด]
INSERT INTO places (name, description, location_text, category, image_url, lat, lng, district, province, is_active, owner_user_id, checkin_token)
VALUES ('อ่างเลิงซิว', 'อ่างเก็บน้ำและสวนสาธารณะใกล้เมืองบัวขาว ชาวบ้านมาพักผ่อน ตกปลา และออกกำลังกาย', 'ต.บัวขาว อ.กุฉินารายณ์ จ.กาฬสินธุ์', 'ธรรมชาติ', '', NULL, NULL, 'กุฉินารายณ์', 'กาฬสินธุ์', 1, NULL, NULL);
SET @p_loengsio = LAST_INSERT_ID();
INSERT INTO quests (place_id, title, description, quest_type, reward_points, target_code) VALUES
(@p_loengsio, 'พักผ่อนริมอ่าง', 'ถ่ายรูปบรรยากาศริมอ่างเลิงซิว', 'checkin', 10, 'DEMO-KCN-LOENGSIO-1');

-- 11) ภูน้ำจั้น (แหล่งฟอสซิลปลาโบราณ) [ข้อมูล: esan108.com · ยังไม่มีพิกัด]
INSERT INTO places (name, description, location_text, category, image_url, lat, lng, district, province, is_active, owner_user_id, checkin_token)
VALUES ('ภูน้ำจั้น (แหล่งฟอสซิลปลาโบราณ)', 'แหล่งขุดค้นในวนอุทยานภูน้ำจั้น พบฟอสซิลปลาโบราณราว 200 ตัว มีการขุดค้นตั้งแต่ พ.ศ. 2544', 'วนอุทยานภูน้ำจั้น อ.กุฉินารายณ์ จ.กาฬสินธุ์', 'ธรรมชาติ', '', NULL, NULL, 'กุฉินารายณ์', 'กาฬสินธุ์', 1, NULL, NULL);
SET @p_namchan = LAST_INSERT_ID();
INSERT INTO quests (place_id, title, description, quest_type, reward_points, target_code) VALUES
(@p_namchan, 'ชมแหล่งฟอสซิลปลา', 'ถ่ายรูปจุดขุดค้นหรือป้ายแหล่งฟอสซิลปลาโบราณ', 'checkin', 20, 'DEMO-KCN-NAMCHAN-1');

-- 12) Yield specialty coffee [Wongnai]
INSERT INTO places (name, description, location_text, category, image_url, lat, lng, district, province, is_active, owner_user_id, checkin_token)
VALUES ('Yield specialty coffee', 'ร้านกาแฟสเปเชียลตี้ในตัวเมือง มีเมล็ดกาแฟหลายระดับการคั่ว แต่งร้านโทนสีดำ เปิดทุกวัน 08:30–17:30', '125/1 ต.บัวขาว อ.กุฉินารายณ์ จ.กาฬสินธุ์', 'ร้านค้า/ร้านอาหาร', '', 16.5380685, 104.0534636, 'กุฉินารายณ์', 'กาฬสินธุ์', 1, NULL, NULL);
SET @p_yield = LAST_INSERT_ID();
INSERT INTO quests (place_id, title, description, quest_type, reward_points, target_code) VALUES
(@p_yield, 'กาแฟแก้วโปรด', 'ถ่ายรูปกาแฟที่สั่งในร้าน', 'checkin', 10, 'DEMO-KCN-YIELD-1');

-- 13) ฮักcoffee (ฮักคอฟฟี้) [Wongnai]
INSERT INTO places (name, description, location_text, category, image_url, lat, lng, district, province, is_active, owner_user_id, checkin_token)
VALUES ('ฮักcoffee (ฮักคอฟฟี้)', 'คาเฟ่ติดแอร์ ที่นั่งเยอะ มีมุมถ่ายรูปหลายจุด เมนูแนะนำ บิงซู สเต็ก ขนมปังปิ้ง เปิดทุกวัน 08:30–21:00', 'ถ.สมเด็จ-มุกดาหาร ต.บัวขาว (ตรงข้ามโรงเรียนกุฉินารายณ์)', 'ร้านค้า/ร้านอาหาร', '', 16.539723, 104.045768, 'กุฉินารายณ์', 'กาฬสินธุ์', 1, NULL, NULL);
SET @p_hug = LAST_INSERT_ID();
INSERT INTO quests (place_id, title, description, quest_type, reward_points, target_code) VALUES
(@p_hug, 'บิงซูหรือปังปิ้ง', 'ถ่ายรูปของหวานที่สั่งในร้าน', 'checkin', 10, 'DEMO-KCN-HUG-1');

-- 14) เฮียนโฮม สลัดคาเฟ่ (Hianhome Salad Cafe) [Wongnai]
INSERT INTO places (name, description, location_text, category, image_url, lat, lng, district, province, is_active, owner_user_id, checkin_token)
VALUES ('เฮียนโฮม สลัดคาเฟ่ (Hianhome Salad Cafe)', 'คาเฟ่อาหารสุขภาพและอาหารนานาชาติ เมนูแนะนำ สเต็กหมู 119 บาท ข้าวปลาซาบะเทอริยากิ 69 บาท สลัดอกไก่พริกไทยดำ 69 บาท เปิดทุกวัน 07:00–18:00', '255 อ.กุฉินารายณ์ จ.กาฬสินธุ์', 'ร้านค้า/ร้านอาหาร', '', 16.548287, 104.035912, 'กุฉินารายณ์', 'กาฬสินธุ์', 1, NULL, NULL);
SET @p_hianhome = LAST_INSERT_ID();
INSERT INTO quests (place_id, title, description, quest_type, reward_points, target_code) VALUES
(@p_hianhome, 'มื้อสุขภาพ', 'ถ่ายรูปสลัดหรือเมนูที่สั่ง', 'checkin', 10, 'DEMO-KCN-HIANHOME-1');

-- 15) หลังสวนคาเฟ่ by สวนก้องจิราภาส [Wongnai]
INSERT INTO places (name, description, location_text, category, image_url, lat, lng, district, province, is_active, owner_user_id, checkin_token)
VALUES ('หลังสวนคาเฟ่ by สวนก้องจิราภาส', 'คาเฟ่ในสวน บรรยากาศโล่ง มีเค้กโฮมเมด อาหารตามสั่ง พายเรือและปั่นจักรยานในสวนได้ เปิดทุกวัน 09:00–18:30', '12 ต.บัวขาว อ.กุฉินารายณ์ จ.กาฬสินธุ์', 'ร้านค้า/ร้านอาหาร', '', 16.537526, 104.059772, 'กุฉินารายณ์', 'กาฬสินธุ์', 1, NULL, NULL);
SET @p_langsuan = LAST_INSERT_ID();
INSERT INTO quests (place_id, title, description, quest_type, reward_points, target_code) VALUES
(@p_langsuan, 'มุมสวนหลังร้าน', 'ถ่ายรูปบรรยากาศสวนหรือเค้กโฮมเมด', 'checkin', 10, 'DEMO-KCN-LANGSUAN-1');

-- 16) แสงชัย ก๋วยเตี๋ยวเป็ดตุ๋น อาหารตามสั่ง [Wongnai]
INSERT INTO places (name, description, location_text, category, image_url, lat, lng, district, province, is_active, owner_user_id, checkin_token)
VALUES ('แสงชัย ก๋วยเตี๋ยวเป็ดตุ๋น อาหารตามสั่ง', 'ร้านก๋วยเตี๋ยวเป็ดตุ๋นและอาหารตามสั่ง เมนูแนะนำ ก๋วยเตี๋ยวเป็ด กะเพราเป็ด เปิดทุกวัน 07:30–16:30', '121/1 ซอยข้างเซเว่นประมาณ 50 ม. อ.กุฉินารายณ์', 'ร้านค้า/ร้านอาหาร', '', 16.537249, 104.052389, 'กุฉินารายณ์', 'กาฬสินธุ์', 1, NULL, NULL);
SET @p_saengchai = LAST_INSERT_ID();
INSERT INTO quests (place_id, title, description, quest_type, reward_points, target_code) VALUES
(@p_saengchai, 'ก๋วยเตี๋ยวเป็ดตุ๋น', 'ถ่ายรูปก๋วยเตี๋ยวเป็ดหรือกะเพราเป็ด', 'checkin', 10, 'DEMO-KCN-SAENGCHAI-1');

-- 17) เตียกิมเซง [Wongnai]
INSERT INTO places (name, description, location_text, category, image_url, lat, lng, district, province, is_active, owner_user_id, checkin_token)
VALUES ('เตียกิมเซง', 'ร้านก๋วยเตี๋ยวและของอีสาน ขึ้นชื่อเรื่องหม่ำหมู หม่ำเนื้อ ไส้กรอก เปิดทุกวัน 08:00–16:30', 'ถ.สมเด็จ–มุกดาหาร ตรงข้าม 7-11 อ.กุฉินารายณ์', 'ร้านค้า/ร้านอาหาร', '', 16.537641, 104.052466, 'กุฉินารายณ์', 'กาฬสินธุ์', 1, NULL, NULL);
SET @p_tiakimseng = LAST_INSERT_ID();
INSERT INTO quests (place_id, title, description, quest_type, reward_points, target_code) VALUES
(@p_tiakimseng, 'ของฝากหม่ำ', 'ถ่ายรูปหม่ำหรือไส้กรอกของร้าน', 'checkin', 10, 'DEMO-KCN-TIAKIMSENG-1');

-- 18) ก๊วยเตี๋ยวป๊อกๆ ป้ารัตนา บัวขาว [Wongnai]
INSERT INTO places (name, description, location_text, category, image_url, lat, lng, district, province, is_active, owner_user_id, checkin_token)
VALUES ('ก๊วยเตี๋ยวป๊อกๆ ป้ารัตนา บัวขาว', 'ร้านก๋วยเตี๋ยวพื้นบ้านในบัวขาว ราคาไม่เกิน 100 บาท (ยังไม่มีข้อมูลเวลาเปิด-ปิด)', 'หน้าชัยรักษ์ บัวขาว อ.กุฉินารายณ์', 'ร้านค้า/ร้านอาหาร', '', 16.536825, 104.055479, 'กุฉินารายณ์', 'กาฬสินธุ์', 1, NULL, NULL);
SET @p_pokpok = LAST_INSERT_ID();
INSERT INTO quests (place_id, title, description, quest_type, reward_points, target_code) VALUES
(@p_pokpok, 'ก๋วยเตี๋ยวป๊อกๆ', 'ถ่ายรูปก๋วยเตี๋ยวชามที่สั่ง', 'checkin', 10, 'DEMO-KCN-POKPOK-1');

-- 19) ร้านทดลองระบบ ล่าพิกัด (ไม่ใช่ร้านจริง) [ร้านทดลอง]
INSERT INTO users (name, email, password_hash, role) VALUES ('ร้านทดลองระบบ (เดโม)', 'demo.shop1@lapikad.test', '$2y$10$Ok9uShkhXkwwwarWFbcQ1O6wNTjCoNo1VgBubZON7QmOEmszkNn0W', 'shop');
SET @u_demoshop = LAST_INSERT_ID();
INSERT INTO places (name, description, location_text, category, image_url, lat, lng, district, province, is_active, owner_user_id, checkin_token)
VALUES ('ร้านทดลองระบบ ล่าพิกัด (ไม่ใช่ร้านจริง)', 'ร้านสมมติสำหรับทดสอบระบบ QR เช็คอินโต๊ะ สะสมแต้ม แลกรางวัล และสแกนหักคะแนน ลบออกก่อนเปิดเว็บจริง', 'ข้อมูลทดสอบ', 'ร้านค้า/ร้านอาหาร', '', NULL, NULL, 'กุฉินารายณ์', 'กาฬสินธุ์', 1, @u_demoshop, UPPER(SUBSTRING(MD5(CONCAT(RAND(), 'demoshop')), 1, 20)));
SET @p_demoshop = LAST_INSERT_ID();
INSERT INTO quests (place_id, title, description, quest_type, reward_points, target_code) VALUES
(@p_demoshop, 'ภารกิจทดลอง: ถ่ายรูป', 'ถ่ายรูปอะไรก็ได้เพื่อทดสอบระบบ', 'checkin', 10, 'DEMO-KCN-DEMOSHOP-1'),
(@p_demoshop, 'ภารกิจทดลอง: สแกน QR', 'พิมพ์ QR ภารกิจจากหน้าแอดมินแล้วสแกน', 'qr', 10, 'DEMO-KCN-DEMOSHOP-2');
INSERT INTO rewards (name, description, cost_points, stock, image_url, place_id, school_id) VALUES
('รางวัลทดลอง 50 แต้ม', 'ใช้ทดสอบการแลก', 50, 0, '', @p_demoshop, NULL),
('รางวัลทดลอง 100 แต้ม', 'ใช้ทดสอบการแลก', 100, 0, '', @p_demoshop, NULL);

COMMIT;
