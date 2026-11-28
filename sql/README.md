# sql/ — ลำดับการรัน migration

ไม่มีตารางเก็บเวอร์ชันของ schema ต้องรันเองตามลำดับและจำเอาว่ารันถึงไหนแล้ว
`init.sql` ถูก mount เข้า `docker-entrypoint-initdb.d` ใน `docker-compose.yml`
ดังนั้น **เครื่องใหม่จะได้ `init.sql` อัตโนมัติ** ที่เหลือต้องรันเองทั้งหมด

ลำดับนี้เรียงตามที่แต่ละไฟล์ต้องพึ่งพากัน (อ่านคอมเมนต์หัวไฟล์ประกอบได้)
สลับลำดับแล้วจะพังเพราะคอลัมน์หรือตารางที่อ้างถึงยังไม่มี

| # | ไฟล์ | เพิ่มอะไร |
|---|---|---|
| 1 | `init.sql` | ตารางหลัก: users, places, quests, user_quests, point_logs, rewards, reward_redemptions |
| 1b | `add_legacy_drift_columns.sql` | คอลัมน์ที่ production เพิ่มด้วยมือแต่ไม่มีใน `init.sql` — **จำเป็นบนฐานข้อมูลใหม่** ข้ามได้บน production (ดูหัวข้อท้ายไฟล์) |
| 2 | `add_auth_features.sql` | `users.is_banned`, `password_resets`, `remember_tokens` |
| 3 | `add_place_active.sql` | `places.is_active` |
| 4 | `add_shop_role.sql` | role `shop`, `places.owner_user_id`, ตาราง `shop_requests` |
| 5 | `add_shop_location.sql` | `shop_requests.province` / `.district` |
| 6 | `add_shop_request_details.sql` | ฟิลด์เพิ่มของ wizard สมัครร้าน 5 ขั้น |
| 7 | `rename_shop_category.sql` | เปลี่ยนชื่อหมวด `ร้านอาหาร` → `ร้านค้า/ร้านอาหาร` |
| 8 | `add_shop_points_redemptions.sql` | `user_shop_points` |
| 9 | `add_shop_checkins.sql` | `places.checkin_token`, `shop_point_txns` |
| 10 | `add_admin_shop_points.sql` | เพิ่ม kind `admin` ใน `shop_point_txns` |
| 11 | `add_quest_daily_refresh.sql` | `user_quests.completed_date` + unique key ใหม่ |
| 12 | `add_student_class_info.sql` | `users.student_name` / `.class_room` / `.student_no` |
| 13 | `add_school_events.sql` | `events`, `event_staff`, `event_attendances`, `users.event_token` |
| 14 | `add_schools.sql` | `schools` + `school_id` ใน users / events / rewards |
| 15 | `add_school_logo.sql` | `schools.logo_url` |
| 16 | `add_event_links.sql` | `event_links` + `event_attendances.link_id` |
| 17 | `add_event_joins.sql` | `event_joins` |
| 18 | `drop_rewards.sql` | ลบระบบรางวัลแบบเก่า (รางวัลของโรงเรียนยังอยู่) |
| 19 | `add_rate_limits.sql` | `rate_limits` — ตัวนับสำหรับ `www/_ratelimit.php` |

จุดที่ลำดับสำคัญเป็นพิเศษ:

- **13 ก่อน 14** — `add_schools.sql` สั่ง `ALTER TABLE events` ถ้ายังไม่มีตาราง `events` จะ error
- **9 ก่อน 10** — `add_admin_shop_points.sql` แก้ ENUM ของ `shop_point_txns` ที่ข้อ 9 สร้าง
- **16 ก่อน 17** — `event_joins.link_id` อ้าง foreign key ไป `event_links`

รันทีละไฟล์ เช่น

```
docker compose exec -T db mysql -uroot -p"$DB_ROOT_PASS" questtrip < sql/add_rate_limits.sql
```

## ไฟล์ข้อมูลตัวอย่าง (ไม่ใช่ migration — รันเฉพาะเมื่อต้องการ)

| ไฟล์ | ใช้ทำอะไร |
|---|---|
| `seed_kalasin_kamalasai_places.sql` | สถานที่ท่องเที่ยวจริง อ.กมลาไสย จ.กาฬสินธุ์ + ภารกิจ |
| `demo_kuchinarai_seed.sql` | ชุดทดลอง อ.กุฉินารายณ์ (สถานที่ ภารกิจ ร้านค้า) |
| `demo_kuchinarai_remove.sql` | ลบชุด `demo_kuchinarai_seed.sql` ออก |
| `demo_event5_attendance.sql` | ใส่ข้อมูลเช็คชื่อปลอมให้กิจกรรม id 5 เพื่อดูหน้ารายงาน |

## หมายเหตุเรื่อง schema drift

`add_shop_role.sql` เขียนไว้ว่า production มีคอลัมน์บางตัวที่ถูก `ALTER` ด้วยมือ
และไม่เคยถูกบันทึกเป็นไฟล์ migration `init.sql` จึงไม่ตรงกับ production

ทดสอบรันลำดับข้างบนบนฐานข้อมูลเปล่าจริงแล้ว (28 พ.ย. 2026) พบว่า
`rename_shop_category.sql` พังด้วย `ERROR 1054: Unknown column 'category'`
จึงเขียน `add_legacy_drift_columns.sql` ขึ้นมาเติมคอลัมน์ที่ขาดทั้งหมด:

- `places.category / image_url / lat / lng / district / province`
- `users.avatar_url / bio`
- `user_quests.photo_url`

หลังเติมแล้วรันทั้ง 19 ไฟล์ผ่านหมด

บน production คอลัมน์พวกนี้มีอยู่แล้ว การรันไฟล์นี้จะได้ `Duplicate column name`
ทุกบรรทัด ซึ่งไม่เป็นไร แต่ให้ข้ามไปเลยจะง่ายกว่า
