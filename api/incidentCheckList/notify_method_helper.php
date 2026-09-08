<?php
/**
 * notify_method_helper.php
 *
 * แปลง "วิธีการรับแจ้ง" ให้เป็นข้อความไทยที่อ่านได้
 *
 * ที่มาของปัญหา (ข้อ 7 ที่ผู้ใช้แจ้ง):
 *  - ร่างรายงานเดิมพิมพ์ออกมาเป็น "ช่องติ๊ก" (☐/☑) ไม่ใช่ข้อความ
 *  - ข้อมูลที่บันทึกไว้มีหลายรูปแบบปนกัน: รหัสอังกฤษจากเช็คลิสต์ (phone/radio/document)
 *    และข้อความไทยหลายสำนวนจากฟอร์มร่างรายงาน (หนังสือ / ทางหนังสือ / ตามหนังสือ)
 *  - บาง generator ไม่ได้ map รหัสอังกฤษ จึงไม่มีช่องไหนถูกติ๊กเลย -> ออกมาว่างเปล่า
 *
 * ไฟล์นี้รวมการ normalize ไว้ที่เดียว ให้ทุก generator เรียกใช้ร่วมกัน
 */

if (!function_exists('notifyMethodToThaiLabels')) {

    /**
     * แปลงค่าที่บันทึกไว้ (string หรือ array, รหัสอังกฤษหรือข้อความไทย)
     * ให้เป็นรายการข้อความไทยมาตรฐาน ไม่ซ้ำกัน และคงลำดับตามที่ผู้ใช้เลือก
     *
     * @param mixed $raw
     * @return string[]
     */
    function notifyMethodToThaiLabels($raw): array
    {
        if ($raw === null || $raw === '' || $raw === []) return [];
        if (!is_array($raw)) $raw = [$raw];

        $map = [
            // รหัสอังกฤษจากเช็คลิสต์ (general_info.report_channel)
            'phone'      => 'ทางโทรศัพท์',
            'telephone'  => 'ทางโทรศัพท์',
            'radio'      => 'ทางวิทยุสื่อสาร',
            'document'   => 'ทางหนังสือ',
            'letter'     => 'ทางหนังสือ',
            'other'      => 'อื่น ๆ',
            // ข้อความไทยหลายสำนวนจากฟอร์มร่างรายงาน
            'โทรศัพท์'        => 'ทางโทรศัพท์',
            'ทางโทรศัพท์'     => 'ทางโทรศัพท์',
            'วิทยุสื่อสาร'     => 'ทางวิทยุสื่อสาร',
            'ทางวิทยุสื่อสาร'  => 'ทางวิทยุสื่อสาร',
            'วิทยุ'           => 'ทางวิทยุสื่อสาร',
            'หนังสือ'         => 'ทางหนังสือ',
            'ทางหนังสือ'      => 'ทางหนังสือ',
            'ตามหนังสือ'      => 'ทางหนังสือ',
            'เอกสาร'          => 'ทางหนังสือ',
            'อื่น ๆ'          => 'อื่น ๆ',
            'อื่นๆ'           => 'อื่น ๆ',
        ];

        $out = [];
        foreach ($raw as $item) {
            if (is_array($item)) continue;
            $key = trim((string)$item);
            if ($key === '') continue;
            // ค่าที่ไม่รู้จักจะถูกแสดงตามเดิม จึงตัด < > กันหน้ารายงานเพี้ยน
            $label = $map[$key] ?? $map[mb_strtolower($key, 'UTF-8')] ?? str_replace(['<', '>'], '', $key);
            if (!in_array($label, $out, true)) $out[] = $label;
        }
        return $out;
    }

    /**
     * ข้อความสำเร็จรูปสำหรับใส่ในรายงาน เช่น "ทางโทรศัพท์ และทางวิทยุสื่อสาร"
     *
     * @param mixed  $raw       ค่าที่บันทึกไว้
     * @param string $otherText ข้อความกรณีเลือก "อื่น ๆ"
     * @param string $fallback  ข้อความเมื่อไม่มีข้อมูล
     * @return string
     */
    function notifyMethodToText($raw, string $otherText = '', string $fallback = '-'): string
    {
        $labels = notifyMethodToThaiLabels($raw);

        // ข้อความช่อง "อื่น ๆ" ผู้ใช้พิมพ์เอง ถ้ามี < > ติดมาจะทำให้หน้ารายงานเพี้ยน
        // ตัดออกที่นี่ที่เดียว ใช้ได้ทั้งฝั่ง HTML/PDF และฝั่ง Word
        $otherText = trim(str_replace(['<', '>'], '', $otherText));

        if ($otherText !== '') {
            $idx = array_search('อื่น ๆ', $labels, true);
            if ($idx !== false) {
                $labels[$idx] = 'อื่น ๆ (' . $otherText . ')';
            } else {
                $labels[] = 'อื่น ๆ (' . $otherText . ')';
            }
        }

        if (count($labels) === 0) return $fallback;
        if (count($labels) === 1) return $labels[0];

        $last = array_pop($labels);
        return implode(' ', $labels) . ' และ' . $last;
    }
}
