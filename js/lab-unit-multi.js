/*
 * lab-unit-multi.js
 * ตัวช่วยกลางสำหรับช่อง "การตรวจพิสูจน์" (กลุ่มงานส่งตรวจ) แบบเลือกได้หลายรายการ
 *
 * รูปแบบ markup ที่ใช้ทุกฟอร์ม:
 *   <select class="form-select lab-unit-multi" multiple size="3"> ...options... </select>
 *   <input type="hidden" class="lab-unit-value" name="<ชื่อเดิม>[]" value="">
 *
 * <select> ไม่มี name (ไม่ถูก submit) ส่วน hidden input ถือค่าที่เลือกทั้งหมดในรูป
 * "a,b,c" ทำให้จำนวนค่าที่ส่งต่อ 1 แถว = 1 ค่าเสมอ (index ของแถวไม่เพี้ยน)
 */
(function (global) {
    'use strict';

    var SELECT_SELECTOR = 'select.lab-unit-multi';

    /**
     * แปลงค่าที่รับเข้ามา (string เดิม / comma string / JSON string / array)
     * ให้เป็น array ของ key เสมอ  -> รองรับข้อมูลเก่าที่เก็บเป็น string เดี่ยว
     */
    function normalize(value) {
        if (value === null || value === undefined || value === false) return [];
        var out = [];
        var i;
        if (Array.isArray(value)) {
            for (i = 0; i < value.length; i++) {
                var nested = normalize(value[i]);
                for (var j = 0; j < nested.length; j++) {
                    if (out.indexOf(nested[j]) === -1) out.push(nested[j]);
                }
            }
            return out;
        }
        if (typeof value === 'object') {
            for (var k in value) {
                if (Object.prototype.hasOwnProperty.call(value, k)) {
                    var sub = normalize(value[k]);
                    for (var m = 0; m < sub.length; m++) {
                        if (out.indexOf(sub[m]) === -1) out.push(sub[m]);
                    }
                }
            }
            return out;
        }
        var str = String(value).trim();
        if (str === '') return [];
        if (str.charAt(0) === '[') {
            try {
                var parsed = JSON.parse(str);
                if (Array.isArray(parsed)) return normalize(parsed);
            } catch (e) { /* ไม่ใช่ JSON - ใช้เป็น comma string ต่อไป */ }
        }
        var parts = str.split(',');
        for (i = 0; i < parts.length; i++) {
            var p = parts[i].trim();
            if (p !== '' && out.indexOf(p) === -1) out.push(p);
        }
        return out;
    }

    /** array/string -> "a,b,c" (รูปแบบที่ส่งไป backend ผ่าน hidden input) */
    function join(value) {
        return normalize(value).join(',');
    }

    /** ค่าแรกอย่างเดียว (ใช้กับที่ยังต้องการค่าเดี่ยว เช่นการเทียบเงื่อนไขเก่า) */
    function first(value) {
        var arr = normalize(value);
        return arr.length ? arr[0] : '';
    }

    /**
     * แปลงเป็นข้อความสำหรับแสดง/พิมพ์ โดยใช้ map key -> label
     * ถ้าไม่มีใน map จะใช้ key ตรง ๆ
     */
    function toText(value, labelMap, separator) {
        var arr = normalize(value);
        var sep = (separator === undefined || separator === null) ? ', ' : separator;
        var labels = [];
        for (var i = 0; i < arr.length; i++) {
            var key = arr[i];
            labels.push((labelMap && labelMap[key]) ? labelMap[key] : key);
        }
        return labels.join(sep);
    }

    /** หา hidden input ที่คู่กับ select */
    function hiddenFor(select) {
        if (!select) return null;
        var el = select.nextElementSibling;
        while (el) {
            if (el.tagName === 'INPUT' && el.className && el.className.indexOf('lab-unit-value') !== -1) return el;
            el = el.nextElementSibling;
        }
        // สำรอง: หาในกล่องแถวเดียวกัน
        var parent = select.parentNode;
        if (parent && parent.querySelector) {
            return parent.querySelector('input.lab-unit-value');
        }
        return null;
    }

    /** หา select ที่คู่กับ hidden input */
    function selectFor(hidden) {
        if (!hidden) return null;
        var el = hidden.previousElementSibling;
        while (el) {
            if (el.tagName === 'SELECT' && el.className && el.className.indexOf('lab-unit-multi') !== -1) return el;
            el = el.previousElementSibling;
        }
        var parent = hidden.parentNode;
        if (parent && parent.querySelector) return parent.querySelector('select.lab-unit-multi');
        return null;
    }

    /** แปลง target (jQuery / NodeList / array / element / selector) เป็น array ของ element */
    function toElements(target) {
        if (!target) return [];
        if (typeof target === 'string') {
            return Array.prototype.slice.call(document.querySelectorAll(target));
        }
        if (target.nodeType === 1) return [target];
        if (typeof target.length === 'number') {
            var out = [];
            for (var i = 0; i < target.length; i++) {
                if (target[i] && target[i].nodeType === 1) out.push(target[i]);
            }
            return out;
        }
        return [];
    }

    /**
     * เขียนค่าลงช่องการตรวจพิสูจน์ ไม่ว่า target จะเป็น <select multiple> หรือ hidden input
     * รองรับ jQuery object / NodeList / element / selector และค่าเก่าที่เป็น string เดี่ยว
     */
    function applyTo(target, value) {
        var els = toElements(target);
        var joined = join(value);
        for (var i = 0; i < els.length; i++) {
            var el = els[i];
            if (el.tagName === 'SELECT') {
                setValue(el, joined);
            } else if (el.tagName === 'INPUT') {
                el.value = joined;
                var sel = selectFor(el);
                if (sel) {
                    setValue(sel, joined); // setValue จะ sync hidden กลับมาเอง
                    el.value = joined;
                }
            }
        }
    }

    /** อ่านค่าจาก target (select หรือ hidden input) -> array */
    function readFrom(target) {
        var els = toElements(target);
        for (var i = 0; i < els.length; i++) {
            var el = els[i];
            if (el.tagName === 'SELECT') return getValue(el);
            if (el.tagName === 'INPUT') {
                var sel = selectFor(el);
                if (sel) return getValue(sel);
                return normalize(el.value);
            }
        }
        return [];
    }

    /** อ่านค่าที่เลือกอยู่จาก select -> array */
    function getValue(select) {
        if (!select) return [];
        if (typeof select.length === 'number' && !select.tagName) select = select[0]; // jQuery object
        if (!select || !select.options) return [];
        var out = [];
        for (var i = 0; i < select.options.length; i++) {
            var opt = select.options[i];
            if (opt.selected && opt.value !== '') out.push(opt.value);
        }
        return out;
    }

    /** เขียนค่าที่เลือก (รับได้ทั้ง string เดิมและ array) แล้ว sync hidden input */
    function setValue(select, value) {
        if (!select) return;
        if (typeof select.length === 'number' && !select.tagName) select = select[0];
        if (!select || !select.options) return;
        var wanted = normalize(value);
        for (var i = 0; i < select.options.length; i++) {
            var opt = select.options[i];
            opt.selected = (opt.value !== '' && wanted.indexOf(opt.value) !== -1);
        }
        syncHidden(select);
    }

    /** คัดลอกค่าที่เลือกลง hidden input */
    function syncHidden(select) {
        if (!select) return '';
        if (typeof select.length === 'number' && !select.tagName) select = select[0];
        var hidden = hiddenFor(select);
        var joined = getValue(select).join(',');
        if (hidden) hidden.value = joined;
        return joined;
    }

    /** sync ทุกตัวใน container (เรียกก่อน submit เพื่อความชัวร์) */
    function syncAll(container) {
        var root = container || document;
        if (root && typeof root.length === 'number' && !root.querySelectorAll) root = root[0];
        if (!root || !root.querySelectorAll) root = document;
        var list = root.querySelectorAll(SELECT_SELECTOR);
        for (var i = 0; i < list.length; i++) syncHidden(list[i]);
    }

    /** ล้างค่าทั้งหมดใน container */
    function clearAll(container) {
        var root = container || document;
        if (root && typeof root.length === 'number' && !root.querySelectorAll) root = root[0];
        if (!root || !root.querySelectorAll) root = document;
        var list = root.querySelectorAll(SELECT_SELECTOR);
        for (var i = 0; i < list.length; i++) setValue(list[i], []);
    }

    var api = {
        SELECT_SELECTOR: SELECT_SELECTOR,
        normalize: normalize,
        join: join,
        first: first,
        toText: toText,
        hiddenFor: hiddenFor,
        selectFor: selectFor,
        applyTo: applyTo,
        readFrom: readFrom,
        getValue: getValue,
        setValue: setValue,
        syncHidden: syncHidden,
        syncAll: syncAll,
        clearAll: clearAll
    };

    global.LabUnitMulti = api;
    // ทางลัดที่ใช้บ่อยในโค้ดฟอร์ม
    global.labUnitsToArray = normalize;
    global.labUnitsToString = join;
    global.setLabUnitValue = setValue;
    // ใช้แทน $(sel).val(x) ทุกจุดที่เป็นช่องการตรวจพิสูจน์
    global.setLabUnits = applyTo;
    global.getLabUnits = readFrom;
    // ใช้แทน $(sel).val() ตอนสร้าง payload -> ได้ "a,b" (ค่าว่าง = "")
    global.getLabUnitsString = function (target) { return readFrom(target).join(','); };

    function bind() {
        if (!global.jQuery) return;
        var $ = global.jQuery;
        if ($(document).data('labUnitMultiBound')) return;
        $(document).data('labUnitMultiBound', true);

        // sync hidden ทุกครั้งที่เปลี่ยนค่า
        $(document).on('change', SELECT_SELECTOR, function () {
            syncHidden(this);
        });

        // คลิกเลือก/ยกเลิกได้เลยโดยไม่ต้องกด Ctrl (สำคัญมากบนแท็บเล็ต)
        $(document).on('mousedown', SELECT_SELECTOR + ' > option, ' + SELECT_SELECTOR + ' > optgroup > option', function (e) {
            var opt = this;
            var select = opt.parentNode;
            while (select && select.tagName !== 'SELECT') select = select.parentNode;
            if (!select || select.disabled || opt.disabled) return;
            e.preventDefault();
            var scrollTop = select.scrollTop;
            if (opt.value === '') {
                for (var i = 0; i < select.options.length; i++) select.options[i].selected = false;
            } else {
                opt.selected = !opt.selected;
            }
            select.scrollTop = scrollTop;
            setTimeout(function () { select.scrollTop = scrollTop; }, 0);
            $(select).trigger('change');
            return false;
        });

        $(function () { syncAll(document); });
    }

    if (global.jQuery) {
        bind();
    } else if (global.document && global.document.addEventListener) {
        global.document.addEventListener('DOMContentLoaded', bind);
    }
})(typeof window !== 'undefined' ? window : this);
