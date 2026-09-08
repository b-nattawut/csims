<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

// 1. ตรวจสอบสสิทธิ์ผู้ใช้งาน (Security Check)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบก่อนทำรายการ']);
    exit;
}

$role_label = $_POST['role_label'] ?? ''; // "หัวหน้าทีม", "ช่างภาพ"
$user_id = $_POST['user_id'] ?? '';
$header_id = $_POST['header_id'] ?? '';

// 1. ดึงสถานะจาก Header หลักเพื่อเช็คว่าเป็น Read-only หรือไม่
$stmtHeader = $pdo->prepare("SELECT status FROM staff_assessment_header WHERE id = ?");
$stmtHeader->execute([$header_id]);
$header = $stmtHeader->fetch();
$is_completed = ($header['status'] === 'COMPLETED');

// 2. แปลง Role Label (ภาษาไทย) เป็น ENUM ใน DB
$role_map = [
    'หัวหน้าทีม' => 'LEADER',
    'ช่างภาพ' => 'PHOTOGRAPHER',
    'ผู้ทำแผนที่' => 'MAP_MAKER',
    'ผู้ค้นหาวัตถุพยาน' => 'SEARCHER',
    'ผู้ตรวจเก็บวัตถุพยาน' => 'COLLECTOR'
];
$role_type = $role_map[$role_label] ?? 'LEADER';

// 3. ดึงข้อมูลคะแนนเดิม (ถ้ามี) โดย Join Results กับ Details
$savedData = [];
$stmtSaved = $pdo->prepare("
    SELECT d.criteria_id, d.score, d.comment, d.remark 
    FROM staff_assessment_results r
    JOIN staff_assessment_details d ON r.id = d.result_id
    WHERE r.header_id = ? AND r.user_id = ? AND r.role_type = ?
");
$stmtSaved->execute([$header_id, $user_id, $role_type]);
$details = $stmtSaved->fetchAll(PDO::FETCH_ASSOC);
foreach ($details as $d) {
    $savedData[$d['criteria_id']] = $d;
}

// 4. ฟังก์ชันสร้างแถว (ปรับ ID ให้ตรงกับระบบ Handwriting)
function generateRow($id, $savedData, $is_completed, $defaultRemark = '', $isStatic = false) {
    $score = $savedData[$id]['score'] ?? null;
    $comment = $savedData[$id]['comment'] ?? '';
    $remark = $isStatic ? $defaultRemark : ($savedData[$id]['remark'] ?? $defaultRemark);
    $disabled = $is_completed ? 'disabled' : '';

    $html = '';
    // 1-4: Radio Scores 0, 1, 2, 3
    for ($i = 0; $i <= 3; $i++) {
        $checked = ($score !== null && (int)$score === $i) ? 'checked' : '';
        $html .= "
        <td class='text-center js-click-score' style='vertical-align: middle; cursor: pointer;'>
            <input type='radio' name='score[$id]' value='$i' required $checked $disabled 
                   style='transform: scale(1.3); pointer-events: none;'>
        </td>";
    }

    // 5: ความเห็น (Comment)
    $html .= "
    <td>
        <div class='input-group input-group-seamless'>
            <textarea id='comment_$id' name='comment[$id]' class='form-control form-control-sm' rows='1' $disabled>$comment</textarea>
            " . (!$is_completed ? "<button type='button' class='btn btn-hw-open btn-hw-dynamic' data-hw-targets='comment_$id' title='เขียนด้วยลายมือ'><i class='fas fa-pen'></i></button>" : "") . "
        </div>
    </td>";

    // 6: หมายเหตุ (Remark) - ถ้ามีค่า Default และเป็น Mode เสร็จสมบูรณ์ ให้โชว์เป็น Text
    // if ($defaultRemark !== '' && ($is_completed || empty($savedData[$id]['remark']))) {
    //      // กรณีข้อ 19, 20 ของหัวหน้าทีมที่มีข้อความกำกับ
    //      $displayRemark = ($savedData[$id]['remark'] ?? '') ?: $defaultRemark;
    //      $html .= "
    //      <td class='text-start small text-muted' style='line-height:1.2;'>
    //         " . nl2br(htmlspecialchars($displayRemark)) . "
    //         <input type='hidden' name='remark[$id]' value='" . htmlspecialchars($displayRemark) . "'>
    //      </td>";
    // } else {
    //     $html .= "
    //     <td>
    //         <div class='input-group input-group-seamless'>
    //             <textarea id='remark_$id' name='remark[$id]' class='form-control form-control-sm' rows='1' $disabled>$remark</textarea>
    //             " . (!$is_completed ? "<button type='button' class='btn btn-hw-open btn-hw-dynamic' data-hw-targets='remark_$id' title='เขียนด้วยลายมือ'><i class='fas fa-pen'></i></button>" : "") . "
    //         </div>
    //     </td>";
    // }

    // 6: หมายเหตุ (Remark) 
    if ($isStatic) {
        // แสดงเป็นข้อความแก้ไขไม่ได้ (เหมือน F-CS-01) และส่งค่าผ่าน hidden input
        $html .= "
        <td class='text-start small text-muted' style='line-height:1.2; vertical-align: middle;'>
            " . nl2br(htmlspecialchars($remark)) . "
            <input type='hidden' name='remark[$id]' value='" . htmlspecialchars($remark) . "'>
        </td>";
    } else {
        // แสดงเป็น Textarea ให้แก้ไขได้ปกติ
        $html .= "
        <td>
            <div class='input-group input-group-seamless'>
                <textarea id='remark_$id' name='remark[$id]' class='form-control form-control-sm' rows='1' $disabled>$remark</textarea>
                " . (!$is_completed ? "<button type='button' class='btn btn-hw-open btn-hw-dynamic' data-hw-targets='remark_$id'><i class='fas fa-pen'></i></button>" : "") . "
            </div>
        </td>";
    }

    return $html;
}

$table_html = '';

if ($role_type === 'LEADER') {
    $table_html .= '
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">1. การเตรียมตัว ณ ที่ตั้ง</td></tr>
        <tr class="table-light"><td colspan="5" class="fw-bold ps-4">1.1 การเตรียมความพร้อม</td><td></td><td class="text-start small text-muted">F-CS-02</td></tr>
        <tr><td class="ps-4 text-muted">- การกำกับดูแลความพร้อม และจำนวนบุคลากร</td>' . generateRow(1, $savedData, $is_completed) . '</tr>
        <tr><td class="ps-4 text-muted">- การกำกับดูแลความพร้อม และจำนวนวัสดุ เครื่องมืออุปกรณ์ รถยนต์</td>' . generateRow(2, $savedData, $is_completed) . '</tr>
        <tr class="table-light"><td colspan="5" class="fw-bold ps-4">1.2 การรับแจ้งเหตุ และการวางแผนก่อนการตรวจ</td><td></td><td class="text-start small text-muted">F-CS-01</td></tr>
        <tr><td class="ps-4 text-muted">- การประสานข้อมูลเบื้องต้น และพฤติการณ์ของคดี จาก พงส.</td>' . generateRow(3, $savedData, $is_completed) . '</tr>
        
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">2. การเตรียมตัวเมื่อเข้าใกล้สถานที่เกิดเหตุ</td></tr>
        <tr class="table-light"><td colspan="7" class="fw-bold ps-4">2.1 กำหนดจุดสั่งการ</td></tr>
        <tr><td class="ps-4 text-muted">- การสำรวจเพื่อกำหนดที่ตั้งจุดสั่งการ</td>' . generateRow(4, $savedData, $is_completed) . '</tr>
        <tr class="table-light"><td colspan="7" class="fw-bold ps-4">2.2 การป้องกันการปนเปื้อน</td></tr>
        <tr><td class="ps-4 text-muted">- การสวมใส่อุปกรณ์ป้องกันของหัวหน้าทีม และชุดตรวจ</td>' . generateRow(5, $savedData, $is_completed) . '</tr>
        
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">3. การรักษาความปลอดภัย และการป้องกันสถานที่เกิดเหตุ</td></tr>
        <tr><td class="ps-4 text-muted">- การกั้นสถานที่เกิดเหตุ</td>' . generateRow(6, $savedData, $is_completed) . '</tr>
        
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">4. การสำรวจสถานที่เกิดเหตุเบื้องต้น</td></tr>
        <tr><td class="ps-4 text-muted">- การสอบถามข้อมูล จาก พงส. ผู้เสียหาย / ผู้เห็นเหตุการณ์</td>' . generateRow(7, $savedData, $is_completed) . '</tr>
        <tr><td class="ps-4 text-muted">- การสำรวจสถานที่เกิดเหตุเบื้องต้น การกำหนดเส้นทางเดินของเจ้าหน้าที่</td>' . generateRow(8, $savedData, $is_completed) . '</tr>
        <tr><td class="ps-4 text-muted">- การวางวงแหวน หรือป้ายหมายเลขแสดงบริเวณที่พบวัตถุพยานที่มีขนาดเล็กสูญหายง่าย</td>' . generateRow(9, $savedData, $is_completed) . '</tr>
        
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">5. การประเมินพยานหลักฐานที่อาจพบในสถานที่เกิดเหตุ</td></tr>
        <tr><td class="ps-4 text-muted">- การวิเคราะห์วัตถุพยาน เช่น ทางเข้า-ออกของคนร้าย ร่องรอยการงัดแงะรื้อค้น ตำแหน่งที่พบศพ วัตถุพยานอื่น ๆ ที่ทำให้ทราบถึงพฤติการณ์ของคดี</td>' . generateRow(10, $savedData, $is_completed) . '</tr>
        
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">6. การเตรียมบรรยายสรุปสภาพของสถานที่เกิดเหตุ</td></tr>
        <tr><td class="ps-4 text-muted">- การบรรยายสรุปให้กับทีมตรวจ ตามที่มีการประเมินในข้อ 5</td>' . generateRow(11, $savedData, $is_completed) . '</tr>
        <tr><td class="ps-4 text-muted">- การแบ่งให้เจ้าหน้าที่ทำหน้าที่ต่าง ๆ ในทีม</td>' . generateRow(12, $savedData, $is_completed) . '</tr>
        
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">7. การถ่ายภาพสถานที่เกิดเหตุ</td></tr>
        <tr><td class="ps-4 text-muted">- การสั่งการให้ช่างภาพถ่ายภาพ</td>' . generateRow(13, $savedData, $is_completed) . '</tr>
        
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">8. การเตรียมแผนผังหรือร่างภาพสถานที่เกิดเหตุ</td></tr>
        <tr><td class="ps-4 text-muted">- การสั่งการให้ผู้ทำแผนที่จัดทำแผนผัง</td>' . generateRow(14, $savedData, $is_completed) . '</tr>
        
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">9. การดำเนินการค้นหาวัตถุพยานในสถานที่เกิดเหตุอย่างละเอียด</td></tr>
        <tr><td class="ps-4 text-muted">- การสั่งการให้ทีมค้นหาค้นหาวัตถุพยานโดยละเอียด</td>' . generateRow(15, $savedData, $is_completed) . '</tr>
        
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">10. การบันทึกและเก็บรวบรวมพยานหลักฐานในสถานที่เกิดเหตุ</td></tr>
        <tr><td class="ps-4 text-muted">- การควบคุมการบันทึกและเก็บรวบรวมพยานหลักฐานและบรรจุหีบห่อให้เป็นไปตามขั้นตอน</td>' . generateRow(16, $savedData, $is_completed) . '</tr>
        
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">11. การดำเนินการสำรวจสถานที่เกิดเหตุขั้นสุดท้าย</td></tr>
        <tr><td class="ps-4 text-muted">- การเข้าสำรวจสถานที่เกิดเหตุขั้นสุดท้าย</td>' . generateRow(17, $savedData, $is_completed) . '</tr>
        <tr><td class="ps-4 text-muted">- ตรวจสอบความเรียบร้อยของบุคลากร เครื่องมือ และบันทึกต่าง ๆ</td>' . generateRow(18, $savedData, $is_completed) . '</tr>
        
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">12. การส่งมอบสถานที่เกิดเหตุ</td></tr>
        <tr><td class="ps-4 text-muted">- การจัดทำบันทึกส่งมอบวัตถุพยานและส่งมอบคืนสถานที่เกิดเหตุ</td>' . generateRow(19, $savedData, $is_completed, "F-CS-11\nF-CS-08/09/10", true) . '</tr>
        
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">13. การจัดทำรายงานผลการตรวจสถานที่เกิดเหตุ</td></tr>
        <tr><td class="ps-4 text-muted">- มีการจัดทำรายงานผลการตรวจสถานที่เกิดเหตุ</td>' . generateRow(20, $savedData, $is_completed, "ตามแบบรายงานการตรวจเฉพาะคดี", true) . '</tr>
    ';

} else if ($role_type === 'PHOTOGRAPHER') {
    $table_html .= '
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">1. การเตรียมตัว ณ ที่ตั้ง</td></tr>
        <tr class="table-light"><td colspan="5" class="fw-bold ps-4">1.1 การเตรียมความพร้อม</td><td></td><td class="text-start small text-muted">F-CS-02</td></tr>
        <tr><td class="ps-4 text-muted">- การตรวจเช็คความพร้อมของอุปกรณ์ถ่ายภาพ</td>' . generateRow(1, $savedData, $is_completed) . '</tr>
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">2. การเตรียมตัวเมื่อเข้าใกล้สถานที่เกิดเหตุ</td></tr>
        <tr class="table-light"><td colspan="5" class="fw-bold ps-4">2.1 กำหนดจุดสั่งการ</td><td></td><td></td></tr>
        <tr><td class="ps-4 text-muted">- การตั้งวางเครื่องมืออุปกรณ์ที่เกี่ยวข้องที่จุดสั่งการ</td>' . generateRow(2, $savedData, $is_completed) . '</tr>
        <tr class="table-light"><td colspan="5" class="fw-bold ps-4">2.2 การป้องกันการปนเปื้อน</td><td></td><td></td></tr>
        <tr><td class="ps-4 text-muted">- การสวมใส่อุปกรณ์ป้องกันการปนเปื้อน</td>' . generateRow(3, $savedData, $is_completed) . '</tr>
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">3. การรักษาความปลอดภัย และการป้องกันสถานที่เกิดเหตุ</td></tr>
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">4. การสำรวจสถานที่เกิดเหตุเบื้องต้น</td></tr>
        <tr><td class="ps-4 text-muted">- การถ่ายภาพ สถานที่เกิดเหตุโดยรอบ</td>' . generateRow(4, $savedData, $is_completed) . '</tr>
        <tr><td class="ps-4 text-muted">- การถ่ายภาพหลังจากการวางวงแหวน หรือ ป้ายหมายเลข แสดงบริเวณที่พบวัตถุพยานขนาดเล็กที่สูญหายง่าย</td>' . generateRow(5, $savedData, $is_completed) . '</tr>
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">5. การประเมินพยานหลักฐานที่อาจพบในสถานที่เกิดเหตุ</td></tr>
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">6. การเตรียมบรรยายสรุปสภาพของสถานที่เกิดเหตุ</td></tr>
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">7. การถ่ายภาพสถานที่เกิดเหตุ</td></tr>
        <tr><td class="ps-4 text-muted">- ทักษะการถ่ายภาพ ระยะไกล กลาง ใกล้ และระยะใกล้แบบมีสเกล</td>' . generateRow(6, $savedData, $is_completed) . '</tr>
        <tr><td class="ps-4 text-muted">- คุณภาพของภาพถ่าย เช่น ความคมชัด เป็นต้น</td>' . generateRow(7, $savedData, $is_completed) . '</tr>
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">8. การเตรียมแผนผังหรือร่างภาพสถานที่เกิดเหตุ</td></tr>
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">9. การดำเนินการค้นหาวัตถุพยานในสถานที่เกิดเหตุอย่างละเอียด</td></tr>
        <tr><td class="ps-4 text-muted">- การถ่ายภาพ เมื่อพบวัตถุพยานเพิ่มเติม</td>' . generateRow(8, $savedData, $is_completed) . '</tr>
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">10. การบันทึกและเก็บรวบรวมพยานหลักฐานในสถานที่เกิดเหตุ</td></tr>
        <tr><td class="ps-4 text-muted">- การถ่ายภาพหีบห่อบรรจุวัตถุพยานทั้งหมด</td>' . generateRow(9, $savedData, $is_completed) . '</tr>
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">11. การดำเนินการสำรวจสถานที่เกิดเหตุขั้นสุดท้าย</td></tr>
        <tr><td class="ps-4 text-muted">- การถ่ายภาพสถานที่เกิดเหตุขั้นสุดท้าย</td>' . generateRow(10, $savedData, $is_completed) . '</tr>
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">12. การส่งมอบสถานที่เกิดเหตุ</td></tr>
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">13. การจัดทำรายงานผลการตรวจสถานที่เกิดเหตุ</td></tr>

    ';

} else if ($role_type === 'MAP_MAKER') {
    $table_html .= '
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">1. การเตรียมตัว ณ ที่ตั้ง</td></tr>
        <tr class="table-light"><td colspan="5" class="fw-bold ps-4">1.1 การเตรียมความพร้อม</td><td></td><td class="text-start small text-muted">F-CS-02</td></tr>
        <tr><td class="ps-4 text-muted">- การตรวจเช็คความพร้อมของอุปกรณ์ทำแผนที่</td>' . generateRow(1, $savedData, $is_completed) . '</tr>
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">2. การเตรียมตัวเมื่อเข้าใกล้สถานที่เกิดเหตุ</td></tr>
        <tr class="table-light"><td colspan="5" class="fw-bold ps-4">2.1 กำหนดจุดสั่งการ</td><td></td><td></td></tr>
        <tr><td class="ps-4 text-muted">- การตั้งวางเครื่องมืออุปกรณ์ที่เกี่ยวข้องที่จุดสั่งการ</td>' . generateRow(2, $savedData, $is_completed) . '</tr>
        <tr class="table-light"><td colspan="5" class="fw-bold ps-4">2.2 การป้องกันการปนเปื้อน</td><td></td><td></td></tr>
        <tr><td class="ps-4 text-muted">- การสวมใส่อุปกรณ์ป้องกันการปนเปื้อน</td>' . generateRow(3, $savedData, $is_completed) . '</tr>
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">3. การรักษาความปลอดภัย และการป้องกันสถานที่เกิดเหตุ</td></tr>
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">4. การสำรวจสถานที่เกิดเหตุเบื้องต้น</td></tr>
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">5. การประเมินพยานหลักฐานที่อาจพบในสถานที่เกิดเหตุ</td></tr>
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">6. การเตรียมบรรยายสรุปสภาพของสถานที่เกิดเหตุ</td></tr>
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">7. การถ่ายภาพสถานที่เกิดเหตุ</td></tr>
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">8. การเตรียมแผนผังหรือร่างภาพสถานที่เกิดเหตุ</td></tr>
        <tr><td class="ps-4 text-muted">- การดำเนินการทำแผนที่แบบหยาบ</td>' . generateRow(4, $savedData, $is_completed) . '</tr>
        <tr><td class="ps-4 text-muted">- การทำแผนที่ระบุตำแหน่งวัตถุพยาน แบบละเอียด เมื่อพบวัตถุพยาน</td>' . generateRow(5, $savedData, $is_completed) . '</tr>
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">9. การดำเนินการค้นหาวัตถุพยานในสถานที่เกิดเหตุอย่างละเอียด</td></tr>
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">10. การบันทึกและเก็บรวบรวมพยานหลักฐานในสถานที่เกิดเหตุ</td></tr>
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">11. การดำเนินการสำรวจสถานที่เกิดเหตุขั้นสุดท้าย</td></tr>
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">12. การส่งมอบสถานที่เกิดเหตุ</td></tr>
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">13. การจัดทำรายงานผลการตรวจสถานที่เกิดเหตุ</td></tr>
    ';

} else if ($role_type === 'SEARCHER') {
    $table_html .= '
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">1. การเตรียมตัว ณ ที่ตั้ง</td></tr>
        <tr class="table-light"><td colspan="5" class="fw-bold ps-4">1.1 การเตรียมความพร้อม</td><td></td><td class="text-start small text-muted">F-CS-02</td></tr>
        <tr><td class="ps-4 text-muted">- การตรวจเช็คความพร้อมของอุปกรณ์ที่เกี่ยวข้อง</td>' . generateRow(1, $savedData, $is_completed) . '</tr>
        <tr><td class="ps-4 text-muted">- การตรวจเช็คความพร้อมของเครื่องมือที่เกี่ยวข้อง</td>' . generateRow(2, $savedData, $is_completed) . '</tr>
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">2. การเตรียมตัวเมื่อเข้าใกล้สถานที่เกิดเหตุ</td></tr>
        <tr class="table-light"><td colspan="5" class="fw-bold ps-4">2.1 กำหนดจุดสั่งการ</td><td></td><td></td></tr>
        <tr><td class="ps-4 text-muted">- การตั้งวางเครื่องมืออุปกรณ์ที่เกี่ยวข้องที่จุดสั่งการ</td>' . generateRow(3, $savedData, $is_completed) . '</tr>
        <tr class="table-light"><td colspan="5" class="fw-bold ps-4">2.2 การป้องกันการปนเปื้อน</td><td></td><td></td></tr>
        <tr><td class="ps-4 text-muted">- การสวมใส่อุปกรณ์ป้องกันการปนเปื้อน</td>' . generateRow(4, $savedData, $is_completed) . '</tr>
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">3. การรักษาความปลอดภัย และการป้องกันสถานที่เกิดเหตุ</td></tr>
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">4. การสำรวจสถานที่เกิดเหตุเบื้องต้น</td></tr>
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">5. การประเมินพยานหลักฐานที่อาจพบในสถานที่เกิดเหตุ</td></tr>
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">6. การเตรียมบรรยายสรุปสภาพของสถานที่เกิดเหตุ</td></tr>
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">7. การถ่ายภาพสถานที่เกิดเหตุ</td></tr>
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">8. การเตรียมแผนผังหรือร่างภาพสถานที่เกิดเหตุ</td></tr>
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">9. การดำเนินการค้นหาวัตถุพยานในสถานที่เกิดเหตุอย่างละเอียด</td></tr>
        <tr><td class="ps-4 text-muted">- เลือกใช้วิธีการค้นหาวัตถุพยานอย่างเหมาะสม ตามหลักวิชาการ</td>' . generateRow(5, $savedData, $is_completed) . '</tr>
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">10. การบันทึกและเก็บรวบรวมพยานหลักฐานในสถานที่เกิดเหตุ</td></tr>
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">11. การดำเนินการสำรวจสถานที่เกิดเหตุขั้นสุดท้าย</td></tr>
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">12. การส่งมอบสถานที่เกิดเหตุ</td></tr>
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">13. การจัดทำรายงานผลการตรวจสถานที่เกิดเหตุ</td></tr>
    ';

} else if ($role_type === 'COLLECTOR') {
    $table_html .= '
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">1. การเตรียมตัว ณ ที่ตั้ง</td></tr>
        <tr class="table-light"><td colspan="5" class="fw-bold ps-4">1.1 การเตรียมความพร้อม</td><td></td><td class="text-start small text-muted">F-CS-02</td></tr>
        <tr><td class="ps-4 text-muted">- การตรวจเช็คความพร้อมของอุปกรณ์ที่เกี่ยวข้อง</td>' . generateRow(1, $savedData, $is_completed) . '</tr>
        <tr><td class="ps-4 text-muted">- การตรวจเช็คความพร้อมของเครื่องมือที่เกี่ยวข้อง</td>' . generateRow(2, $savedData, $is_completed) . '</tr>
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">2. การเตรียมตัวเมื่อเข้าใกล้สถานที่เกิดเหตุ</td></tr>
        <tr class="table-light"><td colspan="5" class="fw-bold ps-4">2.1 กำหนดจุดสั่งการ</td><td></td><td></td></tr>
        <tr><td class="ps-4 text-muted">- การตั้งวางเครื่องมืออุปกรณ์ที่เกี่ยวข้องที่จุดสั่งการ</td>' . generateRow(3, $savedData, $is_completed) . '</tr>
        <tr class="table-light"><td colspan="5" class="fw-bold ps-4">2.2 การป้องกันการปนเปื้อน</td><td></td><td></td></tr>
        <tr><td class="ps-4 text-muted">- การสวมใส่อุปกรณ์ป้องกันการปนเปื้อน</td>' . generateRow(4, $savedData, $is_completed) . '</tr>
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">3. การรักษาความปลอดภัย และการป้องกันสถานที่เกิดเหตุ</td></tr>
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">4. การสำรวจสถานที่เกิดเหตุเบื้องต้น</td></tr>
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">5. การประเมินพยานหลักฐานที่อาจพบในสถานที่เกิดเหตุ</td></tr>
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">6. การเตรียมบรรยายสรุปสภาพของสถานที่เกิดเหตุ</td></tr>
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">7. การถ่ายภาพสถานที่เกิดเหตุ</td></tr>
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">8. การเตรียมแผนผังหรือร่างภาพสถานที่เกิดเหตุ</td></tr>
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">9. การดำเนินการค้นหาวัตถุพยานในสถานที่เกิดเหตุอย่างละเอียด</td></tr>
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">10. การบันทึกและเก็บรวบรวมพยานหลักฐานในสถานที่เกิดเหตุ</td></tr>
        <tr><td class="ps-4 text-muted">- การป้องกันการปนเปื้อนของวัตถุพยาน</td>' . generateRow(5, $savedData, $is_completed) . '</tr>
        <tr><td class="ps-4 text-muted">- การเก็บวัตถุพยาน ถูกต้องตามหลักวิชาการ</td>' . generateRow(6, $savedData, $is_completed) . '</tr>
        <tr><td class="ps-4 text-muted">- การบรรจุหีบห่อวัตถุพยาน ถูกต้องตามหลักวิชาการ</td>' . generateRow(7, $savedData, $is_completed) . '</tr>
        <tr><td class="ps-4 text-muted">- การจัดทำบันทึกตามแบบการตรวจเก็บและส่งมอบวัตถุพยาน</td>' . generateRow(8, $savedData, $is_completed) . '</tr>
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">11. การดำเนินการสำรวจสถานที่เกิดเหตุขั้นสุดท้าย</td></tr>
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">12. การส่งมอบสถานที่เกิดเหตุ</td></tr>
        <tr class="table-secondary"><td colspan="7" class="fw-bold ps-3">13. การจัดทำรายงานผลการตรวจสถานที่เกิดเหตุ</td></tr>

    ';
}

$max_score_map = [
    'LEADER' => 60,
    'PHOTOGRAPHER' => 30,
    'MAP_MAKER' => 15,
    'SEARCHER' => 15,
    'COLLECTOR' => 24
];
$current_max_score = $max_score_map[$role_type] ?? 60;

// 6. กำหนด UI Config ส่งกลับไปให้ JS
$mode = (count($details) > 0) ? ($is_completed ? 'COMPLETED' : 'EDIT') : 'NEW';
$ui_config = [
    'NEW' => ['title' => 'ประเมินความสามารถผู้ปฏิบัติหน้าที่', 'header_class' => 'bg-primary', 'icon' => 'fa-user-check', 'btn_class' => 'btn-primary'],
    'EDIT' => ['title' => 'แก้ไขผลการประเมิน', 'header_class' => 'edit-mode', 'icon' => 'fa-edit', 'btn_class' => 'btn-warning text-dark'],
    'COMPLETED' => ['title' => 'รายละเอียดผลการประเมิน', 'header_class' => 'completed-mode', 'icon' => 'fa-check-circle', 'btn_class' => 'd-none']
];

echo json_encode([
    'status' => 'success',
    'table_html' => $table_html,
    'ui' => $ui_config[$mode],
    'mode' => $mode,  
    'max_score' => $current_max_score, 
    'is_completed' => $is_completed
]);