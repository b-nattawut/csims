<?php
/**
 * word_photo_appendix.php — สร้าง "ภาคผนวกภาพประกอบ" เป็นไฟล์ DOCX แยกต่างหาก
 *
 * ที่มาของปัญหาที่ผู้ใช้แจ้ง:
 *  - ร่างรายงานเดิมรวมเนื้อหากับภาพประกอบไว้ในไฟล์เดียว และเลขหน้าไหลต่อกัน
 *  - ผู้ใช้ต้องการแยกไฟล์ และให้ภาพประกอบเริ่มนับหน้าที่ 1 ใหม่
 *
 * ไฟล์นี้สร้างเอกสาร PhpWord ใหม่ทั้งฉบับ (standalone) จึงได้เลขหน้าเริ่มที่ 1
 * โดยไม่ต้องแก้ลูปภาพในไฟล์ *_report_docx.php แต่ละแบบ
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;

const WORD_PHOTO_APPENDIX_FONT = 'TH Sarabun New';
const WORD_PHOTO_APPENDIX_SIZE = 14;

if (!function_exists('wordPhotoAppendixPrepareFile')) {

    /**
     * เขียน data-uri ของรูปเป็นไฟล์ชั่วคราว — คืน null ถ้าใช้ไม่ได้
     */
    function wordPhotoAppendixPrepareFile($photo): ?string
    {
        $uri = is_array($photo) ? ($photo['uri'] ?? '') : (string) $photo;
        if ($uri === '' || strpos($uri, 'data:image') !== 0) {
            return null;
        }
        if (!preg_match('#^data:(image/[a-zA-Z0-9.+-]+);base64,(.+)$#', $uri, $m)) {
            return null;
        }

        $mime  = strtolower($m[1]);
        $bytes = base64_decode($m[2], true);
        if ($bytes === false || $bytes === '') {
            return null;
        }

        $ext = '.jpg';
        if (strpos($mime, 'png') !== false) {
            $ext = '.png';
        } elseif (strpos($mime, 'gif') !== false) {
            $ext = '.gif';
        } elseif (strpos($mime, 'webp') !== false) {
            // PhpWord มักไม่รองรับ webp → แปลงเป็น png ถ้ามี GD
            if (!function_exists('imagecreatefromstring')) {
                return null;
            }
            $im = @imagecreatefromstring($bytes);
            if ($im === false) {
                return null;
            }
            ob_start();
            imagepng($im);
            $bytes = ob_get_clean();
            imagedestroy($im);
            $ext = '.png';
        }

        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rep_photo_' . uniqid('', true) . $ext;
        if (file_put_contents($path, $bytes) === false) {
            return null;
        }
        return $path;
    }

    /**
     * สร้างเอกสารภาพประกอบแบบยืนพื้นเอง (1 ภาพ/หน้า) เริ่มนับหน้าที่ 1
     *
     * @return array{0: PhpWord, 1: string[]} [เอกสาร, path ไฟล์ชั่วคราวที่ต้องลบหลัง save]
     */
    function wordPhotoAppendixBuild(array $photoDataUris, string $headingText = ''): array
    {
        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName(WORD_PHOTO_APPENDIX_FONT);
        $phpWord->setDefaultFontSize(WORD_PHOTO_APPENDIX_SIZE);

        $settings = $phpWord->getSettings();
        $settings->setHideSpellingErrors(true);
        $settings->setHideGrammaticalErrors(true);

        // pageNumberingStart = 1 → เลขหน้าของภาคผนวกเริ่มใหม่ ไม่ต่อจากเนื้อหา
        $section = $phpWord->addSection([
            'marginTop'          => 680,
            'marginBottom'       => 680,
            'marginLeft'         => 850,
            'marginRight'        => 850,
            'pageNumberingStart' => 1,
        ]);

        $fs = [
            'name'  => WORD_PHOTO_APPENDIX_FONT,
            'size'  => WORD_PHOTO_APPENDIX_SIZE,
            'color' => '000000',
        ];
        $ps = ['spaceBefore' => 0, 'spaceAfter' => 0, 'lineHeight' => 1.15];

        $header = $section->addHeader();
        if (trim($headingText) !== '') {
            $header->addText($headingText, $fs, $ps);
        }
        $header->addPreserveText(
            '{PAGE}/{NUMPAGES}',
            $fs,
            array_merge($ps, ['alignment' => Jc::END])
        );

        $tempPaths  = [];
        $photoIndex = 0;
        foreach (array_values($photoDataUris) as $photo) {
            $path = wordPhotoAppendixPrepareFile($photo);
            if ($path === null) {
                continue;
            }
            if ($photoIndex > 0) {
                $section->addPageBreak();
            }
            try {
                $section->addImage($path, [
                    'width'     => 450,
                    'alignment' => Jc::CENTER,
                ]);
                $photoIndex++;
                $section->addText(
                    'ภาพที่ ' . $photoIndex,
                    $fs,
                    array_merge($ps, ['alignment' => Jc::CENTER, 'spaceBefore' => 80])
                );
                $tempPaths[] = $path;
            } catch (Throwable $e) {
                @unlink($path);
            }
        }

        if ($photoIndex === 0) {
            $section->addText('ไม่มีภาพประกอบ', $fs, array_merge($ps, ['alignment' => Jc::CENTER]));
        }

        return [$phpWord, $tempPaths];
    }

    /**
     * สร้างและส่งไฟล์ภาคผนวกภาพประกอบให้เบราว์เซอร์ดาวน์โหลด
     */
    function wordPhotoAppendixDownload(array $photoDataUris, string $baseName, string $headingText = ''): void
    {
        [$phpWord, $tempPhotos] = wordPhotoAppendixBuild($photoDataUris, $headingText);

        $filenameUtf8  = $baseName . '.docx';
        $filenameAscii = preg_replace('/[^\x20-\x7E]/', '', $baseName);
        $filenameAscii = (trim($filenameAscii) !== '' ? trim($filenameAscii) : 'report_photos') . '.docx';

        $tmpFile = tempnam(sys_get_temp_dir(), 'rep_photos_');
        if ($tmpFile === false) {
            throw new RuntimeException('ไม่สามารถสร้างไฟล์ชั่วคราวได้');
        }
        $docxPath = $tmpFile . '.docx';
        @unlink($tmpFile);

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($docxPath);

        foreach ($tempPhotos as $p) {
            if (is_string($p) && $p !== '' && is_file($p)) {
                @unlink($p);
            }
        }

        header('Cache-Control: max-age=0');
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header(
            'Content-Disposition: attachment; filename="' . $filenameAscii . '"; filename*=UTF-8\'\'' . rawurlencode($filenameUtf8)
        );
        header('Content-Length: ' . filesize($docxPath));
        readfile($docxPath);
        @unlink($docxPath);
    }
}
