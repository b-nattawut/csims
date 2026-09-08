// ============================================================================
// free-text-names.js v1
//
// แก้ปัญหาที่ผู้ใช้แจ้ง (ข้อ 1):
//   "หัวข้อเลือกผู้บันทึก (ใบรับแจ้ง, เช็คลิสต์) รายชื่อไม่ตรงตามจริง
//    ยังมีชื่อทดลองในแบบฟอร์ม ขอให้เป็นช่องว่างแล้วพิมพ์รายชื่อเอง"
//
// วิธีทำ: แปลง <select> ของช่องชื่อคน (ผู้บันทึก/ผู้จดบันทึก/ผู้เก็บวัตถุพยาน/
// ผู้ถ่ายภาพ/ผู้รับมอบ/ผู้ส่งมอบ/ผู้ทบทวน/ผู้อนุมัติ) ให้เป็น <input type="text">
// ที่ "พิมพ์ชื่อเองได้อิสระ" แต่ยังมี <datalist> แนะนำรายชื่อจากฐานข้อมูลให้เลือก
//
// ทำเป็นไฟล์กลางไฟล์เดียว ครอบทุกแบบฟอร์ม (ระเบิด/เพลิงไหม้/ชีวิต/ทรัพย์/จราจร
// ทั้งฟอร์มปกติและฟอร์ม PDF) เพื่อให้แก้/ย้อนกลับได้ง่ายกว่าการไปแก้ทีละจุด
// ============================================================================
(function () {
    'use strict';

    // ชื่อ/ไอดีของช่องที่ถือว่าเป็น "ช่องกรอกชื่อคน"
    var NAME_PATTERN = /(recorder|collector_name|photographer_name|receiver_name|sender_name|receiver_id|deliverer_id|reviewer|approver|investigator_select)/i;

    // คลาสที่ต้องถอดออก เพราะเป็นคลาสที่โค้ดอื่นใช้ผูก select2 ไว้
    // (select2 ใช้กับ <input> ไม่ได้ ถ้าไม่ถอดจะ error)
    var DROP_CLASS = /^(user-select-box|user-select-box-.*|rr-user-select|inspector-select|inspector-select-.*|forensic-select|forensic-select-.*|select2.*)$/;

    var dlSeq = 0;

    function isPlaceholderOption(opt) {
        // <option value="" disabled>-- เลือกผู้จดบันทึก --</option>
        return !opt.value || opt.disabled;
    }

    function buildDatalist(select) {
        var names = [];
        var positions = {};

        Array.prototype.forEach.call(select.options, function (opt) {
            if (isPlaceholderOption(opt)) return;
            var label = (opt.textContent || '').trim();
            var value = (opt.value || '').trim();
            // บาง select เก็บ value เป็น user_id แต่แสดงชื่อใน label
            // ช่องพิมพ์อิสระควรเก็บ "ชื่อ" ไม่ใช่ id จึงใช้ label เป็นค่า
            var name = label || value;
            if (!name) return;
            if (names.indexOf(name) === -1) names.push(name);

            var pos = opt.getAttribute('data-position');
            if (pos) positions[name] = pos;
        });

        if (names.length === 0) return { id: null, positions: positions };

        var dl = document.createElement('datalist');
        dl.id = 'ftn_dl_' + (++dlSeq);
        names.forEach(function (n) {
            var o = document.createElement('option');
            o.value = n;
            dl.appendChild(o);
        });
        document.body.appendChild(dl);
        return { id: dl.id, positions: positions };
    }

    function currentDisplayValue(select) {
        var opt = select.options[select.selectedIndex];
        if (!opt || isPlaceholderOption(opt)) return '';
        return (opt.textContent || '').trim() || opt.value;
    }

    function unlockPositionField(posSelector) {
        if (!posSelector) return;
        var el = document.querySelector(posSelector);
        if (!el) return;
        el.removeAttribute('readonly');
        el.classList.remove('bg-light');
    }

    function convert(select) {
        var key = (select.name || '') + ' ' + (select.id || '');
        if (!NAME_PATTERN.test(key)) return;

        // ถ้า select2 ผูกไว้แล้ว ต้องถอดก่อน ไม่งั้นจะเหลือ DOM ค้าง
        if (window.jQuery && window.jQuery(select).hasClass('select2-hidden-accessible')) {
            try { window.jQuery(select).select2('destroy'); } catch (e) { /* ignore */ }
        }

        var built = buildDatalist(select);
        var value = currentDisplayValue(select);
        var posTarget = select.getAttribute('data-pos-target');

        var input = document.createElement('input');
        input.type = 'text';
        if (select.name) input.name = select.name;
        if (select.id) input.id = select.id;

        // คงคลาสที่ใช้จัดหน้าตาไว้ (form-control, bpf-sel, fpf-sel ฯลฯ)
        // แต่ถอดคลาสที่ผูกกับ select2 ออก
        var keep = [];
        Array.prototype.forEach.call(select.classList, function (c) {
            if (!DROP_CLASS.test(c)) keep.push(c);
        });
        // form-select เป็นสไตล์ของ dropdown ถ้าใช้กับ input จะเหลือลูกศรค้าง
        keep = keep.filter(function (c) { return c !== 'form-select'; });
        var hasStyleClass = keep.indexOf('form-control') !== -1 ||
            keep.some(function (c) { return /-(sel|inp)$/.test(c); });
        if (!hasStyleClass) keep.push('form-control');
        input.className = keep.join(' ');

        if (select.getAttribute('style')) input.setAttribute('style', select.getAttribute('style'));
        if (select.required) input.required = true;
        if (built.id) input.setAttribute('list', built.id);
        input.setAttribute('autocomplete', 'off');
        input.placeholder = 'พิมพ์ชื่อ-สกุล';
        input.value = value;
        input.setAttribute('data-ftn-converted', '1');
        if (posTarget) input.setAttribute('data-pos-target', posTarget);

        select.parentNode.replaceChild(input, select);

        // ตำแหน่งต้องแก้เองได้ เพราะไม่มี dropdown ให้ auto-fill อีกแล้ว
        unlockPositionField(posTarget);

        // แต่ถ้าพิมพ์ตรงกับรายชื่อที่มีอยู่ ก็ยังเติมตำแหน่งให้อัตโนมัติ
        if (posTarget && Object.keys(built.positions).length > 0) {
            input.addEventListener('change', function () {
                var pos = built.positions[input.value.trim()];
                if (!pos) return;
                var el = document.querySelector(posTarget);
                if (el && !el.value.trim()) el.value = pos;
            });
        }
    }

    function convertAll(root) {
        var scope = root || document;
        var list = scope.querySelectorAll('select:not([data-ftn-skip])');
        Array.prototype.forEach.call(list, function (s) { convert(s); });
    }

    function run(root) {
        try { convertAll(root); } catch (e) { console.warn('[free-text-names] failed', e); }
    }

    document.addEventListener('DOMContentLoaded', function () {
        run();
        // โมดัลถูกสร้าง/เติมข้อมูลตอนเปิด และ select2 ก็ init ตอนนั้นด้วย
        // จึงต้องแปลงอีกครั้งหลังโมดัลเปิด (เผื่อ init มาทีหลัง)
        if (window.jQuery) {
            window.jQuery(document).on('shown.bs.modal', function (e) {
                run(e.target);
                setTimeout(function () { run(e.target); }, 400);
            });
        }
    });

    window.convertNameSelectsToFreeText = run;
    console.log('[free-text-names.js v1] loaded OK');
})();
