-- ============================================================
-- SQL Script: เพิ่มผู้ใช้ใหม่ 3 คน สำหรับ dropdown ผู้ทบทวน/ผู้อนุมัติ
-- วันที่: 19 พ.ค. 2569
-- ============================================================

-- ก่อนรัน script นี้ ให้ตรวจสอบ rank_id และ position_id ที่ถูกต้องจากตาราง user_rank และ user_position
-- รัน query นี้เพื่อดูค่า rank_id:
-- SELECT rank_id, rank_name FROM user_rank ORDER BY rank_id;
-- รัน query นี้เพื่อดูค่า position_id:
-- SELECT position_id, position_name FROM user_position ORDER BY position_id;

-- ============================================================
-- 1. พ.ต.อ.หญิง จันทรจิรา ยอดรักษ์ - นวท.(สบ4) กสก.10
-- ============================================================
-- เพิ่มใน users (ใช้ email dummy, password hash ของ '123456')
INSERT INTO users (email, password, role, nvt_sub, spt_sub, ptjv_sub, is_active) 
VALUES ('jantrajira.yodrak@csims.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 4, 10, NULL, 1);

SET @user_id_1 = LAST_INSERT_ID();

-- เพิ่มใน user_profile (ต้องแก้ rank_id และ position_id ให้ตรงกับค่าในระบบ)
-- rank_id สำหรับ "พ.ต.อ.หญิง" และ position_id สำหรับ "นักวิทยาศาสตร์"
INSERT INTO user_profile (user_id, first_name, last_name, rank_id, position_id, phone) 
VALUES (@user_id_1, 'จันทรจิรา', 'ยอดรักษ์', 
    (SELECT rank_id FROM user_rank WHERE rank_name LIKE '%พ.ต.อ.หญิง%' LIMIT 1),
    (SELECT position_id FROM user_position WHERE position_name LIKE '%นักวิทยาศาสตร์%' LIMIT 1),
    NULL);

-- ============================================================
-- 2. พ.ต.อ. ศราวุธ เกาะสมัน - นวท.(สบ4) พฐ.จว.ปัตตานี
-- ============================================================
INSERT INTO users (email, password, role, nvt_sub, spt_sub, ptjv_sub, is_active) 
VALUES ('sarawut.kohsaman@csims.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 4, NULL, 94, 1);

SET @user_id_2 = LAST_INSERT_ID();

INSERT INTO user_profile (user_id, first_name, last_name, rank_id, position_id, phone) 
VALUES (@user_id_2, 'ศราวุธ', 'เกาะสมัน', 
    (SELECT rank_id FROM user_rank WHERE rank_name LIKE '%พ.ต.อ.%' AND rank_name NOT LIKE '%หญิง%' LIMIT 1),
    (SELECT position_id FROM user_position WHERE position_name LIKE '%นักวิทยาศาสตร์%' LIMIT 1),
    NULL);

-- ============================================================
-- 3. พ.ต.อ. มานิตย์ ปานทอง - นวท.(สบ4) พฐ.จว.นราธิวาส
-- ============================================================
INSERT INTO users (email, password, role, nvt_sub, spt_sub, ptjv_sub, is_active) 
VALUES ('manit.panthong@csims.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 4, NULL, 96, 1);

SET @user_id_3 = LAST_INSERT_ID();

INSERT INTO user_profile (user_id, first_name, last_name, rank_id, position_id, phone) 
VALUES (@user_id_3, 'มานิตย์', 'ปานทอง', 
    (SELECT rank_id FROM user_rank WHERE rank_name LIKE '%พ.ต.อ.%' AND rank_name NOT LIKE '%หญิง%' LIMIT 1),
    (SELECT position_id FROM user_position WHERE position_name LIKE '%นักวิทยาศาสตร์%' LIMIT 1),
    NULL);

-- ============================================================
-- หมายเหตุ:
-- - password ที่ใช้คือ hash ของ '123456' (สามารถเปลี่ยนได้ภายหลัง)
-- - หาก rank_name หรือ position_name ไม่ตรง ให้แก้ไข subquery ให้ถูกต้อง
-- - หรือใส่ rank_id และ position_id เป็นตัวเลขตรงๆ แทน
-- ============================================================
