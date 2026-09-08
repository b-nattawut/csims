/**
 * แปลงเลขที่รายงาน (receiveNotiReportNo) และเลขที่เอกสาร (receiveNoti_No)
 * จากภาษาอังกฤษเป็นภาษาไทย (ฝั่ง client) ให้ตรงกับ helpers/report_no.php:
 *   เลขรายงาน:  LC->ท, MD->ช, BM->ร, FR->พ, TF->จร, EV->ต, CM->ต, PS->ต, OT->อ
 *               เช่น "LC-0001/2569" => "ท-0001/2569"
 *   เลขเอกสาร:  แปลงเฉพาะ code ก่อนเลขรันนิ่ง เช่น "10-95-69-PS0003" => "10-95-69-ต0003"
 *
 * - idempotent: ถ้าเป็นภาษาไทยอยู่แล้ว/ไม่รู้จัก prefix จะคืนค่าเดิม
 * - มี MutationObserver คอยแปลง span/ช่องแสดงผลให้เป็นภาษาไทยอัตโนมัติ
 *   ทั้งในหน้า list และในทุก modal (ฟอร์ม checklist, ร่างรายงาน, ขยายเวลา ฯลฯ)
 *   โดยไม่แตะค่า hidden input (#report_no, #doc_no ฯลฯ) ที่ยังต้องเป็นภาษาอังกฤษสำหรับสร้างเอกสาร
 */
(function () {
    function toThaiReportNo(s) {
        if (s === null || s === undefined) return '';
        s = String(s).trim();
        if (!s) return '';
        var map = {
            'LC-': 'ท-',
            'MD-': 'ช-',
            'BM-': 'ร-',
            'FR-': 'พ-',
            'TF-': 'จร-',
            'EV-': 'ต-',
            'CM-': 'ต-',
            'PS-': 'ต-',
            'OT-': 'อ-'
        };
        for (var en in map) {
            if (Object.prototype.hasOwnProperty.call(map, en) && s.indexOf(en) === 0) {
                return map[en] + s.substring(en.length);
            }
        }
        return s;
    }
    window.toThaiReportNo = toThaiReportNo;

    // แปลงเลขที่เอกสาร (receiveNoti_No) เช่น "10-95-69-PS0003" => "10-95-69-ต0003"
    // เปลี่ยนเฉพาะตัวอักษรประเภทก่อนเลขรันนิ่ง (เลข/ปี ยังเป็นอารบิก)
    var DOC_MAP = {
        'LC': 'ท', 'MD': 'ช', 'BM': 'ร', 'FR': 'พ',
        'TF': 'จร', 'EV': 'ต', 'CM': 'ต', 'PS': 'ต', 'OT': 'อ'
    };
    var DOC_RE = /(^|-)(LC|MD|BM|FR|TF|EV|CM|PS|OT)(\d+)/g;
    function toThaiDocNo(s) {
        if (s === null || s === undefined) return '';
        s = String(s).trim();
        if (!s) return '';
        return s.replace(DOC_RE, function (full, pre, code, num) {
            return pre + (DOC_MAP[code] || code) + num;
        });
    }
    window.toThaiDocNo = toThaiDocNo;

    // ช่อง report_no_display ใน modal ร่างรายงาน บางฟอร์มใส่เลขที่เอกสารเต็ม (10-95-69-PS0003)
    // ไม่ใช่เลขรายงาน (PS-0003/2569) — ตรวจรูปแบบแล้วเลือก converter ให้ถูก
    function smartThaiReportOrDoc(s) {
        if (s === null || s === undefined) return '';
        s = String(s).trim();
        if (!s) return '';
        if (/^\d{1,3}-\d{1,3}-\d{2}-/.test(s)) {
            return toThaiDocNo(s);
        }
        return toThaiReportNo(s);
    }
    window.smartThaiReportOrDoc = smartThaiReportOrDoc;

    // id ของ span ที่ใช้แสดงเลขที่รายงานให้ผู้ใช้เห็น (ไม่ใช่ hidden input)
    var SPAN_IDS = [
        'receiveNotiReportNo',
        'receiveNotiReportNo_life',
        'receiveNotiReportNo_bomb',
        'receiveNotiReportNo_fire',
        'receiveNotiReportNo_traffic',
        'receiveNotiReportNo_fpn',
        'receiveNotiReportNo_fp',
        'receiveNotiReportNo_ev7',
        'receiveNotiReportNo_ev8',
        'receiveNotiReportNo_evidence',
        'sevpf_receiveNotiReportNo',
        'rex_report_no_display'
    ];

    // id ของ span ที่ใช้แสดงเลขที่เอกสารให้ผู้ใช้เห็น (ส่วนหัว modal ต่าง ๆ)
    // ไม่รวม hidden input #doc_no_* ที่ยังต้องเป็นภาษาอังกฤษสำหรับสร้างเอกสาร
    var DOC_SPAN_IDS = [
        'receiveNoti_No',
        'receiveNoti_No_life',
        'receiveNoti_No_bomb',
        'receiveNoti_No_fire',
        'receiveNoti_No_traffic',
        'receiveNoti_No_fpn',
        'receiveNoti_No_fp',
        'receiveNoti_No_ev7',
        'receiveNoti_No_ev8',
        'receiveNoti_No_evidence',
        'sevpf_receiveNoti_No'
    ];

    // ช่องแสดง "เลขที่รายงาน" ในฟอร์มพรีวิว/ร่างรายงานทุก modal
    //   - span คลาส *-rpt-no-mirror (ppf/lpf/bpf/fpf/tpf/pepf/sevpf ...)
    //   - span/ช่องที่ id มีคำว่า report_no_display
    var REPORT_SELECTOR = '[class*="-rpt-no-mirror"], [id*="report_no_display"], [id*="report_ref"]';

    // ช่องแสดง "เลขที่เอกสาร" / "คดี" / "ในคดี" ในทุก modal
    //   - span ส่วนหัว id ขึ้นต้น receiveNoti_No
    //   - span คลาส *-doc-no-mirror
    //   - ช่อง "ในคดี" วัตถุพยาน (*_case_no, case_no_display)
    //   - ช่อง "คดี" ทรัพย์/ชีวิต/ระเบิด/ไฟ/จราจร (case_doc_no_*, *_case_doc_no)
    // หมายเหตุ: ไม่แตะ hidden #doc_no_* / #ppf_doc_no ฯลฯ ที่ยังเก็บค่าอังกฤษสำหรับ backend
    var DOC_SELECTOR = '[id^="receiveNoti_No"], [class*="-doc-no-mirror"], [id$="_case_no"], [id$="case_no_display"], [id^="case_doc_no_"], [id$="_case_doc_no"], [id$="_doc_no_display"]';

    function isEditable(el) {
        var tag = el.tagName;
        return tag === 'INPUT' || tag === 'TEXTAREA';
    }

    function normalizeWith(el, converter) {
        if (!el || el.nodeType !== 1) return;
        var editable = isEditable(el);
        var cur = editable ? el.value : el.textContent;
        var thai = converter(cur);
        if (thai !== cur) {
            if (editable) {
                el.value = thai; // idempotent -> ไม่วนซ้ำ
            } else {
                el.textContent = thai;
            }
        }
    }

    // แปลงทุก element ที่ตรง selector ภายใน root ที่กำหนด (ทั้งเลขรายงานและเลขเอกสาร)
    function normalizeAll(root) {
        if (!root) return;
        var scope = (root.nodeType === 1) ? root : document;
        var rNodes = scope.querySelectorAll(REPORT_SELECTOR);
        for (var i = 0; i < rNodes.length; i++) {
            normalizeWith(rNodes[i], smartThaiReportOrDoc);
        }
        var dNodes = scope.querySelectorAll(DOC_SELECTOR);
        for (var k = 0; k < dNodes.length; k++) {
            normalizeWith(dNodes[k], toThaiDocNo);
        }
    }

    // ผูก observer ให้ span id เฉพาะ (หน้า list) ที่มีมาแต่เดิม
    function attachSpanIds() {
        attachIdList(SPAN_IDS, toThaiReportNo);
        attachIdList(DOC_SPAN_IDS, toThaiDocNo);
    }

    function attachIdList(ids, converter) {
        for (var i = 0; i < ids.length; i++) {
            var el = document.getElementById(ids[i]);
            if (!el || el.__rptThObserved) continue;
            el.__rptThObserved = true;
            normalizeWith(el, converter);
            (function (elem, conv) {
                var mo = new MutationObserver(function (list) {
                    for (var j = 0; j < list.length; j++) {
                        var t = list[j].target.nodeType === 3 ? list[j].target.parentNode : list[j].target;
                        normalizeWith(t, conv);
                    }
                });
                mo.observe(elem, { childList: true, characterData: true, subtree: true });
            })(el, converter);
        }
    }

    // ผูก observer ให้ทั้ง subtree ของ modal/คอนเทนเนอร์ เพื่อจับ span ที่ JS เติมค่าทีหลัง
    function observeContainer(container) {
        if (!container || container.__rptThContainerObserved) return;
        container.__rptThContainerObserved = true;
        normalizeAll(container);
        // debounce ด้วย rAF กัน modal ที่มี canvas/รูปภาพยิง mutation ถี่ ๆ
        var scheduled = false;
        var mo = new MutationObserver(function () {
            if (scheduled) return;
            scheduled = true;
            var run = function () { scheduled = false; normalizeAll(container); };
            if (window.requestAnimationFrame) {
                window.requestAnimationFrame(run);
            } else {
                setTimeout(run, 50);
            }
        });
        mo.observe(container, { childList: true, characterData: true, subtree: true });
        // ช่องที่เป็น input ถูกเติมค่าแบบ .val() ซึ่ง MutationObserver ไม่จับ
        // จึงกวาดซ้ำอีกสองสามรอบหลังเปิด modal
        [0, 150, 400, 800, 1500].forEach(function (ms) {
            setTimeout(function () { normalizeAll(container); }, ms);
        });
    }

    function attach(evt) {
        attachSpanIds();
        if (evt && evt.target && evt.target.nodeType === 1) {
            observeContainer(evt.target);
        }
        // เผื่อกรณีเปิดผ่าน jQuery ที่ evt.target อาจไม่ใช่ตัว modal
        normalizeAll(document);
    }

    if (document.readyState !== 'loading') {
        attach();
    } else {
        document.addEventListener('DOMContentLoaded', attach);
    }

    // span/ช่องบางตัวถูกเติมค่าเมื่อเปิด modal — ผูก observer เพิ่มตอน modal แสดง
    // รองรับทั้ง Bootstrap 5 (native event) และ Bootstrap 4 (jQuery event)
    document.addEventListener('shown.bs.modal', function (e) {
        attachSpanIds();
        observeContainer(e.target);
    }, true);
    if (window.jQuery) {
        window.jQuery(document).on('shown.bs.modal', function (e) {
            attachSpanIds();
            observeContainer(e.target || (e.currentTarget));
        });
    }
})();
