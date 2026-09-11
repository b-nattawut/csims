<?php
/**
 * lab_unit_helper.php
 * ตัวช่วยกลางสำหรับค่า "การตรวจพิสูจน์" (กลุ่มงานส่งตรวจ) ที่รองรับหลายค่า
 *
 * ข้อมูลเก่าเก็บเป็น string เดี่ยว เช่น "bio_dna"
 * ข้อมูลใหม่เก็บเป็น array เช่น ["bio_dna","fingerprint"]
 * ฝั่งฟอร์มส่งมาเป็น string คั่นด้วย comma เช่น "bio_dna,fingerprint"
 *
 * ทุกจุดที่อ่านค่าให้เรียก labUnitsNormalize() เพื่อได้ array เสมอ
 */

if (!function_exists('labUnitsNormalize')) {
    /**
     * แปลงค่าอะไรก็ได้ (null / string / comma string / JSON string / array) -> array ของ key
     * @return string[]
     */
    function labUnitsNormalize($value)
    {
        $out = [];
        if ($value === null || $value === false) return $out;

        if (is_array($value)) {
            foreach ($value as $item) {
                foreach (labUnitsNormalize($item) as $key) {
                    if (!in_array($key, $out, true)) $out[] = $key;
                }
            }
            return $out;
        }

        if (is_object($value)) {
            return labUnitsNormalize((array)$value);
        }

        $str = trim((string)$value);
        if ($str === '') return $out;

        // อาจถูกเก็บเป็น JSON array string
        if (substr($str, 0, 1) === '[') {
            $decoded = json_decode($str, true);
            if (is_array($decoded)) return labUnitsNormalize($decoded);
        }

        foreach (explode(',', $str) as $part) {
            $part = trim($part);
            if ($part !== '' && !in_array($part, $out, true)) $out[] = $part;
        }
        return $out;
    }
}

if (!function_exists('labUnitsJoin')) {
    /** array/string -> "a,b" (ใช้เก็บลงคอลัมน์ที่ยังเป็น string หรือส่งกลับฟอร์ม) */
    function labUnitsJoin($value, $separator = ',')
    {
        return implode($separator, labUnitsNormalize($value));
    }
}

if (!function_exists('labUnitsFirst')) {
    /** ค่าแรกอย่างเดียว (ใช้กับ logic เก่าที่ยังต้องการค่าเดี่ยว) */
    function labUnitsFirst($value)
    {
        $arr = labUnitsNormalize($value);
        return count($arr) ? $arr[0] : '';
    }
}

if (!function_exists('labUnitLabelMap')) {
    /**
     * map key -> ชื่อกลุ่มงาน (ใช้ตอน render ข้อความในรายงาน/สติกเกอร์)
     */
    function labUnitLabelMap()
    {
        return [
            'bio_dna'     => 'กลุ่มงานตรวจชีววิทยา',
            'chemical'    => 'กลุ่มงานตรวจทางเคมีฟิสิกส์',
            'fingerprint' => 'กลุ่มงานตรวจลายนิ้วมือแฝง',
            'drug'        => 'กลุ่มงานตรวจยาเสพติด',
            'gun'         => 'กลุ่มงานตรวจอาวุธปืนและเครื่องกระสุน',
            'document'    => 'กลุ่มงานตรวจเอกสาร',
            'digital'     => 'กลุ่มงานตรวจพิสูจน์หลักฐานดิจิทัล',
            'computer'    => 'กลุ่มงานตรวจพิสูจน์อาชญากรรมคอมพิวเตอร์',
            'explosive'   => 'กลุ่มงานตรวจวัตถุระเบิด กก.ตว.',
        ];
    }
}

if (!function_exists('labUnitsToText')) {
    /**
     * แปลงเป็นข้อความสำหรับพิมพ์ เช่น "กลุ่มงานตรวจชีววิทยา, กลุ่มงานตรวจลายนิ้วมือแฝง"
     * ค่าที่ไม่รู้จักจะถูกแสดงตามเดิม (เผื่อระบบเก่าเก็บเป็นชื่อไทยไว้แล้ว)
     */
    function labUnitsToText($value, $separator = ', ')
    {
        $map = labUnitLabelMap();
        $labels = [];
        foreach (labUnitsNormalize($value) as $key) {
            $labels[] = isset($map[$key]) ? $map[$key] : $key;
        }
        return implode($separator, $labels);
    }
}

if (!function_exists('stickerEvidenceCell')) {
    function stickerEvidenceCell($row, $keys)
    {
        foreach ((array) $keys as $k) {
            if (!is_array($row) || !isset($row[$k]) || $row[$k] === '' || $row[$k] === null) continue;
            $v = $row[$k];
            if (is_array($v)) return $v;
            return trim((string) $v);
        }
        return '';
    }
}

if (!function_exists('normalizeStickerEvidenceRow')) {
    /** แถววัตถุพยานสำหรับสติกเกอร์: รายละเอียด / จำนวน / ตำแหน่ง / กลุ่มงาน */
    function normalizeStickerEvidenceRow($row)
    {
        $detail = stickerEvidenceCell($row, ['detail', 'item', 'description']);
        $qty = stickerEvidenceCell($row, ['quantity_val', 'qty', 'quantity']);
        $position = stickerEvidenceCell($row, ['area_found', 'position', 'area']);
        $lab = labUnitsNormalize(stickerEvidenceCell($row, ['lab_unit', 'forensic_unit', 'lab_units']));
        if (is_array($detail)) $detail = implode(', ', array_filter(array_map('strval', $detail)));
        if (is_array($qty)) $qty = implode(', ', array_filter(array_map('strval', $qty)));
        if (is_array($position)) $position = implode(', ', array_filter(array_map('strval', $position)));
        return [
            'detail' => trim((string) $detail),
            'qty' => trim((string) $qty),
            'position' => trim((string) $position),
            'lab_unit' => $lab,
        ];
    }
}

if (!function_exists('collectStickerEvidenceRows')) {
    /**
     * รวมรายการวัตถุพยานจาก checklist (ระเบิด/ทรัพย์ใช้ measurements เป็นหลัก)
     * @return array<int, array{detail:string,qty:string,position:string,lab_unit:array}>
     */
    function collectStickerEvidenceRows(array $data)
    {
        $gen = $data['general_info'] ?? [];
        $evidenceForm = $data['evidence_form'] ?? [];
        $evfDetails = is_array($evidenceForm['evidence_details'] ?? null) ? $evidenceForm['evidence_details'] : [];
        $evidenceList = is_array($data['evidences'] ?? null) ? $data['evidences'] : [];
        $measurementList = is_array($data['measurements'] ?? null) ? $data['measurements'] : [];
        $evidencesFound = is_array($data['evidences_found'] ?? null) ? $data['evidences_found'] : [];

        if (!empty($evfDetails)) {
            $evidenceList = $evfDetails;
        }

        $caseType = $gen['case_type'] ?? '';
        $usesMeasurements = in_array($caseType, ['theft', 'snatch', 'robbery', 'bomb'], true) && !empty($measurementList);

        $rows = [];
        if ($usesMeasurements) {
            foreach ($measurementList as $ms) {
                $rows[] = normalizeStickerEvidenceRow($ms);
            }
            foreach ($evidenceList as $idx => $ev) {
                if (!empty($ev['hidden'])) continue;
                $e = normalizeStickerEvidenceRow($ev);
                if (isset($rows[$idx])) {
                    if ($rows[$idx]['qty'] === '' && $e['qty'] !== '') $rows[$idx]['qty'] = $e['qty'];
                    if ($rows[$idx]['position'] === '' && $e['position'] !== '') $rows[$idx]['position'] = $e['position'];
                    if (empty($rows[$idx]['lab_unit']) && !empty($e['lab_unit'])) $rows[$idx]['lab_unit'] = $e['lab_unit'];
                }
            }
        } else {
            foreach ($evidenceList as $ev) {
                if (!empty($ev['hidden'])) continue;
                $rows[] = normalizeStickerEvidenceRow($ev);
            }
            foreach ($measurementList as $idx => $ms) {
                $m = normalizeStickerEvidenceRow($ms);
                if (isset($rows[$idx])) {
                    if ($rows[$idx]['detail'] === '' && $m['detail'] !== '') $rows[$idx]['detail'] = $m['detail'];
                    if ($rows[$idx]['qty'] === '' && $m['qty'] !== '') $rows[$idx]['qty'] = $m['qty'];
                    if ($rows[$idx]['position'] === '' && $m['position'] !== '') $rows[$idx]['position'] = $m['position'];
                    if (empty($rows[$idx]['lab_unit']) && !empty($m['lab_unit'])) $rows[$idx]['lab_unit'] = $m['lab_unit'];
                } else {
                    $rows[] = $m;
                }
            }
            foreach ($evidencesFound as $idx => $ef) {
                $f = normalizeStickerEvidenceRow($ef);
                if (isset($rows[$idx])) {
                    if ($rows[$idx]['detail'] === '' && $f['detail'] !== '') $rows[$idx]['detail'] = $f['detail'];
                    if ($rows[$idx]['qty'] === '' && $f['qty'] !== '') $rows[$idx]['qty'] = $f['qty'];
                    if ($rows[$idx]['position'] === '' && $f['position'] !== '') $rows[$idx]['position'] = $f['position'];
                } else {
                    $rows[] = $f;
                }
            }
        }

        return array_values(array_filter($rows, function ($r) {
            return $r['detail'] !== '' || $r['qty'] !== '' || $r['position'] !== '';
        }));
    }
}

if (!function_exists('buildStickerPagesByLabUnit')) {
    /**
     * แยกสติกเกอร์ตามกลุ่มงานส่งตรวจ
     * ชิ้นที่มีหลายกลุ่มงานจะโผล่ในทุกแผ่นที่เกี่ยวข้อง
     *
     * @return array<int, array{unit:string,label:string,part:int,parts:int,items:array}>
     */
    function buildStickerPagesByLabUnit(array $rows, $maxPerPage = 10)
    {
        $buckets = [];
        foreach ($rows as $row) {
            $units = labUnitsNormalize($row['lab_unit'] ?? []);
            if (!$units) $units = ['__none__'];
            foreach ($units as $u) {
                if (!isset($buckets[$u])) $buckets[$u] = [];
                $buckets[$u][] = $row;
            }
        }

        $orderedKeys = array_keys(labUnitLabelMap());
        $orderedKeys[] = '__none__';
        $pages = [];
        foreach ($orderedKeys as $unit) {
            if (empty($buckets[$unit])) continue;
            $chunks = array_chunk($buckets[$unit], max(1, (int) $maxPerPage));
            $parts = count($chunks);
            foreach ($chunks as $i => $chunk) {
                $pages[] = [
                    'unit' => $unit,
                    'label' => ($unit === '__none__') ? 'ไม่ระบุกลุ่มงานส่งตรวจ' : labUnitsToText($unit),
                    'part' => $i + 1,
                    'parts' => $parts,
                    'items' => $chunk,
                ];
            }
        }
        if (!$pages) {
            $pages[] = [
                'unit' => '__none__',
                'label' => '',
                'part' => 1,
                'parts' => 1,
                'items' => [],
            ];
        }
        return $pages;
    }
}
