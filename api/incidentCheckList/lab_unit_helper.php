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
