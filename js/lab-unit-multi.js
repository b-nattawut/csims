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

    /** แถวที่ครอบ select + hidden (หลัง enhance แล้ว select อยู่ใน .lab-unit-picker) */
    function rowScope(el) {
        if (!el || !el.closest) return el && el.parentNode ? el.parentNode : null;
        return el.closest('.sevpf-evidence-item-row, .ev7-evidence-item-row, .lab-unit-picker') || el.parentNode;
    }

    /** หา hidden input ที่คู่กับ select */
    function hiddenFor(select) {
        if (!select) return null;
        var wrap = select.closest ? select.closest('.lab-unit-picker') : null;
        if (wrap && wrap.parentNode && wrap.parentNode.querySelector) {
            var inRow = wrap.parentNode.querySelector('input.lab-unit-value');
            if (inRow) return inRow;
        }
        var el = select.nextElementSibling;
        while (el) {
            if (el.tagName === 'INPUT' && el.className && el.className.indexOf('lab-unit-value') !== -1) return el;
            if (el.className && el.className.indexOf('lab-unit-picker') !== -1) {
                var nested = el.querySelector && el.querySelector('input.lab-unit-value');
                if (nested) return nested;
            }
            el = el.nextElementSibling;
        }
        var parent = rowScope(select);
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
            if (el.className && el.className.indexOf('lab-unit-picker') !== -1) {
                var nested = el.querySelector && el.querySelector('select.lab-unit-multi');
                if (nested) return nested;
            }
            el = el.previousElementSibling;
        }
        var parent = rowScope(hidden);
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
        if (select._labRebuild) select._labRebuild();
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

    /**
     * ครอบ property "value" ของ hidden input ไว้ เพื่อให้โค้ดเก่าที่เขียนค่าตรง ๆ
     * (el.value = "bio_dna" หรือ $(el).val("bio_dna")) ยังทำให้ <select multiple>
     * แสดงผลตรงกันอัตโนมัติ โดยไม่ต้องแก้ทุกจุดที่ load ข้อมูล
     */
    var nativeValueDesc = (global.HTMLInputElement && global.HTMLInputElement.prototype)
        ? Object.getOwnPropertyDescriptor(global.HTMLInputElement.prototype, 'value')
        : null;
    var applyingFromHidden = false;

    function hookHidden(hidden) {
        if (!hidden || hidden.__labUnitHooked) return;
        if (!nativeValueDesc || !nativeValueDesc.get || !nativeValueDesc.set) return;
        hidden.__labUnitHooked = true;
        Object.defineProperty(hidden, 'value', {
            configurable: true,
            enumerable: true,
            get: function () {
                return nativeValueDesc.get.call(this);
            },
            set: function (v) {
                var joined = join(v);
                nativeValueDesc.set.call(this, joined);
                if (applyingFromHidden) return;
                applyingFromHidden = true;
                try {
                    var sel = selectFor(this);
                    if (sel) setValue(sel, joined);
                } finally {
                    applyingFromHidden = false;
                }
            }
        });
    }

    /** ครอบ hidden input ทุกตัวใน container */
    function hookAll(container) {
        var root = container || document;
        if (root && typeof root.length === 'number' && !root.querySelectorAll) root = root[0];
        if (!root || !root.querySelectorAll) root = document;
        var list = root.querySelectorAll('input.lab-unit-value');
        for (var i = 0; i < list.length; i++) hookHidden(list[i]);
        if (root.nodeType === 1 && root.matches && root.matches('input.lab-unit-value')) hookHidden(root);
        enhanceAll(root);
    }

    /** แปลง <select multiple> เป็นช่องติ๊ก ที่กดบนแท็บเล็ตได้ทีละหลายข้อ */
    function enhanceSelect(select) {
        if (!select || select.dataset.labEnhanced === '1') return;
        if (!select.parentNode) return;
        select.dataset.labEnhanced = '1';
        select.setAttribute('aria-hidden', 'true');
        select.tabIndex = -1;

        var wrap = document.createElement('div');
        wrap.className = 'lab-unit-picker';
        select.parentNode.insertBefore(wrap, select);
        wrap.appendChild(select);

        var list = document.createElement('div');
        list.className = 'lab-unit-picker-list';
        wrap.appendChild(list);

        function rebuild() {
            list.innerHTML = '';
            Array.prototype.forEach.call(select.options, function (opt) {
                if (!opt.value) return;
                var lab = document.createElement('label');
                lab.className = 'lab-unit-chip' + (opt.selected ? ' is-on' : '');
                var cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.checked = !!opt.selected;
                cb.addEventListener('click', function (e) { e.stopPropagation(); });
                cb.addEventListener('change', function () {
                    opt.selected = cb.checked;
                    lab.classList.toggle('is-on', cb.checked);
                    syncHidden(select);
                    if (global.jQuery) global.jQuery(select).trigger('change');
                });
                lab.appendChild(cb);
                lab.appendChild(document.createTextNode(opt.textContent || opt.value));
                list.appendChild(lab);
            });
        }

        select._labRebuild = rebuild;
        rebuild();
    }

    function enhanceAll(container) {
        var root = container || document;
        if (root && typeof root.length === 'number' && !root.querySelectorAll) root = root[0];
        if (!root || !root.querySelectorAll) root = document;
        var list = root.querySelectorAll(SELECT_SELECTOR);
        for (var i = 0; i < list.length; i++) enhanceSelect(list[i]);
        if (root.nodeType === 1 && root.matches && root.matches(SELECT_SELECTOR)) enhanceSelect(root);
    }

    /**
     * ย้อนทาง: เอาค่าจาก hidden input (เช่นถูกเขียนทับด้วยโค้ด sync ที่ copy ตาม name)
     * กลับไปเลือกใน <select multiple> ให้ตรงกัน
     */
    function refreshFromHidden(container) {
        var root = container || document;
        if (root && typeof root.length === 'number' && !root.querySelectorAll) root = root[0];
        if (!root || !root.querySelectorAll) root = document;
        var list = root.querySelectorAll('input.lab-unit-value');
        for (var i = 0; i < list.length; i++) {
            var sel = selectFor(list[i]);
            if (sel) {
                var joined = join(list[i].value);
                setValue(sel, joined);
                list[i].value = joined;
            }
        }
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
        hookAll: hookAll,
        refreshFromHidden: refreshFromHidden,
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

        // เปิด modal ใหม่ทีไร ให้ select แสดงตรงกับ hidden ที่ถูกโหลดมา
        $(document).on('shown.bs.modal', function (e) {
            hookAll(e.target);
            refreshFromHidden(e.target);
        });

        $(function () {
            hookAll(document);
            syncAll(document);
            observeNewRows();
        });
    }

    /** แถวที่ถูกสร้างทีหลัง (dynamic row) ต้องถูกครอบ value property ด้วย */
    function observeNewRows() {
        if (!global.MutationObserver || !global.document || !global.document.body) return;
        var observer = new global.MutationObserver(function (records) {
            for (var i = 0; i < records.length; i++) {
                var added = records[i].addedNodes;
                for (var j = 0; j < added.length; j++) {
                    if (added[j].nodeType === 1) {
                        hookAll(added[j]);
                        enhanceAll(added[j]);
                    }
                }
            }
        });
        observer.observe(global.document.body, { childList: true, subtree: true });
    }

    if (global.jQuery) {
        bind();
    } else if (global.document && global.document.addEventListener) {
        global.document.addEventListener('DOMContentLoaded', bind);
    }
})(typeof window !== 'undefined' ? window : this);
