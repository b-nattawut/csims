# CSIMS Project Analysis

> **วันที่วิเคราะห์:** 27 พฤษภาคม 2569  
> **เวอร์ชัน:** 1.0

---

## 1) Project Summary

**CSIMS (Crime Scene Investigation Management System)** คือ **ระบบบริหารจัดการฐานข้อมูลวัตถุพยาน** สำหรับหน่วยงานตำรวจในพื้นที่ 3 จังหวัดชายแดนภาคใต้ (ยะลา, ปัตตานี, นราธิวาส)

### วัตถุประสงค์หลัก
- **รับแจ้งเหตุ** — บันทึกข้อมูลการรับแจ้งเหตุอาชญากรรม 8 ประเภท (ทรัพย์, ชีวิต, ระเบิด, เพลิงไหม้, จราจร, หลักฐาน, สถานที่เกิดเหตุ, หลักฐานบุคคล)
- **แบบตรวจเก็บวัตถุพยาน (Checklist)** — ฟอร์มดิจิทัลสำหรับบันทึกรายละเอียดการตรวจสอบที่เกิดเหตุ พร้อมลายเซ็น, แผนผัง, รูปภาพ
- **สร้าง PDF รายงาน** — สร้างรายงานผลการตรวจสถานที่เกิดเหตุเป็นไฟล์ PDF อัตโนมัติ (รองรับทุกประเภทคดี)
- **จัดการข้อมูลห้องปฏิบัติการ** — บันทึกการใช้เครื่องมือ, คอมพิวเตอร์, อุณหภูมิ, สารเคมี, แผนซ่อมบำรุง
- **ประเมินความพร้อมเจ้าหน้าที่** — บันทึกจำนวน/ความพร้อม และแบบประเมินความสามารถ
- **ระบบ Offline** — รองรับการทำงานเมื่อไม่มีอินเทอร์เน็ต ด้วย Service Worker + IndexedDB (Dexie.js)

---

## 2) Tech Stack

| หมวด | เทคโนโลยี |
|---|---|
| **Programming Language** | PHP (Backend), JavaScript (Frontend) |
| **Framework** | ไม่มี PHP Framework (Plain PHP / Vanilla) |
| **Frontend** | Bootstrap 5, jQuery 3.7.x, Select2, Tom Select, SweetAlert2, Chart.js, DataTables, Signature Pad, Day.js |
| **Backend** | PHP + PDO (MySQL) |
| **Database** | MySQL (`csims` database), charset `utf8mb4` |
| **PDF Generation** | mPDF 8.2, wkhtmltopdf (via `mikehaertl/phpwkhtmltopdf`) |
| **Excel/Word Export** | PhpSpreadsheet 5.2, PhpWord 1.4 |
| **Email/OTP** | Mailjet SMTP (ส่ง OTP สำหรับ 2FA) |
| **API Documentation** | Swagger UI (OpenAPI 3.0) |
| **Offline Support** | Service Worker + Dexie.js (IndexedDB) |
| **Handwriting** | handwriting.canvas.js (รองรับลายมือเขียน) |
| **Printer** | Zebra Printer API (พิมพ์ฉลาก ZPL) |
| **Hosting** | Apache/Nginx บน `192.168.0.95` (Internal) + `csims.ppunix.org` (Public) |

---

## 3) Folder / File Structure

```
csims/
├── index.php                          # Entry point → redirect ไป dashboard หรือ login
├── login.html / login.php             # หน้า Login + OTP 2FA
├── verify_otp.html                    # หน้ายืนยัน OTP
├── forget_password.php                # หน้าลืมรหัสผ่าน
├── register.php                       # หน้าลงทะเบียน
├── layout.php                         # Layout หลัก (session check, navbar, sidebar)
├── dashboard.php                      # หน้าแดชบอร์ดสรุปข้อมูลคดี
├── db_config.php                      # ตั้งค่าการเชื่อมต่อ Database (PDO)
├── .env                               # ค่า API Key สำหรับ Mailjet
│
├── incident.php                       # หน้ารายการรับแจ้งเหตุ (CRUD)
├── incidentDetail.php                 # รายละเอียดเหตุการณ์
├── incidentChecklist.php              # แบบตรวจเก็บวัตถุพยาน (หน้าหลัก)
├── incidentReport.php                 # ร่างรายงาน
├── report_extension.php               # ขอขยายเวลาออกรายงาน
├── field_visit_log.php                # บันทึกการออกภาคสนาม
│
├── trans_equipment_usage.php          # บันทึกการใช้เครื่องมือ
├── trans_computer_usage.php           # บันทึกการใช้คอมพิวเตอร์
├── trans_maintenance_plan.php         # แผนซ่อมบำรุง/สอบเทียบ
├── trans_equipment_log.php            # บันทึกประวัติซ่อมบำรุง
├── trans_temperature_record.php       # บันทึกอุณหภูมิ
├── chemical_inventory.php             # คลังสารเคมี
├── trans_chemical_preparation.php     # เตรียมสารเคมี
├── trans_chemical_validation.php      # ทดสอบสารเคมี
├── trans_latent_chemical_test.php     # ตรวจความพร้อมสารเคมี (ลายนิ้วมือแฝง)
├── trans_readiness_check.php          # บันทึกความพร้อมเจ้าหน้าที่
├── trans_staff_assessment.php         # แบบประเมินความสามารถ
│
├── master_equipment_list.php          # Master Data: เครื่องมือ
├── master_computer_list.php           # Master Data: คอมพิวเตอร์
├── master_temperature_device.php      # Master Data: อุปกรณ์วัดอุณหภูมิ
├── master_chemical_list.php           # Master Data: สารเคมี
├── master_equipment_category.php      # Master Data: หมวดหมู่เครื่องมือ
│
├── manage_user.php                    # จัดการผู้ใช้
├── manage_permission.php              # จัดการสิทธิ์
├── user_profile.php                   # โปรไฟล์ผู้ใช้
├── user_activity_log.php              # ประวัติกิจกรรมผู้ใช้
├── login_history.php                  # ประวัติ Login
│
├── includes/                          # Shared PHP Components
│   ├── head.php                       # <head> tag (CSS, meta)
│   ├── navbar.php                     # Navigation bar
│   ├── sidebar.php                    # เมนูด้านข้าง + permission map
│   ├── session_config.php             # Session timeout 30 นาที
│   ├── check_permission.php           # ระบบตรวจสิทธิ์ (RBAC)
│   ├── activity_logger.php            # บันทึก user activity
│   └── menu_modal.php                 # Modal เมนู
│
├── modals/                            # Modal Forms (40+ ไฟล์)
│   ├── modal_bomb.php                 # ฟอร์ม Checklist ระเบิด (~467KB)
│   ├── modal_life.php                 # ฟอร์ม Checklist ชีวิต
│   ├── modal_property.php             # ฟอร์ม Checklist ทรัพย์
│   ├── modal_fire_new.php             # ฟอร์ม Checklist เพลิงไหม้
│   ├── modal_traffic.php              # ฟอร์ม Checklist จราจร
│   ├── modal_*_pdf_form.php           # ฟอร์มสร้าง PDF
│   └── modal_report_*.php             # ฟอร์มรายงาน
│
├── api/                               # Backend API
│   ├── incidentCheckList/             # API หลัก (232 ไฟล์)
│   │   ├── save*.php                  # บันทึกข้อมูลแต่ละประเภทคดี
│   │   ├── get*Data.php               # ดึงข้อมูลแต่ละประเภทคดี
│   │   ├── gen_pdf_*.php              # สร้าง PDF รายงาน
│   │   ├── gen_pdf_router.php         # Router เลือก PDF template ตามประเภทคดี
│   │   ├── searchData.php             # ค้นหา/กรองข้อมูล
│   │   └── gen_zpl_evidence.php       # สร้างฉลาก ZPL (Zebra Printer)
│   ├── ReceiveNoti/                   # API รับแจ้งเหตุ (CRUD, export, dashboard)
│   ├── incident/                      # PDF ใบเหตุการณ์
│   ├── Transaction_*/                 # API สำหรับ Transaction ต่างๆ
│   ├── Master_*/                      # API สำหรับ Master Data
│   ├── forgot_password/               # API ลืมรหัสผ่าน
│   ├── login_otp.php                  # Login + OTP
│   ├── send_otp.php                   # ส่ง OTP ทาง Email
│   ├── verify_otp.php                 # ยืนยัน OTP
│   ├── user_auth.php                  # Authentication
│   └── health_check.php              # Health Check (DB status)
│
├── js/                                # JavaScript Libraries
│   ├── jquery-3.7.1.min.js            # jQuery
│   ├── bootstrap.bundle.min.js        # Bootstrap 5
│   ├── chart.js / chart.umd.min.js    # Chart.js
│   ├── dexie.js                       # IndexedDB wrapper (Offline)
│   ├── offline-db.js                  # Offline queue logic
│   ├── sweetalert2.all.min.js         # SweetAlert2
│   ├── signature_pad.umd.min.js       # ลายเซ็นดิจิทัล
│   ├── handwriting.canvas.js          # ลายมือเขียน
│   └── select2.min.js / tom-select    # Dropdown components
│
├── css/                               # Stylesheets
├── fonts/                             # TH Sarabun (สำหรับ PDF)
├── uploads/ / uploads_2/              # ไฟล์อัปโหลด
├── sql/                               # Migration scripts
├── swagger-ui/ + swagger.json         # API Documentation
├── vendor/                            # Composer dependencies (2371 items)
└── service-worker.js                  # PWA Service Worker
```

---

## 4) Code Flow

### 4.1 Authentication Flow
```
login.html → login.php (POST email/password)
  → password_verify() → ส่ง OTP ผ่าน Mailjet SMTP
  → verify_otp.html → verify_otp.php
  → สร้าง Session → redirect ไป dashboard.php
```

### 4.2 หน้าเว็บทั่วไป
```
index.php → ตรวจ Session
  ├─ มี Session → dashboard.php
  └─ ไม่มี Session → login.html

ทุกหน้า PHP → layout.php
  → session_config.php (ตรวจ timeout 30 นาที)
  → ตรวจ user active? → ถ้าไม่ active → logout
  → activity_logger.php (บันทึกการใช้งาน)
  → head.php + navbar.php + sidebar.php (UI)
  → sidebar.php ใช้ check_permission.php (RBAC) กรองเมนู
```

### 4.3 Incident Workflow (Core Flow)
```
1. รับแจ้งเหตุ: incident.php
   → searchData.php (ค้นหา/กรอง)
   → api/ReceiveNoti/save.php (บันทึกรับแจ้ง)

2. ดูรายละเอียด: incidentDetail.php
   → api/ReceiveNoti/getDataByID.php

3. เปิด Checklist: incidentChecklist.php
   → เลือกประเภทคดี (01-08)
   → เปิด Modal ที่ตรงกับประเภทคดี (modal_property.php, modal_life.php, ฯลฯ)
   → กรอกข้อมูล + ลายเซ็น + รูปภาพ (base64 → BLOB)
   → api/incidentCheckList/save*.php (บันทึกลง DB)

4. สร้าง PDF: gen_pdf_router.php
   → ตรวจ complaints_type → redirect ไป gen_pdf_*_html.php ที่ถูกต้อง
   → gen_pdf.php → mPDF → output PDF

5. ร่างรายงาน: incidentReport.php
   → api/incidentCheckList/save*Report.php (บันทึกรายงาน)
   → gen_pdf_report*.php (สร้าง PDF รายงาน)
```

### 4.4 Lab Management Flow
```
trans_*.php (แต่ละหน้า Transaction)
  → api/Transaction_*/save.php (บันทึก)
  → api/Transaction_*/searchData.php (ค้นหา)
  → api/Transaction_*/gen_pdf.php (สร้าง PDF)
```

### 4.5 Offline Flow
```
service-worker.js → cache หน้าหลักทั้งหมด
offline-db.js (Dexie.js)
  → เมื่อ offline: saveDataOffline() → เก็บลง IndexedDB
  → เมื่อ online: syncPendingData() → ส่งข้อมูลค้างไปยัง API
```

---

## 5) Important Files

| ไฟล์ | เหตุผล |
|---|---|
| `db_config.php` | จุดเชื่อมต่อ DB เดียวของทั้งระบบ ทุก API require ไฟล์นี้ |
| `layout.php` | Layout หลัก ควบคุม session, activity log, UI framework |
| `includes/session_config.php` | ควบคุม session timeout และ security |
| `includes/check_permission.php` | ระบบ RBAC ใช้ตรวจสิทธิ์ทั้ง sidebar และ API |
| `includes/sidebar.php` | กำหนดโครงสร้างเมนูทั้งหมด + permission mapping |
| `incidentChecklist.php` | หน้า Checklist หลัก (~1MB) ควบคุม logic ทุกประเภทคดี |
| `api/incidentCheckList/save*.php` | API บันทึกข้อมูลหลัก แยกตามประเภทคดี (18 ไฟล์) |
| `api/incidentCheckList/gen_pdf_router.php` | Router เลือก template PDF ตามประเภทคดี |
| `api/ReceiveNoti/save.php` | API บันทึกข้อมูลรับแจ้งเหตุ |
| `modals/modal_bomb.php` | ไฟล์ Modal ใหญ่ที่สุด (~467KB) ใช้เป็นแม่แบบของ modal อื่น |
| `service-worker.js` + `js/offline-db.js` | ระบบ Offline/PWA |
| `.env` | เก็บ API Key ของ Mailjet |

---

## 6) Issues / Bugs

### 🔴 Critical

1. **API Key เปิดเผยใน `.env` ที่ไม่มี `.gitignore`**  
   - ไฟล์ `.env` มี `MAILJET_API_KEY` และ `MAILJET_SECRET_KEY` แต่ไม่พบ `.gitignore` ที่จะป้องกันการ commit ขึ้น repository
   - `db_config.php` มี credentials ฮาร์ดโค้ด (`csims` / `csimsSQL2025@`) ไม่ได้อ่านจาก `.env`

2. **ไม่มี CSRF Protection**  
   - ไม่พบ CSRF token ในทุก form และ API endpoint ทำให้เสี่ยงต่อ CSRF attack

3. **ไม่มี Rate Limiting สำหรับ Login/OTP**  
   - ไม่พบ rate limit หรือ brute force protection ใน `login.php`, `send_otp.php`, `verify_otp.php`

### 🟡 Warning

4. **ไฟล์ Modal ขนาดใหญ่มาก**  
   - `modal_bomb.php` = **467KB**, `incidentChecklist.php` = **1MB**
   - ส่งผลต่อ performance, ยากต่อการ maintain และ debug

5. **Duplicated Helper Functions**  
   - `jsonResponse()`, `saveBase64ToFile()`, `normalizeValue()` ถูกประกาศซ้ำในทุกไฟล์ `save*.php` (~18 ไฟล์) แทนที่จะใช้ shared include

6. **Debug Code ค้างอยู่ใน Production**  
   - `gen_pdf.php` มี `file_put_contents()` บันทึก debug log ลง `/logs/` directory
   - มีไฟล์ `debug_save_life.php`, `debug_life_check.php` ค้างอยู่

7. **Service Worker Cache ไม่ได้ versioning ตาม content**  
   - `CACHE_NAME = 'csims-cache-v1'` เป็น static string ไม่มีกลไก cache busting ที่เชื่อถือได้

8. **Session Timeout Hardcoded หลายจุด**  
   - `session_config.php` ตั้ง 1800 วินาที, `login.php` ก็ตั้ง 1800 วินาทีซ้ำ → ควรมีจุดเดียว

9. **ไม่มี Input Validation ระดับ API**  
   - API `searchData.php` รับ POST แล้วใส่ LIKE query ตรง แม้ใช้ prepared statement แต่ไม่ validate ความยาว/ประเภทข้อมูล

10. **Temp Files ไม่ถูก Cleanup**  
    - `api/incidentCheckList/tmp/` มี 113 items ค้างอยู่

---

## 7) Suggestions

### 🏗️ Architecture

1. **แยก Helper Functions เป็น Shared File**  
   - สร้าง `includes/helpers.php` รวม `jsonResponse()`, `normalizeValue()`, `saveBase64ToFile()` ไว้จุดเดียว ลดการ duplicate ใน 18+ ไฟล์

2. **แยก Modal ออกเป็น Component ย่อย**  
   - Modal ไฟล์เดียว 400KB+ ควรแยกเป็น section/component ที่ include กัน เช่น `modal_bomb_section1.php`, `modal_bomb_section2.php`

3. **ใช้ Router/Framework เบาๆ**  
   - พิจารณาใช้ micro-framework เช่น Slim หรือ Flight เพื่อจัดการ routing, middleware (auth, rate limit) แทนการ require ซ้ำทุกไฟล์

### 🔒 Security

4. **เพิ่ม `.gitignore`** — ป้องกัน `.env`, `vendor/`, `uploads/`, `tmp/` ถูก commit

5. **ย้าย DB credentials ไป `.env`** — ใช้ `parse_ini_file()` หรือ `vlucas/phpdotenv` อ่านค่าจาก `.env`

6. **เพิ่ม CSRF Token** — สร้าง token ใน session แล้วตรวจทุก POST request

7. **เพิ่ม Rate Limiting** — จำกัดจำนวน login/OTP attempts ต่อ IP ต่อช่วงเวลา

### ⚡ Performance

8. **Lazy Load Modal** — โหลด modal content ผ่าน AJAX เมื่อจำเป็น แทนที่จะ render HTML ทั้งหมดตั้งแต่โหลดหน้า

9. **ตั้ง Cron Job ลบ Temp Files** — `api/incidentCheckList/tmp/` ควรถูก cleanup อัตโนมัติ

10. **Database Indexing** — ตรวจสอบว่ามี index บน column ที่ใช้ filter บ่อย เช่น `complaints_type`, `provinceID`, `statusDelete`

### 📋 Code Quality

11. **ลบ Debug/Backup Files** — `debug_*.php`, `*.bak_*`, `TEMP_DO_NOT_USE.txt`, `_temp_check.js`

12. **เพิ่ม Error Logging แบบรวมศูนย์** — ใช้ Monolog หรือ custom logger แทน `error_log()` กระจายทั่ว

13. **เขียน Unit Test** — เริ่มจาก API หลัก (`save*.php`, `searchData.php`) เพื่อป้องกัน regression

14. **อัปเดต Service Worker Strategy** — ใช้ Workbox หรือ stale-while-revalidate strategy แทน cache-first เพื่อให้ข้อมูลทันสมัยขึ้น

---

> **สรุป:** CSIMS เป็นระบบที่ครอบคลุมงานสืบสวนนิติวิทยาศาสตร์ได้ดี รองรับ 8 ประเภทคดี + ห้องปฏิบัติการ + Offline จุดที่ควรปรับปรุงเร่งด่วนคือ **Security (CSRF, Rate Limit, Credentials)** และ **Code Maintainability (ลด Duplication, แยก Modal)**
