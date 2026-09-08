<?php

/**
 * แปลงเลขที่รายงาน (receiveNotiReportNo) จากรูปแบบภาษาอังกฤษ เป็นภาษาไทย
 * ตามตารางมาตรฐาน:
 *   LC -> ท   (คดีเกี่ยวกับทรัพย์)
 *   MD -> ช   (คดีเกี่ยวกับชีวิต)
 *   BM -> ร   (คดีเกี่ยวกับระเบิด)
 *   FR -> พ   (คดีเพลิงไหม้)
 *   TF -> จร  (คดีจราจร)
 *   EV -> ต   (ตรวจเก็บวัตถุพยาน)
 *   CM -> ต   (ตรวจเก็บวัตถุพยานที่เกิดเหตุ)
 *   PS -> ต   (ตรวจเก็บวัตถุพยานบุคคล)
 *   OT -> อ   (อื่น ๆ)
 *
 * ฟังก์ชันนี้ idempotent: ถ้าส่งค่าที่เป็นภาษาไทยอยู่แล้ว หรือไม่รู้จัก prefix
 * จะคืนค่าเดิมกลับไปโดยไม่แก้ไข (เลขรันนิ่ง/ปี ยังเป็นเลขอารบิกเหมือนเดิม)
 *
 * ตัวอย่าง: "LC-0001/2569" => "ท-0001/2569"
 */

if (!function_exists('convertReportNoToThai')) {
    function convertReportNoToThai($reportNo)
    {
        if ($reportNo === null) {
            return '';
        }
        $reportNo = trim((string)$reportNo);
        if ($reportNo === '') {
            return '';
        }

        $map = [
            'LC-' => 'ท-',
            'MD-' => 'ช-',
            'BM-' => 'ร-',
            'FR-' => 'พ-',
            'TF-' => 'จร-',
            'EV-' => 'ต-',
            'CM-' => 'ต-',
            'PS-' => 'ต-',
            'OT-' => 'อ-',
        ];

        foreach ($map as $en => $th) {
            if (strncmp($reportNo, $en, strlen($en)) === 0) {
                return $th . substr($reportNo, strlen($en));
            }
        }

        // ไม่รู้จัก prefix (หรือเป็นภาษาไทยอยู่แล้ว) => คืนค่าเดิม
        return $reportNo;
    }
}

if (!function_exists('convertDocNoToThai')) {
    /**
     * แปลงเลขที่เอกสาร (receiveNoti_No) จากภาษาอังกฤษเป็นภาษาไทย
     * รูปแบบเลขที่เอกสาร: 10-{จังหวัด}-{ปี}-{TypeCode}{running}
     *   เช่น 10-95-69-PS0003, 10-95-69-BM0009
     * แปลงเฉพาะตัวอักษรประเภท (TypeCode) ที่อยู่ก่อนเลขรันนิ่ง โดยเลข/ปี ยังเป็นอารบิกเหมือนเดิม
     *   LC->ท, MD->ช, BM->ร, FR->พ, TF->จร, EV->ต, CM->ต, PS->ต, OT->อ
     * ตัวอย่าง: "10-95-69-PS0003" => "10-95-69-ต0003"
     *
     * idempotent: ถ้าเป็นภาษาไทยอยู่แล้ว/ไม่พบ code จะคืนค่าเดิม
     */
    function convertDocNoToThai($docNo)
    {
        if ($docNo === null) {
            return '';
        }
        $docNo = trim((string)$docNo);
        if ($docNo === '') {
            return '';
        }

        $map = [
            'LC' => 'ท',
            'MD' => 'ช',
            'BM' => 'ร',
            'FR' => 'พ',
            'TF' => 'จร',
            'EV' => 'ต',
            'CM' => 'ต',
            'PS' => 'ต',
            'OT' => 'อ',
        ];

        // จับ code ที่ขึ้นต้นด้วยต้นสตริงหรือหลังเครื่องหมาย "-" แล้วตามด้วยตัวเลขรันนิ่ง
        $pattern = '/(^|-)(LC|MD|BM|FR|TF|EV|CM|PS|OT)(\d+)/';
        $result = preg_replace_callback($pattern, function ($m) use ($map) {
            return $m[1] . $map[$m[2]] . $m[3];
        }, $docNo);

        return $result === null ? $docNo : $result;
    }
}

if (!function_exists('smartThaiReportOrDoc')) {
    /**
     * เลือก converter ตามรูปแบบ: เลขเอกสารเต็ม (10-xx-xx-XX####) หรือเลขรายงาน (XX-####/ปี)
     */
    function smartThaiReportOrDoc($s)
    {
        if ($s === null) {
            return '';
        }
        $s = trim((string)$s);
        if ($s === '') {
            return '';
        }
        if (preg_match('/^\d{1,3}-\d{1,3}-\d{2}-/', $s)) {
            return convertDocNoToThai($s);
        }
        return convertReportNoToThai($s);
    }
}

if (!function_exists('getIncidentDocNoTH')) {
    /**
     * ดึงเลขที่เอกสารภาษาไทยของเหตุการณ์จากฐานข้อมูล (แหล่งข้อมูลหลัก)
     * ลำดับความสำคัญ:
     *   1) rn_ReceiveNoti.receiveNoti_No_TH (ค่าที่บันทึกไว้เป็นภาษาไทย)
     *   2) แปลงจาก rn_ReceiveNoti.receiveNoti_No (ภาษาอังกฤษ)
     *   3) แปลงจากค่า fallback ที่ส่งเข้ามา
     */
    function getIncidentDocNoTH($pdo, $incidentId, $fallbackRaw = '')
    {
        $th = '';
        $incidentId = (int)$incidentId;
        if ($incidentId > 0) {
            try {
                $stmt = $pdo->prepare("SELECT receiveNoti_No, receiveNoti_No_TH FROM rn_ReceiveNoti WHERE id = ? LIMIT 1");
                $stmt->execute([$incidentId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    if (!empty($row['receiveNoti_No_TH'])) {
                        $th = $row['receiveNoti_No_TH'];
                    } elseif (!empty($row['receiveNoti_No'])) {
                        $th = convertDocNoToThai($row['receiveNoti_No']);
                    }
                }
            } catch (Exception $e) {
                // ignore แล้วใช้ fallback
            }
        }
        if ($th === '' && $fallbackRaw !== '') {
            $th = convertDocNoToThai($fallbackRaw);
        }
        return $th;
    }
}

if (!function_exists('getIncidentReportNoTH')) {
    /**
     * ดึงเลขที่รายงานภาษาไทยของเหตุการณ์จากฐานข้อมูล (แหล่งข้อมูลหลัก)
     * ลำดับความสำคัญ:
     *   1) rn_ReceiveNoti.receiveNotiReportNo_TH (ค่าที่บันทึกไว้เป็นภาษาไทย)
     *   2) แปลงจาก rn_ReceiveNoti.receiveNotiReportNo (ภาษาอังกฤษ)
     *   3) แปลงจากค่า fallback ที่ส่งเข้ามา (เช่น report_no ใน checklist)
     * คืนค่าเป็นสตริงเต็ม เช่น "ท-0018/2569"
     */
    function getIncidentReportNoTH($pdo, $incidentId, $fallbackRaw = '')
    {
        $th = '';
        $incidentId = (int)$incidentId;
        if ($incidentId > 0) {
            try {
                $stmt = $pdo->prepare("SELECT receiveNotiReportNo, receiveNotiReportNo_TH FROM rn_ReceiveNoti WHERE id = ? LIMIT 1");
                $stmt->execute([$incidentId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    if (!empty($row['receiveNotiReportNo_TH'])) {
                        $th = $row['receiveNotiReportNo_TH'];
                    } elseif (!empty($row['receiveNotiReportNo'])) {
                        $th = convertReportNoToThai($row['receiveNotiReportNo']);
                    }
                }
            } catch (Exception $e) {
                // ignore แล้วใช้ fallback
            }
        }
        if ($th === '' && $fallbackRaw !== '') {
            $th = convertReportNoToThai($fallbackRaw);
        }
        return $th;
    }
}
