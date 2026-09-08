<?php
/**
 * gen_pdf_router.php - Router for PDF Generation
 *
 * ตรวจสอบประเภทคดี (ทรัพย์/ชีวิต/ระเบิด) แล้ว redirect ไปยัง PDF Generator ที่ถูกต้อง
 */

require_once __DIR__ . '/../../db_config.php';

$incident_id = isset($_GET['incident_id']) ? intval($_GET['incident_id']) : 0;

if ($incident_id <= 0) {
    die("Error: กรุณาระบุ incident_id");
}

try {
    // 1. ดึง complaints_type จาก rn_ReceiveNoti (แหล่งข้อมูลหลัก)
    $complaintsType = '';
    $stmtRn = $pdo->prepare("SELECT complaints_type FROM rn_ReceiveNoti WHERE id = ? LIMIT 1");
    $stmtRn->execute([$incident_id]);
    $rnRow = $stmtRn->fetch(PDO::FETCH_ASSOC);
    if ($rnRow && !empty($rnRow['complaints_type'])) {
        $complaintsType = $rnRow['complaints_type'];
    }

    // 2. Mapping complaints_type → PDF Generator
    $routeMap = [
        '01' => 'gen_pdf_property_html.php',
        '02' => 'gen_pdf_life_html.php',
        '03' => 'gen_pdf_bomb_html.php',
        '04' => 'gen_pdf_fire_html.php',
        '05' => 'gen_pdf_traffic_html.php',
        '06' => 'gen_pdf_fingerprint_checklist_html.php',
        '07' => 'gen_pdf_scene_evidence_html.php',
        '08' => 'gen_pdf_person_evidence_html.php',
    ];

    if (!empty($complaintsType) && isset($routeMap[$complaintsType])) {
        header("Location: /csims/api/incidentCheckList/" . $routeMap[$complaintsType] . "?incident_id=" . $incident_id);
        exit;
    }

    // 3. Fallback: ดึง case_type จาก JSON (กรณี complaints_type ไม่มี)
    $stmt = $pdo->prepare("SELECT incident_checklist_data FROM incident_checklist_transaction WHERE incident_id = ? ORDER BY create_date DESC LIMIT 1");
    $stmt->execute([$incident_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        die("Error: ไม่พบข้อมูล Checklist สำหรับ incident_id: " . $incident_id);
    }

    $data = json_decode($result['incident_checklist_data'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        die("Error: ไม่สามารถอ่านข้อมูล JSON ได้");
    }

    $caseType = $data['general_info']['case_type'] ?? '';

    $caseTypeMap = [
        'property'        => 'gen_pdf_property_html.php',
        'life'            => 'gen_pdf_life_html.php',
        'bomb'            => 'gen_pdf_bomb_html.php',
        'fire'            => 'gen_pdf_fire_html.php',
        'traffic'         => 'gen_pdf_traffic_html.php',
        'fingerprint'     => 'gen_pdf_fingerprint_checklist_html.php',
        'scene_evidence'  => 'gen_pdf_scene_evidence_html.php',
        'person_evidence' => 'gen_pdf_person_evidence_html.php',
    ];

    if (!empty($caseType) && isset($caseTypeMap[$caseType])) {
        header("Location: /csims/api/incidentCheckList/" . $caseTypeMap[$caseType] . "?incident_id=" . $incident_id);
        exit;
    }

    // Fallback เพิ่มเติม: ตรวจสอบจากคีย์เฉพาะใน JSON
    if (isset($data['inspection_results']['bomb_evidence'])) {
        header("Location: /csims/api/incidentCheckList/gen_pdf_bomb_html.php?incident_id=" . $incident_id);
        exit;
    }
    if (isset($data['body_info']) || isset($data['victim_info'])) {
        header("Location: /csims/api/incidentCheckList/gen_pdf_life_html.php?incident_id=" . $incident_id);
        exit;
    }

    // Default: ทรัพย์
    header("Location: /csims/api/incidentCheckList/gen_pdf_property_html.php?incident_id=" . $incident_id);
    exit;

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}