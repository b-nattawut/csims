// service-worker.js

const CACHE_NAME = 'csims-cache-v1';

// รายชื่อไฟล์ที่ต้องการให้ใช้งาน Offline ได้
const ASSETS_TO_CACHE = [
    './dashboard.php',
    './login.html',
    './css/bootstrap.min.css',
    './css/style.css',
    './js/dexie.js',
    './js/offline-db.js',
    
    // --- 2. การจัดการคดี ---
    './incident.php',
    './incidentDetail.php',
    './incidentChecklist.php',
    './incidentReport.php',

    // --- 3. การจัดการข้อมูลห้องปฏิบัติการ ---
    './trans_equipment_usage.php',
    './trans_computer_usage.php',
    './trans_equipment_log.php',
    './trans_maintenance_plan.php',
    './trans_temperature_record.php',
    './chemical_inventory.php',
    './trans_chemical_preparation.php',
    './trans_chemical_validation.php',
    // './trans_latent_fingerprint_chemical_test.php',
    './trans_staff_assessment.php',
    './trans_staff_assessment_detail.php',

    // --- 4. การจัดการข้อมูล Master Data ---
    './master_equipment_list.php',
    './master_computer_list.php',
    './master_temperature_device.php',
    './master_chemical_list.php',

    // --- ข้อมูลผู้ใช้งาน ---
    './user_profile.php'
];

// ติดตั้งและเริ่มเก็บ Cache
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            // ใช้ map เพื่อวนลูป cache ทีละไฟล์
            return Promise.all(
                ASSETS_TO_CACHE.map((url) => {
                    return cache.add(url).catch((error) => {
                        console.error(`[SW] ไม่สามารถ Cache ไฟล์ได้: ${url}`, error);
                    });
                })
            );
        })
    );
});

// กลยุทธ์การดึงข้อมูล: Network First (ดึงเน็ตก่อน ถ้าไม่มีค่อยใช้ Cache)
self.addEventListener('fetch', (event) => {
    event.respondWith(
        fetch(event.request).catch(() => {
            return caches.match(event.request);
        })
    );
});