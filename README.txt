ล่าพิกัด.com — PHP 8.2 + MySQL 8 + Docker
==========================================

เว็บล่าพิกัด: สแกน QR ตามสถานที่เพื่อรับคะแนน เช็คอินร้านค้าสะสมแต้ม
แลกรางวัล และเช็คชื่อกิจกรรมโรงเรียน


วิธีรัน (เครื่อง dev)
---------------------
1. เปิด Docker Desktop

2. สร้างไฟล์ .env จากตัวอย่าง แล้วใส่รหัสผ่านของตัวเอง

     cp .env.example .env

   .env ต้องมี DB_ROOT_PASS และ DB_PASS

3. ตั้งค่า mail กับ Claude — ข้ามได้ ไม่ต้องสร้างไฟล์อะไรเลย

   ถ้าไม่มี www/_mail_config.php  → ส่งอีเมลไม่ออก หน้าลืมรหัสผ่านยังใช้ได้
                                     เหตุผลไปอยู่ใน error log
   ถ้าไม่มี www/_claude_config.php → ใช้ mock mode อัตโนมัติ ไม่ยิง API จริง ไม่เสียเงิน

   จะตั้งค่าจริงก็ก๊อปจากตัวอย่าง

     cp www/_mail_config.example.php   www/_mail_config.php
     cp www/_claude_config.example.php www/_claude_config.php

   และไม่ต้องสร้าง www/_db_config.php สำหรับ Docker — docker-compose.yml
   ส่ง DB_HOST/DB_USER/DB_PASS/DB_NAME เข้าคอนเทนเนอร์ให้แล้ว

4. รัน

     docker compose up -d --build

5. รัน migration ที่เหลือตามลำดับใน sql/README.md
   (init.sql ถูกรันอัตโนมัติตอนสร้าง container ครั้งแรก ที่เหลือต้องรันเอง
    และบนฐานข้อมูลใหม่ต้องรัน add_legacy_drift_columns.sql ด้วย ไม่งั้นจะพัง)


เปิดใช้งาน
----------
เว็บ         http://localhost:8080
phpMyAdmin   http://localhost:8081   (server: db, user: root, pass: ค่า DB_ROOT_PASS ใน .env)


ขึ้น production (cPanel / shared hosting)
-----------------------------------------
ที่นั่นตั้ง environment variable ให้ PHP ไม่ได้ จึงต้องใช้ไฟล์ config

  1. cp www/_db_config.example.php www/_db_config.php แล้วใส่ค่าจริง
  2. แก้ APP_ALLOWED_HOSTS ใน www/_bootstrap.php ให้เป็นโดเมนจริง
     ลิงก์รีเซ็ตรหัสผ่านและ QR ทุกใบสร้างจากค่านี้ ไม่ใช่จาก Host header
     ถ้าใส่ผิด ลิงก์ที่ส่งทางอีเมลจะชี้ไปผิดที่
  3. รัน migration ใน sql/README.md ให้ครบ
  4. www/.htaccess บังคับ HTTPS และปิดการเข้าถึง _*.php, includes/, lib/ โดยตรง
     ต้องแน่ใจว่าโฮสต์เปิด AllowOverride ให้

ไฟล์ที่ห้าม commit (อยู่ใน .gitignore แล้ว)
  .env, www/_db_config.php, www/_mail_config.php, www/_claude_config.php


บัญชีตัวอย่าง (เฉพาะชุด demo — อย่าใช้บน production)
--------------------------------------------------
ผู้ใช้    demo@lapikad.local  / 123456
แอดมิน    admin@lapikad.local / 123456


ระบบในเว็บ
----------
แอดมิน        /admin.php — จัดการผู้ใช้ สถานที่ ภารกิจ โรงเรียน คำขอเปิดร้าน
              เพิ่ม/ลดคะแนน และรายงาน (location / retention พร้อม export PDF)
ร้านค้า       สมัครเป็นร้าน → แอดมินอนุมัติ → พิมพ์ QR ติดโต๊ะ ลูกค้าเช็คอินวันละครั้ง
              แลกรางวัลโดยร้านสแกน QR ของลูกค้าแล้วหักแต้ม
กิจกรรม       สภานักเรียน/ครูสร้างกิจกรรม เช็คชื่อได้ 2 แบบ
              - นักเรียนสแกน QR บนจอ (QR หมุนเปลี่ยนทุกช่วงเวลา)
              - เจ้าหน้าที่สแกน QR ส่วนตัวของนักเรียน
AI            น้องพิกัดแนะนำภารกิจถัดไป (quest_recommend.php)


QR Code
-------
ระบบรับเฉพาะ QR ของเว็บเรา เช่น

  LAPIKAD:TEMPLE-2026
  LAPIKAD:MUSEUM-2026

สแกน QR อื่นจะขึ้นหน้าแดง สแกนถูกจะขึ้นหน้าเขียวและเพิ่มคะแนนอัตโนมัติ

QR เช็คชื่อกิจกรรมเป็นแบบหมุนเปลี่ยน (HMAC ตามช่วงเวลา) ถ่ายรูปไปใช้ย้อนหลังไม่ได้


ทดสอบกล้องบนมือถือ
------------------
กล้องบนเบราว์เซอร์ต้องเป็น https เท่านั้น ตอน dev ใช้ ngrok

  ngrok http 8080

แล้วเพิ่มโดเมน ngrok เข้า APP_ALLOWED_HOSTS ใน www/_bootstrap.php ชั่วคราว
ไม่งั้น QR ที่สร้างจะชี้กลับไปที่ localhost


โครงสร้างโฟลเดอร์
-----------------
www/              document root
  _*.php          โมดูลร่วม (db, auth, events, school, shop_points, ratelimit, claude, mail)
                  ปิดการเข้าถึงตรงไว้ใน .htaccess
  admin_*.php     หน้าแอดมิน
  event_*.php     ระบบกิจกรรมโรงเรียน
  shop_*.php      ระบบร้านค้า
  api_*.php       endpoint สำหรับ like / comment / follow
  includes/       ส่วนประกอบหน้าเว็บที่ include ซ้ำ ๆ
  lib/            PHPMailer, tFPDF
  assets/, css/   รูป ฟอนต์ สไตล์
sql/              migration + ข้อมูลตัวอย่าง (ดู sql/README.md)
