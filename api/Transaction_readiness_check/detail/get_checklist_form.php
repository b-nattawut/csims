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

// 1. รับค่า Category จาก AJAX
$category = $_POST['category'] ?? '';
$header_id = $_POST['header_id'] ?? '';

// 2. ตรวจสอบข้อมูลที่ส่งมา
if (empty($category) || empty($header_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing parameters']);
    exit;
}

// ----------------------------------------------------------------------------------
// 2. ดึงข้อมูล Header เพื่อเอามาแสดงในส่วน Info ของ Modal
// ----------------------------------------------------------------------------------
$stmtHead = $pdo->prepare("SELECT * FROM trans_readiness_check_header WHERE id = ? AND delete_token = 0");
$stmtHead->execute([$header_id]);
$header = $stmtHead->fetch(PDO::FETCH_ASSOC);

if (!$header) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูล Header']);
    exit;
}

$is_completed = ($header['status'] === 'COMPLETED');

// ดึงข้อมูลผลตรวจเดิมจาก 2 ตารางที่สร้างไว้ (Results -> Details)
$savedData = [];
$stmtSaved = $pdo->prepare("
    SELECT d.item_id, d.score, d.correction, d.remark 
    FROM trans_readiness_check_results r
    JOIN trans_readiness_check_details d ON r.id = d.result_id
    WHERE r.header_id = ? AND r.category = ?
");
$stmtSaved->execute([$header_id, $category]);
$details = $stmtSaved->fetchAll(PDO::FETCH_ASSOC);

foreach ($details as $d) {
    $savedData[$d['item_id']] = $d; // Map ไว้เรียกใช้ตาม item_id ใน loop
}

// 1. ตรวจสอบโหมดปัจจุบัน
$is_checked = (count($details) > 0); // มีข้อมูลเดิมไหม
$mode = 'NEW';
if ($is_completed) {
    $mode = 'COMPLETED';
} else if ($is_checked) {
    $mode = 'EDIT';
}

// 2. กำหนด UI สไตล์ตามโหมด 
$ui_config = [
    'COMPLETED' => [
        'title' => 'รายละเอียดผลการตรวจสอบ (เสร็จสมบูรณ์)',
        'header_class' => 'completed-mode',
        'alert_class' => 'alert-success',
        'icon' => 'fa-circle-check',
        'btn_text' => '', // ไม่โชว์ปุ่ม
        'user_icon_class' => 'text-success'
    ],
    'EDIT' => [
        'title' => 'แก้ไขผลการตรวจสอบ',
        'header_class' => 'edit-mode',
        'alert_class' => 'alert-warning',
        'icon' => 'fa-edit',
        'btn_text' => '<i class="fas fa-save me-2"></i> บันทึกข้อมูล',
        'btn_class' => 'btn-warning text-dark',
        'user_icon_class' => 'text-dark'
    ],
    'NEW' => [
        'title' => 'เริ่มตรวจสอบความพร้อม',
        'header_class' => 'bg-primary text-white',
        'alert_class' => 'alert-primary',
        'icon' => 'fa-clipboard-check',
        'btn_text' => '<i class="fas fa-save me-2"></i> บันทึกข้อมูล',
        'btn_class' => 'btn-primary',
        'user_icon_class' => 'text-primary'
    ]
];

$current_ui = $ui_config[$mode];

// ----------------------------------------------------------------------------------
// 3. ฟังก์ชันช่วยสร้าง Radio (รับค่า savedValue มาเช็คด้วย)
// ----------------------------------------------------------------------------------
function renderRadioCell($id, $val, $savedValue, $isDisabled = false)
{
    // ตรวจสอบว่าต้องติ๊กเลือกหรือไม่
    $checked = ($savedValue !== null && (string)$savedValue === (string)$val) ? 'checked' : '';
    $disabledAttr = $isDisabled ? 'disabled' : '';

    return '
    <td class="text-center js-click-score" style="vertical-align: middle; cursor: ' . ($isDisabled ? 'default' : 'pointer') . ';">
        <input type="radio" name="result[' . $id . ']" value="' . $val . '" required ' . $checked . ' ' . $disabledAttr . '
               style="transform: scale(1.5); cursor: ' . ($isDisabled ? 'default' : 'pointer') . ';">
    </td>';
}

// ฟังก์ชันคำนวณจำนวนแถวของ details ตามจำนวน <br/>
function calculateTextAreaRows($details_text) {
    // นับจำนวนการขึ้นบรรทัดใหม่จาก <br> หรือ <br/>
    $line_breaks = substr_count(strtolower($details_text), '<br');
    
    // ตั้งค่าเริ่มต้น: 2 แถว (รวมหัวข้อภาษาไทยตัวหนา) 
    // และบวกเพิ่มตามจำนวนบรรทัดในรายละเอียด
    $rows = $line_breaks + 2; 

    // จำกัดขั้นต่ำที่ 2 และสูงสุดไม่ควรเกิน 6 (เพื่อความสวยงาม ไม่ให้ยาวเกินไป)
    if ($rows < 2) return 2;
    if ($rows > 6) return 6;
    
    return $rows;
}

// ----------------------------------------------------------------------------------
// 4. สร้าง info_html และ Config ตาราง ( ตาม layout ที่ต้องการและข้อมูลอิงจากฟอร์ม )
// ----------------------------------------------------------------------------------

// 3. สร้าง info_html โดยใช้ alert_class ที่คำนวณได้
// ผมปรับสี Icon ให้ล้อไปตาม alert_class ด้วยครับ
$info_html = '<div class="alert ' . $current_ui['alert_class'] . ' d-flex align-items-center border-0 shadow-sm mb-4 w-100">';
$icon_type = ''; 
switch ($category) {
    case 'VEHICLE': $icon_type = 'fa-car'; break;
    case 'BAG':     $icon_type = 'fa-briefcase'; break;
    case 'TOOLS':   $icon_type = 'fa-toolbox'; break;
    case 'CAMERA':  $icon_type = 'fa-camera'; break;
}
$info_html .= '<i class="fas ' . $icon_type . ' fa-2x me-3 ' . $current_ui['user_icon_class'] . '"></i>';
$info_html .= '<div class="row flex-grow-1 g-0">';

switch ($category) {
    case 'VEHICLE':
        $info_html .= '
                    <div class="col-md-6">
                        <label class="small text-muted d-block">หมายเลขทะเบียน/โล่</label>
                        <span class="fw-bold text-dark">' . htmlspecialchars($header['car_license']) . '</span>
                    </div>
                    <div class="col-md-6">
                        <label class="small text-muted d-block">เลขกิโลเมตรเริ่มต้น</label>
                        <span class="fw-bold text-dark">' . ($header['car_mileage'] ? number_format($header['car_mileage']) : '-') . ' กม.</span>
                    </div>';
        break;
    case 'BAG':
        $info_html .= '
                <div class="col-12">
                    <label class="small text-muted d-block">กระเป๋าตรวจสถานที่เกิดเหตุทั่วไป</label>
                    <span class="fw-bold text-dark">ชุดที่ ' . htmlspecialchars($header['bag_set_no']) . '</span>
                </div>';
        break;
    case 'TOOLS':
        $info_html .= '
                <div class="col-12">
                    <label class="small text-muted d-block">เครื่องมือตรวจสถานที่เกิดเหตุ</label>
                    <span class="fw-bold text-dark">' . ($header['other_set_no'] ? 'ชุดที่ ' . htmlspecialchars($header['other_set_no']) : '- ไม่ระบุ -') . '</span>
                </div>';
        break;
    case 'CAMERA':
        $info_html .= '
                    <div class="col-md-7">
                        <label class="small text-muted d-block">กล้องถ่ายภาพแบบดิจิทัล (ยี่ห้อ - รุ่น)</label>
                        <span class="fw-bold text-dark">' . htmlspecialchars($header['camera_brand'] . ' ' . $header['camera_model']) . '</span>
                    </div>
                    <div class="col-md-5">
                        <label class="small text-muted d-block">เลขหมายประจำกล้อง (Serial Number)</label>
                        <span class="fw-bold text-dark text-uppercase">' . htmlspecialchars($header['camera_sn'] ?: '-') . '</span>
                    </div>';
        break;
}

$info_html .= '</div></div>'; // ปิด div alert

$configs = [
    'VEHICLE' => [
        'labels' => ['ปกติ', 'ไม่ปกติ'],
        'has_split_notes' => true, // แยกคอลัมน์ การแก้ไข และ หมายเหตุ
        'note_labels' => ['การแก้ไข', 'หมายเหตุ'],
        'widths' => ['35%', '10%', '10%', '22.5%', '22.5%'] // % ความกว้าง 5 คอลัมน์
    ],
    'BAG' => [
        'labels' => ['ครบ', 'ไม่ครบ'],
        'has_split_notes' => false, // รวมคอลัมน์ การแก้ไข/หมายเหตุ
        'note_labels' => ['การแก้ไข / หมายเหตุ'],
        'widths' => ['40%', '10%', '10%', '40%'] // % ความกว้าง 4 คอลัมน์
    ],
    'CAMERA' => [
        'labels' => ['ปกติ', 'ไม่ปกติ'],
        'has_split_notes' => true,
        'note_labels' => ['การแก้ไข', 'หมายเหตุ'],
        'widths' => ['35%', '10%', '10%', '22.5%', '22.5%'] // % ความกว้าง 5 คอลัมน์

    ],
    'TOOLS' => [
        'labels' => ['ใช้งานได้', 'ใช้งานไม่ได้'],
        'has_split_notes' => true,
        'note_labels' => ['การแก้ไข', 'หมายเหตุ'],
        'widths' => ['35%', '10%', '10%', '22.5%', '22.5%'] // % ความกว้าง 5 คอลัมน์

    ]

];

$toolSetNo = !empty($header['other_set_no']) ? htmlspecialchars($header['other_set_no']) : '....';

// 3. เตรียมข้อมูล Items
$items = [];
if ($category === 'VEHICLE') {
    $items = [
        ['id' => 1, 'category' => 'ระบบไฟฟ้าและแบตเตอรี่', 'details' => '- แบตเตอรี่รถ น้ำกลั่นแบตเตอรี่<br>- วิทยุสื่อสารประจำรถ'],
        ['id' => 2, 'category' => 'ยาง', 'details' => '- ลมยาง หน้า - หลัง ดอกยางรถ'],
        ['id' => 3, 'category' => 'ระบบส่องสว่าง', 'details' => '- ไฟหน้า, ไฟท้าย, ไฟเบรก, ไฟเลี้ยว, ไฟฉุกเฉิน<br>- ไฟวับวาบและสัญญาณไซเรน / ไฟส่องสว่าง'],
        ['id' => 4, 'category' => 'สภาพรถ', 'details' => '- สภาพตัวถังรถ<br>- กระจกมองข้าง, กระจกมองหลัง'],
        ['id' => 5, 'category' => 'แตร', 'details' => '- แตรรถ'],
        ['id' => 6, 'category' => 'ระบบเบรก', 'details' => '- ระดับปริมาณน้ำมันเบรก ระบบเบรก'],
        ['id' => 7, 'category' => 'ใบปัดน้ำฝน', 'details' => '- ใบปัดและน้ำปัดน้ำฝน'],
        ['id' => 8, 'category' => 'ของเหลวต่างๆ', 'details' => '- ปริมาณน้ำมันเชื้อเพลิงหรือแก๊ส / ปริมาณน้ำมันเครื่อง<br>- ระดับปริมาณน้ำมันพวงมาลัยพาวเวอร์ และน้ำหล่อเย็นหม้อน้ำ'],
        ['id' => 9, 'category' => 'ระบบปรับอากาศ', 'details' => '- เครื่องปรับอากาศภายในรถ'],
        ['id' => 10, 'category' => 'ความสะอาดภายในรถ', 'details' => '- ความสะอาดภายในห้องโดยสาร'],
        ['id' => 11, 'category' => 'การทดลองขับ', 'details' => ' '],
    ];
} else if ($category === 'BAG') {
    $items = [
        ['id' => 1, 'category' => 'ชุดอุปกรณ์เก็บลายนิ้วมือ / ฝ่ามือ / ฝ่าเท้า', 'details' => '- ผงฝุ่นดำ 2 ขวด, ผงฝุ่นขาว 1 ขวด, ผงฝุ่นแม่เหล็ก 1 ขวด, 
            ผงฝุ่นอลูมิเนียม 1 ขวด<br/>- แปรงขนกระรอก / ขนอูฐ 3 อัน, แปรงขนกระต่าย 2 อัน, แปรงแม่เหล็ก 2 อัน <br/>- แปรงขนไฟเบอร์ 1 อัน<br/>- แว่นขยาย 1 อัน, เทปเก็บรอยนิ้วมือแฝงขนาด 1 นิ้ว 1 ม้วน, ขนาด 2 นิ้ว 1 ม้วน<br/>
            - กระดาษเก็บรอยลายนิ้วมือแฝง สีขาว 30 แผ่น สีดำ 15 แผ่น, กระดาษ A4 15 แผ่น'],
        ['id' => 2, 'category' => 'ชุดอุปกรณ์ทำแผนผัง', 'details' => '- เข็มทิศ 1 อัน, ตลับเมตร 50 เมตร 1 อัน, ตลับเมตร 5 เมตร 1 อัน'],
        ['id' => 3, 'category' => 'ชุดอุปกรณ์เครื่องเขียน', 'details' => '- ปากกาเคมีสีน้ำเงิน / ดำ 1 ด้าม, ปากกาลูกลื่น สีน้ำเงิน / ดำ 1 ด้าม<br/>- กรรไกร 2 เล่ม, มีดคัตเตอร์ 1 เล่ม'],
        ['id' => 4, 'category' => 'ชุดหล่อรอย', 'details' => '- ชุดหล่อรอยเครื่องมือ เช่น Microsil 1 ชุด<br/>- ชุดหล่อรอยเท้าพร้อมปูนปลาสเตอร์ 1 ชุด, กรอบอลูมิเนียม 1 อัน<br/>
            - กระบอกตวงพลาสติก ขนาด 1 ลิตร 1 อัน, ถ้วยพลาสติกเล็ก 1 อัน'],
        ['id' => 5, 'category' => 'ชุดอุปกรณ์วัดขนาด', 'details' => '- สเกลแบบฉากขนาดใหญ่ 5 อัน, สเกลแบบฉากขนาดเล็ก 5 อัน<br/>- สติ๊กเกอร์สเกล 5 ซม. 5 แผ่น, สติ๊กเกอร์ตัวเลข 5 แผ่น'],
        ['id' => 6, 'category' => 'ชุดตรวจเก็บ DNA และทดสอบคราบโลหิต', 'details' => '- สำลีพันก้านเก็บสารพันธุกรรมแบบปลอดเชื้อ 60 ก้าน<br/>
            - กล่องบรรจุสำลีพันก้านเก็บสารพันธุกรรม 30 กล่อง<br/>
            - สมุดยินยอมการตรวจเก็บ DNA และการพิมพ์มือ 1 เล่ม<br/>
            - Hexagon 5 ชุด, Blue Star 1 ชุด, กระบอกฉีด 1 อัน<br/>
            - น้ำกลั่น 10 ขวด, Forceps 5 อัน, กระดาษกรอง 1 กล่อง<br/>
            - ก้านสำลี 1 ห่อ, Hemastix / Phenolphthalein 1 กล่อง<br/>'],
        ['id' => 7, 'category' => 'ชุดเก็บเขม่าปืน', 'details' => '- 5 % Nitric Acid 1 ขวด, ก้านสำลี 1 ห่อ<br/>- ถุงซิป 50 ถุง, ซองเก็บเขม่าปืน 10 ซอง'],
        ['id' => 8, 'category' => 'ชุดตรวจเก็บสารระเบิดจากตัวบุคคล', 'details' => '- Alcohol Pads 20 แผ่น, ผ้าก็อต / ก้านสำลี 20 แผ่น, น้ำกลั่น 10 ขวด<br/>- ซองเก็บวัตถุพยานพร้อมถุงซิป 20 ซอง'],
        ['id' => 9, 'category' => 'ชุดตรวจเก็บสารระเบิดจากวัตถุ', 'details' => '- Acetone 1 ขวด, ผ้าก็อต / ก้านสำลี 20 แผ่น, น้ำกลั่น 20 ขวด<br/> - ซองเก็บวัตถุพยานพร้อมถุงซิป 20 ซอง'],
        ['id' => 10, 'category' => 'ชุดระบุตำแหน่งวัตถุพยาน', 'details' => '- ป้ายหมายเลข 1 - 20 พร้อมลูกศร 4 อัน 1 ชุด, ป้าย A - J 1 ชุด<br/>
            - วงแหวนครอบวัตถุพยาน 30 อัน, สติ๊กเกอร์ตัวเลข 5 แผ่น'],
        ['id' => 11, 'category' => 'วัสดุหีบห่อ', 'details' => '- เทปปิดวัตถุพยานแบบกระดาษ 3 ม้วน และแบบพลาสติก 2 ม้วน <br/>
            - ถุงซิป ขนาดเล็ก, กลาง, ใหญ่ อย่างละ 20 ถุง<br/>
            - ถุงพลาสติกเก็บวัตถุพยาน ขนาดเล็ก, กลาง, ใหญ่ อย่างละ 20 ถุง<br/>
            - ซองกระดาษเก็บวัตถุพยานขนาดเล็ก, กลาง, ใหญ่ อย่างละ 15 ซอง<br/>
            - กระปุกพลาสติก 5 ใบ, กระป๋องโลหะ 5 ใบ<br/>
            - ขวดแก้วพร้อมช้อนตัก 5 ชุด, กระดาษรองวัตถุพยาน 10 แผ่น'],
        ['id' => 12, 'category' => 'ชุดอุปกรณ์ป้องกันสถานที่เกิดเหตุ', 'details' => '- Police Line 1 ม้วน, เชือกและหมุด 1 ชุด'],
        ['id' => 13, 'category' => 'ชุดอุปกรณ์ป้องกันส่วนบุคคล', 'details' => '- ชุดป้องกันการปนเปื้อน (PPE) 3 ชุด, ถุงมือไนไตร 1 กล่อง, ถุงมือผ้าถัก 5 คู่<br/>
            - หน้ากากอนามัย 1 กล่อง, หน้ากาก N-95 3 อัน, ถุงคลุมเท้า 20 คู่<br/>
            - หมวกคลุมผม 20 ชิ้น<br/>
            - ถุงมือป้องกันของมีคม 5 คู่'],
        ['id' => 14, 'category' => 'ชุดตรวจค้นหาวัตถุพยาน', 'details' => '- ตะแกรงร่อน 1 อัน, ชุดแม่เหล็ก 1 อัน, พลั่ว 3 เล่ม'],
    ];
} else if ($category === 'CAMERA') {
    $items = [
        ['id' => 1, 'category' => 'ตัวกล้องและเลนส์', 'details' => '- ตัวกล้อง, สภาพปุ่ม ON / OFF, ปุ่มชัตเตอร์, 
            ปุ่มฟังก์ชั่น<br/>
            - เลนส์, ช่องมองภาพ, จอภาพ<br/>
            - แฟลชเสริม 1 อัน<br/>
            - ความสะอาด'],
        ['id' => 2, 'category' => 'แบตเตอรี่', 'details' => '- แบตเตอรี่ 1 ชุด, แบตเตอรี่สำรอง 1 ชุด'],
        ['id' => 3, 'category' => 'อุปกรณ์เสริม', 'details' => '- สายกล้อง, ขาตั้งกล้อง, กระเป๋ากล้อง, เมมโมรี่การ์ดสำรอง'],
        ['id' => 4, 'category' => 'เมมโมรี่การ์ด', 'details' => '- นำภาพลงคอมพิวเตอร์ และลบภาพในเมมโมรี่การ์ด'],
        ['id' => 5, 'category' => 'การทดสอบกล้อง', 'details' => ' '],
    ];
} else if ($category === 'TOOLS') {
    $items = [
        ['id' => 1, 'category' => "เครื่องตรวจโลหะ ชุดที่ {$toolSetNo}", 'details' => '- ชุดควบคุม, ก้านปรับความยาว, จานรับส่งสัญญาณ<br/>
            - แบตเตอรี่'],
        ['id' => 2, 'category' => "ไฟฉายหลายความถี่แบบพกพา ชุดที่ {$toolSetNo}", 'details' => '- ตัวเครื่องฉายแสง, ฟิลเตอร์, แว่นตาสีแดง / เหลือง / ส้ม / ใส<br/>- แบตเตอรี่'],
        ['id' => 3, 'category' => "เครื่องลอกลายฝุ่น ชุดที่ {$toolSetNo}", 'details' => '- ตัวเครื่องลอกลายฝุ่น, แผ่นเหล็ก, แผ่นพลาสติก, ฟิล์มลอกลาย<br/>
            - ลูกกลิ้ง, ตัวต่อสายดิน, แบตเตอรี่'],
        ['id' => 4, 'category' => "เครื่องวัดระยะด้วยเลเซอร์ ชุดที่ {$toolSetNo}", 'details' => '- ตัวเครื่องวัดระยะ<br/>- แบตเตอรี่'],
        ['id' => 5, 'category' => "เครื่องระบุพิกัดภูมิศาสตร์ (GPS) ชุดที่ {$toolSetNo}", 'details' => '- ตัวเครื่องระบุพิกัดภูมิศาสตร์ (GPS)<br/>- แบตเตอรี่'],
        ['id' => 6, 'category' => "ไฟฉายส่องสว่างแรงสูง ชุดที่ {$toolSetNo}", 'details' => '- ไฟฉาย<br/>- แบตเตอรี่'],
    ];
}

// 4. สร้าง Header ของตารางแบบ Dynamic
$currentConfig = $configs[$category] ?? $configs['VEHICLE'];
$label1 = $currentConfig['labels'][0];
$label2 = $currentConfig['labels'][1];
$widths = $currentConfig['widths'];

// ----------------------------------------------------------------------------------
// 5. เตรียมรายการ Items และสร้างตาราง
// ----------------------------------------------------------------------------------
$table_html = '
    <table class="table table-bordered align-middle readiness-table shadow-sm">
        <thead class="table-light text-center">
            <tr>
                <th rowspan="2" class="align-middle" style="width: ' . $widths[0] . ';">ประเภทรายการ</th>
                <th style="width: ' . $widths[1] . ';">' . $label1 . '</th>
                <th style="width: ' . $widths[2] . ';">' . $label2 . '</th>';

// Logic จัดการคอลัมน์ หมายเหตุ (รวม หรือ แยก)
if ($currentConfig['has_split_notes']) {
    $table_html .= '<th rowspan="2" class="align-middle" style="width: ' . $widths[3] . ';">' . $currentConfig['note_labels'][0] . '</th>
                    <th rowspan="2" class="align-middle" style="width: ' . $widths[4] . ';">' . $currentConfig['note_labels'][1] . '</th>';
} else {
    $table_html .= '<th rowspan="2" class="align-middle" style="width: ' . $widths[3] . ';">' . $currentConfig['note_labels'][0] . '</th>';
}

$table_html .= '
            </tr>
        </thead>
        <tbody>';

// --- ส่วนวนลูป <tr> ---
foreach ($items as $row) {
    $itemId = $row['id'];
    $valOld = $savedData[$itemId]['score'] ?? null;
    $corOld = $savedData[$itemId]['correction'] ?? '';
    $remOld = $savedData[$itemId]['remark'] ?? '';

    $dynamicRows = calculateTextAreaRows($row['details']);

    $table_html .= '
    <tr class="check-item-row">
        <td class="ps-3">
            <div class="fw-bold text-dark">' . $row['category'] . '</div>
            <div class="small text-muted">' . $row['details'] . '</div>
        </td>';

    // หยอด Radio (ส่งค่า $valOld เข้าไปเช็ค Checked)
    $table_html .= renderRadioCell($itemId, 1, $valOld, $is_completed);
    $table_html .= renderRadioCell($itemId, 0, $valOld, $is_completed);

    if ($currentConfig['has_split_notes']) {
        // ใช้ $dynamicRows ที่คำนวณได้มาใส่ใน attribute rows
        // คอลัมน์ "การแก้ไข"
        $table_html .= '<td>
            <div class="input-group input-group-seamless">
                <textarea name="correction[' . $itemId . ']" class="form-control form-control-sm" rows="' . $dynamicRows . '" ' . ($is_completed ? 'disabled' : '') . '>' . $corOld . '</textarea>
                ' . (!$is_completed ? '<button type="button" class="btn btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>' : '') . '
            </div>
        </td>';
        // คอลัมน์ "หมายเหตุ"
        $table_html .= '<td>
            <div class="input-group input-group-seamless">
                <textarea name="remark[' . $itemId . ']" class="form-control form-control-sm" rows="' . $dynamicRows . '" ' . ($is_completed ? 'disabled' : '') . '>' . $remOld . '</textarea>
                ' . (!$is_completed ? '<button type="button" class="btn btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>' : '') . '
            </div>
        </td>';
    } else {
        // กรณี "การแก้ไข/หมายเหตุ" รวมกัน
        $table_html .= '<td>
            <div class="input-group input-group-seamless">
                <textarea name="remark[' . $itemId . ']" class="form-control form-control-sm" rows="' . $dynamicRows . '" ' . ($is_completed ? 'disabled' : '') . '>' . $remOld . '</textarea>
                ' . (!$is_completed ? '<button type="button" class="btn btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>' : '') . '
            </div>
        </td>';
    }
    $table_html .= '</tr>';
}
$table_html .= '</tbody></table></div>';

// 6. ส่งผลลัพธ์กลับเป็น JSON 
echo json_encode([
    'status' => 'success',
    'info_html' => $info_html,
    'table_html' => $table_html,
    'ui' => $current_ui, // ส่งข้อมูล UI ไปให้ JavaScript
    'mode' => $mode
]);
exit;
