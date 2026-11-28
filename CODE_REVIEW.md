# รีวิวโค้ด lapikad.com

รีวิวจาก clone `main` วันที่ 28 พ.ย. 2026 (push ล่าสุด 27 พ.ย. 2026)
ขอบเขต: 90 ไฟล์ PHP ใน `www/` (~15,500 บรรทัด), 22 ไฟล์ SQL, Docker config

> **สถานะ:** ข้อ 1–10 แก้แล้ว `php -l` ผ่าน 86 ไฟล์ และทดสอบรันจริงด้วย
> `docker compose up` แล้ว (28 พ.ย. 2026)
>
> **ต้องทำก่อน deploy:**
> 1. แก้ `APP_ALLOWED_HOSTS` ใน `www/_bootstrap.php` ให้เป็นโดเมนจริงที่ใช้ production
> 2. รัน `sql/add_rate_limits.sql` — ถ้าไม่รัน ระบบจะยังทำงานได้ปกติแต่ไม่มีการจำกัดจำนวนครั้ง
>    (ฟังก์ชันใน `_ratelimit.php` ออกแบบให้ fail open และเขียน error log ไว้ — ทดสอบแล้ว)
>
> หมวด "ปัญหาเชิงโครงสร้าง" ที่เหลือยังไม่ได้แก้

---

## สิ่งที่เจอเพิ่มตอนรันจริง (แก้แล้วทั้งหมด)

การทดสอบด้วย `docker compose up` บนฐานข้อมูลเปล่าทำให้เจอปัญหาที่อ่านโค้ดเฉย ๆ ไม่เห็น

1. **`forgot_password.php` พังทั้งหน้าเมื่อไม่มี `_mail_config.php`** และพิมพ์ stack trace
   พร้อม path จริงของเซิร์ฟเวอร์ออกหน้าเว็บ (`/var/www/html/_mail.php`)
   `_claude.php` มีปัญหาเดียวกันกับ `_claude_config.php` → แก้ให้ config เป็น optional
   เหมือน `_db.php` ทั้งสองไฟล์ ไม่มี config ก็ทำงานต่อได้ (mail ส่งไม่ออก / Claude ใช้ mock)
2. **`display_errors` เปิดอยู่ใน image** → ใส่ `errors.ini` ปิด และเปิด `log_errors`
3. **service `web` ไม่ publish port เลย** ทำให้ `http://localhost:8080` ที่ README บอก
   ใช้ไม่ได้มาตลอด (Caddy ที่อยู่หน้าตอบเฉพาะโดเมนจริง) → เพิ่ม `127.0.0.1:8080:80`
   ภายหลังเอา service `caddy` ออกทั้งหมด เพราะ production เป็น cPanel ไม่ได้ใช้ Caddy
   และ dev เข้าทาง `localhost:8080` ตรงเข้า `web` อยู่แล้ว
4. **กฎบังคับ HTTPS ทำให้ dev เข้าไม่ได้** หลังเปิด `AllowOverride` — regression ที่เกิดจาก
   การแก้ข้อ 8 เอง → ยกเว้น localhost/127.0.0.1 ออกจากกฎ
5. **`rename_shop_category.sql` พังบนฐานข้อมูลใหม่** → เขียน `add_legacy_drift_columns.sql`

---

## ภาพรวม

คุณภาพโค้ดดีกว่าที่คาดสำหรับงาน PHP แบบ page-per-file ไม่มี framework จุดที่ทำถูกและทำดีจริง:

- **prepared statement ทุกจุดที่รับ input จากผู้ใช้** — ไม่พบ SQL injection เลย
- CSRF token ใน `_bootstrap.php` และเรียก `csrf_verify()` ในทุกไฟล์ที่รับ POST
- `password_hash` / `password_verify` (PASSWORD_DEFAULT) — ไม่มี md5/sha1 กับรหัสผ่าน
- remember-me แบบ **selector:validator** เก็บแค่ hash ของ validator และ rotate ทุกครั้งที่ใช้ — ทำถูกตามตำรา
- `session_regenerate_id(true)` ตอน login และตอน remember-login
- reset password token เก็บเป็น sha256 hash, หมดอายุ 1 ชม., ลบ remember_tokens ทุกเครื่องหลังเปลี่ยน
- **QR เช็คชื่อกิจกรรมใช้ HMAC-SHA256 หมุนตามช่วงเวลา** (`eventQrToken`) + grace slots + `hash_equals` — ป้องกันการถ่ายรูป QR ไปใช้ภายหลังได้จริง
- `shop_deduct.php` ระบุตัวลูกค้าจาก token ที่สแกนเท่านั้น ไม่เคยเชื่อ user id ที่ POST มา + มี one-time nonce กัน refresh หักซ้ำ
- `redeemShopPoints` ใช้ `SELECT ... FOR UPDATE` ใน transaction — กัน race ตอนหักแต้มถูกต้อง
- unique key `(user_id, quest_id, completed_date)` กันรับแต้มซ้ำที่ชั้น DB
- upload: whitelist นามสกุล + จำกัดขนาด + ตั้งชื่อไฟล์ใหม่ด้วย `random_bytes` — ไม่มีช่องอัปโหลด `.php`
- escape output ด้วย `e()` สม่ำเสมอ ไม่พบ `echo $_GET` ตรง ๆ
- `.gitignore` คุม config จริงทั้ง 3 ไฟล์ (db/mail/claude) + `data/` + `.mcp.json` — ไม่มี secret หลุดใน repo
- คอมเมนต์อธิบาย "ทำไม" ไม่ใช่แค่ "ทำอะไร" ซึ่งหาได้ยากในโปรเจกต์ขนาดนี้

---

## ปัญหาที่ควรแก้

### 1. Host header injection ในลิงก์ reset password — ความรุนแรงสูง

`www/forgot_password.php:35`

```php
$resetUrl = "$scheme://" . $_SERVER["HTTP_HOST"] . "/reset_password.php?token=" . $token;
```

`HTTP_HOST` มาจาก header ที่ผู้โจมตีกำหนดได้ ถ้ายิง `Host: evil.com` พร้อมอีเมลของเหยื่อ
เหยื่อจะได้เมลจริงจากระบบเราแต่ลิงก์ชี้ไป `evil.com` → token หลุด → ยึดบัญชีได้

แก้: hardcode base URL จาก config หรือ whitelist host ที่อนุญาต

```php
$resetUrl = APP_BASE_URL . "/reset_password.php?token=" . $token;
```

`siteBaseUrl()` ใน `_events.php:120` ก็ใช้ `HTTP_HOST` เหมือนกัน — จุดนี้กระทบแค่ QR ที่สร้าง จึงเบากว่า แต่ควรแก้ไปพร้อมกัน

### 2. DEBUG SMTP error โชว์บนหน้าสาธารณะ — ความรุนแรงสูง

`www/forgot_password.php:8,147-148,158-159`

```php
$mailError = null; // TEMP debug — remove once SMTP is confirmed working
...
DEBUG (ลบออกทีหลัง): <?= e($mailError) ?>
```

`$mail->ErrorInfo` ของ PHPMailer มักมี SMTP host, username และข้อความตอบกลับจากเซิร์ฟเวอร์
ใครก็เปิดหน้านี้ได้ เท่ากับเปิดเผย config เมลให้คนนอก คอมเมนต์เองก็เขียนไว้ว่าเป็นของชั่วคราว — ลบทิ้ง และให้ log ผ่าน `error_log()` อย่างเดียว

### 3. User enumeration ที่หน้าลืมรหัสผ่าน — ความรุนแรงกลาง

`www/forgot_password.php:41` ตั้ง `$notFound = true` เมื่อไม่มีอีเมลนั้น แล้วแสดงข้อความต่างออกไป
→ ใช้ไล่เช็คได้ว่าอีเมลใดมีบัญชีในระบบ (เป็นข้อมูลนักเรียน)

แก้: ตอบข้อความเดียวกันทุกกรณี เช่น "ถ้าอีเมลนี้มีบัญชีอยู่ เราได้ส่งลิงก์ไปแล้ว"

### 4. ไม่มีการจำกัดจำนวนครั้ง (rate limiting) — ความรุนแรงกลาง

- `login.php` ไม่มี throttle / lockout เลย → brute force รหัสผ่านได้ไม่จำกัด ยิ่งเพราะ `reset_password.php:28` ยอมรับรหัสผ่านสั้นสุด 6 ตัว
- `forgot_password.php` ไม่มี throttle → ใช้ยิงเมลถล่มเหยื่อ และเผาโควตา SMTP
- `quest_recommend.php` (endpoint เดียวที่เรียก Claude จริง — `ai_quests.php` เป็นแค่หน้าเว็บ) มี cooldown 5 วินาที + cache คำตอบ 60 วินาทีอยู่แล้ว แต่ทั้งสองอย่างเก็บใน session → ทิ้ง cookie แล้วยิงใหม่ก็ผ่าน และไม่มีเพดานรวมต่อชั่วโมง = ค่า API บานปลายได้

แก้แบบเบาสุด: ตารางนับความพยายามต่อ email+IP ในช่วงเวลา หรือ cooldown ใน session แบบที่ `complete_quest.php` ทำ

### 5. ไม่ตั้งค่าความปลอดภัยของ session cookie — ความรุนแรงกลาง

`_bootstrap.php` เรียก `session_start()` เปล่า ๆ ไม่มี `session_set_cookie_params`
cookie `remember_token` ตั้ง `httponly`/`secure`/`samesite` ไว้ครบ แต่ `PHPSESSID` ไม่ได้ตั้ง — ไม่สมเหตุสมผลกัน

```php
session_set_cookie_params([
    "httponly" => true,
    "secure"   => !empty($_SERVER["HTTPS"]),
    "samesite" => "Lax",
    "path"     => "/",
]);
session_start();
```

### 6. CSRF token ตัวเดียวตลอดอายุ session — ความรุนแรงต่ำ

`_bootstrap.php:5` สร้าง token ครั้งเดียวและไม่ rotate หลัง login
พอ `session_regenerate_id(true)` ก็ได้ session id ใหม่แต่ยัง csrf token เดิม ควร rotate หลัง login/logout ด้วย

### 7. `shop_deduct.php` ยอมให้ร้านใส่จำนวนแต้มเอง — ความรุนแรงต่ำ (เชิงออกแบบ)

`shop_deduct.php:40` รับ `points` จากฟอร์มได้อิสระ (`redeemShopPoints` กันแค่ `<= 0`)
ตั้งใจให้เป็น "custom amount" ก็เข้าใจได้ แต่ควรมีเพดานต่อรายการ และ `adminAdjustShopPoints` มีเพดาน 100,000 อยู่แล้ว — ใส่ให้สอดคล้องกัน

---

## ปัญหาเชิงโครงสร้างและการดูแลต่อ

### env ของ Docker ไม่ได้ถูกใช้จริง

`docker-compose.yml` ส่ง `DB_HOST/DB_NAME/DB_USER/DB_PASS` เข้า service `web`
แต่ `_db.php:3` อ่านจาก `_db_config.php` (ไฟล์ที่ต้องก๊อปมือ) — env พวกนั้นไม่มีใครอ่าน
สับสนและทำให้ตั้งค่าผิดได้ง่าย ควรเลือกทางเดียว: ให้ `_db_config.php` อ่าน `getenv()` เป็นค่า fallback

### Dockerfile ทั้ง COPY และ mount ทับ

`Dockerfile:12` `COPY ./www /var/www/html` แต่ compose mount `./www` ทับอีกชั้น
dev ใช้ได้ แต่ทำให้แยกไม่ออกว่า image ที่ build มีโค้ดเวอร์ชันไหน

### SQL migration เรียงมือ ไม่มีตัวติดตาม

มี `init.sql` + `add_*.sql` 20 ไฟล์ ที่ต้องรันตามลำดับถูกต้องเอง ไม่มีตาราง schema version
ตั้งเครื่องใหม่หรือย้าย host จะพลาดง่าย อย่างน้อยเขียนลำดับที่ต้องรันไว้ใน `sql/README.md`

### `www/` รูทเดียวกับไฟล์ `_*.php`

ไฟล์ที่ไม่ควรเข้าถึงตรง (`_db.php`, `_events.php`, …) อยู่ใน document root
ตอนนี้ปลอดภัยเพราะทุกไฟล์มีแต่ฟังก์ชัน/ไม่ส่ง output แต่ `.htaccess` มีแค่ redirect HTTPS
แนะนำบล็อกไว้ด้วย:

```apache
<FilesMatch "^_">
  Require all denied
</FilesMatch>
```

### ไฟล์ใหญ่เกินไป

`shop_apply.php` 710 บรรทัด, `community.php` 704, `dashboard.php` 627, `admin_users.php` 571
แต่ละไฟล์ผสม logic + HTML + CSS inline ในที่เดียว CSS inline ซ้ำกันหลายไฟล์ (ชุด `font-family: 'Kanit'` + reset เดียวกันปรากฏในแทบทุกหน้า)
ควรย้าย CSS ซ้ำไป `css/modern.css` และแยกส่วน PHP ขึ้นหัวไฟล์ให้ชัด

### README.txt ไม่ตรงกับ compose

README บอก phpMyAdmin `user: root / pass: root` แต่ compose ใช้ `${DB_ROOT_PASS}` จาก `.env`
และ README ไม่ได้พูดถึง `_db_config.php` / `_mail_config.php` / `_claude_config.php` ที่ต้องก๊อปก่อนรัน — คนใหม่รันไม่ขึ้นแน่

### `Claude outputs/` ถูก commit เข้า repo

6 ไฟล์ JPG รวม ~2.2 MB ของแผนผัง flow ถ้าเป็นเอกสารประกอบก็ควรอยู่ใน `docs/` และบีบอัดก่อน ไม่ใช่ชื่อโฟลเดอร์ว่า "Claude outputs"

### ฟังก์ชัน haversine ซ้ำสองที่

`_db.php` มี `haversineMeters()` และ `_claude.php` มี `haversineKm()` — logic เดียวกัน ต่างแค่หน่วย ควรเหลือตัวเดียว

---

## สรุปลำดับความสำคัญ

| ลำดับ | เรื่อง | ไฟล์ | สถานะ |
|---|---|---|---|
| 1 | Host header injection ในลิงก์ reset | `_bootstrap.php`, `forgot_password.php`, `_events.php` | แก้แล้ว |
| 2 | ลบ DEBUG SMTP error ออกจากหน้าเว็บ | `forgot_password.php` | แก้แล้ว |
| 3 | rate limit login / forgot / AI | `_ratelimit.php`, `login.php`, `forgot_password.php`, `quest_recommend.php` | แก้แล้ว |
| 4 | ปิด user enumeration | `forgot_password.php` | แก้แล้ว |
| 5 | ตั้งค่า session cookie ให้ปลอดภัย | `_bootstrap.php` | แก้แล้ว |
| 6 | rotate CSRF token หลัง login | `_bootstrap.php`, `login.php`, `_db.php` | แก้แล้ว |
| 7 | เพดานแต้มต่อรายการ | `_shop_points.php`, `shop_deduct.php`, `shop_rewards.php` | แก้แล้ว |
| 8 | บล็อก `_*.php`, `includes/`, `lib/` | `www/.htaccess` + `includes/`, `lib/`, `Dockerfile` | แก้แล้ว |
| 9 | รวม env/config ของ DB ให้เหลือทางเดียว | `_db.php`, `_db_config.example.php` | แก้แล้ว |
| 10 | เขียน README/ลำดับ migration ให้ถูก | `README.txt`, `sql/README.md` | แก้แล้ว |

หมวด "ปัญหาเชิงโครงสร้าง":

| เรื่อง | สถานะ |
|---|---|
| `haversine` ซ้ำสองที่ | แก้แล้ว — เหลือ `haversineMeters()` ใน `_db.php` ตัวเดียว |
| `Dockerfile` ทั้ง COPY และ mount ทับ | แก้แล้ว — เอา `COPY ./www` ออก |
| `Claude outputs/` ที่ commit เข้ามา | แก้แล้ว — ย้ายไป `docs/figures/flows/` |
| env/config ของ DB ซ้ำซ้อน | แก้แล้ว (ข้อ 9) |
| ไฟล์ใหญ่เกินไป / CSS inline ซ้ำ | **ไม่แก้** — ดูเหตุผลด้านล่าง |

### ทำไมไม่แตะ CSS inline

ตอนเขียนรีวิวผมบอกว่า "CSS inline ซ้ำกันหลายไฟล์" ซึ่งพอวัดจริงแล้วไม่ตรงนัก
39 ไฟล์มี `<style>` ของตัวเอง แต่เนื้อในเป็นกฎเฉพาะหน้าแทบทั้งหมด
(`places.php` 181 บรรทัด, `community.php` 233, `dashboard.php` 177) ส่วนที่ซ้ำกันจริงมีแค่

- บรรทัด reset `*, *::before, *::after { box-sizing… }` — ซ้ำใน 8 ไฟล์
- `<link>` ของ favicon / preconnect / Kanit — ซ้ำใน ~47 ไฟล์ แต่ `<title>` กับ `<meta viewport>`
  (ซึ่งมี 3 แบบต่างกัน) แทรกอยู่กลางบล็อก จึงตัดเป็นก้อนเดียวไม่ได้ ต้องแยกเป็น 2 include
  แลกกับการแก้ `<head>` ของ 47 ไฟล์เพื่อลดโค้ดซ้ำ 4 บรรทัด

การรวม `body` rule ทั้ง 35 แบบเข้าด้วยกันต้องอ่านและตัดสินใจเรื่องดีไซน์ทีละไฟล์
ไม่ใช่งานแทนที่แบบกลไก และต้องตรวจหน้าตาทีละหน้า 39 หน้าโดยไม่มีภาพอ้างอิงเดิม
เป็นงาน refactor จริงที่ควรทำแยกรอบพร้อมคนที่ตัดสินใจเรื่องดีไซน์ได้ ไม่ใช่เหมารวมมาในชุดนี้
