-- =============================================
-- Permission System Tables for CSIMS
-- =============================================

-- 1. ตาราง permissions: เก็บรายการสิทธิ์ทั้งหมด
CREATE TABLE IF NOT EXISTS `permissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE COMMENT 'ชื่อสิทธิ์ เช่น incident.view, incident.create',
    `module` VARCHAR(50) NOT NULL COMMENT 'กลุ่มโมดูล เช่น incident, checklist, evidence',
    `description` VARCHAR(255) DEFAULT NULL COMMENT 'คำอธิบายสิทธิ์',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. ตาราง role_permissions: เก็บว่า role ไหนมีสิทธิ์อะไรบ้าง
CREATE TABLE IF NOT EXISTS `role_permissions` (
    `role_name` VARCHAR(30) NOT NULL COMMENT 'ชื่อ role ตรงกับ users.role (admin, supervisor, user)',
    `permission_id` INT NOT NULL,
    PRIMARY KEY (`role_name`, `permission_id`),
    FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Seed: เพิ่มรายการสิทธิ์เริ่มต้น
-- =============================================
INSERT INTO `permissions` (`name`, `module`, `description`) VALUES
-- รับแจ้งเหตุ
('incident.view',       'incident',     'ดูรายการรับแจ้งเหตุ'),
('incident.create',     'incident',     'สร้างรายการรับแจ้งเหตุ'),
('incident.edit',       'incident',     'แก้ไขรายการรับแจ้งเหตุ'),
('incident.delete',     'incident',     'ลบรายการรับแจ้งเหตุ'),

-- รายการตรวจ (Checklist)
('checklist.view',      'checklist',    'ดูรายการตรวจ'),
('checklist.create',    'checklist',    'สร้างรายการตรวจ'),
('checklist.edit',      'checklist',    'แก้ไขรายการตรวจ'),
('checklist.delete',    'checklist',    'ลบรายการตรวจ'),

-- วัตถุพยาน (Evidence)
('evidence.view',       'evidence',     'ดูวัตถุพยาน'),
('evidence.create',     'evidence',     'สร้างวัตถุพยาน'),
('evidence.edit',       'evidence',     'แก้ไขวัตถุพยาน'),
('evidence.delete',     'evidence',     'ลบวัตถุพยาน'),

-- รายงาน
('report.view',         'report',       'ดูรายงาน'),
('report.export',       'report',       'ส่งออกรายงาน'),

-- จัดการผู้ใช้
('user.view',           'user',         'ดูข้อมูลผู้ใช้'),
('user.create',         'user',         'สร้างผู้ใช้ใหม่'),
('user.edit',           'user',         'แก้ไขข้อมูลผู้ใช้'),
('user.delete',         'user',         'ลบผู้ใช้'),

-- กำหนดสิทธิ์
('permission.manage',   'permission',   'จัดการสิทธิ์การเข้าถึง'),

-- ร่างรายงาน
('incident_report.view',    'incident_report',    'ดูร่างรายงาน'),
('incident_report.create',  'incident_report',    'สร้างร่างรายงาน'),
('incident_report.edit',    'incident_report',    'แก้ไขร่างรายงาน'),
('incident_report.delete',  'incident_report',    'ลบร่างรายงาน'),

-- ขอขยายเวลาออกรายงาน
('report_extension.view',   'report_extension',   'ดูคำขอขยายเวลา'),
('report_extension.create', 'report_extension',   'สร้างคำขอขยายเวลา'),
('report_extension.edit',   'report_extension',   'แก้ไขคำขอขยายเวลา'),
('report_extension.delete', 'report_extension',   'ลบคำขอขยายเวลา'),

-- บันทึกรายงานภาคสนาม
('field_visit_log.view',    'field_visit_log',    'ดูรายงานภาคสนาม'),
('field_visit_log.create',  'field_visit_log',    'สร้างรายงานภาคสนาม'),
('field_visit_log.edit',    'field_visit_log',    'แก้ไขรายงานภาคสนาม'),
('field_visit_log.delete',  'field_visit_log',    'ลบรายงานภาคสนาม'),

-- แดชบอร์ด
('dashboard.view',          'dashboard',          'ดูแดชบอร์ด'),

-- ใช้งานเครื่องมือทั่วไป
('equipment_usage.view',    'equipment_usage',    'ดูบันทึกการใช้งานเครื่องมือทั่วไป'),
('equipment_usage.create',  'equipment_usage',    'สร้างบันทึกการใช้งานเครื่องมือทั่วไป'),
('equipment_usage.edit',    'equipment_usage',    'แก้ไขบันทึกการใช้งานเครื่องมือทั่วไป'),
('equipment_usage.delete',  'equipment_usage',    'ลบบันทึกการใช้งานเครื่องมือทั่วไป'),

-- ใช้งานคอมพิวเตอร์
('computer_usage.view',     'computer_usage',     'ดูบันทึกการใช้งานคอมพิวเตอร์'),
('computer_usage.create',   'computer_usage',     'สร้างบันทึกการใช้งานคอมพิวเตอร์'),
('computer_usage.edit',     'computer_usage',     'แก้ไขบันทึกการใช้งานคอมพิวเตอร์'),
('computer_usage.delete',   'computer_usage',     'ลบบันทึกการใช้งานคอมพิวเตอร์'),

-- ซ่อมบำรุง/สอบเทียบ
('maintenance.view',        'maintenance',        'ดูแผนซ่อมบำรุง/สอบเทียบ'),
('maintenance.create',      'maintenance',        'สร้างแผนซ่อมบำรุง/สอบเทียบ'),
('maintenance.edit',        'maintenance',        'แก้ไขแผนซ่อมบำรุง/สอบเทียบ'),
('maintenance.delete',      'maintenance',        'ลบแผนซ่อมบำรุง/สอบเทียบ'),

-- ควบคุมอุณหภูมิ
('temperature.view',        'temperature',        'ดูบันทึกการควบคุมอุณหภูมิ'),
('temperature.create',      'temperature',        'สร้างบันทึกการควบคุมอุณหภูมิ'),
('temperature.edit',        'temperature',        'แก้ไขบันทึกการควบคุมอุณหภูมิ'),
('temperature.delete',      'temperature',        'ลบบันทึกการควบคุมอุณหภูมิ'),

-- สารเคมี (คลัง/เตรียม/ทดสอบ/ตรวจความพร้อม)
('chemical.view',           'chemical',           'ดูข้อมูลสารเคมี'),
('chemical.create',         'chemical',           'สร้างข้อมูลสารเคมี'),
('chemical.edit',           'chemical',           'แก้ไขข้อมูลสารเคมี'),
('chemical.delete',         'chemical',           'ลบข้อมูลสารเคมี'),

-- ความพร้อมเจ้าหน้าที่
('readiness.view',          'readiness',          'ดูบันทึกความพร้อมเจ้าหน้าที่'),
('readiness.create',        'readiness',          'สร้างบันทึกความพร้อมเจ้าหน้าที่'),
('readiness.edit',          'readiness',          'แก้ไขบันทึกความพร้อมเจ้าหน้าที่'),
('readiness.delete',        'readiness',          'ลบบันทึกความพร้อมเจ้าหน้าที่'),

-- ประเมินความสามารถเจ้าหน้าที่
('staff_assessment.view',   'staff_assessment',   'ดูแบบประเมินความสามารถ'),
('staff_assessment.create', 'staff_assessment',   'สร้างแบบประเมินความสามารถ'),
('staff_assessment.edit',   'staff_assessment',   'แก้ไขแบบประเมินความสามารถ'),
('staff_assessment.delete', 'staff_assessment',   'ลบแบบประเมินความสามารถ'),

-- Master Data (เครื่องมือ/คอม/อุณหภูมิ/สารเคมี)
('master_data.view',        'master_data',        'ดู Master Data'),
('master_data.create',      'master_data',        'สร้าง Master Data'),
('master_data.edit',        'master_data',        'แก้ไข Master Data'),
('master_data.delete',      'master_data',        'ลบ Master Data');

-- =============================================
-- Seed: กำหนดสิทธิ์เริ่มต้นให้แต่ละ role
-- =============================================

-- Admin: ได้ทุกสิทธิ์
INSERT INTO `role_permissions` (`role_name`, `permission_id`)
SELECT 'admin', id FROM `permissions`;

-- Supervisor: ได้ทุกอย่างยกเว้น จัดการผู้ใช้ และ กำหนดสิทธิ์
INSERT INTO `role_permissions` (`role_name`, `permission_id`)
SELECT 'supervisor', id FROM `permissions`
WHERE `module` NOT IN ('user', 'permission');

-- User: ดูและสร้างได้ แต่ลบไม่ได้
INSERT INTO `role_permissions` (`role_name`, `permission_id`)
SELECT 'user', id FROM `permissions`
WHERE `name` LIKE '%.view' OR `name` LIKE '%.create'
  AND `module` NOT IN ('user', 'permission');
