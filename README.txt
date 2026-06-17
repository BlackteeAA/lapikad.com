ล่าพิกัด.com PHP + MySQL + Docker

วิธีรัน
1. เปิด Docker Desktop
2. เปิด Terminal ในโฟลเดอร์นี้
3. รันคำสั่ง

docker compose up -d --build

เปิดใช้งาน
เว็บ: http://localhost:8080
phpMyAdmin: http://localhost:8081

phpMyAdmin
server: db
user: root
pass: root

บัญชีตัวอย่าง
email: demo@lapikad.local
password: 123456


บัญชีแอดมิน
email: admin@lapikad.local
password: 123456

ระบบใหม่
- Admin Panel: http://localhost:8080/admin.php
- เพิ่ม/ลดคะแนนผู้ใช้
- เพิ่มสถานที่
- เพิ่มภารกิจ QR Code
- สแกน QR ด้วยกล้องมือถือ
- หน้าสำเร็จสีเขียวเมื่อได้รับคะแนน

หมายเหตุ
การสแกนกล้องบนมือถือผ่าน ngrok ต้องใช้ลิงก์ https เท่านั้น


QR Code รูปแบบใหม่
ระบบจะรับเฉพาะ QR ของเว็บเรา เช่น
LAPIKAD:TEMPLE-2026
LAPIKAD:MUSEUM-2026

ถ้าสแกน QR อื่น จะขึ้นหน้าแดง
ถ้าสแกน QR ถูกต้อง จะขึ้นหน้าเขียวและเพิ่มคะแนนอัตโนมัติ

การทดสอบกล้องมือถือ
ควรเปิดผ่าน ngrok ที่เป็น https เช่น
ngrok http 8080
