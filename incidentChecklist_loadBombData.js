// =========================================================
// LOAD SAVED BOMB DATA (โหลดข้อมูลระเบิดที่บันทึกไว้กลับมาแก้ไข)
// =========================================================
function loadBombData(incidentId, onComplete) {
    $.ajax({
        url: '/csims/api/incidentCheckList/getBombData.php',
        type: 'GET',
        dataType: 'json',
        data: {
            incident_id: incidentId
        },
        success: function(response) {
            if (!response.success || !response.data) {
                console.warn('No bomb data found for incident_id:', incidentId);
                return;
            }

            const d = response.data;
            const gi = d.general_info || {};
            const scene = d.scene_info || {};
            const ir = d.inspection_results || {};

            // ==================== แสดงจำนวนการแก้ไข ====================
            if (response.edit_info) {
                const ei = response.edit_info;
                $('#editCountBomb').text(ei.count_edit || 0);
                $('#editCountBombPdf').text(ei.count_edit || 0);
                if (ei.edit_date) {
                    const ed = new Date(ei.edit_date);
                    const dd = String(ed.getDate()).padStart(2, '0');
                    const mm = String(ed.getMonth() + 1).padStart(2, '0');
                    const yyyy = ed.getFullYear() + 543;
                    const hh = String(ed.getHours()).padStart(2, '0');
                    const mi = String(ed.getMinutes()).padStart(2, '0');
                    $('#editDateBomb').text(dd + '/' + mm + '/' + yyyy + ' ' + hh + ':' + mi + ' น.');
                } else {
                    $('#editDateBomb').text('-');
                }
                $('#editInfoBomb').removeClass('d-none');
                $('#editInfoBombPdf').removeClass('d-none');
            }

            // ใช้ฟังก์ชัน loadBombDataToModal ที่มีอยู่แล้วใน modal_bomb.php
            if (typeof loadBombDataToModal === 'function') {
                loadBombDataToModal(incidentId, {
                    showStandardModal: false,
                    onLoaded: function(data) {
                        console.log('Bomb data loaded via loadBombDataToModal:', data);
                        if (typeof onComplete === 'function') {
                            onComplete(data);
                        }
                    }
                });
            } else {
                console.error('loadBombDataToModal function not found');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading bomb data:', error);
        }
    });
}
