// js/offline-db.js
const db = new Dexie("CSIMS_OfflineDB");

// กำหนดตาราง เช่น เก็บข้อมูลเหตุการณ์ (incidents)
db.version(1).stores({
    pendingActions: '++id, url, method, data, timestamp'
});

// version 2: เพิ่ม checklistQueue สำหรับเก็บ offline checklist (รองรับ Blob ไม่จำกัดขนาด)
db.version(2).stores({
    pendingActions: '++id, url, method, data, timestamp',
    checklistQueue: '++id, url, saved_at'
});

// ฟังก์ชันสำหรับเก็บข้อมูลลง Dexie เมื่อ Offline
async function saveDataOffline(url, method, data) {
    await db.pendingActions.add({
        url: url,
        method: method,
        data: data,
        timestamp: new Date().getTime()
    });
    console.log("บันทึกข้อมูลลงในเครื่องแล้ว (Offline Mode)");
}

// ฟังก์ชันสำหรับส่งข้อมูลที่ค้างอยู่เมื่อกลับมา Online
async function syncPendingData() {
    const pending = await db.pendingActions.toArray();
    if (pending.length === 0) return;

    for (const action of pending) {
        try {
            const response = await fetch(action.url, {
                method: action.method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(action.data)
            });

            if (response.ok) {
                // ถ้าส่งสำเร็จ ให้ลบออกจาก Dexie
                await db.pendingActions.delete(action.id);
                console.log("Sync ข้อมูลสำเร็จ:", action.url);
            }
        } catch (err) {
            console.error("การส่งข้อมูลล้มเหลว จะลองใหม่เมื่อออนไลน์ครั้งหน้า");
            break; // หยุดถ้ายังส่งไม่ได้
        }
    }
}

// ตรวจจับสถานะออนไลน์เพื่อเริ่ม Sync
window.addEventListener('online', syncPendingData);