SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

DROP DATABASE IF EXISTS questtrip;
CREATE DATABASE questtrip CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE questtrip;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('user','admin') NOT NULL DEFAULT 'user',
  points INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE places (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  description TEXT,
  location_text VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE quests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  place_id INT NOT NULL,
  title VARCHAR(180) NOT NULL,
  description TEXT,
  quest_type VARCHAR(50) NOT NULL,
  reward_points INT NOT NULL DEFAULT 10,
  target_code VARCHAR(100) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (place_id) REFERENCES places(id) ON DELETE CASCADE
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE user_quests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  quest_id INT NOT NULL,
  completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_user_quest (user_id, quest_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (quest_id) REFERENCES quests(id) ON DELETE CASCADE
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE point_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  admin_id INT NULL,
  points INT NOT NULL,
  reason VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE rewards (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  description TEXT,
  cost_points INT NOT NULL,
  stock INT NOT NULL DEFAULT 10
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE reward_redemptions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  reward_id INT NOT NULL,
  redeemed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (reward_id) REFERENCES rewards(id) ON DELETE CASCADE
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT INTO users (name, email, password_hash, role, points) VALUES
('Tem', 'demo@lapikad.local', '$2y$10$p3KZlqrG0ig7Pbh1/9kB5.8eC6FYkcs/PJq9DpMW65ccWmXgmmZMW', 'user', 0),
('Admin', 'admin@lapikad.local', '$2y$10$p3KZlqrG0ig7Pbh1/9kB5.8eC6FYkcs/PJq9DpMW65ccWmXgmmZMW', 'admin', 0);

INSERT INTO places (name, description, location_text) VALUES
('วัดพระธาตุ', 'สถานที่ท่องเที่ยวเชิงวัฒนธรรม เหมาะสำหรับทำภารกิจเดินสำรวจ เรียนรู้ประวัติ และเช็คอินตามจุดสำคัญ', 'อำเภอกุฉินารายณ์'),
('พิพิธภัณฑ์ชุมชน', 'แหล่งเรียนรู้เรื่องประวัติท้องถิ่น วิถีชีวิต และของเก่าชุมชน เหมาะสำหรับการสำรวจแบบภารกิจ', 'ศูนย์ชุมชน');

INSERT INTO quests (place_id, title, description, quest_type, reward_points, target_code) VALUES
(1, 'เช็คอินหน้าอุโบสถ', 'เดินไปยังจุดเช็คอินหน้าอุโบสถ แล้วกดยืนยันภารกิจ', 'checkin', 10, 'CHECKIN-TEMPLE'),
(1, 'ตอบคำถามประวัติสถานที่', 'อ่านข้อมูลสถานที่แล้วตอบคำถามให้ถูกต้อง', 'quiz', 15, 'QUIZ-TEMPLE'),
(1, 'สแกนรหัสภารกิจ', 'ค้นหา QR Code บริเวณจุดแลนด์มาร์กของสถานที่', 'qr', 20, 'TEMPLE-2026'),
(2, 'สำรวจห้องจัดแสดง', 'เดินชมข้อมูลในห้องจัดแสดงหลักของพิพิธภัณฑ์', 'checkin', 10, 'CHECKIN-MUSEUM'),
(2, 'สแกนรหัสพิพิธภัณฑ์', 'สแกนหรือกรอกรหัสที่อยู่ในจุดกิจกรรม', 'qr', 20, 'MUSEUM-2026');

INSERT INTO rewards (name, description, cost_points, stock) VALUES
('น้ำสมุนไพรชุมชน', 'แลกรับน้ำสมุนไพรจากร้านค้าชุมชน', 50, 20),
('ส่วนลดร้านอาหาร', 'คูปองส่วนลดร้านอาหารในพื้นที่', 100, 10),
('ของที่ระลึก', 'ของที่ระลึกจากแหล่งท่องเที่ยว', 150, 5);
