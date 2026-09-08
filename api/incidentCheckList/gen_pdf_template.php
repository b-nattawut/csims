<?php
/**
 * gen_pdf_template.php - Template file for mPDF
 * 
 * ไฟล์นี้ถูก include จาก gen_pdf.php
 * ต้องการตัวแปร: $data, $mpdf จาก gen_pdf.php
 */

// ==========================================
// --- Helper Functions ---
// ==========================================

function chk($val, $target)
{
    $isChecked = false;

    // ตรวจสอบเงื่อนไข (รองรับทั้ง Array และ String)
    if (is_array($val)) {
        $isChecked = in_array($target, $val);
    } else {
        $isChecked = ($val == $target);
    }

    $size = "20";
    $style = 'style="vertical-align: -4px;"';

    // SVG สำหรับกล่องสี่เหลี่ยมเปล่า (Unchecked)
    $svgUnchecked = '
        <svg width="' . $size . '" height="' . $size . '" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" ' . $style . '>
            <rect x="5" y="5" width="90" height="90" fill="none" stroke="black" stroke-width="8" />
        </svg>';

    // SVG สำหรับกล่องที่มีเครื่องหมายถูก (Checked)
    // ขีดถูกจะวาดด้วย path
    $svgChecked = '
        <svg width="' . $size . '" height="' . $size . '" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" ' . $style . '>
            <rect x="5" y="5" width="90" height="90" fill="none" stroke="black" stroke-width="8" />
            <path d="M20 50 L40 75 L80 20" fill="none" stroke="black" stroke-width="12" />
        </svg>';

    return $isChecked ? $svgChecked : $svgUnchecked;
}

function bbox()
{
    $size = "16";

    $style = 'style="vertical-align: -3px;"';

    return '
    <svg width="' . $size . '" height="' . $size . '" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" ' . $style . '>
        <rect x="0" y="0" width="100" height="100" fill="black" />
    </svg>';
}

function tab($n = 3)
{
    return str_repeat('&nbsp;', $n);
}

function str_dots($val, $length = 50)
{
    if (!empty($val) && $val != '-') {
        // ถ้ามีข้อมูล ให้แสดงข้อมูล ขีดเส้นใต้
        return '<span class="data-text" style="border-bottom: 1px dotted #000; padding: 0 5px; font-size: 24pt;">' . $val . '</span>';
    } else {
        // ถ้าไม่มีข้อมูล ให้แสดงจุดไข่ปลาตามความยาวที่กำหนด
        return str_repeat('.', $length);
    }
}

// ฟังก์ชันสร้างบรรทัดว่างสำหรับเติมเต็ม (Fill Remaining Lines)
function getFillLinesHTML($text, $total_lines, $first_line_chars = 65, $full_line_chars = 95, $extra_style = '')
{
    $char_len = mb_strlen($text ?? '');
    $used_lines = 1;

    // คำนวณจำนวนบรรทัดที่ข้อความใช้ไปจริง
    if ($char_len > $first_line_chars) {
        $remaining = $char_len - $first_line_chars;
        // ถ้ามีข้อความเหลือ ให้หารด้วยความยาวของบรรทัดถัดไป
        $used_lines += ceil($remaining / $full_line_chars);
    }

    // คำนวณจำนวนบรรทัดว่างที่ต้องเติม (ห้ามติดลบ)
    $lines_to_add = max(0, $total_lines - $used_lines);
    $html = '';

    for ($i = 0; $i < $lines_to_add; $i++) {
        // สร้าง tr/td พร้อม style ที่ส่งเข้ามา (เช่น padding-left)
        $html .= '<tr><td style="border: none; line-height: 1.4; ' . $extra_style . '">' . str_dots('', $full_line_chars) . '</td></tr>';
    }

    return $html;
}

// ฟังก์ชันแปลงวันที่จาก YYYY-MM-DDTHH:mm เป็น Format ที่ต้องการ
function parseDateTime($datetimeStr)
{
    if (empty($datetimeStr)) {
        return ['date' => '', 'time' => '', 'year' => ''];
    }

    // แปลง String เป็น Timestamp
    $ts = strtotime($datetimeStr);
    if (!$ts) {
        return ['date' => '', 'time' => '', 'year' => ''];
    }

    // array เดือนไทย
    $thai_months = [
        1 => 'ม.ค.',
        2 => 'ก.พ.',
        3 => 'มี.ค.',
        4 => 'เม.ย.',
        5 => 'พ.ค.',
        6 => 'มิ.ย.',
        7 => 'ก.ค.',
        8 => 'ส.ค.',
        9 => 'ก.ย.',
        10 => 'ต.ค.',
        11 => 'พ.ย.',
        12 => 'ธ.ค.'
    ];

    $d = date('j', $ts); // วันที่ (1-31)
    $m = (int)date('n', $ts); // เดือน (1-12)
    $y = date('Y', $ts) + 543; // ปี พ.ศ.
    $time = date('H:i', $ts); // เวลา HH:mm

    $date_th = $d . ' ' . ($thai_months[$m] ?? '') . ' ' . $y;

    return [
        'date' => $date_th,  // Ex: "1 มี.ค. 2567"
        'time' => $time,     // Ex: "10:30"
        'year' => $y         // Ex: "2567"
    ];
}

// ==========================================
// ส่วนเตรียมข้อมูลวันที่ (Date Processing)
// ==========================================

// 1. วันที่รับแจ้งเหตุ (Report Date)
$report_dt_raw = $data['general_info']['report_datetime'] ?? '';
$report_dt_info = parseDateTime($report_dt_raw);
$report_date_th = $report_dt_info['date'];
$report_time    = $report_dt_info['time'];

// 2. วันที่ทราบเหตุ/เกิดเหตุ (Incident Date)
$incident_dt_raw = $data['general_info']['incident_datetime'] ?? '';
$incident_dt_info = parseDateTime($incident_dt_raw);
$incident_date_th = $incident_dt_info['date'];
$incident_time    = $incident_dt_info['time'];

// 3. วันที่ พงส. ทราบเหตุ (Investigator Known)
$invest_dt_raw = $data['general_info']['investigator_known_datetime'] ?? '';
$invest_dt_info = parseDateTime($invest_dt_raw);
$invest_date_th = $invest_dt_info['date'];
$invest_time    = $invest_dt_info['time'];

// 4. วันที่ตรวจสถานที่เกิดเหตุ (Inspection Date)
$inspect_dt_raw = $data['general_info']['inspection_datetime'] ?? '';
$inspect_dt_info = parseDateTime($inspect_dt_raw);
$inspection_date_th = $inspect_dt_info['date'];
$inspection_time    = $inspect_dt_info['time'];
$report_year_th     = $inspect_dt_info['year'];

// 5. วันที่ตรวจเพิ่มเติม (Additional Inspection)
$add_inspect_dt_raw = $data['general_info']['inspection_additional_datetime'] ?? '';
$add_inspect_info = parseDateTime($add_inspect_dt_raw);
$add_inspect_date_th = $add_inspect_info['date'];
$add_inspect_time    = $add_inspect_info['time'];

// 6. วันที่ตรวจเสร็จสิ้น (Finish Date)
$finish_dt_raw = $data['general_info']['inspection_end_datetime'] ?? '';
$finish_dt_info = parseDateTime($finish_dt_raw);
$finish_date_th = $finish_dt_info['date'];
$finish_time_th = $finish_dt_info['time'];

// ==========================================
// 1. เตรียมข้อมูลสำหรับหน้า 1 (Page 1 Data Prep)
// ==========================================

// ---------------------------------------------------------
// 1.1 เตรียม HTML ส่วนรายการตึก (Building Rows)
// ---------------------------------------------------------

// --- 1. แปลงข้อมูล Building Types ให้เป็น Key-Value เพื่อให้เรียกใช้ง่าย ---
$b_list = []; // ตัวแปรสำหรับเก็บข้อมูลที่จัดรูปแบบแล้ว
$floor_display = ""; // ตัวแปรสำหรับเก็บจำนวนชั้น

if (!empty($data['scene_characteristics']['building_types'])) {
    foreach ($data['scene_characteristics']['building_types'] as $item) {
        $type = $item['type'];

        // เก็บข้อมูลลง Array โดยใช้ type เป็น key
        $b_list[$type] = [
            'checked' => true,
            'detail'  => $item['detail'] ?? '', // ข้อความขยายความ
            'floor'   => $item['floor'] ?? ''   // จำนวนชั้น
        ];

        // รวบรวมจำนวนชั้น (กรณีเลือกหลายตึก และมีหลายชั้นต่างกัน)
        if (!empty($item['floor'])) {
            // ถ้ามีค่าเดิมอยู่แล้ว ให้คั่นด้วยจุลภาค (,)
            $sep = ($floor_display == "") ? "" : ", ";
            $floor_display .= $sep . $item['floor'];
        }
    }
}

// --- 2. ฟังก์ชันช่วยสร้างบรรทัดตึก (Helper Function) ---
// $key: ชื่อ key ใน database (เช่น 'concrete')
// $label: ชื่อภาษาไทยที่จะแสดง (เช่น 'บ้านคอนกรีต')
// $b_list: รายการข้อมูลที่เราเตรียมไว้ข้างบน

function renderBuildingRow($key, $label, $b_list)
{
    // ตรวจสอบว่ามีข้อมูลของตึกประเภทนี้ไหม
    $has_data = isset($b_list[$key]);

    // 1. จัดการ Checkbox (ใช้ฟังก์ชัน chk ที่เป็น SVG)
    $chk_html = '<span class="chk-box">' . ($has_data ? chk(true, true) : chk(false, true)) . '</span>';

    // 2. จัดการข้อความหลัง Checkbox ด้วย str_dots
    // เตรียมค่า: ถ้ามีข้อมูลก็ส่งไปแสดง ถ้าไม่มีส่งค่าว่างไปเพื่อให้ str_dots สร้างจุดไข่ปลาให้
    $val = ($has_data && !empty($b_list[$key]['detail'])) ? $b_list[$key]['detail'] : '';

    // เรียกใช้ str_dots (กำหนดความยาวจุดไข่ปลา เช่น 30 ตัวอักษร)
    $detail_text = str_dots($val, 24);

    return '<div style="font-size: 26px;">' . $chk_html . '  ' . $label . $detail_text . '</div>';
}

$building_rows_html = '';
$building_rows_html .= renderBuildingRow('concrete', 'บ้านคอนกรีต', $b_list);
$building_rows_html .= renderBuildingRow('wood', 'บ้านไม้', $b_list);
$building_rows_html .= renderBuildingRow('half', 'บ้านครึ่งตึกครึ่งไม้', $b_list);
$building_rows_html .= renderBuildingRow('row_building', 'ตึกแถว', $b_list);
$building_rows_html .= renderBuildingRow('concrete_bldg', 'อาคารคอนกรีต', $b_list);
$building_rows_html .= renderBuildingRow('townhouse', 'ทาวน์เฮาส์', $b_list);
$building_rows_html .= renderBuildingRow('commercial', 'อาคารพาณิชย์', $b_list);
$building_rows_html .= renderBuildingRow('other', 'อื่นๆ', $b_list);

// ---------------------------------------------------------
// 1.2 เตรียม HTML ส่วนจำนวนชั้น (Floor Content)
// ---------------------------------------------------------

// เช็คว่ามีข้อมูลชั้นหรือไม่ (ถ้า $floor_display ไม่ว่าง แปลว่ามีการระบุชั้นมา)
$has_floor_info = !empty($floor_display);

// สร้าง HTML ส่วนจำนวนชั้น
$floor_section_html = '
    <span class="chk-box">' . ($has_floor_info ? chk(true, true) : chk(false, true)) . '</span> 
    ' . str_dots($floor_display, 10) . ' <span style="font-size: 26px">ชั้น</span>';

// ---------------------------------------------------------
// 1.3 เตรียม HTML ส่วนรายชื่อผู้ตรวจ (Inspectors)
// ---------------------------------------------------------
$inspector_rows_html = '';

// ดึงรายชื่อผู้ตรวจจาก $data['inspectors'] (array of IDs)
$inspectors_ids = $data['inspectors'] ?? [];

// สร้าง array เก็บชื่อผู้ตรวจจาก user_id
$inspectors_names = [];

if (!empty($inspectors_ids) && isset($pdo)) {
    // สร้าง placeholders สำหรับ IN clause
    $placeholders = implode(',', array_fill(0, count($inspectors_ids), '?'));
    
    $qryInspectors = "SELECT t1.user_id, CONCAT(t2.rank_name,' ',t1.first_name,' ',t1.last_name) AS fullname 
                      FROM user_profile t1 
                      LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id 
                      WHERE t1.user_id IN ($placeholders)";
    
    $stmtInspectors = $pdo->prepare($qryInspectors);
    $stmtInspectors->execute($inspectors_ids);
    
    // สร้าง map ของ user_id => fullname
    $inspectorMap = [];
    while ($row = $stmtInspectors->fetch(PDO::FETCH_ASSOC)) {
        $inspectorMap[$row['user_id']] = $row['fullname'];
    }
    
    // เรียงตาม order ใน $inspectors_ids
    foreach ($inspectors_ids as $id) {
        $inspectors_names[] = $inspectorMap[$id] ?? '';
    }
}

// วนลูปสร้าง 8 บรรทัด (5.1 ถึง 5.8)
for ($i = 0; $i < 8; $i++) {
    $item_no = $i + 1; // ลำดับที่ (1, 2, ..., 8)

    // ดึงชื่อ (ถ้าไม่มีให้เป็นค่าว่าง)
    // ใช้ ?? '' เพื่อป้องกัน Error กรณี Array มีไม่ครบ 8 คน
    $name = $inspectors_names[$i] ?? '';

    // สร้าง HTML row
    $inspector_rows_html .= '<tr>
                                <td style="border: none; line-height: 1.2; font-size: 26px; padding-top: 0px;">
                                    5.' . $item_no . ' ' . str_dots($name, 85) . '
                                </td>
                             </tr>';
}

// ==========================================
// 2. เตรียมข้อมูลสำหรับหน้า 2 (Page 2 Data Prep)
// ==========================================

// ฟังก์ชันเช็คว่าค่าที่ต้องการ (target) อยู่ใน Array ของข้อมูลหรือไม่
function is_checked($arr, $target)
{
    if (empty($arr)) return false;

    // กรณีข้อมูลเป็น string เดี่ยวๆ (เช่น radio button)
    if (!is_array($arr)) {
        return $arr == $target;
    }

    // กรณีข้อมูลเป็น array (เช่น checkbox หลายตัวเลือก)
    return in_array($target, $arr);
}

// ดึงข้อมูลส่วนพฤติการณ์คดีมาพักไว้ในตัวแปรสั้นๆ เพื่อให้เรียกง่าย
$behavior = $data['case_behavior_info'] ?? [];

// ---------------------------------------------------------
// 2.1 รายละเอียดจุดที่คนร้ายเข้า (Entry Location Details)
// ---------------------------------------------------------
$entry_loc = $behavior['entry_locations_detail'] ?? [];

// Helper function เล็กๆ สำหรับเช็คและดึงข้อความของจุดเข้า
function getEntryDetail($arr, $key)
{
    return [
        'chk' => isset($arr[$key]),
        'txt' => $arr[$key] ?? ''
    ];
}

$loc_other_loc = getEntryDetail($entry_loc, 'other_location'); // ร่องรอยอื่นๆ
$loc_door      = getEntryDetail($entry_loc, 'door');
$loc_window    = getEntryDetail($entry_loc, 'window');
$loc_ceiling   = getEntryDetail($entry_loc, 'ceiling');
$loc_roof      = getEntryDetail($entry_loc, 'roof');
// 'other' นี้คือ checkbox "อื่นๆ" ด้านล่างสุดของกลุ่มนี้ (อาจจะ map กับ entry_other_detail หรือ key อื่นตาม payload)
$loc_other_gen = !empty($behavior['entry_other_detail'])
    ? ['chk' => true, 'txt' => $behavior['entry_other_detail']]
    : ['chk' => false, 'txt' => ''];


// ---------------------------------------------------------
// 2.2 เครื่องมือที่ใช้ (Burglary Tools)
// ---------------------------------------------------------
$tools = $behavior['burglary_tools'] ?? [];

$tool_screw   = is_checked($tools, 'screwdriver');
$tool_crowbar = is_checked($tools, 'crowbar');
$tool_cutter  = is_checked($tools, 'metal_cutter');
$tool_other   = is_checked($tools, 'other');
$tool_other_txt = $tool_other ? ($behavior['tool_other_detail'] ?? '') : '';
$trace_width  = $behavior['trace_width'] ?? '';


// ---------------------------------------------------------
// 2.3 คดีชิงทรัพย์/อาวุธ (Robbery & Weapons)
// ---------------------------------------------------------
$perp_count = $behavior['perpetrator_count'] ?? ''; // จำนวนคนร้าย

// สถานะการใช้อาวุธ
$weapon_status = $behavior['weapon_status'] ?? '';
$is_weapon_unused = ($weapon_status === 'unused');
$is_weapon_used   = ($weapon_status === 'used');

// ประเภทอาวุธ
$wp_types = $behavior['weapon_types'] ?? [];
$wp_knife = is_checked($wp_types, 'knife');
$wp_gun   = is_checked($wp_types, 'gun');
$wp_rope  = is_checked($wp_types, 'rope');
$wp_other = is_checked($wp_types, 'other');
$wp_other_txt = $wp_other ? ($behavior['weapon_other_detail'] ?? '') : '';


// ---------------------------------------------------------
// 2.4 การพันธนาการและผู้บาดเจ็บ (Restraint & Injury)
// ---------------------------------------------------------
$restraints = $behavior['restraint_methods'] ?? [];
$res_confine = is_checked($restraints, 'confinement'); // กักขัง
$res_bind    = is_checked($restraints, 'binding');     // พันธนาการ
$res_bind_txt = $res_bind ? ($behavior['binding_material'] ?? '') : '';

$vic_status = $behavior['victim_status'] ?? [];
$is_injured = is_checked($vic_status, 'injured');
$is_dead    = is_checked($vic_status, 'deceased');
$injury_txt = $behavior['injury_detail'] ?? '';


// ---------------------------------------------------------
// 2.5 สภาพร่องรอย (Trace Points) - Dynamic Generation
// ---------------------------------------------------------
$traces = $data['trace_points'] ?? [];

// แยกข้อมูล: รายการแรก (index 0) เอาไปไว้ฝั่งซ้ายล่าง, ที่เหลือ (index 1+) เอาไปไว้ฝั่งขวา
$trace_first = isset($traces[0]) ? $traces[0] : null;
$trace_remaining = array_slice($traces, 1);

// ปรับให้ฝั่งขวามี 3 บล็อกเสมอ (Fill ให้ครบ 3)
$trace_right_list = [];
for ($i = 0; $i < 3; $i++) {
    // ถ้ามีข้อมูลจริง ให้ใช้ข้อมูลจริง
    if (isset($trace_remaining[$i])) {
        $trace_right_list[] = $trace_remaining[$i];
    } else {
        // ถ้าไม่มี ให้ใส่ null (เพื่อไป render เป็นกล่องเปล่าที่มีจุดไข่ปลา)
        $trace_right_list[] = null;
    }
}

// ฟังก์ชันสร้าง HTML สำหรับการ์ดร่องรอย (รองรับการตั้งค่าแยกฝั่งซ้าย/ขวา)
function renderTraceCardHTML($item, $config = [])
{
    // --- ตั้งค่า Default (ค่าเริ่มต้นสำหรับฝั่งซ้าย) ---
    $defaults = [
        'dots' => 85,          // ความยาวจุดไข่ปลาต่อบรรทัด
        'lines' => [           // จำนวนบรรทัดของแต่ละหัวข้อ
            'area' => 2,
            'entry' => 2,
            'pry' => 3,
            'rummage' => 4
        ]
    ];

    // ผสานค่า Config ที่ส่งมาทับค่า Default
    // ถ้าส่งค่าไหนมาใหม่ ก็จะใช้ค่านั้น ถ้าไม่ส่งก็ใช้ค่าเดิม
    $cfg = array_replace_recursive($defaults, $config);

    // --- ดึงข้อมูล ---
    $area       = $item['area_detail'] ?? '';
    $entry_txt  = $item['entry']['detail'] ?? '';
    $pry_txt    = $item['pry']['detail'] ?? '';
    $rum_txt    = $item['rummage']['detail'] ?? '';

    // เช็คสถานะ Checkbox (Logic เดิม)
    $is_area_chk = !empty($area);
    $is_entry    = !empty($item['entry']['checked']);
    $is_pry      = !empty($item['pry']['checked']);
    $is_rummage  = !empty($item['rummage']['checked']);

    // --- สร้าง HTML ---
    $html = '<table width="100%" style="border: none; border-collapse: collapse;">';

    // 1. บริเวณ (Area)
    $html .= '<tr><td style="border: none; padding-bottom: 2px;"><span class="chk-box">' . chk($is_area_chk, true) . '</span>' . tab(1) . ' บริเวณ ' . str_dots($area, 65) . '</td></tr>';
    // ใช้ค่า lines['area'] และ dots จาก Config
    $html .= getFillLinesHTML($area, $cfg['lines']['area'], 65, $cfg['dots']);

    $html .= '<tr><td style="border: none; height: 5px;"></td></tr>';

    // 2. ทางเข้าคนร้าย (Entry)
    $html .= '<tr><td style="border: none; padding-bottom: 2px; padding-left: 38px;"><span class="chk-box">' . chk($is_entry, true) . '</span>' . tab(1) . ' ทางเข้าของคนร้าย ' . str_dots($entry_txt, 50) . '</td></tr>';
    $html .= getFillLinesHTML($entry_txt, $cfg['lines']['entry'], 50, $cfg['dots']);

    $html .= '<tr><td style="border: none; height: 5px;"></td></tr>';

    // 3. รอยงัด (Pry)
    $html .= '<tr><td style="border: none; padding-bottom: 2px; padding-left: 38px;"><span class="chk-box">' . chk($is_pry, true) . '</span>' . tab(1) . ' รอยงัด ' . str_dots($pry_txt, 65) . '</td></tr>';
    $html .= getFillLinesHTML($pry_txt, $cfg['lines']['pry'], 65, $cfg['dots']);

    $html .= '<tr><td style="border: none; height: 5px;"></td></tr>';

    // 4. ร่องรอยรื้อค้น (Rummage)
    $html .= '<tr><td style="border: none; padding-bottom: 2px; padding-left: 38px;"><span class="chk-box">' . chk($is_rummage, true) . '</span>' . tab(1) . ' ร่องรอยรื้อค้น ' . str_dots($rum_txt, 55) . '</td></tr>';
    $html .= getFillLinesHTML($rum_txt, $cfg['lines']['rummage'], 55, $cfg['dots']);

    $html .= '</table>';

    return $html;
}

// สร้าง HTML ก้อนใหญ่สำหรับฝั่งขวา
$trace_right_html = '';

// ตั้งค่าสำหรับฝั่งขวาโดยเฉพาะ (ปรับจูนตัวเลขตรงนี้ได้เลย)
$right_side_config = [
    'dots' => 130,  // เพิ่มจำนวนจุดให้เยอะขึ้น เพื่อดัน Layout ให้เต็มความกว้าง
    'lines' => [
        'area' => 2,      // จำนวนบรรทัดของ Area
        'entry' => 2,     // จำนวนบรรทัดของ Entry
        'pry' => 3,       // จำนวนบรรทัดของ Pry
        'rummage' => 5    // จำนวนบรรทัดของ Rummage 
    ]
];

// 1. นับจำนวน item ทั้งหมดก่อน
$total_items = count($trace_right_list);
$counter = 0;

foreach ($trace_right_list as $t) {
    $counter++; // นับจำนวนรอบ

    // สร้าง HTML การ์ด
    $trace_right_html .= renderTraceCardHTML($t, $right_side_config);

    // 2. เช็คเงื่อนไข: ใส่ตัวเว้นวรรค เฉพาะเมื่อ "ไม่ใช่" ตัวสุดท้าย
    if ($counter < $total_items) {
        $trace_right_html .= '<div style="height: 30px; line-height: 30px;">&nbsp;</div>';
    }
}


// ==========================================
// 3. เตรียมข้อมูลสำหรับหน้า 3 (Page 3 Data Prep)
// ==========================================

// ---------------------------------------------------------
// 3.1 ทรัพย์สินที่ถูกโจรกรรม
// ---------------------------------------------------------

$stolen_prop = $data['stolen_property'] ?? '';

// ---------------------------------------------------------
// 3.2 วัตถุพยาน (Logic แยกประเภทจาก Array evidences)
// ---------------------------------------------------------

$evidences = $data['evidences'] ?? [];

// ตัวแปรสำหรับเก็บข้อมูลที่จะนำไปแสดง
$blood_found = null; // สำหรับ Section "วัตถุพยานที่ตรวจพบ" (เน้นคราบเลือด)
$collected_fingerprint = ['found' => false, 'detail' => ''];
$collected_dna         = ['found' => false, 'detail' => ''];
$collected_toolmark    = ['found' => false, 'detail' => ''];
$collected_other       = ['found' => false, 'detail' => ''];

// Loop แยกประเภทวัตถุพยาน
foreach ($evidences as $ev) {
    $type = $ev['type'] ?? '';
    $detail = $ev['detail'] ?? '';

    if ($type === 'blood') {
        // ถ้าเจอเลือด ให้เก็บข้อมูลก้อนแรกไว้ใช้แสดงในส่วน "คราบสีแดงคล้ายโลหิต"
        if (is_null($blood_found)) {
            $blood_found = $ev;
        }
    } elseif ($type === 'fingerprint') {
        $collected_fingerprint['found'] = true;
        $collected_fingerprint['detail'] .= $detail . ' ';
    } elseif ($type === 'dna') {
        $collected_dna['found'] = true;
        $collected_dna['detail'] .= $detail . ' ';
    } elseif ($type === 'toolmark') {
        $collected_toolmark['found'] = true;
        $collected_toolmark['detail'] .= $detail . ' ';
    } else { // other
        $collected_other['found'] = true;
        $collected_other['detail'] .= $detail . ' ';
    }
}

// ย่อยข้อมูล Blood Test (จาก $blood_found)
$has_blood      = !empty($blood_found);
$blood_detail   = $blood_found['detail'] ?? '';
$blood_test     = $blood_found['blood_test'] ?? [];

$test_hemastix  = !empty($blood_test['hemastix_tested']); // มีการทดสอบ Hemastix?
$res_hemastix   = $blood_test['hemastix_result'] ?? ''; // change / no_change

$test_phenol    = !empty($blood_test['phenol_tested']); // มีการทดสอบ Phenol?
$res_phenol     = $blood_test['phenol_result'] ?? ''; // change / no_change

// ---------------------------------------------------------
// 3.3 การตรวจสอบครั้งสุดท้าย (Final Check)
// ---------------------------------------------------------

$final_checks = $data['final_check'] ?? [];
// สมมติ key: 'final_verified', 'collected_all', 'photo_taken'
$chk_final_verify = is_checked($final_checks, 'final_verified'); // การตรวจสอบครั้งสุดท้าย
$chk_collect_all  = is_checked($final_checks, 'collected_all');  // ตรวจเก็บครบถ้วน
$chk_photo_taken  = is_checked($final_checks, 'photo_taken');    // ถ่ายภาพและส่งมอบ

// ---------------------------------------------------------
// 3.4 การส่งมอบ (Handover)
// ---------------------------------------------------------

$handover = $data['handover'] ?? [];
// วันที่เสร็จสิ้น (ใช้ inspection_end_datetime จาก general_info หรือ finish_datetime)
$finish_datetime = $data['general_info']['inspection_end_datetime'] ?? '';
$finish_date_th = '';
$finish_time_th = '';

if (!empty($finish_datetime)) {
    // แปลง YYYY-MM-DDTHH:mm เป็น วันที่ และ เวลา
    $ts = strtotime($finish_datetime);
    if ($ts) {
        $finish_date_th = date('j', $ts) . ' ' . getThaiMonth(date('n', $ts)) . ' ' . (date('Y', $ts) + 543); // ฟังก์ชัน getThaiMonth ต้องมี (ถ้าไม่มีให้ใช้ date ธรรมดา)
        // หรือถ้าไม่มี function แปลงเดือน ให้ใช้ date format ง่ายๆไปก่อน
        // $finish_date_th = date('d/m/Y', $ts); 
        $finish_time_th = date('H:i', $ts);
    }
}

// ข้อมูลผู้รับมอบ/ส่งมอบ
// Receiver - ดึงชื่อจาก user_id
$recv_id = $handover['receiver_id'] ?? '';
$recv_name = '';
$recv_pos  = $handover['receiver_pos'] ?? '';

if (!empty($recv_id) && isset($pdo)) {
    $stmtRecv = $pdo->prepare("SELECT CONCAT(t2.rank_name,' ',t1.first_name,' ',t1.last_name) AS fullname 
                               FROM user_profile t1 
                               LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id 
                               WHERE t1.user_id = ?");
    $stmtRecv->execute([$recv_id]);
    $rowRecv = $stmtRecv->fetch(PDO::FETCH_ASSOC);
    if ($rowRecv) {
        $recv_name = $rowRecv['fullname'];
    }
}

$recv_sig_img = '';
if (!empty($handover['receiver_sig'])) {
    // สร้าง tag img สำหรับลายเซ็น (ปรับความสูงตามเหมาะสม)
    $recv_sig_img = '<img src="' . $handover['receiver_sig'] . '" style="height: 25px; vertical-align: middle;">';
} else {
    $recv_sig_img = str_dots('', 35); // ถ้าไม่มีลายเซ็น ให้เป็นจุดไข่ปลา
}

// Deliverer - ดึงชื่อจาก user_id
$delv_id = $handover['deliverer_id'] ?? '';
$delv_name = '';
$delv_pos  = $handover['deliverer_pos'] ?? '';

if (!empty($delv_id) && isset($pdo)) {
    $stmtDelv = $pdo->prepare("SELECT CONCAT(t2.rank_name,' ',t1.first_name,' ',t1.last_name) AS fullname 
                               FROM user_profile t1 
                               LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id 
                               WHERE t1.user_id = ?");
    $stmtDelv->execute([$delv_id]);
    $rowDelv = $stmtDelv->fetch(PDO::FETCH_ASSOC);
    if ($rowDelv) {
        $delv_name = $rowDelv['fullname'];
    }
}

$delv_sig_img = '';
if (!empty($handover['deliverer_sig'])) {
    $delv_sig_img = '<img src="' . $handover['deliverer_sig'] . '" style="height: 25px; vertical-align: middle;">';
} else {
    $delv_sig_img = str_dots('', 35);
}

function getThaiMonth($m)
{
    $months = [1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.', 5 => 'พ.ค.', 6 => 'มิ.ย.', 7 => 'ก.ค.', 8 => 'ส.ค.', 9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'];
    return $months[$m] ?? '';
}

// ==========================================
// 4. เตรียมข้อมูลสำหรับหน้า 4 (Page 4 Data Prep)
// ==========================================

// ---------------------------------------------------------
// 4.1 รูปภาพแผนผัง (Base64 String)
// ---------------------------------------------------------

$sketch_data = $data['attachments_meta']['sketch'] ?? '';
$sketch_html = '';

if (!empty($sketch_data)) {
    // ถ้ามีรูป ให้สร้าง tag img (กำหนด max-height เพื่อไม่ให้ล้นหน้า)
    $sketch_html = '<img src="' . $sketch_data . '" style="max-width: 100%; max-height: 600px; width: auto; height: auto;">';
}

// ---------------------------------------------------------
// 4.2 หมายเหตุ (Sketch Remark)
// ---------------------------------------------------------

$sketch_remark = $data['attachments_meta']['sketch_remark'] ?? '';

// ---------------------------------------------------------
// 4.3 ผู้จดบันทึก (เอาคนแรกของรายการผู้ตรวจ - ใช้ชื่อที่ query มาแล้ว)
// ---------------------------------------------------------

$recorder_name = $inspectors_names[0] ?? '';

// ---------------------------------------------------------
// 4.4 วันเวลา (เอาวันเวลาที่ตรวจสถานที่เกิดเหตุ)
// ---------------------------------------------------------

if (!empty($inspection_date_th)) {
    $record_datetime = 'วัน/เวลา ' . str_dots($inspection_date_th . ' ' . $inspection_time . ' น.', 80);
} else {
    $record_datetime = 'วัน/เวลา ' . str_dots('', 80);
}

// ==========================================
// 5. เตรียมข้อมูลสำหรับหน้า 5 (Page 5 Data Prep)
// ==========================================

// ---------------------------------------------------------
// 5.1 เตรียมข้อมูลวัตถุพยาน (Evidences)
// ---------------------------------------------------------

$evidence_rows_html = '';
$evidence_list = $data['evidences'] ?? [];

// จำนวนแถวขั้นต่ำที่ต้องการในหน้านี้ (เพื่อให้ตารางเต็มหน้า)
// สมมติว่าหน้าหนึ่งรับได้ประมาณ 20-25 แถว (ลองปรับตัวเลขดูตามความเหมาะสมของ font size)
$min_rows = 18;
$row_count = 0;

if (!empty($evidence_list)) {
    foreach ($evidence_list as $ev) {
        $no = $ev['no'] ?? '';
        $detail = $ev['detail'] ?? '';

        // จุดอ้างอิง (Ref Points) ในตาราง (ค่าระยะห่าง)
        $refs = $ev['ref_points'] ?? [];
        $ref1 = $refs[0]['dist'] ?? '';
        $ref2 = $refs[1]['dist'] ?? '';
        $ref3 = $refs[2]['dist'] ?? '';
        $ref4 = $refs[3]['dist'] ?? '';

        $azimuth = $ev['azimuth'] ?? '';
        $remark = $ev['remark'] ?? '';

        $evidence_rows_html .= '
        <tr>
            <td style="text-align: center;">' . str_dots($no, 5) . '</td>
            <td style="padding-left: 5px;">' . str_dots($detail, 40) . '</td>
            <td style="text-align: center;">' . $ref1 . '</td>
            <td style="text-align: center;">' . $ref2 . '</td>
            <td style="text-align: center;">' . $ref3 . '</td>
            <td style="text-align: center;">' . $ref4 . '</td>
            <td style="text-align: center;">' . $azimuth . '</td>
            <td style="text-align: center;">' . $remark . '</td>
        </tr>';

        $row_count++;
    }
}

// ---------------------------------------------------------
// 5.2 เติมแถวว่าง (Empty Rows) ให้เต็มหน้า
// ---------------------------------------------------------

// ถ้าข้อมูลจริงมีน้อยกว่า $min_rows ให้ loop สร้างแถวว่างเพิ่ม
for ($i = $row_count; $i < $min_rows; $i++) {
    $evidence_rows_html .= '
    <tr>
        <td style="height: 30px;">&nbsp;</td> <td>&nbsp;</td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
    </tr>';
}

// ---------------------------------------------------------
// 5.3 คำอธิบายจุดอ้างอิง (Footer ของตาราง)
// ---------------------------------------------------------

$ref_points_footer_html = '';

// ดึงข้อมูลจุดอ้างอิงจาก evidence รายการแรก (ถ้ามี)
$first_ev_refs = $evidence_list[0]['ref_points'] ?? [];

// วนลูปสร้าง 4 บรรทัด (จุดที่ 1 - 4)
for ($i = 0; $i < 4; $i++) {
    $num = $i + 1;
    // ดึงคำอธิบาย (desc) จาก index 0, 1, 2, 3
    $desc = $first_ev_refs[$i]['desc'] ?? '';

    // สร้างบรรทัด HTML
    $ref_points_footer_html .= 'จุดอ้างอิงที่ ' . $num . ' คือ ' . str_dots($desc, 160) . '<br>';
}

// ผู้บันทึกและวันเวลา (ใช้ตัวแปรเดิมจากหน้า 4)
// $recorder_name และ $record_datetime เตรียมไว้แล้วใน Page 4 Data Prep

// ==========================================
// 6. เตรียมข้อมูลสำหรับหน้า 6 (Page 6 Data Prep)
// ==========================================

// ---------------------------------------------------------
// 6.1 ข้อมูลวันที่ตรวจเก็บ (Header ของหน้านี้)
// ---------------------------------------------------------

$inspect_date_txt = str_dots($inspection_date_th, 50);
$inspect_time_txt = str_dots($inspection_time, 25);

// ---------------------------------------------------------
// 6.2 เตรียมแถวตาราง (Collection Rows)
// ---------------------------------------------------------

$collection_rows_html = '';
$evidence_list = $data['evidences'] ?? [];
$min_rows_page6 = 11; // หน้าแนวนอน พื้นที่แนวตั้งจะน้อยลง อาจรับได้ประมาณ 12-15 แถว
$row_count_p6 = 0;

if (!empty($evidence_list)) {
    foreach ($evidence_list as $ev) {
        $no = $ev['no'] ?? '';
        $detail = $ev['detail'] ?? '';
        $qty_val = $ev['quantity']['val'] ?? '';
        $qty_unit = $ev['quantity']['unit'] ?? '';
        $qty_display = $qty_val . ' ' . $qty_unit; // รวมจำนวนและหน่วย

        $area = $ev['area_found'] ?? '';
        $label = $ev['label_no'] ?? '';

        // การบรรจุหีบห่อ
        $pack = $ev['packaging'] ?? [];
        $chk_plastic = !empty($pack['plastic']) ? '&#10003;' : '';
        $chk_paper   = !empty($pack['paper'])   ? '&#10003;' : '';
        $chk_other   = !empty($pack['other'])   ? '&#10003;' : '';

        // การดำเนินการ
        $act = $ev['action'] ?? [];
        $chk_return = !empty($act['return']) ? '&#10003;' : ''; // ส่งคืน พงส.
        $chk_act_other = !empty($act['other']) ? '&#10003;' : '';

        $remark = $ev['remark'] ?? '';

        $collection_rows_html .= '
        <tr>
            <td style="text-align: center;">' . $no . '</td>
            <td style="padding-left: 5px;">' . str_dots($detail, 30) . '</td>
            <td style="text-align: center;">' . $qty_display . '</td>
            <td style="padding-left: 5px;">' . str_dots($area, 25) . '</td>
            <td style="text-align: center;">' . $label . '</td>
            
            <td style="text-align: center;">' . $chk_plastic . '</td>
            <td style="text-align: center;">' . $chk_paper . '</td>
            <td style="text-align: center;">' . $chk_other . '</td>
            
            <td style="text-align: center;">' . $chk_return . '</td>
            <td style="text-align: center;">' . $chk_act_other . '</td>
            
            <td style="text-align: center;">' . $remark . '</td>
        </tr>';

        $row_count_p6++;
    }
}

// ---------------------------------------------------------
// 6.3 เติมแถวว่างให้เต็มหน้า (Loop Fill Empty Rows)
// ---------------------------------------------------------

for ($i = $row_count_p6; $i < $min_rows_page6; $i++) {
    $collection_rows_html .= '
    <tr>
        <td style="height: 30px;">&nbsp;</td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
    </tr>';
}

// ผู้บันทึกและวันเวลา ใช้ตัวแปรเดิม ($recorder_name, $record_datetime)

// ==========================================
// 7. เตรียมข้อมูลสำหรับหน้า 7 (Page 7 Data Prep - Photo Log)
// ==========================================

// ---------------------------------------------------------
// 7.1 ข้อมูลส่วนหัวของบันทึกการถ่ายภาพ
// ---------------------------------------------------------

$photo_datetime_txt = 'วันที่ตรวจสถานที่เกิดเหตุ ' . str_dots($inspection_date_th, 50) . ' เวลาประมาณ ' . str_dots($inspection_time, 50) . ' น.';

$photo_start = $data['attachments_meta']['photo_start'] ?? '-';
$photo_end   = $data['attachments_meta']['photo_end'] ?? '-';
$photo_amt   = $data['attachments_meta']['photo_amount'] ?? '0';

$photo_meta_txt = 'รหัสภาพถ่ายที่ ' . str_dots($photo_start, 40) . ' ถึง ' . str_dots($photo_end, 40) . ' จำนวน ' . str_dots($photo_amt, 40) . ' ภาพ';

// ---------------------------------------------------------
// 7.2 จัดการไฟล์รูปภาพ (Image Processing)
// ---------------------------------------------------------

// ดึงรูปภาพจาก $data ที่บันทึกเป็น base64 ใน JSON
$uploaded_photos = $data['photos'] ?? [];

// ---------------------------------------------------------
// 7.3 สร้าง HTML ตารางรูปภาพ (Grid Layout 2 Columns)
// ---------------------------------------------------------

$photos_html = '';

// เริ่มต้นตาราง
$photos_html .= '<table width="100%" style="border-collapse: collapse; border: none;">';

// วนลูปรูปภาพ
$total_photos = count($uploaded_photos);

// ถ้าไม่มีรูป ให้แสดงข้อความแจ้ง หรือเว้นว่าง
if ($total_photos == 0) {
    $photos_html .= '<tr><td colspan="2" style="text-align: center; padding: 50px; border: 1px solid #ccc;">- ไม่พบไฟล์ภาพแนบ -</td></tr>';
} else {
    // วนลูปทีละ 2 รูป (เพื่อสร้าง 1 แถว)
    for ($i = 0; $i < $total_photos; $i += 2) {
        $photos_html .= '<tr>';

        // --- คอลัมน์ซ้าย (รูปที่ i) ---
        $photos_html .= '<td width="50%" style="border: none; padding: 10px; vertical-align: top; text-align: center;">';
        if (isset($uploaded_photos[$i])) {
            // ดึง base64 จาก object (รูปภาพถูกบันทึกเป็น {filename, original_name, base64})
            $photo_base64 = is_array($uploaded_photos[$i]) ? ($uploaded_photos[$i]['base64'] ?? '') : $uploaded_photos[$i];
            if (!empty($photo_base64)) {
                // กำหนดความสูงภาพให้พอดี ไม่ล้นหน้า (เช่น max-height: 300px)
                $photos_html .= '<div style="border: 1px solid #000; padding: 5px; height: 320px; display: flex; align-items: center; justify-content: center;">';
                $photos_html .= '<img src="' . $photo_base64 . '" style="max-width: 100%; max-height: 310px; width: auto; height: auto;">';
                $photos_html .= '</div>';
            }
            // คำบรรยายใต้ภาพ (ถ้ามี) - ตอนนี้ใช้ dummy
            // $photos_html .= '<div style="margin-top: 5px;">ภาพที่ ' . ($i + 1) . '</div>';
        }
        $photos_html .= '</td>';

        // --- คอลัมน์ขวา (รูปที่ i+1) ---
        $photos_html .= '<td width="50%" style="border: none; padding: 10px; vertical-align: top; text-align: center;">';
        if (isset($uploaded_photos[$i + 1])) {
            // ดึง base64 จาก object (รูปภาพถูกบันทึกเป็น {filename, original_name, base64})
            $photo_base64_2 = is_array($uploaded_photos[$i + 1]) ? ($uploaded_photos[$i + 1]['base64'] ?? '') : $uploaded_photos[$i + 1];
            if (!empty($photo_base64_2)) {
                $photos_html .= '<div style="border: 1px solid #000; padding: 5px; height: 320px; display: flex; align-items: center; justify-content: center;">';
                $photos_html .= '<img src="' . $photo_base64_2 . '" style="max-width: 100%; max-height: 310px; width: auto; height: auto;">';
                $photos_html .= '</div>';
            }
            // $photos_html .= '<div style="margin-top: 5px;">ภาพที่ ' . ($i + 2) . '</div>';
        }
        $photos_html .= '</td>';

        $photos_html .= '</tr>';
    }
}

$photos_html .= '</table>';

// ผู้บันทึกและวันเวลา ใช้ตัวแปรเดิม ($recorder_name, $record_datetime)

// ==========================================
// เตรียม HTML ส่วนหัวกระดาษ (Header)
// ==========================================

// เตรียมตัวแปรสำหรับข้อมูลไดนามิก (ถ้าไม่มีให้ใส่จุดไข่ปลา)
$report_no = $data['general_info']['report_no'] ?? '...........';
$year_th = !empty($report_year_th) ? $report_year_th : '25..';

$header_html = '
<table width="100%" style="border: none; border-collapse: collapse;">
    <tr>
        <td width="18%" style="text-align: left; vertical-align: bottom;"> 
            <img src="' . __DIR__ . '/../../images/office-of-police-forensic-icon.jpg" style="width: 105px; height: auto;">
        </td>

        <td width="47%" style="vertical-align: bottom; text-align: center; font-weight: bold; font-size: 20px; line-height: 1.4; padding-bottom: 5px; padding-left:50px;">
                แบบตรวจสอบการปฏิบัติงาน (Check list)<br> การตรวจสถานที่เกิดเหตุคดีเกี่ยวกับทรัพย์
        </td>

        <td width="35%" align="right" style="vertical-align: bottom;">
            <table style="border: 1px solid #000; border-collapse: collapse; width: 100%;">
                <tr>
                    <td style="padding: 5px 5px 5px 10px; text-align: left; border: none; font-weight: bold; font-size: 20px; line-height: 1.4;">
                        เลขรับที่/เลขรายงาน ' . $report_no . ' / ' . $year_th . '<br>
                        
                        หน้าที่ {PAGENO} / {nbpg}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
';

// ==========================================
// เตรียม Header สำหรับหน้าแนวนอน (Page 6)
// ==========================================
$header_landscape_html = '
<table width="100%" style="border: none; border-collapse: collapse;">
    <tr>
        <td width="15%" style="text-align: left; vertical-align: top;"> 
            <img src="' . __DIR__ . '/../../images/office-of-police-forensic-icon.jpg" style="width: 100px; height: auto;">
        </td>

        <td width="55%" style="vertical-align: middle; text-align: center; font-weight: bold; font-size: 16pt; line-height: 1.4;">
            แบบตรวจสอบการปฏิบัติงาน (Check list)<br> 
            การตรวจสถานที่เกิดเหตุคดีเกี่ยวกับทรัพย์<br>
            <span style="font-size: 18pt;">บันทึกการตรวจเก็บวัตถุพยาน</span>
        </td>

        <td width="30%" align="right" style="vertical-align: top;">
            <table style="border: 1px solid #000; border-collapse: collapse; width: 100%;">
                <tr>
                    <td style="padding: 5px; text-align: left; border: none; font-size: 14pt; line-height: 1.4;">
                        เลขรับที่/เลขรายงาน ' . $report_no . ' / ' . $year_th . '<br>
                        หน้าที่ {PAGENO} / {nbpg}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
';

// ==========================================
// เตรียม HTML ส่วนท้ายกระดาษ (Footer)
// ==========================================
$footer_html = '
<table width="100%" style="border: none; border-collapse: collapse; margin-top: 20px;">
    <tr>
        <td width="75%" style="border: none; text-align: left; vertical-align: top; font-size: 16px;">
            <span class="bold" style="font-size: 16px;">หมายเหตุ</span> &nbsp; กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถบันทึกเพิ่มเติมได้ที่ด้านหลังกระดาษ
        </td>
        
        <td width="25%" style="border: none; text-align: right; vertical-align: top; font-size: 16px; line-height: 1.4;">
            F-CS-08 แก้ไขครั้งที่ 2<br>
            แก้ไขวันที่ 2 ก.ย. 63<br>
            เริ่มใช้ 1 ต.ค. 63
        </td>
    </tr>
</table>
';

// =========================================================
//  PART 1: HTML หน้าที่ 1
// =========================================================
$html_page1 = '
<table class="main-layout">
        <thead>
            <tr style="background-color: #f0f0f0;">
                <td class="layout-col" width="50%" style="border-right: none !important;">
                    <table class="inner-table" width="100%">
                        <tr>
                            <td class="seq-col-1" style="border-bottom: none; font-size: 32px;" width="15% ">ลำดับ</td>
                            <td class="content-col text-center bold" style="border-bottom: none; font-size: 32px;">ข้อมูล</td>
                        </tr>
                    </table>
                </td>
                <td class="layout-col" style="border-left: none !important; border-right: none !important;">
                    <table class="inner-table" width="100%">
                        <tr>
                            <td class="seq-col-1" style="border-bottom: none; font-size: 32px;" width="15%">ลำดับ</td>
                            <td class="content-col text-center bold" style="border-bottom: none; font-size: 32px;">ข้อมูล</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="layout-col" width="50%" style="border-right: none !important; ">
                    <table class="inner-table" width="100%">
                        
                        <tr>
                            <td class="seq-col" width="15%" style="vertical-align: top; padding-top: 10px; font-size: 34px;">
                                1.<br>การรับ<br>แจ้งเหตุ
                            </td>
                            <td class="content-col align-top" style="line-height: 1.4; padding: 10px 5px;">
                                
                                <table width="100%" class="no-border border-collapse">
                                    <tr>
                                        <td style="border: none; padding-bottom: 0px;">
                                            <span class="bold" style="font-size: 32px;">คดี</span> 
                                            ' . tab(2) . ' <span class="chk-box">' . chk($data['general_info']['case_type'], 'theft') . '</span>' . tab(1) . ' <span style="font-size: 32px;">ลักทรัพย์</span>
                                            ' . tab(3) . ' <span class="chk-box">' . chk($data['general_info']['case_type'], 'snatch') . '</span>' . tab(1) . ' <span style="font-size: 32px;">ชิงทรัพย์</span>
                                            ' . tab(3) . ' <span class="chk-box">' . chk($data['general_info']['case_type'], 'robbery') . '</span>' . tab(1) . ' <span style="font-size: 32px;">ปล้นทรัพย์</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="border: none; padding-bottom: 0px; padding-left: 48px; font-size: 32px;">
                                            <span class="chk-box">' . chk($data['general_info']['case_type'], 'other') . '</span>' . tab(1) . ' อื่นๆ ' . str_dots($data['general_info']['case_type_other'], 80) . '
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="border: none; padding-bottom: 0px; font-size: 32px;">
                                            วันที่ ' . str_dots($report_date_th, 40) . ' 
                                            เวลาประมาณ ' . str_dots($report_time, 30) . ' น.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="border: none; padding-bottom: 0px; font-size: 32px;">
                                            การรับแจ้ง 
                                            ' . tab(2) . ' <span class="chk-box">' . chk($data['general_info']['report_channel'], 'phone') . '</span>' . tab(1) . ' ทางโทรศัพท์ 
                                            ' . tab(2) . ' <span class="chk-box">' . chk($data['general_info']['report_channel'], 'radio') . '</span>' . tab(1) . ' ทางวิทยุสื่อสาร
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="border: none; padding-bottom: 0px; font-size: 32px;">
                                            <span class="chk-box">' . chk($data['general_info']['report_channel'], 'document') . '</span>' . tab(1) . ' ทางหนังสือ 
                                            ' . tab(1) . '<span class="chk-box">' . chk($data['general_info']['report_channel'], 'other') . '</span>' . tab(1) . ' อื่นๆ ' . str_dots($data['general_info']['report_channel_other'] ?? '', 15) . '
                                            สน./สภ. ' . str_dots($data['general_info']['source_station'] ?? '', 15) . '
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="border: none; padding-bottom: 0px; font-size: 32px;">
                                            ที่ ' . str_dots($data['general_info']['document_no'], 35) . ' 
                                            ลง ' . str_dots($data['general_info']['document_date'], 35) . '
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="border: none; padding-bottom: 0px; font-size: 32px;">
                                            พนักงานสอบสวน ' . str_dots(trim(($data['general_info']['investigator']['firstname'] ?? '') . ' ' . ($data['general_info']['investigator']['lastname'] ?? '')), 60) . '
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="border: none; font-size: 32px;">
                                            หมายเลขโทรศัพท์ ' . str_dots($data['general_info']['investigator']['phone'], 60) . '
                                        </td>
                                    </tr>
                                </table>

                            </td>
                        </tr>

                        <tr>
                            <td class="seq-col" style="vertical-align: top; padding-top: 10px; font-size: 34px;">
                                2.<br>สถานที่<br>เกิดเหตุ
                            </td>
                            <td class="content-col" style="vertical-align: top; line-height: 1.4; padding: 10px 5px;">
                                
                                <table width="100%" style="border: none; border-collapse: collapse;">
                                    <tr>
                                        <td style="border: none; padding-bottom: 0px;">
                                            <span class="bold" style="font-size: 32px;">สถานที่เกิดเหตุ</span> ' . str_dots($data['general_info']['location_detail'], 65) . '
                                        </td>
                                    </tr>
                                    ' . getFillLinesHTML($data['general_info']['location_detail'], 4, 65, 99) . '
                                    <tr>
                                        <td style="border: none; padding-top: 0px; padding-left: 100px; font-size: 32px;">
                                            <span class="chk-box">' . chk($data['general_info']['victim']['type'], 'owner') . '</span>' . tab(1) . ' เจ้าของบ้าน
                                            ' . tab(3) . '
                                            <span class="chk-box">' . chk($data['general_info']['victim']['type'], 'victim') . '</span>' . tab(1) . ' ผู้เสียหาย
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="border: none; padding-left: 100px; padding-bottom: 0px; font-size: 32px;">
                                            <span class="chk-box">' . chk($data['general_info']['victim']['type'], 'other') . '</span>' . tab(1) . ' ผู้เกี่ยวข้องอื่น 
                                            ' . str_dots($data['general_info']['victim']['type_other'] ?? '', 40) . '
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="border: none; font-size: 32px;">
                                            ชื่อ ' . str_dots(trim(($data['general_info']['victim']['firstname'] ?? '') . ' ' . ($data['general_info']['victim']['lastname'] ?? '')), 45) . ' 
                                            อายุประมาณ ' . str_dots($data['general_info']['victim']['age'] ?? '', 10) . ' ปี
                                        </td>
                                    </tr>
                                </table>

                            </td>
                        </tr>

                        <tr>
                            <td class="seq-col" style="vertical-align: top; padding-top: 10px; padding-bottom: 10px; font-size: 34px;">
                                3.<br>วันเวลาที่<br>ทราบเหตุ<br>/เกิดเหตุ
                            </td>
                            <td class="content-col" style="vertical-align: top; line-height: 1.4; padding: 10px 5px;">

                                <table width="100%" style="border: none; border-collapse: collapse;">
                                    <tr>
                                        <td style="border: none;">
                                            ' . bbox() . '' . tab(2) . '<span class="bold" style="font-size: 32px;">วันเวลาที่ผู้เสียหาย ทราบเหตุ/เกิดเหตุ</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="border: none; padding-left: 10px; font-size: 32px; padding-top: 0px;">
                                            วันที่ ' . str_dots($incident_date_th, 43) . ' 
                                            เวลาประมาณ ' . str_dots($incident_time, 20) . ' น.
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <td style="border: none; padding-top:30px ">
                                            ' . bbox() . '' . tab(2) . '<span class="bold" style="font-size: 32px;">วันเวลาที่พนักงานสอบสวนทราบเหตุ</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="border: none; padding-left: 10px; font-size: 32px; padding-top: 0px;">
                                            วันที่ ' . str_dots($invest_date_th, 43) . ' 
                                            เวลาประมาณ ' . str_dots($invest_time, 20) . ' น.
                                        </td>
                                    </tr>
                                </table>

                            </td>
                        </tr>

                        <tr>
                            <td class="seq-col" style="vertical-align: top; padding-top: 10px; font-size: 26px;">
                                4.<br>วันเวลาที่<br>ตรวจเหตุ
                            </td>
                            <td class="content-col" style="vertical-align: top; line-height: 1.4; padding: 10px 5px;">

                                <table width="100%" style="border: none; border-collapse: collapse;">
                                    <tr>
                                        <td style="border: none;">
                                            ' . bbox() . '' . tab(2) . '<span class="bold" style="font-size: 26px;">วันเวลาที่ทำการตรวจสถานที่เกิดเหตุ</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="border: none; padding-left: 10px; font-size: 26px; padding-top: 0px;">
                                            วันที่ ' . str_dots($inspection_date_th, 43) . ' 
                                            เวลาประมาณ ' . str_dots($inspection_time, 20) . ' น.
                                        </td>
                                    </tr>

                                    <tr>
                                        <td style="border: none;">
                                            ' . bbox() . '' . tab(2) . '<span class="bold" style="font-size: 26px;">วันเวลาที่ทำการตรวจสถานที่เกิดเหตุเพิ่มเติม</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="border: none; padding-left: 10px; font-size: 26px; padding-top: 0px;">
                                            วันที่ ' . str_dots($add_inspect_date_th, 43) . ' 
                                            เวลาประมาณ ' . str_dots($add_inspect_time, 20) . ' น.
                                        </td>
                                    </tr>
                                </table>

                            </td>
                        </tr>

                        <tr class="last-row">
                            <td class="seq-col" style="vertical-align: top; padding-top: 10px; font-size: 26px;">
                                <br>5.<br>ผู้ตรวจ<br>สถานที่<br>เกิดเหตุ
                            </td>
                            <td class="content-col" style="vertical-align: top; line-height: 1.4; padding: 10px 5px;"> 
                                
                                <table width="100%" style="border: none; border-collapse: collapse;">
                                    <tr>
                                        <td style="border: none; padding-bottom: 10px;">
                                            ' . bbox() . '' . tab(2) . '<span class="bold" style="font-size: 26px;">ผู้ตรวจสถานที่เกิดเหตุ</span>
                                        </td>
                                    </tr>
                                    ' . $inspector_rows_html . '
                                </table>

                            </td>
                        </tr>
                    </table>
                </td>

                <td class="layout-col" style="border-left: none !important; border-right: none !important;">
                    <table class="inner-table" width="100%">
                        <tr>
                            <td class="seq-col" width="15%" style="font-size: 26px;">6. <br>ลักษณะ<br>สถานที่<br>เกิดเหตุ</td>
                            
                            <td class="content-col" style="vertical-align: top; padding: 10px;">
                                
                                <table width="100%" style="border: none; border-collapse: collapse;">
                                    
                                    <tr>
                                        <td style="border: none; padding-bottom: 0px;">
                                            ' . bbox() . '' . tab(2) . '<span class="bold" style="font-size: 26px;">สภาพสถานที่เกิดเหตุเมื่อไปถึง</span>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td style="border: none; padding: 0px 0px 5px 35px;">
                                            ' . bbox() . '' . tab(2) . '<span class="bold" style="font-size: 26px;">การรักษาสถานที่เกิดเหตุ</span>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td style="border: none; padding-left: 62px; line-height: 1; font-size: 26px;">
                                            <span class="chk-box">' . chk($data['scene_characteristics']['preservation'], 'yes') . '</span>' . tab(1) . ' มี ' . str_dots($data['scene_characteristics']['preservation_detail'], 120) . '
                                        </td>
                                    </tr>

                                    <tr>
                                        <td style="border: none; padding-left: 62px; line-height: 1; font-size: 26px;">
                                            <span class="chk-box">' . chk($data['scene_characteristics']['preservation'], 'no') . '</span>' . tab(1) . ' ไม่มี ' . str_dots($data['scene_characteristics']['preservation_detail'], 120) . '
                                        </td>
                                    </tr>

                                    <tr>
                                        <td style="border: none; padding-bottom: 0px;">
                                            ' . bbox() . '' . tab(2) . '<span class="bold" style="font-size: 26px;">ลักษณะสถานที่เกิดเหตุ</span>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td style="border: none;">
                                            ' . bbox() . '' . tab(2) . '<span class="bold" style="font-size: 26px;">ลักษณะภายนอก</span>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td style="border: none; padding-left: 33px;">
                                            <table width="100%" style="border: none; border-collapse: collapse;">
                                                <tr>
                                                    <td style="border: none; padding-right: 0px; vertical-align: middle; line-height: 1.4;">
                                                        ' . $building_rows_html . '
                                                    </td>
                                                    
                                                    <td style="border: none; padding-right: 5px; vertical-align: middle; text-align: center;">
                                                        <svg width="28px" height="180px" viewBox="0 0 20 180" style="vertical-align: middle;">
                                                            <path d="M2,5 Q15,5 15,40 L15,80 Q15,90 20,90 Q15,90 15,100 L15,140 Q15,175 2,175" 
                                                                fill="none" stroke="#000" stroke-width="1.5" />
                                                        </svg>                             
                                                    </td>

                                                    <td style="border: none; vertical-align: middle;">
                                                        ' . $floor_section_html . '
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td style="border: none; padding-left: 20px; padding-bottom: 0px; line-height: 1.4; font-size: 26px">
                                            บริเวณโดยรอบ 
                                            ' . tab(15) . '<span style="padding-left: 10px;">
                                                <span class="chk-box">' . chk($data['scene_characteristics']['fence'], 'has_fence') . '</span>' . tab(1) . ' <span style="font-size: 26px">มีรั้ว</span>
                                            </span>
                                            ' . tab(7) . '<span style="padding-left: 10px;">
                                                <span class="chk-box">' . chk($data['scene_characteristics']['fence'], 'no_fence') . '</span>' . tab(1) . ' <span style="font-size: 26px">ไม่มีรั้ว</span>
                                            </span>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td style="border: none; padding-left: 20px; padding-bottom: 0; line-height: 1.4; font-size: 26px">
                                            เมื่อหันหน้าเข้า
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <td style="border: none; padding-left: 24px; line-height: 1.4; font-size: 26px">
                                            ด้านหน้าติด ' . str_dots($data['scene_characteristics']['surroundings']['front'], 50) . '<br>
                                            ด้านซ้ายติด ' . str_dots($data['scene_characteristics']['surroundings']['left'], 50) . '<br>
                                            ด้านขวาติด ' . str_dots($data['scene_characteristics']['surroundings']['right'], 50) . '<br>
                                            ด้านหลังติด ' . str_dots($data['scene_characteristics']['surroundings']['back'], 50) . '
                                        </td>
                                    </tr>
   
                                    <tr>
                                        <td style="border: none; padding-bottom: 0;">
                                            ' . bbox() . '' . tab(2) . '<span class="bold" style="padding-left: 5px; font-size: 26px">ลักษณะภายใน</span>
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <td style="border: none; line-height: 1.4; font-size: 26px">
                                            ' . str_dots($data['scene_characteristics']['interior_detail'], 70) . '
                                        </td>
                                    </tr>
                                    ' . getFillLinesHTML($data['scene_characteristics']['interior_detail'], 2, 90, 90) . '

                                    <tr>
                                        <td style="border: none;">
                                            ' . bbox() . '' . tab(2) . '<span class="bold" style="padding-left: 5px; font-size: 26px">บริเวณที่เกิดเหตุ เกิดเหตุที่</span> ' . str_dots($data['scene_characteristics']['point_detail'], 35) . '
                                        </td>
                                    </tr>
                                    ' . getFillLinesHTML($data['scene_characteristics']['point_detail'], 3, 55, 90) . '

                                </table>
                            </td>
                        </tr>
                            
                      <tr class="last-row">
                            <td class="seq-col" style="vertical-align: top; padding-top: 10px; font-size: 26px">
                                7.<br>ผลการ<br>ตรวจ<br>สถานที่<br>เกิดเหตุ
                            </td>
                            
                            <td class="content-col" style="vertical-align: top; padding: 5px;">
                                
                                <table width="100%" style="border: none; border-collapse: collapse;">
                                    
                                    <tr>
                                        <td style="border: none; line-height: 1.4;">
                                            ' . bbox() . '' . tab(2) . '<span class="bold" style="font-size: 26px">พฤติการณ์ของคดี</span> ' . str_dots($data['case_behavior_info']['behavior_text'], 90) . '
                                        </td>
                                    </tr>
                                    ' . getFillLinesHTML($data['case_behavior_info']['behavior_text'], 4, 90, 99) . '
                                    <tr>
                                        <td style="border: none; line-height: 1.4;">
                                            ' . bbox() . '' . tab(2) . '<span class="bold" style="font-size: 26px">ทางเข้าของคนร้าย</span>
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <td style="border: none; padding-left: 33px; line-height: 1.4; padding-top: -3px">
                                            
                                            <div style="margin-bottom: 2px;">
                                                <span class="chk-box">' . chk($data['case_behavior_info']['entry_points'] ?? [], 'no_trace') . '</span> 
                                                ' . tab(1) . '<span style="font-size: 26px">ไม่พบร่องรอยใดๆ บริเวณสถานที่เกิดเหตุ</span>
                                            </div>

                                            <div style="margin-bottom: 2px;">
                                                <span class="chk-box">' . chk($data['case_behavior_info']['entry_points'] ?? [], 'unlocked') . '</span> 
                                                ' . tab(1) . '<span style="font-size: 26px">ผู้เสียหายไม่ได้ทำการปิดล็อกประตู/หน้าต่าง</span>
                                            </div>

                                            <div style="margin-bottom: 2px;">
                                                <span class="chk-box">' . chk($data['case_behavior_info']['entry_points'] ?? [], 'found_trace') . '</span> 
                                                ' . tab(1) . '<span style="font-size: 26px">พบร่องรอย 
                                                ' . tab(2) . '
                                                <span class="chk-box">' . chk($data['case_behavior_info']['entry_points'] ?? [], 'pry') . '</span>' . tab(1) . ' <span style="font-size: 26px">การงัด </span>
                                                ' . tab(2) . '
                                                <span class="chk-box">' . chk($data['case_behavior_info']['entry_points'] ?? [], 'cut') . '</span>' . tab(1) . ' <span style="font-size: 26px">การตัด </span>
                                                ' . tab(2) . '
                                                <span class="chk-box">' . chk($data['case_behavior_info']['entry_points'] ?? [], 'drill') . '</span>' . tab(1) . ' <span style="font-size: 26px">การเจาะ</span>
                                            </div>

                                        </td>
                                    </tr>
                                </table>

                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </tbody>
    </table>
';

// =========================================================
//  PART 2: HTML หน้าที่ 2 (สร้างตามรูปภาพที่แนบมา)
// =========================================================
$html_page2 = '
<table class="main-layout">
    <thead>
        <tr style="background-color: #f0f0f0; height: 35px;">
            <td class="layout-col" width="50%" style="border-right: none !important;">
                <table class="inner-table" >
                    <tr>
                        <td class="seq-col-1" style="border-bottom: none;" width="15%">ลำดับ</td>
                        <td class="content-col text-center bold" style="border-bottom: none; text-align:center;">ข้อมูล</td>
                    </tr>
                </table>
            </td>
            <td class="layout-col" style="border-left: none !important; border-right: none !important;">
                <table class="inner-table">
                    <tr>
                        <td class="seq-col-1" style="border-bottom: none;" width="15%">ลำดับ</td>
                        <td class="content-col text-center bold" style="border-bottom: none; text-align:center;">ข้อมูล</td>
                    </tr>
                </table>
            </td>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="layout-col" width="50%" style="border-right: none !important;">
                <table class="inner-table">
                    
                    <tr class="last-row">
                        <td class="seq-col" width="15%" style="vertical-align: middle; text-align: center;">
                            7.<br>(ต่อ)
                        </td>
                        <td class="content-col" style="vertical-align: top; line-height: 1.6; padding: 5px;">
                            
                            <table width="100%" style="border: none; border-collapse: collapse;">
                                
                                <tr>
                                    <td style="border: none; padding-left: 33px; padding-bottom: 10px;">
                                        <span class="chk-box">' . chk($loc_other_loc['chk'], true) . '</span>' . tab(1) . ' ร่องรอยอื่นๆ ' . str_dots($loc_other_loc['txt'], 60) . ' ที่
                                    </td>
                                </tr>
                                <tr>
                                    <td style="border: none; padding-left: 33px; padding-bottom: 10px;">
                                        <span class="chk-box">' . chk($loc_door['chk'], true) . '</span>' . tab(1) . ' ประตู ' . str_dots($loc_door['txt'], 75) . '
                                    </td>
                                </tr>
                                <tr>
                                    <td style="border: none; padding-left: 33px; padding-bottom: 10px;">
                                        <span class="chk-box">' . chk($loc_window['chk'], true) . '</span>' . tab(1) . ' หน้าต่าง ' . str_dots($loc_window['txt'], 70) . '
                                    </td>
                                </tr>
                                <tr>
                                    <td style="border: none; padding-left: 33px; padding-bottom: 10px;">
                                        <span class="chk-box">' . chk($loc_ceiling['chk'], true) . '</span>' . tab(1) . ' ฝ้าเพดาน ' . str_dots($loc_ceiling['txt'], 69) . '
                                    </td>
                                </tr>
                                <tr>
                                    <td style="border: none; padding-left: 33px; padding-bottom: 10px;">
                                        <span class="chk-box">' . chk($loc_roof['chk'], true) . '</span>' . tab(1) . ' หลังคา ' . str_dots($loc_roof['txt'], 72) . '
                                    </td>
                                </tr>
                                <tr>
                                    <td style="border: none; padding-left: 33px; padding-bottom: 10px;">
                                        <span class="chk-box">' . chk($loc_other_gen['chk'], true) . '</span>' . tab(1) . ' อื่นๆ ' . str_dots($loc_other_gen['txt'], 76) . '
                                    </td>
                                </tr>
                                
                                <tr>
                                    <td style="border: none; padding-bottom: 10px;">
                                        ' . bbox() . '' . tab(2) . '<span class="bold">เครื่องมือที่คนร้ายใช้ในการโจรกรรม</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="border: none; padding-left: 33px;">
                                            <span class="chk-box">' . chk($tool_screw, true) . '</span>' . tab(1) . ' ไขควง ' . tab(2) . '
                                            <span class="chk-box">' . chk($tool_crowbar, true) . '</span>' . tab(1) . ' ชะแลง ' . tab(2) . '
                                            <span class="chk-box">' . chk($tool_cutter, true) . '</span>' . tab(1) . ' คีมตัดโลหะ
                                    </td>
                                </tr>
                                <tr>
                                    <td style="border: none; padding-left: 33px;">
                                        <span class="chk-box">' . chk($tool_other, true) . '</span>' . tab(1) . ' อื่นๆ ' . str_dots($tool_other_txt, 60) . '
                                    </td>
                                </tr>
                                
                                <tr>
                                    <td style="border: none; padding: 5px 0px 10px 33px;">
                                        ขนาดความกว้างของรอยประมาณ ' . str_dots($trace_width, 15) . ' ซม.
                                    </td>
                                </tr>
                                <tr>

                                <tr>
                                    <td style="border: none; padding-bottom: 5px;">
                                        ' . bbox() . '' . tab(2) . '<span class="bold">คดีชิงทรัพย์/ปล้นทรัพย์ กรณีทราบข้อมูลคนร้าย</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="border: none; padding-left: 33px; padding-bottom: 10px;">
                                        จำนวนคนร้าย ' . str_dots($perp_count, 40) . ' คน
                                    </td>
                                </tr>
                                <tr>
                                    <td style="border: none; padding-left: 33px; padding-bottom: 10px;">
                                        <span class="chk-box">' . chk($is_weapon_unused, true) . '</span>' . tab(1) . ' คนร้ายไม่ใช้อาวุธในการก่อเหตุ
                                    </td>
                                </tr>
                                <tr>
                                    <td style="border: none; padding-left: 33px; padding-bottom: 10px;">
                                        <span class="chk-box">' . chk($is_weapon_used, true) . '</span>' . tab(1) . ' อาวุธที่คนร้ายใช้ในการก่อเหตุ
                                    </td>
                                </tr>
                                <tr>
                                    <td style="border: none; padding-left: 33px; padding-bottom: 10px;">
                                        <span class="chk-box">' . chk($wp_knife, true) . '</span>' . tab(1) . ' อาวุธมีด ' . tab(4) . ' 
                                        <span class="chk-box">' . chk($wp_gun, true) . '</span>' . tab(1) . ' อาวุธปืน ' . tab(4) . ' 
                                        <span class="chk-box">' . chk($wp_rope, true) . '</span>' . tab(1) . ' เชือก
                                    </td>
                                </tr>
                                <tr>
                                    <td style="border: none; padding-left: 33px; padding-bottom: 10px;">
                                        <span class="chk-box">' . chk($wp_other, true) . '</span>' . tab(1) . ' อื่นๆ ' . str_dots($wp_other_txt, 45) . '
                                    </td>
                                </tr>

                                <tr>
                                    <td style="border: none; padding-left: 33px; padding-bottom: 10px;">
                                        คนร้ายได้พันธนาการผู้เสียหายอย่างไร
                                    </td>
                                </tr>
                                <tr>
                                    <td style="border: none; padding-left: 33px; padding-bottom: 10px;">
                                        <span class="chk-box">' . chk($res_confine, true) . '</span>' . tab(1) . ' กักขังภายในห้อง
                                    </td>
                                </tr>
                                <tr>
                                    <td style="border: none; padding-left: 33px; padding-bottom: 10px;">
                                        <span class="chk-box">' . chk($res_bind, true) . '</span>' . tab(1) . ' คนร้ายใช้ ' . str_dots($res_bind_txt, 35) . ' ในการพันธนาการ
                                    </td>
                                </tr>

                                <tr>
                                    <td style="border: none; padding-left: 33px; padding-bottom: 10px;">
                                        <span class="chk-box">' . chk($is_injured, true) . '</span>' . tab(1) . ' มีผู้บาดเจ็บ ' . tab(16) . '
                                        <span style="padding-left: 40px;">
                                            <span class="chk-box">' . chk($is_dead, true) . '</span>' . tab(1) . ' มีผู้เสียชีวิต
                                        </span>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="border: none; padding-left: 33px;">
                                        ลักษณะ/ตำแหน่ง/จำนวนของบาดแผล
                                    </td>
                                </tr>
                                <tr>
                                    <td style="border: none; padding-left: 33px; line-height: 1.6;">
                                        ' . str_dots($injury_txt, 90) . '
                                    </td>
                                </tr>

                                ' . getFillLinesHTML($injury_txt, 2, 90, 90, 'padding-left: 33px;') . '
                                                                
                                <tr>
                                    <td style="border: none;">
                                        ' . bbox() . '' . tab(2) . '<span class="bold">สภาพร่องรอยและตำแหน่งที่ตรวจพบ</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="border: none;">
                                        ' . renderTraceCardHTML($trace_first) . '
                                    </td>
                                </tr>

                            </table>
                        </td>
                    </tr>
                </table>
            </td>

            <td class="layout-col" style="border-left: none !important; border-right: none !important;">
                <table class="inner-table">
                    <tr class="last-row">
                        <td class="seq-col" width="15%" style="vertical-align: middle; text-align: center;">
                            7.<br>(ต่อ)
                        </td>
                        <td class="content-col" style="vertical-align: top; line-height: 1.6; padding: 5px;">
                            
                            ' . $trace_right_html . '

                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </tbody>
</table>
';

// =========================================================
//  PART 3: HTML หน้าที่ 3 
// =========================================================
$html_page3 = '
<table class="main-layout">
    <thead>
        <tr style="background-color: #f0f0f0; height: 35px;">
            <td class="layout-col" width="50%" style="border-right: none !important;">
                <table class="inner-table">
                    <tr>
                        <td class="seq-col-1" style="border-bottom: none;" width="15%">ลำดับ</td>
                        <td class="content-col text-center bold" style="border-bottom: none; text-align:center;">ข้อมูล</td>
                    </tr>
                </table>
            </td>
            <td class="layout-col" style="border-left: none !important; border-right: none !important;">
                <table class="inner-table">
                    <tr>
                        <td class="seq-col-1" style="border-bottom: none;" width="15.8%">ลำดับ</td>
                        <td class="content-col text-center bold" style="border-bottom: none; text-align:center;">ข้อมูล</td>
                    </tr>
                </table>
            </td>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="layout-col" width="50%" style="border-right: none !important;">
                <table class="inner-table">
                    
                    <tr class="last-row">
                        <td class="seq-col" width="15%" style="vertical-align: middle; text-align: center;">
                            7.<br>(ต่อ)
                        </td>
                        <td class="content-col" style="vertical-align: top; line-height: 1.6; padding: 5px;">
                            
                            <table width="100%" style="border: none; border-collapse: collapse;">
                                
                                <tr>
                                    <td style="border: none;">
                                        ' . bbox() . '' . tab(2) . '<span class="bold">ทรัพย์สินที่ถูกโจรกรรม</span> ' . str_dots($stolen_prop, 40) . '
                                    </td>
                                </tr>

                                ' . getFillLinesHTML($stolen_prop, 12, 40, 83) . '
                                
                                <tr>
                                    <td style="border: none;">
                                        ' . bbox() . '' . tab(2) . '<span class="bold">วัตถุพยานที่ตรวจพบ</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="border: none; padding-left: 33px; padding-top: 10px;">
                                        <span class="chk-box">' . chk($has_blood, true) . '</span>' . tab(1) . ' คราบสีแดงคล้ายโลหิต ' . str_dots($blood_detail, 30) . '
                                    </td>
                                </tr>
                                ' . getFillLinesHTML($blood_detail, 3, 30, 75) . '
                                <tr>
                                    <td style="border: none; padding-left: 33px; padding-top: 10px;">
                                        <span class="chk-box">' . chk(($test_hemastix || $test_phenol), true) . '</span>' . tab(1) . ' ทดสอบด้วยชุดทดสอบคราบโลหิตเบื้องต้น
                                    </td>
                                </tr>

                                <tr>
                                    <td style="border: none; padding-left: 63px;">
                                        <span class="chk-box">' . chk($test_hemastix, true) . '</span>' . tab(1) . ' Hemastix
                                    </td>
                                </tr>
                                <tr>
                                    <td style="border: none; padding-left: 90px;">
                                        <span class="chk-box">' . chk(($test_hemastix && $res_hemastix === 'change'), true) . '</span>' . tab(1) . ' เกิดการเปลี่ยนแปลงเป็นสีเขียวแกมน้ำเงิน
                                    </td>
                                </tr>
                                <tr>
                                    <td style="border: none; padding-left: 90px;">
                                        <span class="chk-box">' . chk(($test_hemastix && $res_hemastix === 'no_change'), true) . '</span>' . tab(1) . ' ไม่เกิดการเปลี่ยนแปลง
                                    </td>
                                </tr>

                                <tr>
                                    <td style="border: none; padding-left: 63px;">
                                        <span class="chk-box">' . chk($test_phenol, true) . '</span>' . tab(1) . ' Phenolphthalein
                                    </td>
                                </tr>
                                <tr>
                                    <td style="border: none; padding-left: 90px;">
                                        <span class="chk-box">' . chk(($test_phenol && $res_phenol === 'change'), true) . '</span>' . tab(1) . ' เกิดการเปลี่ยนแปลงเป็นสีชมพูในทันที
                                    </td>
                                </tr>
                                <tr>
                                    <td style="border: none; padding-left: 90px; padding-bottom: 10px;">
                                        <span class="chk-box">' . chk(($test_phenol && $res_phenol === 'no_change'), true) . '</span>' . tab(1) . ' ไม่เกิดการเปลี่ยนแปลง
                                    </td>
                                </tr>

                                <tr>
                                    <td style="border: none;">
                                        <span class="chk-box">' . chk($collected_other['found'], true) . '</span>' . tab(1) . ' วัตถุพยานอื่นๆ
                                    </td>
                                </tr>
                                ' . getFillLinesHTML($collected_other['detail'], 8, 80, 80) . '

                            </table>
                        </td>
                    </tr>
                </table>
            </td>

            <td class="layout-col" style="border-left: none !important; border-right: none !important;">
                <table class="inner-table">
                    
                    <tr>
                        <td class="seq-col" width="15%" style="vertical-align: middle; text-align: center;">
                            7.<br>(ต่อ)
                        </td>
                        <td class="content-col" style="vertical-align: top; line-height: 1.6; padding: 5px;">
                            
                            <table width="100%" style="border: none; border-collapse: collapse;">
                                <tr>
                                    <td style="border: none;">
                                        ' . bbox() . '' . tab(2) . '<span class="bold">วัตถุพยานที่ตรวจเก็บ/การดำเนินการเกี่ยวกับวัตถุพยาน<br>เพื่อส่งตรวจพิสูจน์</span>
                                    </td>
                                </tr>                                

                                <tr>
                                    <td style="border: none; padding-left: 33px;">
                                        <span class="chk-box">' . chk($collected_fingerprint['found'], true) . '</span>' . tab(1) . ' วัตถุพยานประเภทลายนิ้วมือ/ฝ่ามือ/ฝ่าเท้าแฝง
                                    </td>
                                </tr>
                                ' . getFillLinesHTML($collected_fingerprint['detail'], 3, 80, 80) . '
                                
                                <tr>
                                    <td style="border: none; padding-left: 33px;">
                                        <span class="chk-box">' . chk($collected_dna['found'], true) . '</span>' . tab(1) . ' วัตถุพยานประเภทสารพันธุกรรม
                                    </td>
                                </tr>
                                ' . getFillLinesHTML($collected_dna['detail'], 3, 80, 80) . '

                                <tr>
                                    <td style="border: none; padding-left: 33px;">
                                        <span class="chk-box">' . chk($collected_toolmark['found'], true) . '</span>' . tab(1) . ' วัตถุพยานประเภทร่องรอยการตัด (Toolmark)
                                    </td>
                                </tr>
                                ' . getFillLinesHTML($collected_toolmark['detail'], 3, 80, 80) . '

                                <tr>
                                    <td style="border: none; padding-left: 33px;">
                                        <span class="chk-box">' . chk($collected_other['found'], true) . '</span>' . tab(1) . ' วัตถุพยานประเภทอื่นๆ
                                    </td>
                                </tr>
                                ' . getFillLinesHTML($collected_other['detail'], 6, 80, 80) . '

                                <tr>
                                    <td style="border: none; padding: 10px 0px 0px 10px">
                                        <span class="chk-box">' . chk($chk_final_verify, true) . '</span>' . tab(1) . ' การตรวจสอบครั้งสุดท้าย
                                    </td>
                                </tr>
                                <tr>
                                    <td style="border: none; padding: 10px 0px 0px 10px">
                                        <span class="chk-box">' . chk($chk_collect_all, true) . '</span>' . tab(1) . ' ตรวจเก็บวัตถุพยานครบถ้วน
                                    </td>
                                </tr>
                                <tr>
                                    <td style="border: none; padding: 10px 0px 0px 10px">
                                        <span class="chk-box">' . chk($chk_photo_taken, true) . '</span>' . tab(1) . ' ถ่ายภาพสถานที่เกิดเหตุ และดำเนินการส่งมอบ
                                    </td>
                                </tr>
                                <tr>
                                    <td style="border: none; padding-left: 35px; padding-bottom: 10px;">
                                        สถานที่เกิดเหตุให้แก่พนักงานสอบสวน
                                    </td>
                                </tr>
                                </table>

                        </td>
                    </tr>

                    <tr class="last-row">
                        <td class="seq-col" width="15%" style="vertical-align: top; padding-top: 10px; text-align: center;">
                            <br>8.<br>การส่ง<br>มอบคืน<br>สถานที่<br>เกิดเหตุ
                        </td>
                        <td class="content-col" style="vertical-align: top; line-height: 1.6; padding: 5px;">
                            
                            <table width="100%" style="border: none; border-collapse: collapse;">
                                <tr>
                                    <td style="border: none; padding-top: 10px; padding-bottom: 10px;">
                                        ' . bbox() . '' . tab(2) . '<span class="bold">วันเวลาที่ทำการตรวจสถานที่เกิดเหตุเสร็จ</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="border: none;">
                                        วันที่ ' . str_dots($finish_date_th, 30) . ' 
                                        เวลาประมาณ ' . str_dots($finish_time_th, 15) . ' น.
                                    </td>
                                </tr>

                                <tr><td style="border: none; height: 10px;"></td></tr>

                                <tr>
                                    <td style="border: none;  padding-top: 40px; padding-bottom: 10px;">
                                        ' . bbox() . '' . tab(2) . '<span class="bold">การส่งมอบสถานที่เกิดเหตุ</span>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <td style="border: none; vertical-align: bottom; padding-bottom: 10px;">
                                        ลงชื่อ ' . $recv_sig_img . ' ผู้รับมอบสถานที่เกิดเหตุ
                                    </td>
                                </tr>
                                <tr>
                                    <td style="border: none; padding-left: 35px; padding-bottom: 10px;">
                                        (' . str_dots($recv_name, 45) . ')
                                    </td>
                                </tr>
                                <tr>
                                    <td style="border: none; padding-bottom: 10px; padding-bottom: 20px;">
                                        ตำแหน่ง ' . str_dots($recv_pos, 55) . '
                                    </td>
                                </tr>

                                <tr>
                                    <td style="border: none; vertical-align: bottom; padding-bottom: 10px;">
                                        ลงชื่อ ' . $delv_sig_img . ' ผู้ส่งมอบสถานที่เกิดเหตุ
                                    </td>
                                </tr>
                                <tr>
                                    <td style="border: none; padding-left: 35px; padding-bottom: 10px;">
                                        (' . str_dots($delv_name, 45) . ')
                                    </td>
                                </tr>
                                <tr>
                                    <td style="border: none;">
                                        ตำแหน่ง ' . str_dots($delv_pos, 55) . '
                                    </td>
                                </tr>
                            </table>

                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </tbody>
</table>
';

// =========================================================
//  PART 4: HTML หน้าที่ 4 (แผนผังสังเขป)
// =========================================================
$html_page4 = '
<table width="100%" style="border-collapse: collapse; border: none; table-layout: fixed;">
    
    <thead>
        <tr>
            <td style="border: none; text-align: center; font-weight: bold; font-size: 16px; padding-bottom: 10px;">
                แผนผังสังเขป
            </td>
        </tr>
    </thead>

    <tbody>
        <tr>
            <td style="border: 1px solid #000; height: 600px; vertical-align: middle; text-align: center; padding: 10px;">
                ' . $sketch_html . '
            </td>
        </tr>

        <tr>
            <td style="border: none; text-align: right; padding-top: 5px; font-size: 16px;">
                * NOT TO SCALE
            </td>
        </tr>

        <tr>
            <td style="border: none; padding-top: 5px; padding-left: 20px; line-height: 1.8; font-size: 14px;">
                <span>หมายเหตุ</span> ' . str_dots($sketch_remark, 190) . '
        </tr>
        ' . getFillLinesHTML($sketch_remark, 4, 190, 210, 'padding-left: 20px; font-size: 14px;') . '

        <tr>
            <td style="border: none; padding-top: 10px;">
                <table width="100%" style="border: none;">
                    <tr>
                        <td width="40%" style="border: none;"></td> 
                        <td width="60%" style="border: none; text-align: right; line-height: 1.8; font-size: 14px;">
                            ผู้จดบันทึก ' . str_dots($recorder_name, 80) . '<br>
                            ' . $record_datetime . '
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </tbody>
</table>
';

// =========================================================
//  PART 5: HTML หน้าที่ 5 (ตารางรายการวัตถุพยาน)
// =========================================================
$html_page5 = '
<table width="100%" style="border-collapse: collapse; table-layout: fixed;">
    
    <thead>
        <tr>
            <td colspan="8" style="border: none; font-size: 16px; padding-bottom: 10px; padding-left: 10px;">
                ' . bbox() . '' . tab(2) . '<span class="bold">วัตถุพยานและตำแหน่งที่ตรวจพบ</span>
            </td>
        </tr>
        <tr style="text-align: center;">
            <td width="10%" style="border: 1px solid #000; padding: 5px; font-size: 16px; text-align: center; vertical-align: middle;">ป้าย<br>หมายเลข</td>
            <td width="30%" style="border: 1px solid #000; padding: 5px; font-size: 16px; text-align: center; vertical-align: middle;">วัตถุพยาน</td>
            
            <td colspan="4" width="30%" style="border: 1px solid #000; padding: 0;">
                <table width="100%" style="border-collapse: collapse; margin: 0;">
                    <tr>
                        <td colspan="4" style="border-bottom: 1px solid #000; padding: 5px; font-size: 16px; text-align: center; vertical-align: middle;">ระยะห่าง (m) จากจุดอ้างอิง</td>
                    </tr>
                    <tr>
                        <td width="25%" style="border-right: 1px solid #000; padding: 2px; font-size: 16px; text-align: center; vertical-align: middle;">1</td>
                        <td width="25%" style="border-right: 1px solid #000; padding: 2px; font-size: 16px; text-align: center; vertical-align: middle;">2</td>
                        <td width="25%" style="border-right: 1px solid #000; padding: 2px; font-size: 16px; text-align: center; vertical-align: middle;">3</td>
                        <td width="25%" style="padding: 2px; font-size: 16px; text-align: center; vertical-align: middle;">4</td>
                    </tr>
                </table>
            </td>

            <td width="17%" style="border: 1px solid #000; padding: 5px; font-size: 16px; text-align: center; vertical-align: middle;">Azimuth<br>พิกัด/องศา/ระยะ</td>
            <td width="13%" style="border: 1px solid #000; padding: 5px; font-size: 16px; text-align: center; vertical-align: middle;">หมายเหตุ</td>
        </tr>
    </thead>

    <tbody>
        <style>
            .ev-table td { border: 1px solid #000; vertical-align: middle; }
        </style>
        
        ' . str_replace('<tr>', '<tr class="ev-table">', $evidence_rows_html) . '
        
    </tbody>
</table>

<div style="margin-top: 10px;">
    <table width="100%" style="border: none;">
        <tr>
            <td style="border: none; line-height: 1.8; font-size: 14px; padding-left: 20px;">
                
                ' . $ref_points_footer_html . '

            </td>
        </tr>
        <tr>
            <td style="border: none; padding-top: 20px;">
                <table width="100%" style="border: none;">
                    <tr>
                        <td width="40%" style="border: none;"></td>
                        <td width="60%" style="border: none; text-align: right; line-height: 1.8; font-size: 14px;">
                            ผู้จดบันทึก ' . str_dots($recorder_name, 80) . '<br>
                            ' . $record_datetime . '
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>
';

// =========================================================
//  PART 6: HTML หน้าที่ 6 (แนวนอน - บันทึกการตรวจเก็บ)
// =========================================================
$html_page6 = '
<table width="100%" style="border-collapse: collapse;">
    <tr>
        <td style="text-align: center; font-weight: bold; font-size: 16px; border: none; padding-right: 150px;">
            บันทึกการตรวจเก็บวัตถุพยาน
        </td>
    </tr>

    <tr>
        <td style="text-align: left; border: none; padding-top: 5px; padding-bottom: 5px; font-size: 16px;">
            วันที่ตรวจสถานที่เกิดเหตุ ' . $inspect_date_txt . ' 
            เวลาประมาณ ' . $inspect_time_txt . ' น.
        </td>
    </tr>
</table>

<table width="100%" style="border-collapse: collapse; table-layout: fixed;">
    <thead>
        <tr style="text-align: center;">
            <td rowspan="2" width="5%" style="border: 1px solid #000; font-size: 16px; text-align: center; vertical-align: middle; padding: 5px;">ลำดับ</td>
            <td rowspan="2" width="20%" style="border: 1px solid #000; font-size: 16px; text-align: center; vertical-align: middle; padding: 5px;">รายการวัตถุพยาน</td>
            <td rowspan="2" width="5%" style="border: 1px solid #000; font-size: 16px; text-align: center; vertical-align: middle; padding: 5px;">จำนวน</td>
            <td rowspan="2" width="20%" style="border: 1px solid #000; font-size: 16px; text-align: center; vertical-align: middle; padding: 5px;">บริเวณที่ตรวจพบ</td>
            <td rowspan="2" width="7%" style="border: 1px solid #000; font-size: 16px; text-align: center; vertical-align: middle; padding: 5px;">ป้าย<br>หมายเลข</td>
            
            <td colspan="3" style="border: 1px solid #000; font-size: 16px; text-align: center; vertical-align: middle; padding: 5px;">การบรรจุหีบห่อ</td>
            <td colspan="2" style="border: 1px solid #000; font-size: 16px; text-align: center; vertical-align: middle; padding: 5px;">การดำเนินการ<br>เกี่ยวกับวัตถุพยาน</td>
            <td rowspan="2" width="8%" style="border: 1px solid #000; font-size: 16px; text-align: center; vertical-align: middle; padding: 5px;">หมายเหตุ</td>
        </tr>
        <tr style="text-align: center;">
            <td width="7%" style=" border: 1px solid #000; font-size: 16px; text-align: center; vertical-align: middle; padding: 3px;">พลาสติก</td>
            <td width="7%" style=" border: 1px solid #000; font-size: 16px; text-align: center; vertical-align: middle; padding: 3px;">กระดาษ</td>
            <td width="6%" style=" border: 1px solid #000; font-size: 16px; text-align: center; vertical-align: middle; padding: 3px;">อื่นๆ</td>
            <td width="9%" style=" border: 1px solid #000; font-size: 16px; text-align: center; vertical-align: middle; padding: 3px;">ส่งคืน พงส.</td>
            <td width="6%" style=" border: 1px solid #000; font-size: 16px; text-align: center; vertical-align: middle; padding: 3px;">อื่นๆ</td>
        </tr>
    </thead>
    <tbody>
        <style>
            .col-table td { border: 1px solid #000; vertical-align: middle; }
        </style>
        
        ' . str_replace('<tr>', '<tr class="col-table">', $collection_rows_html) . '
        
    </tbody>
</table>

<div style="padding-top: 10px;">
    <table width="100%" style="border: none;">
        <tr>
            <td width="50%" style="border: none;"></td>
            <td width="50%" style="border: none; text-align: right; line-height: 1.8; font-size: 14px;">
                ผู้จดบันทึก ' . str_dots($recorder_name, 80) . '<br>
                ' . $record_datetime . '
            </td>
        </tr>
    </table>
</div>
';

// =========================================================
//  PART 7: HTML หน้าที่ 7 (บันทึกการถ่ายภาพ)
// =========================================================
$html_page7 = '
<table width="100%" style="border-collapse: collapse; border: none;">
    <thead>
        <tr>
            <td style="text-align: center; border: none; padding-bottom: 5px; padding-right: 70px;">
                <span class="bold" style="font-size: 16px;">บันทึกการถ่ายภาพ</span>
            </td>
        </tr>
        <tr>
            <td style="text-align: left; border: none; line-height: 1.8; padding-left: 20px; font-size: 16px;">
                ' . $photo_datetime_txt . '<br>
                ' . $photo_meta_txt . '<br>
                (ตามภาพถ่ายรวมที่แนบ)
            </td>
        </tr>
    </thead>
</table>

<div style="margin-top: 10px; font-size: 16px;">
    ' . $photos_html . '
</div>

<div style="margin-top: 20px; page-break-inside: avoid;">
    <table width="100%" style="border: none;">
        <tr>
            <td width="40%" style="border: none;"></td>
            <td width="60%" style="border: none; text-align: right; line-height: 1.8; font-size: 16px;">
                ผู้จดบันทึก ' . str_dots($recorder_name, 80) . '<br>
                ' . $record_datetime . '
            </td>
        </tr>
    </table>
</div>
';

// =========================================================
//  Final PART : ประกอบร่าง และ Write PDF
// =========================================================

// สร้าง HTML หลักโดยเอา หน้า 1 + Pagebreak + หน้า 2 ไปเรื่อย ๆ จนครบ
$html = '
<html>
<head>
     <style>      
    
        body, table, td, th, span, div {
            font-family: "sarabun"; 
            font-size: 16pt;        
            line-height: 1.2;     
        }

        /* -------------------- ส่วนของ Main Table -------------------- */ 

        .chk-box { font-family: "dejabusans"; font-size: 20px; margin-right: 2px; }
        .bold { font-weight: bold; }
        
        /* 1. ตารางแม่ (โครงสร้างหลัก ซ้าย-ขวา) */
        table.main-layout {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000;
            table-layout: fixed;
        }
        
        /* เซลล์ของตารางแม่ */
        td.layout-col {
            vertical-align: top;
            padding: 0;
            border: 1px solid #000; 
            overflow-wrap: break-word; 
        }

        /* 2. ตารางลูก (เนื้อหาข้างใน) */
        table.inner-table {
            width: 100%;
            border-collapse: collapse;
            border: none;
            table-layout: fixed; 
        }

        /* เซลล์ของตารางลูก */
        table.inner-table td {
            padding: 5px;
            vertical-align: top;
            border-bottom: 1px solid #000;
            border-right: 1px solid #000;
            overflow-wrap: break-word; 
            word-wrap: break-word;    
        }

        .last-row td {
            border-bottom: none !important;
        }


        /* Helper classes */
        .text-center { text-align: center; }
        .box-table { width: 100%; border-collapse: collapse; border: 1px solid #000; }

        .seq-col-1, .seq-col  { text-align: center; font-weight: bold;}
        .seq-col { line-height: 1.4; }
        .content-col { padding: 20px; }

        .no-border { border: none !important; }
        .align-top { vertical-align: top; }
        .align-bottom { vertical-align: bottom; }
        .border-collapse { border-collapse: collapse; }
        
        /* ระยะห่างบรรทัดมาตรฐาน */
        .row-item { padding-bottom: 4px; line-height: 1.6; }
        
        /* การย่อหน้า (Indentation) แทนการใช้ tab() */
        .pl-10 { padding-left: 10px; }
        .pl-20 { padding-left: 20px; }
        .pl-30 { padding-left: 30px; }
        .pl-50 { padding-left: 50px; }
        
        /* สไตล์สำหรับหัวข้อ */
        .section-title { font-weight: bold; padding-top: 10px; }

    </style>
</head>
<body>
    ' . $html_page1 . '
    
    <pagebreak />
    
    ' . $html_page2 . '

    <pagebreak />
    
    ' . $html_page3 . '

    <pagebreak />
    
    ' . $html_page4 . '

    <pagebreak />
    
    ' . $html_page5 . '

    <pagebreak orientation="L"/>
    
    ' . $html_page6 . '

    <pagebreak orientation="P"/>
    
    ' . $html_page7 . '
</body>
</html>
';

$id = "";
// id น่าจะเอา doc / report no มาใช้

// =========================================================
//  PART: การตั้งค่า Header และ Generate PDF
// =========================================================

// ---------------------------------------------------------
// 1. ลงทะเบียน Header ทั้ง 2 แบบเก็บไว้ในระบบก่อน (แนวตั้ง - แนวนอน)
// ---------------------------------------------------------
$mpdf->DefHTMLHeaderByName('PortraitHeader', $header_html);
$mpdf->DefHTMLHeaderByName('LandscapeHeader', $header_landscape_html);

// ---------------------------------------------------------
// 2. สั่งว่า "หน้าแรก" ให้เริ่มใช้ Header ตัวแนวตั้ง ส่วนแนวนอนไปเรียกแยกเอาใน pagebreak
// ---------------------------------------------------------

$mpdf->SetHTMLHeaderByName('PortraitHeader');

// ---------------------------------------------------------
// 3. ตั้งค่า Footer (ความกว้าง Footer ปรับตามแนวตั้ง/แนวนอนให้อัตโนมัติถ้าวาง Table width="100%" ไว้) 
// ---------------------------------------------------------

$mpdf->SetHTMLFooter($footer_html);

// ---------------------------------------------------------
// 4. Write HTML (ใน HTML มีคำสั่งสลับ Header รออยู่แล้ว)
// ---------------------------------------------------------
$mpdf->WriteHTML($html);

// ---------------------------------------------------------
// 5. Output PDF File ออกมา
// ---------------------------------------------------------
$mpdf->Output('IncidentChecklist_Report_' . $id . '.pdf', 'I');
// Parameter ตัวที่ 1 ก็เป็นการกำหนดชื่อไฟล์
// parameter ตัวที่ 2 สลับระหว่าง D (Download) - I (Inline) เพื่อจะให้มาหน้านี้แล้วโหลดเลย หรือแสดงตัวอย่างก่อน แล้วค่อยให้ user กด download เอง
