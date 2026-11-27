-- Removes the Kuchinarai dataset (sql/demo_kuchinarai_seed.sql).
-- Quests, rewards, check-ins and shop points cascade with the places;
-- community posts about them and the demo shop account are removed too.
START TRANSACTION;
DELETE FROM posts WHERE place_id IN (SELECT id FROM places WHERE name IN ('หมู่บ้านวัฒนธรรมผู้ไทยโคกโก่ง', 'วัดศรีภูขันธุ์ (วัดบ้านโคกโก่ง)', 'ภูผาผึ้ง', 'วนอุทยานภูผาวัว', 'น้ำตกตาดสูง และน้ำตกตาดยาว', 'ถ้ำฝ่ามือแดง', 'ศูนย์หัตถกรรมผู้ไทยหนองห้าง', 'วัดสามัคคีธรรมบัวขาว', 'วัดสิมนาโก', 'อ่างเลิงซิว', 'ภูน้ำจั้น (แหล่งฟอสซิลปลาโบราณ)', 'Yield specialty coffee', 'ฮักcoffee (ฮักคอฟฟี้)', 'เฮียนโฮม สลัดคาเฟ่ (Hianhome Salad Cafe)', 'หลังสวนคาเฟ่ by สวนก้องจิราภาส', 'แสงชัย ก๋วยเตี๋ยวเป็ดตุ๋น อาหารตามสั่ง', 'เตียกิมเซง', 'ก๊วยเตี๋ยวป๊อกๆ ป้ารัตนา บัวขาว', 'ร้านทดลองระบบ ล่าพิกัด (ไม่ใช่ร้านจริง)') AND district = 'กุฉินารายณ์');
DELETE FROM places WHERE name IN ('หมู่บ้านวัฒนธรรมผู้ไทยโคกโก่ง', 'วัดศรีภูขันธุ์ (วัดบ้านโคกโก่ง)', 'ภูผาผึ้ง', 'วนอุทยานภูผาวัว', 'น้ำตกตาดสูง และน้ำตกตาดยาว', 'ถ้ำฝ่ามือแดง', 'ศูนย์หัตถกรรมผู้ไทยหนองห้าง', 'วัดสามัคคีธรรมบัวขาว', 'วัดสิมนาโก', 'อ่างเลิงซิว', 'ภูน้ำจั้น (แหล่งฟอสซิลปลาโบราณ)', 'Yield specialty coffee', 'ฮักcoffee (ฮักคอฟฟี้)', 'เฮียนโฮม สลัดคาเฟ่ (Hianhome Salad Cafe)', 'หลังสวนคาเฟ่ by สวนก้องจิราภาส', 'แสงชัย ก๋วยเตี๋ยวเป็ดตุ๋น อาหารตามสั่ง', 'เตียกิมเซง', 'ก๊วยเตี๋ยวป๊อกๆ ป้ารัตนา บัวขาว', 'ร้านทดลองระบบ ล่าพิกัด (ไม่ใช่ร้านจริง)') AND district = 'กุฉินารายณ์';
DELETE FROM users WHERE email LIKE 'demo.shop%@lapikad.test';
COMMIT;
