const criteriaTemplates = {
    'หัวหน้าทีม': {
        maxScore: 60,
        html: `
            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">1. การเตรียมตัว ณ ที่ตั้ง</td>
            </tr>

            <tr class="table-light">
                <td colspan="5" class="fw-bold ps-4">1.1 การเตรียมความพร้อม</td>
                <td></td>
                <td class="text-start small text-muted">F-CS-02</td>
            </tr>

            <tr>
                <td class="ps-4 text-muted">- การกำกับดูแลความพร้อม และจำนวนบุคลากร</td>
                ${generateAssessmentRow(1)}
            </tr>

            <tr>
                <td class="ps-4 text-muted">- การกำกับดูแลความพร้อม และจำนวนวัสดุ เครื่องมืออุปกรณ์ รถยนต์</td>
                ${generateAssessmentRow(2)}
            </tr>

            <tr class="table-light">
                <td colspan="5" class="fw-bold ps-4">1.2 การรับแจ้งเหตุ และการวางแผนก่อนการตรวจ</td>
                <td></td>
                <td class="text-start small text-muted">F-CS-01</td>
            </tr>

            <tr>
                <td class="ps-4 text-muted">- การประสานข้อมูลเบื้องต้น และพฤติการณ์ของคดี จาก พงส.</td>
                ${generateAssessmentRow(3)}
            </tr>


            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">2. การเตรียมตัวเมื่อเข้าใกล้สถานที่เกิดเหตุ</td>
            </tr>

            <tr class="table-light">
                <td colspan="5" class="fw-bold ps-4">2.1 กำหนดจุดสั่งการ</td>
                <td></td>
                <td></td>
            </tr>

            <tr>
                <td class="ps-4 text-muted">- การสำรวจเพื่อกำหนดที่ตั้งจุดสั่งการ</td>
                ${generateAssessmentRow(4)}
            </tr>

            <tr class="table-light">
                <td colspan="5" class="fw-bold ps-4">2.2 การป้องกันการปนเปื้อน</td>
                <td></td>
                <td></td>
            </tr>

            <tr>
                <td class="ps-4 text-muted">- การสวมใส่อุปกรณ์ป้องกันของหัวหน้าทีม และชุดตรวจ</td>
                ${generateAssessmentRow(5)}
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">3. การรักษาความปลอดภัย และการป้องกันสถานที่เกิดเหตุ</td>
            </tr>

            <tr>
                <td class="ps-4 text-muted">- การกั้นสถานที่เกิดเหตุ</td>
                ${generateAssessmentRow(6)}
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">4. การสำรวจสถานที่เกิดเหตุเบื้องต้น</td>
            </tr>

            <tr>
                <td class="ps-4 text-muted">- การสอบถามข้อมูล จาก พงส. ผู้เสียหาย / ผู้เห็นเหตุการณ์</td>
                ${generateAssessmentRow(7)}
            </tr>

            <tr>
                <td class="ps-4 text-muted">- การสำรวจสถานที่เกิดเหตุเบื้องต้น การกำหนดเส้นทางเดินของเจ้าหน้าที่</td>
                ${generateAssessmentRow(8)}
            </tr>

            <tr>
                <td class="ps-4 text-muted">- การวางวงแหวน หรือป้ายหมายเลขแสดงบริเวณที่พบวัตถุพยานที่มีขนาดเล็กสูญหายง่าย</td>
                ${generateAssessmentRow(9)}
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">5. การประเมินพยานหลักฐานที่อาจพบในสถานที่เกิดเหตุ</td>
            </tr>

            <tr>
                <td class="ps-4 text-muted">- การวิเคราะห์วัตถุพยาน เช่น ทางเข้า-ออกของคนร้าย ร่องรอยการงัดแงะรื้อค้น ตำแหน่งที่พบศพ วัตถุพยานอื่น ๆ ที่ทำให้ทราบถึงพฤติการณ์ของคดี</td>
                ${generateAssessmentRow(10)}
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">6. การเตรียมบรรยายสรุปสภาพของสถานที่เกิดเหตุ</td>
            </tr>

            <tr>
                <td class="ps-4 text-muted">- การบรรยายสรุปให้กับทีมตรวจ ตามที่มีการประเมินในข้อ 5</td>
                ${generateAssessmentRow(11)}
            </tr>

            <tr>
                <td class="ps-4 text-muted">- การแบ่งให้เจ้าหน้าที่ทำหน้าที่ต่าง ๆ ในทีม</td>
                ${generateAssessmentRow(12)}
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">7. การถ่ายภาพสถานที่เกิดเหตุ</td>
            </tr>

            <tr>
                <td class="ps-4 text-muted">- การสั่งการให้ช่างภาพถ่ายภาพ</td>
                ${generateAssessmentRow(13)}
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">8. การเตรียมแผนผังหรือร่างภาพสถานที่เกิดเหตุ</td>
            </tr>

            <tr>
                <td class="ps-4 text-muted">- การสั่งการให้ผู้ทำแผนที่จัดทำแผนผัง</td>
                ${generateAssessmentRow(14)}
            </tr>


            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">9. การดำเนินการค้นหาวัตถุพยานในสถานที่เกิดเหตุอย่างละเอียด</td>
            </tr>

            <tr>
                <td class="ps-4 text-muted">- การสั่งการให้ทีมค้นหาค้นหาวัตถุพยานโดยละเอียด</td>
                ${generateAssessmentRow(15)}
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">10. การบันทึกและเก็บรวบรวมพยานหลักฐานในสถานที่เกิดเหตุ</td>
            </tr>

            <tr>
                <td class="ps-4 text-muted">- การควบคุมการบันทึกและเก็บรวบรวมพยานหลักฐานและบรรจุหีบห่อให้เป็นไปตามขั้นตอน</td>
                ${generateAssessmentRow(16)}
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">11. การดำเนินการสำรวจสถานที่เกิดเหตุขั้นสุดท้าย</td>
            </tr>

            <tr>
                <td class="ps-4 text-muted">- การเข้าสำรวจสถานที่เกิดเหตุขั้นสุดท้าย</td>
                ${generateAssessmentRow(17)}
            </tr>

            <tr>
                <td class="ps-4 text-muted">- ตรวจสอบความเรียบร้อยของบุคลากร เครื่องมือ และบันทึกต่าง ๆ </td>
                ${generateAssessmentRow(18)}
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">12. การส่งมอบสถานที่เกิดเหตุ</td>
            </tr>

            <tr>
                <td class="ps-4 text-muted">- การจัดทำบันทึกส่งมอบวัตถุพยานและส่งมอบคืนสถานที่เกิดเหตุ</td>
                ${generateAssessmentRow(19, 'F-CS-11\nF-CS-08/09/10')}
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">13. การจัดทำรายงานผลการตรวจสถานที่เกิดเหตุ</td>
            </tr>

            <tr>
                <td class="ps-4 text-muted">- มีการจัดทำรายงานผลการตรวจสถานที่เกิดเหตุ</td>
                ${generateAssessmentRow(20, 'ตามแบบรายงานการตรวจเฉพาะคดี')}
            </tr>
            `
    },
    'ช่างภาพ': {
        maxScore: 30,
        html: `
            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">1. การเตรียมตัว ณ ที่ตั้ง</td>
            </tr>

            <tr class="table-light">
                <td colspan="5" class="fw-bold ps-4">1.1 การเตรียมความพร้อม</td>
                <td></td>
                <td class="text-start small text-muted">F-CS-02</td>
            </tr>

            <tr>
                <td class="ps-4 text-muted">- การตรวจเช็คความพร้อมของอุปกรณ์ถ่ายภาพ</td>
                ${generateAssessmentRow(1)}
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">2. การเตรียมตัวเมื่อเข้าใกล้สถานที่เกิดเหตุ</td>
            </tr>

            <tr class="table-light">
                <td colspan="5" class="fw-bold ps-4">2.1 กำหนดจุดสั่งการ</td>
                <td></td>
                <td></td>
            </tr>

            <tr>
                <td class="ps-4 text-muted">- การตั้งวางเครื่องมืออุปกรณ์ที่เกี่ยวข้องที่จุดสั่งการ</td>
                ${generateAssessmentRow(2)}
            </tr>

            <tr class="table-light">
                <td colspan="5" class="fw-bold ps-4">2.2 การป้องกันการปนเปื้อน</td>
                <td></td>
                <td></td>
            </tr>

            <tr>
                <td class="ps-4 text-muted">- การสวมใส่อุปกรณ์ป้องกันการปนเปื้อน</td>
                ${generateAssessmentRow(3)}
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">3. การรักษาความปลอดภัย และการป้องกันสถานที่เกิดเหตุ</td>
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">4. การสำรวจสถานที่เกิดเหตุเบื้องต้น</td>
            </tr>

            <tr>
                <td class="ps-4 text-muted">- การถ่ายภาพ สถานที่เกิดเหตุโดยรอบ</td>
                ${generateAssessmentRow(4)}
            </tr>

            <tr>
                <td class="ps-4 text-muted">- การถ่ายภาพหลังจากการวางวงแหวน หรือ ป้ายหมายเลข แสดงบริเวณที่พบวัตถุพยานขนาดเล็กที่สูญหายง่าย</td>
                ${generateAssessmentRow(5)}
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">5. การประเมินพยานหลักฐานที่อาจพบในสถานที่เกิดเหตุ</td>
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">6. การเตรียมบรรยายสรุปสภาพของสถานที่เกิดเหตุ</td>
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">7. การถ่ายภาพสถานที่เกิดเหตุ</td>
            </tr>

            <tr>
                <td class="ps-4 text-muted">- ทักษะการถ่ายภาพ ระยะไกล กลาง ใกล้ และระยะใกล้แบบมีสเกล</td>
                ${generateAssessmentRow(6)}
            </tr>

            <tr>
                <td class="ps-4 text-muted">- คุณภาพของภาพถ่าย เช่น ความคมชัด เป็นต้น</td>
                ${generateAssessmentRow(7)}
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">8. การเตรียมแผนผังหรือร่างภาพสถานที่เกิดเหตุ</td>
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">9. การดำเนินการค้นหาวัตถุพยานในสถานที่เกิดเหตุอย่างละเอียด</td>
            </tr>

            <tr>
                <td class="ps-4 text-muted">- การถ่ายภาพ เมื่อพบวัตถุพยานเพิ่มเติม</td>
                ${generateAssessmentRow(8)}
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">10. การบันทึกและเก็บรวบรวมพยานหลักฐานในสถานที่เกิดเหตุ</td>
            </tr>

            <tr>
                <td class="ps-4 text-muted">- การถ่ายภาพหีบห่อบรรจุวัตถุพยานทั้งหมด</td>
                ${generateAssessmentRow(9)}
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">11. การดำเนินการสำรวจสถานที่เกิดเหตุขั้นสุดท้าย</td>
            </tr>

            <tr>
                <td class="ps-4 text-muted">- การถ่ายภาพสถานที่เกิดเหตุขั้นสุดท้าย</td>
                ${generateAssessmentRow(10)}
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">12. การส่งมอบสถานที่เกิดเหตุ</td>
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">13. การจัดทำรายงานผลการตรวจสถานที่เกิดเหตุ</td>
            </tr>

        `
    },
    'ผู้ทำแผนที่': {
        maxScore: 15,
        html: `
            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">1. การเตรียมตัว ณ ที่ตั้ง</td>
            </tr>

            <tr class="table-light">
                <td colspan="5" class="fw-bold ps-4">1.1 การเตรียมความพร้อม</td>
                <td></td>
                <td class="text-start small text-muted">F-CS-02</td>
            </tr>

            <tr>
                <td class="ps-4 text-muted">- การตรวจเช็คความพร้อมของอุปกรณ์ทำแผนที่</td>
                ${generateAssessmentRow(1)}
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">2. การเตรียมตัวเมื่อเข้าใกล้สถานที่เกิดเหตุ</td>
            </tr>

            <tr class="table-light">
                <td colspan="5" class="fw-bold ps-4">2.1 กำหนดจุดสั่งการ</td>
                <td></td>
                <td></td>
            </tr>

            <tr>
                <td class="ps-4 text-muted">- การตั้งวางเครื่องมืออุปกรณ์ที่เกี่ยวข้องที่จุดสั่งการ</td>
                ${generateAssessmentRow(2)}
            </tr>

            <tr class="table-light">
                <td colspan="5" class="fw-bold ps-4">2.2 การป้องกันการปนเปื้อน</td>
                <td></td>
                <td></td>
            </tr>

            <tr>
                <td class="ps-4 text-muted">- การสวมใส่อุปกรณ์ป้องกันการปนเปื้อน</td>
                ${generateAssessmentRow(3)}
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">3. การรักษาความปลอดภัย และการป้องกันสถานที่เกิดเหตุ</td>
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">4. การสำรวจสถานที่เกิดเหตุเบื้องต้น</td>
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">5. การประเมินพยานหลักฐานที่อาจพบในสถานที่เกิดเหตุ</td>
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">6. การเตรียมบรรยายสรุปสภาพของสถานที่เกิดเหตุ</td>
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">7. การถ่ายภาพสถานที่เกิดเหตุ</td>
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">8. การเตรียมแผนผังหรือร่างภาพสถานที่เกิดเหตุ</td>
            </tr>

            <tr>
                <td class="ps-4 text-muted">- การดำเนินการทำแผนที่แบบหยาบ</td>
                ${generateAssessmentRow(4)}
            </tr>

            <tr>
                <td class="ps-4 text-muted">- การทำแผนที่ระบุตำแหน่งวัตถุพยาน แบบละเอียด เมื่อพบวัตถุพยาน</td>
                ${generateAssessmentRow(5)}
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">9. การดำเนินการค้นหาวัตถุพยานในสถานที่เกิดเหตุอย่างละเอียด</td>
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">10. การบันทึกและเก็บรวบรวมพยานหลักฐานในสถานที่เกิดเหตุ</td>
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">11. การดำเนินการสำรวจสถานที่เกิดเหตุขั้นสุดท้าย</td>
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">12. การส่งมอบสถานที่เกิดเหตุ</td>
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">13. การจัดทำรายงานผลการตรวจสถานที่เกิดเหตุ</td>
            </tr>
        `
    },
    'ผู้ค้นหาวัตถุพยาน': {
        maxScore: 15,
        html: `
            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">1. การเตรียมตัว ณ ที่ตั้ง</td>
            </tr>

            <tr class="table-light">
                <td colspan="5" class="fw-bold ps-4">1.1 การเตรียมความพร้อม</td>
                <td></td>
                <td class="text-start small text-muted">F-CS-02</td>
            </tr>

            <tr>
                <td class="ps-4 text-muted">- การตรวจเช็คความพร้อมของอุปกรณ์ที่เกี่ยวข้อง</td>
                ${generateAssessmentRow(1)}
            </tr>

            <tr>
                <td class="ps-4 text-muted">- การตรวจเช็คความพร้อมของเครื่องมือที่เกี่ยวข้อง</td>
                ${generateAssessmentRow(2)}
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">2. การเตรียมตัวเมื่อเข้าใกล้สถานที่เกิดเหตุ</td>
            </tr>

            <tr class="table-light">
                <td colspan="5" class="fw-bold ps-4">2.1 กำหนดจุดสั่งการ</td>
                <td></td>
                <td></td>
            </tr>

            <tr>
                <td class="ps-4 text-muted">- การตั้งวางเครื่องมืออุปกรณ์ที่เกี่ยวข้องที่จุดสั่งการ</td>
                ${generateAssessmentRow(3)}
            </tr>

            <tr class="table-light">
                <td colspan="5" class="fw-bold ps-4">2.2 การป้องกันการปนเปื้อน</td>
                <td></td>
                <td></td>
            </tr>

            <tr>
                <td class="ps-4 text-muted">- การสวมใส่อุปกรณ์ป้องกันการปนเปื้อน</td>
                ${generateAssessmentRow(4)}
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">3. การรักษาความปลอดภัย และการป้องกันสถานที่เกิดเหตุ</td>
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">4. การสำรวจสถานที่เกิดเหตุเบื้องต้น</td>
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">5. การประเมินพยานหลักฐานที่อาจพบในสถานที่เกิดเหตุ</td>
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">6. การเตรียมบรรยายสรุปสภาพของสถานที่เกิดเหตุ</td>
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">7. การถ่ายภาพสถานที่เกิดเหตุ</td>
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">8. การเตรียมแผนผังหรือร่างภาพสถานที่เกิดเหตุ</td>
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">9. การดำเนินการค้นหาวัตถุพยานในสถานที่เกิดเหตุอย่างละเอียด</td>
            </tr>

            <tr>
                <td class="ps-4 text-muted">- เลือกใช้วิธีการค้นหาวัตถุพยานอย่างเหมาะสม ตามหลักวิชาการ</td>
                ${generateAssessmentRow(5)}
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">10. การบันทึกและเก็บรวบรวมพยานหลักฐานในสถานที่เกิดเหตุ</td>
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">11. การดำเนินการสำรวจสถานที่เกิดเหตุขั้นสุดท้าย</td>
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">12. การส่งมอบสถานที่เกิดเหตุ</td>
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">13. การจัดทำรายงานผลการตรวจสถานที่เกิดเหตุ</td>
            </tr>
        `
    },
    'ผู้ตรวจเก็บวัตถุพยาน': {
        maxScore: 24,
        html: `
            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">1. การเตรียมตัว ณ ที่ตั้ง</td>
            </tr>

            <tr class="table-light">
                <td colspan="5" class="fw-bold ps-4">1.1 การเตรียมความพร้อม</td>
                <td></td>
                <td class="text-start small text-muted">F-CS-02</td>
            </tr>

            <tr>
                <td class="ps-4 text-muted">- การตรวจเช็คความพร้อมของอุปกรณ์ที่เกี่ยวข้อง</td>
                ${generateAssessmentRow(1)}
            </tr>

            <tr>
                <td class="ps-4 text-muted">- การตรวจเช็คความพร้อมของเครื่องมือที่เกี่ยวข้อง</td>
                ${generateAssessmentRow(2)}
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">2. การเตรียมตัวเมื่อเข้าใกล้สถานที่เกิดเหตุ</td>
            </tr>

            <tr class="table-light">
                <td colspan="5" class="fw-bold ps-4">2.1 กำหนดจุดสั่งการ</td>
                <td></td>
                <td></td>
            </tr>

            <tr>
                <td class="ps-4 text-muted">- การตั้งวางเครื่องมืออุปกรณ์ที่เกี่ยวข้องที่จุดสั่งการ</td>
                ${generateAssessmentRow(3)}
            </tr>

            <tr class="table-light">
                <td colspan="5" class="fw-bold ps-4">2.2 การป้องกันการปนเปื้อน</td>
                <td></td>
                <td></td>
            </tr>

            <tr>
                <td class="ps-4 text-muted">- การสวมใส่อุปกรณ์ป้องกันการปนเปื้อน</td>
                ${generateAssessmentRow(4)}
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">3. การรักษาความปลอดภัย และการป้องกันสถานที่เกิดเหตุ</td>
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">4. การสำรวจสถานที่เกิดเหตุเบื้องต้น</td>
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">5. การประเมินพยานหลักฐานที่อาจพบในสถานที่เกิดเหตุ</td>
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">6. การเตรียมบรรยายสรุปสภาพของสถานที่เกิดเหตุ</td>
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">7. การถ่ายภาพสถานที่เกิดเหตุ</td>
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">8. การเตรียมแผนผังหรือร่างภาพสถานที่เกิดเหตุ</td>
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">9. การดำเนินการค้นหาวัตถุพยานในสถานที่เกิดเหตุอย่างละเอียด</td>
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">10. การบันทึกและเก็บรวบรวมพยานหลักฐานในสถานที่เกิดเหตุ</td>
            </tr>

            <tr>
                <td class="ps-4 text-muted">- การป้องกันการปนเปื้อนของวัตถุพยาน</td>
                ${generateAssessmentRow(5)}
            </tr>

            <tr>
                <td class="ps-4 text-muted">- การเก็บวัตถุพยาน ถูกต้องตามหลักวิชาการ</td>
                ${generateAssessmentRow(6)}
            </tr>

            <tr>
                <td class="ps-4 text-muted">- การบรรจุหีบห่อวัตถุพยาน ถูกต้องตามหลักวิชาการ</td>
                ${generateAssessmentRow(7)}
            </tr>

            <tr>
                <td class="ps-4 text-muted">- การจัดทำบันทึกตามแบบการตรวจเก็บและส่งมอบวัตถุพยาน</td>
                ${generateAssessmentRow(8)}
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">11. การดำเนินการสำรวจสถานที่เกิดเหตุขั้นสุดท้าย</td>
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">12. การส่งมอบสถานที่เกิดเหตุ</td>
            </tr>

            <tr class="table-secondary">
                <td colspan="7" class="fw-bold ps-3">13. การจัดทำรายงานผลการตรวจสถานที่เกิดเหตุ</td>
            </tr>
        `
    }
};


function generateAssessmentRow(id, defaultRemark = '') {

    // 1. สร้าง ID ที่ไม่ซ้ำกันสำหรับ textarea แต่ละช่อง
    const commentId = `comment_assess_${id}`;
    const remarkId = `remark_assess_${id}`;

    // Helper function เพื่อสร้าง Cell ที่คลิกได้ทั้งช่อง
    const createRadioCell = (val) => `
    <td class="text-center js-click-score" style="vertical-align: middle;">
        <input type="radio" name="score[${id}]" value="${val}" required 
               style="transform: scale(1.2); pointer-events: none;"> </td>
`;

    // ส่วนของ Radio 0-3 และ Textarea ความเห็น
    const scoreAndCommentHtml = `
        ${createRadioCell(0)}
        ${createRadioCell(1)}
        ${createRadioCell(2)}
        ${createRadioCell(3)}
        <td>
            <div class="input-group input-group-seamless">
                <textarea id="${commentId}" name="comment[${id}]" class="form-control form-control-sm" rows="1"></textarea>
                <button type="button" class="btn btn-hw-open btn-hw-open data-hw-targets="${commentId}" title="เขียนด้วยลายมือ">
                    <i class="fas fa-pen"></i>
                </button>
            </div>
        </td>
    `;

    // ส่วนของหมายเหตุ (ปรับให้ Dynamic)
    let remarkHtml = '';
    if (defaultRemark !== '') {
        // กรณีมี Remark จากระบบ
        // และเพิ่ม hidden input เพื่อส่งค่าเดิมไปบันทึกใน Database ด้วย
        remarkHtml = `
            <td class="text-start small text-muted">
                ${defaultRemark.replace(/\n/g, '<br />')}
                <input type="hidden" name="remark[${id}]" value="${defaultRemark}">
            </td>
        `;
    } else {
        // กรณีไม่มี Remark: แสดงเป็น Textarea ให้กรอกเอง
        remarkHtml = `
            <td>
                <div class="input-group input-group-seamless">
                    <textarea id="${remarkId}" name="remark[${id}]" class="form-control form-control-sm" rows="1"></textarea>
                    <button type="button" class="btn btn-hw-open btn-hw-open" data-hw-targets="${remarkId}" title="เขียนด้วยลายมือ">
                        <i class="fas fa-pen"></i>
                    </button>
                </div>
            </td>
        `;
    }

    return scoreAndCommentHtml + remarkHtml;
}