-- สิทธิ์เมนูการตรวจร่างรายงาน (ไม่สร้างตารางใหม่ — ใช้ permissions / role_permissions เดิม)
INSERT IGNORE INTO `permissions` (`name`, `module`, `description`) VALUES
('report_review.view',    'report_review',    'ดูการตรวจร่างรายงาน'),
('report_review.create',  'report_review',    'กำหนดผู้ตรวจร่างรายงาน'),
('report_review.edit',    'report_review',    'อนุมัติ/ย้อนกลับการตรวจร่างรายงาน'),
('report_review.delete',  'report_review',    'ลบข้อมูลการตรวจร่างรายงาน');

INSERT IGNORE INTO `role_permissions` (`role_name`, `permission_id`)
SELECT 'admin', id FROM `permissions`
WHERE `module` = 'report_review'
  AND id NOT IN (SELECT permission_id FROM role_permissions WHERE role_name = 'admin');

INSERT IGNORE INTO `role_permissions` (`role_name`, `permission_id`)
SELECT 'supervisor', id FROM `permissions`
WHERE `module` = 'report_review'
  AND id NOT IN (SELECT permission_id FROM role_permissions WHERE role_name = 'supervisor');

INSERT IGNORE INTO `role_permissions` (`role_name`, `permission_id`)
SELECT 'user', id FROM `permissions`
WHERE `module` = 'report_review'
  AND (`name` LIKE '%.view' OR `name` LIKE '%.create' OR `name` LIKE '%.edit')
  AND id NOT IN (SELECT permission_id FROM role_permissions WHERE role_name = 'user');
