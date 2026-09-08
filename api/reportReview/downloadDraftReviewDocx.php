<?php
/**
 * ดาวน์โหลดบันทึกการตรวจร่างและรายงาน เป็น .docx
 * layout อิง form_draft_review_html.php + ลายเซ็นผู้ตรวจ 3 คน + ข้อมูลจากร่างรายงาน
 */
session_start();
require __DIR__ . '/../../db_config.php';
require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../helpers/report_no.php';
require __DIR__ . '/_helpers.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\TblWidth;

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

$incidentId = (int)($_GET['incident_id'] ?? $_POST['incident_id'] ?? 0);
if ($incidentId <= 0) {
    http_response_code(400);
    echo 'Missing incident_id';
    exit;
}

const DR_FONT = 'TH Sarabun New';
const DR_SIZE = 14;

/** @var string[] */
$tmpSigFiles = [];

function drFs(array $extra = []): array
{
    return array_merge(['name' => DR_FONT, 'size' => DR_SIZE, 'color' => '000000'], $extra);
}

function drPs(array $extra = []): array
{
    return array_merge([
        'spaceBefore' => 0,
        'spaceAfter'  => 0,
        'lineHeight'  => 1.0,
    ], $extra);
}

/** มีข้อมูล = แสดงค่าอย่างเดียว / ไม่มี = ไข่ปลา */
function drDots(string $v, int $len = 24): string
{
    $v = trim($v);
    return $v !== '' ? $v : str_repeat('.', $len);
}

/** ข้อความไข่ปลาเฉพาะตอนว่าง — ใช้ต่อท้าย label */
function drOrDots(string $v, int $len = 24): string
{
    return drDots($v, $len);
}

function drSlashDate(?string $dt): string
{
    if ($dt === null || trim($dt) === '' || strpos($dt, '0000-00-00') === 0) {
        return '';
    }
    try {
        $d = new DateTime($dt);
    } catch (Throwable $e) {
        return '';
    }
    return $d->format('d') . '/' . $d->format('m') . '/' . ((int)$d->format('Y') + 543);
}

function drTime(?string $dt): string
{
    if ($dt === null || trim($dt) === '' || strpos($dt, '0000-00-00') === 0) {
        return '';
    }
    try {
        $d = new DateTime($dt);
    } catch (Throwable $e) {
        return '';
    }
    return $d->format('H:i');
}

function drNvtLabel($nvt): string
{
    if ($nvt === null || $nvt === '') {
        return '  ';
    }
    return (string)$nvt;
}

function drComplaintName(?string $code): string
{
    $map = [
        '01' => 'คดีเกี่ยวกับทรัพย์',
        '02' => 'คดีเกี่ยวกับชีวิต',
        '03' => 'คดีเกี่ยวกับระเบิด',
        '04' => 'คดีเกี่ยวกับเพลิงไหม้',
        '05' => 'คดีเกี่ยวกับจราจร',
        '06' => 'ตรวจเก็บวัตถุพยาน(ลายนิ้วมือแฝง)',
        '07' => 'ตรวจเก็บวัตถุพยานที่เกิดเหตุ',
        '08' => 'ตรวจเก็บวัตถุพยานบุคคล',
    ];
    return $map[$code ?? ''] ?? (string)($code ?? '');
}

function drCellText($cell, string $text, array $font = [], array $para = []): void
{
    $cell->addText($text, drFs($font), drPs($para));
}

/**
 * เขียน binary ลายเซ็นลง temp แล้วคืน path
 */
function drWriteSigTemp($bin): ?string
{
    global $tmpSigFiles;
    if ($bin === null || $bin === false || $bin === '') {
        return null;
    }
    // ถ้าเป็นชื่อไฟล์สั้นๆ ไม่ใช่ binary
    if (is_string($bin) && strlen($bin) < 260 && preg_match('/^[A-Za-z0-9_\-\.\/\\\\]+\.(png|jpe?g|gif)$/i', $bin)) {
        $candidates = [
            __DIR__ . '/../../uploads/signatures/' . basename($bin),
            __DIR__ . '/../../uploads/checklist_signatures/' . basename($bin),
            $bin,
        ];
        foreach ($candidates as $p) {
            if (is_file($p)) {
                return $p;
            }
        }
        return null;
    }
    if (!is_string($bin) && !is_resource($bin)) {
        return null;
    }
    $raw = is_resource($bin) ? stream_get_contents($bin) : $bin;
    if ($raw === false || $raw === '') {
        return null;
    }
    // data-url
    if (is_string($raw) && strpos($raw, 'base64,') !== false) {
        $raw = base64_decode(substr($raw, strpos($raw, 'base64,') + 7), true) ?: '';
    }
    if ($raw === '') {
        return null;
    }
    $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rr_sig_' . uniqid('', true) . '.png';
    if (file_put_contents($path, $raw) === false) {
        return null;
    }
    $tmpSigFiles[] = $path;
    return $path;
}

/**
 * ตัดขอบขาว/โปร่งของรูปลายเซ็น เพื่อไม่ให้ดูลอยห่างบรรทัด
 */
function drCropSigImage(?string $path): ?string
{
    global $tmpSigFiles;
    if (!$path || !is_file($path) || !function_exists('imagecreatefromstring')) {
        return $path;
    }
    $bin = @file_get_contents($path);
    if ($bin === false || $bin === '') {
        return $path;
    }
    $im = @imagecreatefromstring($bin);
    if (!$im) {
        return $path;
    }
    $w = imagesx($im);
    $h = imagesy($im);
    if ($w < 4 || $h < 4) {
        imagedestroy($im);
        return $path;
    }

    $minX = $w;
    $minY = $h;
    $maxX = 0;
    $maxY = 0;
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $rgba = imagecolorat($im, $x, $y);
            $a = ($rgba & 0x7F000000) >> 24;
            $r = ($rgba >> 16) & 0xFF;
            $g = ($rgba >> 8) & 0xFF;
            $b = $rgba & 0xFF;
            // ข้ามโปร่ง / ขาวเกือบทั้งพิกเซล
            if ($a > 110) {
                continue;
            }
            if ($r > 245 && $g > 245 && $b > 245) {
                continue;
            }
            if ($x < $minX) $minX = $x;
            if ($y < $minY) $minY = $y;
            if ($x > $maxX) $maxX = $x;
            if ($y > $maxY) $maxY = $y;
        }
    }

    if ($maxX <= $minX || $maxY <= $minY) {
        imagedestroy($im);
        return $path;
    }

    $pad = 2;
    $minX = max(0, $minX - $pad);
    $minY = max(0, $minY - $pad);
    $maxX = min($w - 1, $maxX + $pad);
    $maxY = min($h - 1, $maxY + $pad);
    $cw = $maxX - $minX + 1;
    $ch = $maxY - $minY + 1;

    $cropped = imagecreatetruecolor($cw, $ch);
    imagealphablending($cropped, false);
    imagesavealpha($cropped, true);
    $transparent = imagecolorallocatealpha($cropped, 255, 255, 255, 127);
    imagefilledrectangle($cropped, 0, 0, $cw, $ch, $transparent);
    imagealphablending($cropped, true);
    imagecopy($cropped, $im, 0, 0, $minX, $minY, $cw, $ch);

    $out = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rr_sig_crop_' . uniqid('', true) . '.png';
    imagesavealpha($cropped, true);
    imagepng($cropped, $out);
    imagedestroy($cropped);
    imagedestroy($im);

    if (is_file($out)) {
        $tmpSigFiles[] = $out;
        return $out;
    }
    return $path;
}

/**
 * บล็อกลายเซ็น:
 * - มีลายเซ็น → ใส่รูปแทนไข่ปลา แล้วตามด้วยชื่อ (ไม่ใส่ ลงชื่อ.....)
 * - ไม่มีลายเซ็น → ลงชื่อ + ไข่ปลา
 * - ชื่อ/วันที่ มีค่าแล้วไม่ใส่ไข่ปลาซ้ำ
 */
function drSigBlock($cell, ?string $sigPath, string $name, string $nvt, string $date, string $posSuffix = 'กสก.ศพฐ.10'): void
{
    $sigPath = drCropSigImage($sigPath);
    $name = trim($name);
    $date = trim($date);

    $inner = $cell->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMarginTop' => 0,
        'cellMarginBottom' => 0,
        'cellMarginLeft' => 0,
        'cellMarginRight' => 0,
        'alignment' => Jc::CENTER,
    ]);
    $inner->addRow();
    $c = $inner->addCell(4200, [
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'valign' => 'center',
    ]);

    $hasSig = ($sigPath && is_file($sigPath));
    if ($hasSig) {
        try {
            // รูปลายเซ็นแทนบรรทัด "ลงชื่อ ...." — ไม่ใส่ไข่ปลาทับ
            $c->addImage($sigPath, [
                'width' => 90,
                'alignment' => Jc::CENTER,
                'wrappingStyle' => 'inline',
            ]);
        } catch (Throwable $e) {
            $hasSig = false;
        }
    }

    if (!$hasSig) {
        $c->addText(
            'ลงชื่อ ' . str_repeat('.', 22),
            drFs(['size' => 13]),
            drPs(['alignment' => Jc::CENTER, 'spaceBefore' => 20])
        );
    }

    $nameShow = $name !== '' ? $name : str_repeat('.', 24);
    $c->addText('(' . $nameShow . ')', drFs(), drPs(['alignment' => Jc::CENTER, 'spaceBefore' => 0, 'spaceAfter' => 0]));

    $pos = trim('นวท. (สบ ' . drNvtLabel($nvt) . ') ' . $posSuffix);
    $c->addText($pos, drFs(), drPs(['alignment' => Jc::CENTER]));

    $dateShow = $date !== '' ? $date : ('......../......../256' . str_repeat('.', 2));
    $c->addText($dateShow, drFs(), drPs(['alignment' => Jc::CENTER]));
}

/**
 * ดึงข้อมูลจากร่างรายงาน (incident_report_data / checklist)
 *
 * @return array{inspect_dt:string,know_dt:string,recv_dt:string,location:string,inspectors:string}
 */
function drLoadDraftReport(PDO $pdo, int $incidentId): array
{
    $out = [
        'inspect_dt' => '',
        'know_dt' => '',
        'recv_dt' => '',
        'location' => '',
        'inspectors' => '',
    ];

    try {
        $st = $pdo->prepare("SELECT incident_checklist_data, incident_report_data
            FROM incident_checklist_transaction
            WHERE incident_id = ?
            ORDER BY create_date DESC LIMIT 1");
        $st->execute([$incidentId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return $out;
        }

        $data = [];
        if (!empty($row['incident_report_data'])) {
            $data = json_decode($row['incident_report_data'], true) ?: [];
        }
        if (!$data && !empty($row['incident_checklist_data'])) {
            $data = json_decode($row['incident_checklist_data'], true) ?: [];
        }
        if (!$data) {
            return $out;
        }

        // บางประเภทเก็บซ้อนใน key
        foreach (['property', 'life', 'bomb', 'fire', 'traffic', 'fingerprint', 'scene', 'person', 'general'] as $k) {
            if (isset($data[$k]) && is_array($data[$k]) && (isset($data[$k]['general_info']) || isset($data[$k]['inspectors']))) {
                $data = array_merge($data, $data[$k]);
                break;
            }
        }

        $gen = [];
        if (isset($data['general_info']) && is_array($data['general_info'])) {
            $gen = $data['general_info'];
        } elseif (isset($data['general']) && is_array($data['general'])) {
            $gen = $data['general'];
        }

        foreach (['inspection_datetime', 'inspect_datetime', 'scene_inspect_datetime'] as $dk) {
            if (!empty($gen[$dk])) {
                $out['inspect_dt'] = (string)$gen[$dk];
                break;
            }
        }
        if ($out['inspect_dt'] === '' && !empty($gen['inspect_date'])) {
            $out['inspect_dt'] = trim((string)$gen['inspect_date'] . ' ' . (string)($gen['inspect_time'] ?? ''));
        }

        foreach (['investigator_known_datetime', 'incident_datetime', 'victim_known_datetime'] as $dk) {
            if (!empty($gen[$dk])) {
                $out['know_dt'] = (string)$gen[$dk];
                break;
            }
        }

        if (!empty($gen['report_datetime'])) {
            $out['recv_dt'] = (string)$gen['report_datetime'];
        }

        foreach (['location_detail', 'scene_location', 'crime_location', 'location'] as $lk) {
            if (!empty($gen[$lk])) {
                $out['location'] = trim((string)$gen[$lk]);
                break;
            }
        }

        $inspectorIds = $data['inspectors'] ?? ($gen['inspectors'] ?? []);
        if (is_array($inspectorIds) && $inspectorIds) {
            $names = [];
            $uq = $pdo->prepare("SELECT TRIM(CONCAT(COALESCE(r.short_rank, r.rank_name, ''), ' ', COALESCE(p.first_name, ''), ' ', COALESCE(p.last_name, ''))) AS fullname
                FROM user_profile p
                LEFT JOIN user_rank r ON p.rank_id = r.rank_id
                WHERE p.user_id = ? LIMIT 1");
            foreach ($inspectorIds as $uid) {
                if ($uid === '' || $uid === null) {
                    continue;
                }
                $uq->execute([(int)$uid]);
                $nm = trim((string)$uq->fetchColumn());
                if ($nm !== '') {
                    $names[] = $nm;
                }
            }
            $out['inspectors'] = implode(', ', $names);
        }
    } catch (Throwable $e) {
        // optional
    }

    return $out;
}

/**
 * ลายเซ็นผู้ร่าง: ใช้ seq_no=0 ใน report_incident_approve ก่อน แล้วค่อย fallback ใบรับแจ้ง
 *
 * @return array{path:?string,date:string,name:string}
 */
function drLoadDrafterSig(PDO $pdo, int $incidentId): array
{
    $out = ['path' => null, 'date' => '', 'name' => ''];
    try {
        $st = $pdo->prepare("SELECT a.signature, a.dateApprove,
                TRIM(CONCAT(COALESCE(r.short_rank, r.rank_name, ''), ' ', COALESCE(p.first_name, ''), ' ', COALESCE(p.last_name, ''))) AS fullname
            FROM report_incident_approve a
            LEFT JOIN user_profile p ON p.user_id = a.user_id
            LEFT JOIN user_rank r ON p.rank_id = r.rank_id
            WHERE a.id_incident = ? AND a.seq_no = 0
            LIMIT 1");
        $st->execute([$incidentId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $out['path'] = drWriteSigTemp($row['signature'] ?? null);
            $out['date'] = (string)($row['dateApprove'] ?? '');
            $out['name'] = trim(preg_replace('/\s+/', ' ', (string)($row['fullname'] ?? '')));
            if ($out['path']) {
                return $out;
            }
        }
    } catch (Throwable $e) {
        // ignore
    }

    try {
        $st = $pdo->prepare("SELECT signature FROM rn_ReceiveNotiApprove
            WHERE ComplaintsID = ? AND seqSignature = 1
            ORDER BY id DESC LIMIT 1");
        $st->execute([$incidentId]);
        $out['path'] = drWriteSigTemp($st->fetchColumn());
    } catch (Throwable $e) {
        // ignore
    }
    return $out;
}

// ---------- load incident ----------
$stmt = $pdo->prepare("SELECT t1.id, t1.create_by, t1.receiveNoti_No, t1.receiveNoti_No_TH,
        t1.receiveNotiReportNo, t1.receiveNotiReportNo_TH,
        t1.complaints_From, t1.province, t1.complaints_type, t1.location_crime,
        t1.time_Occurrence, t1.create_date, t1.basic_Info,
        COALESCE(t1.seqStatusApprove, 1) AS seqStatusApprove,
        TRIM(CONCAT(COALESCE(r.short_rank, r.rank_name, ''), ' ', COALESCE(p.first_name, ''), ' ', COALESCE(p.last_name, ''))) AS drafter_name,
        u.nvt_sub AS drafter_nvt
    FROM rn_ReceiveNoti t1
    LEFT JOIN users u ON u.user_id = t1.create_by
    LEFT JOIN user_profile p ON p.user_id = t1.create_by
    LEFT JOIN user_rank r ON p.rank_id = r.rank_id
    WHERE t1.id = ? AND t1.statusDelete = 0
    LIMIT 1");
$stmt->execute([$incidentId]);
$inc = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$inc) {
    http_response_code(404);
    echo 'ไม่พบข้อมูลคดี';
    exit;
}

$docNo = $inc['receiveNoti_No_TH'] ?: convertDocNoToThai($inc['receiveNoti_No'] ?? '');
$reportNo = $inc['receiveNotiReportNo_TH'] ?: convertReportNoToThai($inc['receiveNotiReportNo'] ?? '');
if ($docNo === '') {
    $docNo = $inc['receiveNoti_No'] ?? '';
}

$yearBuddhist = (string)((int)date('Y') + 543);
if (preg_match('/\/(25\d{2})/', $docNo, $ym)) {
    $yearBuddhist = $ym[1];
}

$draft = drLoadDraftReport($pdo, $incidentId);

$locationFull = $draft['location'] !== '' ? $draft['location'] : trim((string)($inc['location_crime'] ?? ''));
$location = $locationFull;
$location2 = '';
if (mb_strlen($location) > 55) {
    $location2 = mb_substr($location, 55);
    $location = mb_substr($location, 0, 55);
}

$knowDt = $draft['know_dt'] !== '' ? $draft['know_dt'] : (string)($inc['time_Occurrence'] ?? '');
$recvDt = $draft['recv_dt'] !== '' ? $draft['recv_dt'] : (string)($inc['create_date'] ?? '');
$inspectDt = $draft['inspect_dt'];
$inspectorNames = $draft['inspectors'];

$approvers = rrGetApprovers($pdo, $incidentId);

$loadApproveSig = static function (PDO $pdo, ?array $row): ?string {
    if (!$row || (int)($row['has_signature'] ?? 0) !== 1) {
        return null;
    }
    $st = $pdo->prepare("SELECT signature FROM report_incident_approve WHERE approve_id = ? LIMIT 1");
    $st->execute([(int)$row['approve_id']]);
    return drWriteSigTemp($st->fetchColumn());
};

$loadNvt = static function (PDO $pdo, ?array $row): string {
    if (!$row || empty($row['user_id'])) {
        return '  ';
    }
    $st = $pdo->prepare("SELECT nvt_sub FROM users WHERE user_id = ? LIMIT 1");
    $st->execute([(int)$row['user_id']]);
    return drNvtLabel($st->fetchColumn());
};

$r1 = $approvers[1] ?? null;
$r2 = $approvers[2] ?? null;
$r3 = $approvers[3] ?? null;

$r1Sig = $loadApproveSig($pdo, $r1);
$r2Sig = $loadApproveSig($pdo, $r2);
$r3Sig = $loadApproveSig($pdo, $r3);
$drafterInfo = drLoadDrafterSig($pdo, $incidentId);
$drafterSig = $drafterInfo['path'];

$r1Name = trim((string)($r1['fullname'] ?? ''));
$r2Name = trim((string)($r2['fullname'] ?? ''));
$r3Name = trim((string)($r3['fullname'] ?? ''));
$drafterName = trim(preg_replace('/\s+/', ' ', (string)($inc['drafter_name'] ?? '')));
if ($drafterInfo['name'] !== '') {
    $drafterName = $drafterInfo['name'];
}
$drafterDateRaw = $drafterInfo['date'] !== ''
    ? $drafterInfo['date']
    : ($recvDt !== '' ? $recvDt : (string)($inc['create_date'] ?? ''));

// ผู้ตรวจฯ = ชื่อผู้ตรวจ/ผู้อนุมัติ 3 คน (fallback รายชื่อจากร่างรายงาน)
$threeNames = array_values(array_filter([$r1Name, $r2Name, $r3Name], static function ($n) {
    return trim((string)$n) !== '';
}));
if ($threeNames) {
    $inspectorNames = implode(', ', $threeNames);
}

$F = [
    'doc_no_right' => $docNo !== '' ? $docNo : ('................../' . substr($yearBuddhist, 0, 3) . '....'),
    'case_name' => drComplaintName($inc['complaints_type'] ?? ''),
    'station' => trim((string)($inc['complaints_From'] ?? '')),
    'location' => $location,
    'location2' => $location2,
    'know_date' => drSlashDate($knowDt),
    'know_time' => drTime($knowDt),
    'recv_date' => drSlashDate($recvDt),
    'recv_time' => drTime($recvDt),
    'inspect_date' => drSlashDate($inspectDt),
    'inspect_time' => drTime($inspectDt),
    'inspector' => $inspectorNames,
    'drafter' => $drafterName,
    'drafter_nvt' => drNvtLabel($inc['drafter_nvt'] ?? ''),
    'drafter_position_extra' => '',
    'drafter_date' => drSlashDate($drafterDateRaw),
    'drafter_sig_src' => '',
    'drafter_sign_line' => '',
    'r1_name' => $r1Name,
    'r1_nvt' => $loadNvt($pdo, $r1),
    'r1_remark' => trim((string)($r1['remark'] ?? '')),
    'r1_date' => drSlashDate($r1['dateApprove'] ?? null),
    'r1_sig_src' => '',
    'r1_sign_line' => '',
    'r1_edit' => false,
    'r1_print' => false,
    'r2_name' => $r2Name,
    'r2_nvt' => $loadNvt($pdo, $r2),
    'r2_remark' => trim((string)($r2['remark'] ?? '')),
    'r2_date' => drSlashDate($r2['dateApprove'] ?? null),
    'r2_sig_src' => '',
    'r2_sign_line' => '',
    'r2_edit' => false,
    'r2_print' => false,
    'r3_name' => $r3Name,
    'r3_nvt' => $loadNvt($pdo, $r3),
    'r3_remark' => trim((string)($r3['remark'] ?? '')),
    'r3_date' => drSlashDate($r3['dateApprove'] ?? null),
    'r3_sig_src' => '',
    'r3_sign_line' => '',
];

if (isset($_GET['preview']) && $_GET['preview'] === '1') {
    header('Content-Type: text/html; charset=UTF-8');
    include __DIR__ . '/form_draft_review_html.php';
    exit;
}

// ---------- build DOCX ----------
$phpWord = new PhpWord();
$phpWord->setDefaultFontName(DR_FONT);
$phpWord->setDefaultFontSize(DR_SIZE);
$phpWord->setDefaultParagraphStyle(drPs());

$settings = $phpWord->getSettings();
$settings->setHideSpellingErrors(true);
$settings->setHideGrammaticalErrors(true);
if (class_exists('\\PhpOffice\\PhpWord\\Style\\Language') && method_exists($settings, 'setThemeFontLang')) {
    try {
        $settings->setThemeFontLang(new \PhpOffice\PhpWord\Style\Language('th-TH', 'th-TH'));
    } catch (Throwable $e) {
        // ignore
    }
}

$section = $phpWord->addSection([
    'marginTop'    => 720,
    'marginBottom' => 540,
    'marginLeft'   => 850,
    'marginRight'  => 850,
]);

$cellBorder = [
    'borderSize' => 8,
    'borderColor' => '000000',
    'valign' => 'top',
];

$section->addText('ที่ ' . drDots($F['doc_no_right'], 16), drFs(), drPs(['alignment' => Jc::END]));
$section->addText('บันทึกการตรวจร่างและรายงาน', drFs(['bold' => true, 'underline' => 'single']), drPs(['alignment' => Jc::CENTER]));
$section->addText('กลุ่มงานตรวจสถานที่เกิดเหตุ ศพฐ.10', drFs(['bold' => true, 'underline' => 'single']), drPs(['alignment' => Jc::CENTER, 'spaceAfter' => 60]));

$tr = $section->addTextRun(drPs());
$tr->addText('คดี ', drFs());
$tr->addText(drDots($F['case_name'], 28), drFs());
$tr->addText('    สภ. ', drFs());
$tr->addText(drDots($F['station'], 28), drFs());

$tr = $section->addTextRun(drPs());
$tr->addText('สถานที่เกิดเหตุ ', drFs());
$tr->addText(drDots($F['location'], 50), drFs());
// บรรทัดที่ 2 เฉพาะตอนข้อความยาวเกิน — ไม่ใส่ไข่ปลาว่างๆ
if (trim((string)$F['location2']) !== '') {
    $section->addText($F['location2'], drFs(), drPs());
}

$tr = $section->addTextRun(drPs());
$tr->addText('วันเวลาที่ทราบเหตุ   วันที่ ', drFs());
$tr->addText(drDots($F['know_date'], 12), drFs());
$tr->addText('  เวลาประมาณ ', drFs());
$tr->addText(drDots($F['know_time'], 8), drFs());
$tr->addText(' น.', drFs());

$tr = $section->addTextRun(drPs());
$tr->addText('วันเวลาที่รับ           วันที่ ', drFs());
$tr->addText(drDots($F['recv_date'], 12), drFs());
$tr->addText('  เวลา ', drFs());
$tr->addText(drDots($F['recv_time'], 8), drFs());
$tr->addText(' น.', drFs());

$tr = $section->addTextRun(drPs());
$tr->addText('วันเวลาที่ตรวจฯ     วันที่ ', drFs());
$tr->addText(drDots($F['inspect_date'], 12), drFs());
$tr->addText('  เวลาประมาณ ', drFs());
$tr->addText(drDots($F['inspect_time'], 8), drFs());
$tr->addText(' น.', drFs());

$tr = $section->addTextRun(drPs());
$tr->addText('ผู้ตรวจฯ ', drFs());
$tr->addText(drDots($F['inspector'], 40), drFs());

$tr = $section->addTextRun(drPs(['spaceAfter' => 60]));
$tr->addText('ผู้ร่างรายงาน ', drFs());
$tr->addText(drDots($F['drafter'], 40), drFs());

// 2x2 — ไม่ล็อกความสูงแถว เพื่อไม่ให้ลายเซ็นลอย
$grid = $section->addTable([
    'borderSize' => 8,
    'borderColor' => '000000',
    'width' => 100 * 50,
    'unit' => TblWidth::PERCENT,
]);

$half = 4500;

$grid->addRow();
$cDrafter = $grid->addCell($half, $cellBorder);
drCellText($cDrafter, 'ผู้ร่างรายงาน', ['bold' => true]);
drCellText($cDrafter, 'เรียน นวท.(สบ ' . drNvtLabel($F['drafter_nvt']) . ') กสก.ศพฐ.10');
drCellText($cDrafter, 'เพื่อโปรดพิจารณา ร่างรายงาน');
drSigBlock($cDrafter, $drafterSig, $F['drafter'], $F['drafter_nvt'], $F['drafter_date'], '');

$cR1 = $grid->addCell($half, $cellBorder);
drCellText($cR1, 'ผู้ตรวจร่างรายงาน', ['bold' => true]);
drCellText($cR1, 'รายการแก้ไขดำเนินการ/ดำเนินการ(ครั้งที่ 1)');
drCellText($cR1, ($F['r1_edit'] ? '☑' : '☐') . ' แก้ไข');
drCellText($cR1, ($F['r1_print'] ? '☑' : '☐') . ' พิมพ์รายงาน');
if ($F['r1_remark'] !== '') {
    drCellText($cR1, $F['r1_remark']);
} else {
    drCellText($cR1, str_repeat('.', 36));
    drCellText($cR1, str_repeat('.', 36));
}
drSigBlock($cR1, $r1Sig, $F['r1_name'], $F['r1_nvt'], $F['r1_date'], 'กสก.ศพฐ.10');

$grid->addRow();
$cR2 = $grid->addCell($half, $cellBorder);
drCellText($cR2, 'ผู้ตรวจร่างรายงาน', ['bold' => true]);
drCellText($cR2, 'รายการแก้ไขดำเนินการ/ดำเนินการ(ครั้งที่ 1)');
drCellText($cR2, ($F['r2_edit'] ? '☑' : '☐') . ' แก้ไข');
drCellText($cR2, ($F['r2_print'] ? '☑' : '☐') . ' พิมพ์รายงาน');
if ($F['r2_remark'] !== '') {
    drCellText($cR2, $F['r2_remark']);
} else {
    drCellText($cR2, str_repeat('.', 36));
    drCellText($cR2, str_repeat('.', 36));
}
drSigBlock($cR2, $r2Sig, $F['r2_name'], $F['r2_nvt'], $F['r2_date'], 'กสก.ศพฐ.10');

$cR3 = $grid->addCell($half, $cellBorder);
drCellText($cR3, 'ผู้อนุมัติร่างรายงาน', ['bold' => true]);
if ($F['r3_remark'] !== '') {
    drCellText($cR3, $F['r3_remark']);
} else {
    drCellText($cR3, str_repeat('.', 36));
    drCellText($cR3, str_repeat('.', 36));
}
$r3Nvt = (trim((string)$F['r3_nvt']) !== '') ? $F['r3_nvt'] : '4';
drSigBlock($cR3, $r3Sig, $F['r3_name'], $r3Nvt, $F['r3_date'], 'กสก.ศพฐ.10');

$section->addTextBreak(0);
$ft = $section->addTable([
    'borderSize' => 8,
    'borderColor' => '000000',
    'width' => 100 * 50,
    'unit' => TblWidth::PERCENT,
]);
$ft->addRow();
$headers = [
    'บันทึกการรับแจ้งเหตุ',
    'แบบฟอร์ม (Check List)',
    'บันทึกส่งมอบวัตถุพยาน',
    'ผ่านการตรวจร่าง',
    'ผู้ตรวจสอบเอกสาร',
];
$colW = 1800;
foreach ($headers as $h) {
    $cell = $ft->addCell($colW, $cellBorder);
    $cell->addText($h, drFs(['size' => 11, 'bold' => true]), drPs(['alignment' => Jc::CENTER]));
}
$ft->addRow(900);
for ($i = 0; $i < 5; $i++) {
    $ft->addCell($colW, $cellBorder)->addText('', drFs(), drPs());
}

$baseName = 'บันทึกการตรวจร่างรายงาน_' . ($reportNo !== '' ? $reportNo : $incidentId);
$baseName = preg_replace('/[\\\\\\/:*?"<>|]+/u', '_', $baseName);
$filenameUtf8 = $baseName . '.docx';
$filenameAscii = 'draft_review_' . $incidentId . '.docx';

header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $filenameAscii . '"; filename*=UTF-8\'\'' . rawurlencode($filenameUtf8));
header('Cache-Control: max-age=0');

$tmpFile = tempnam(sys_get_temp_dir(), 'rrdocx');
$docxPath = $tmpFile . '.docx';
@unlink($tmpFile);

try {
    $writer = IOFactory::createWriter($phpWord, 'Word2007');
    $writer->save($docxPath);
    readfile($docxPath);
} finally {
    @unlink($docxPath);
    foreach ($tmpSigFiles as $f) {
        // ลบเฉพาะไฟล์ temp ที่เราสร้าง (ไม่ลบไฟล์ใน uploads)
        if (strpos($f, sys_get_temp_dir()) === 0) {
            @unlink($f);
        }
    }
}
exit;
