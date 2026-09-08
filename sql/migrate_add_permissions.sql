-- =============================================
-- Migration: เพิ่ม permissions ที่ขาดสำหรับทุกเมนู
-- รันไฟล์นี้บน DB ที่มีอยู่แล้ว (ใช้ INSERT IGNORE เพื่อไม่ซ้ำ)
-- =============================================

INSERT IGNORE INTO `permissions` (`name`, `module`, `description`) VALUES
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
-- ให้ Admin ได้สิทธิ์ใหม่ทั้งหมดอัตโนมัติ
-- =============================================
INSERT IGNORE INTO `role_permissions` (`role_name`, `permission_id`)
SELECT 'admin', id FROM `permissions`
WHERE id NOT IN (SELECT permission_id FROM role_permissions WHERE role_name = 'admin');

-- =============================================
-- ให้ Supervisor ได้สิทธิ์ใหม่ (ยกเว้น user, permission)
-- =============================================
INSERT IGNORE INTO `role_permissions` (`role_name`, `permission_id`)
SELECT 'supervisor', id FROM `permissions`
WHERE `module` NOT IN ('user', 'permission')
  AND id NOT IN (SELECT permission_id FROM role_permissions WHERE role_name = 'supervisor');

-- =============================================
-- ให้ User ได้สิทธิ์ view + create (ยกเว้น user, permission)
-- =============================================
INSERT IGNORE INTO `role_permissions` (`role_name`, `permission_id`)
SELECT 'user', id FROM `permissions`
WHERE (`name` LIKE '%.view' OR `name` LIKE '%.create')
  AND `module` NOT IN ('user', 'permission')
  AND id NOT IN (SELECT permission_id FROM role_permissions WHERE role_name = 'user');
