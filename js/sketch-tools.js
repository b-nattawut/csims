// ============================================================================
// sketch-tools.js v8 — Undo (snapshot-based) + Palm rejection + Color + Pen Size + Eraser
//
// v8 แก้ 2 ปัญหาที่ผู้ใช้แจ้ง:
//  1) ปุ่ม "ย้อนกลับ" กดไม่ได้ / ไม่มีผล
//     เดิม undo อ่านจาก pad.toData() ซึ่งจะว่างเมื่อแผนผังถูกโหลดมาจากรูปเดิม
//     (fromDataURL / drawImage) หรือเมื่อใช้ยางลบแบบ destination-out
//     v8 เก็บ "ภาพ snapshot" ของ canvas ก่อนเริ่มลากทุกเส้น แล้ว undo = คืนภาพก่อนหน้า
//  2) วางฝ่ามือบนจอแล้วเกิดเส้นลากอัตโนมัติ
//     เพิ่ม palm rejection: ตัดนิ้ว/ฝ่ามือที่แตะพร้อมกันหลายจุด, ตัดพื้นที่สัมผัสกว้าง
//     และถ้าตรวจพบว่าใช้ปากกา (stylus) จะไม่รับ input จากนิ้วเลย
// ============================================================================
window.signaturePads = window.signaturePads || {};
window._sketchColor  = {};
window._sketchOrigColor = {};
window._sketchPenSize = {};
window._sketchEraser = {};

// --- state ภายในของ v8 ---
var _sketchHistory   = {};   // canvasId -> [dataURL, ...] ภาพก่อนเริ่มแต่ละเส้น
var _sketchHooked    = {};   // canvasId -> true เมื่อ hook pad แล้ว
var _sketchPenSeen   = {};   // canvasId -> true เมื่อเคยเจอ pointerType 'pen'
var _sketchPalmGuard = {};   // canvasId -> true เมื่อติดตั้ง palm rejection แล้ว
var SKETCH_HISTORY_MAX = 25;

// ขนาดพื้นที่สัมผัส (px) ที่ถือว่าเป็นฝ่ามือ ไม่ใช่ปลายนิ้ว/ปากกา
var PALM_CONTACT_PX = 45;

// ============================================================================
// Palm rejection
// ============================================================================
function _installPalmGuard(canvasId) {
    if (_sketchPalmGuard[canvasId]) return;
    var canvas = document.getElementById(canvasId);
    if (!canvas) return;
    _sketchPalmGuard[canvasId] = true;

    var activeTouches = 0;

    function block(e) {
        e.stopImmediatePropagation();
        e.preventDefault();
    }

    // ใช้ capture phase เพื่อสกัดก่อนที่ SignaturePad จะได้รับ event
    canvas.addEventListener('pointerdown', function (e) {
        if (e.pointerType === 'pen' || e.pointerType === 'mouse') {
            if (e.pointerType === 'pen') _sketchPenSeen[canvasId] = true;
            return;
        }
        // pointerType === 'touch'
        // ใช้ปากกาอยู่ -> ไม่รับนิ้ว/ฝ่ามือเลย
        if (_sketchPenSeen[canvasId]) { block(e); return; }
        // แตะพร้อมกันหลายจุด (ฝ่ามือ) -> ไม่รับ
        if (activeTouches > 0) { block(e); return; }
        // พื้นที่สัมผัสกว้างเกินปลายนิ้ว (ฝ่ามือ/สันมือ) -> ไม่รับ
        if ((e.width || 0) > PALM_CONTACT_PX || (e.height || 0) > PALM_CONTACT_PX) { block(e); return; }
    }, true);

    // นับจำนวนจุดสัมผัสจาก touch events (แม่นกว่าใน iOS/Android บางรุ่น)
    canvas.addEventListener('touchstart', function (e) {
        activeTouches = e.touches ? e.touches.length : 1;
        if (_sketchPenSeen[canvasId]) { block(e); return; }
        if (activeTouches > 1) { block(e); return; }
        var t = e.touches && e.touches[0];
        if (t && (((t.radiusX || 0) * 2) > PALM_CONTACT_PX || ((t.radiusY || 0) * 2) > PALM_CONTACT_PX)) {
            block(e);
        }
    }, true);

    canvas.addEventListener('touchmove', function (e) {
        var n = e.touches ? e.touches.length : 1;
        if (n > 1 || _sketchPenSeen[canvasId]) block(e);
    }, true);

    ['touchend', 'touchcancel'].forEach(function (ev) {
        canvas.addEventListener(ev, function (e) {
            activeTouches = e.touches ? e.touches.length : 0;
        }, true);
    });
}

// ============================================================================
// Undo history (snapshot-based)
// ============================================================================
function _sketchSnapshot(canvasId) {
    var canvas = document.getElementById(canvasId);
    if (!canvas) return;
    try {
        var url = canvas.toDataURL('image/png');
        if (!_sketchHistory[canvasId]) _sketchHistory[canvasId] = [];
        _sketchHistory[canvasId].push(url);
        if (_sketchHistory[canvasId].length > SKETCH_HISTORY_MAX) {
            _sketchHistory[canvasId].shift();
        }
    } catch (e) {
        // canvas ถูก taint จากรูปข้ามโดเมน -> ข้าม snapshot รอบนี้
        console.warn('[sketch-tools] snapshot failed for ' + canvasId, e);
    }
}

// เรียกหลังโหลดแผนผังเดิมเข้ามา เพื่อให้ undo ย้อนได้ไม่เกินภาพต้นฉบับ
function sketchResetHistory(canvasId) {
    _sketchHistory[canvasId] = [];
}
window.sketchResetHistory = sketchResetHistory;

function _restoreSnapshot(canvasId, dataUrl, done) {
    var canvas = document.getElementById(canvasId);
    if (!canvas) { if (done) done(); return; }
    var ctx = canvas.getContext('2d');
    var img = new Image();
    img.onload = function () {
        ctx.save();
        ctx.globalCompositeOperation = 'source-over';
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
        ctx.restore();
        if (done) done();
    };
    img.onerror = function () { if (done) done(); };
    img.src = dataUrl;
}

// ★ ย้อนกลับเส้นล่าสุด (Undo)
function sketchUndo(canvasId) {
    var pad = window.signaturePads[canvasId];
    var hist = _sketchHistory[canvasId] || [];

    if (hist.length > 0) {
        var prev = hist.pop();
        _restoreSnapshot(canvasId, prev, function () {
            // sync ข้อมูลใน pad ให้ตรงกับภาพ (เพื่อไม่ให้ toData เพี้ยน)
            if (pad) {
                try {
                    var d = pad.toData();
                    if (d && d.length > 0) { d.pop(); pad._data = d; }
                    pad._isEmpty = (hist.length === 0 && (!d || d.length === 0));
                } catch (e) { /* ignore */ }
            }
        });
        return;
    }

    // ไม่มี history (เช่นสคริปต์โหลดทีหลัง) -> fallback แบบเดิม
    if (!pad) return;
    var data = pad.toData();
    if (!data || data.length === 0) return;
    data.pop();
    var canvas = document.getElementById(canvasId);
    if (canvas) {
        var ctx = canvas.getContext('2d');
        ctx.save();
        ctx.globalCompositeOperation = 'source-over';
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.restore();
    }
    pad._data = [];
    pad._isEmpty = true;
    if (data.length > 0) pad.fromData(data);
}
window.sketchUndo = sketchUndo;

// ============================================================================
// Patch SignaturePad instance (สี + hook undo history)
// ============================================================================
function _patchPad(canvasId) {
    var pad = window.signaturePads[canvasId];
    if (!pad) return;

    _installPalmGuard(canvasId);

    if (!pad._sketchColorPatched) {
        pad._sketchColorPatched = true;
        var origFn = pad._getPointGroupOptions.bind(pad);
        pad._getPointGroupOptions = function () {
            var opts = origFn();
            if (window._sketchEraser[canvasId]) {
                opts.penColor = 'rgba(0,0,0,1)';
                opts.compositeOperation = 'destination-out';
            } else {
                if (window._sketchColor[canvasId]) {
                    opts.penColor = window._sketchColor[canvasId];
                }
                opts.compositeOperation = 'source-over';
            }
            return opts;
        };
    }

    // hook "ก่อนเริ่มลากเส้น" เพื่อเก็บ snapshot ไว้ให้ undo
    if (!_sketchHooked[canvasId]) {
        _sketchHooked[canvasId] = true;
        var snap = function () { _sketchSnapshot(canvasId); };

        if (typeof pad.addEventListener === 'function') {
            // SignaturePad v4
            pad.addEventListener('beginStroke', snap);
        } else {
            // SignaturePad v2/v3
            var prevOnBegin = pad.onBegin;
            pad.onBegin = function () {
                snap();
                if (typeof prevOnBegin === 'function') prevOnBegin.apply(this, arguments);
            };
        }
    }
}

// สแกนหา pad ที่ยังไม่ถูก hook (เผื่อ pad ถูกสร้างหลังไฟล์นี้โหลด)
setInterval(function () {
    for (var id in window.signaturePads) {
        if (!_sketchHooked[id] && window.signaturePads[id]) _patchPad(id);
    }
}, 800);

function sketchSetColor(canvasId, color) {
    window._sketchColor[canvasId] = color;
    // เปลี่ยนสีแล้วปิด eraser อัตโนมัติ
    window._sketchEraser[canvasId] = false;
    _updateEraserBtn(canvasId, false);
    var pad = window.signaturePads[canvasId];
    if (pad) { _patchPad(canvasId); }
}

// ★ เปลี่ยนขนาดปากกา
function sketchSetPenSize(canvasId, size) {
    window._sketchPenSize[canvasId] = parseFloat(size) || 2;
    var pad = window.signaturePads[canvasId];
    if (pad) {
        pad.minWidth = window._sketchPenSize[canvasId] * 0.5;
        pad.maxWidth = window._sketchPenSize[canvasId];
    }
}

// ★ Toggle ยางลบ
function sketchToggleEraser(canvasId) {
    window._sketchEraser[canvasId] = !window._sketchEraser[canvasId];
    var isEraser = window._sketchEraser[canvasId];
    _updateEraserBtn(canvasId, isEraser);

    var pad = window.signaturePads[canvasId];
    if (pad) {
        _patchPad(canvasId);
        if (isEraser) {
            pad.penColor = 'rgba(0,0,0,1)';
            pad.compositeOperation = 'destination-out';
            pad.minWidth = 10;
            pad.maxWidth = 20;
        } else {
            var c = window._sketchColor[canvasId] || window._sketchOrigColor[canvasId] || '#000';
            pad.penColor = c;
            pad.compositeOperation = 'source-over';
            var sz = window._sketchPenSize[canvasId] || 2;
            pad.minWidth = sz * 0.5;
            pad.maxWidth = sz;
        }
    }
}

function _updateEraserBtn(canvasId, active) {
    // Try both ID conventions: suffix '_eraser_btn' and replace '_canvas' → '_eraser_btn'
    var btn = document.getElementById(canvasId + '_eraser_btn')
           || document.getElementById(canvasId.replace('_canvas', '_eraser_btn'));
    if (btn) {
        if (active) {
            btn.style.backgroundColor = '#fbbf24';
            btn.style.color = '#000';
        } else {
            btn.style.backgroundColor = '';
            btn.style.color = '';
        }
    }
}

function buildSketchToolbar(canvasId, style) {
    var defColor = window._sketchOrigColor[canvasId] || '#000000';
    if (style === 'pdf') {
        return '<span style="display:inline-flex;gap:4px;align-items:center;margin-right:6px;">' +
            '<button type="button" onclick="sketchUndo(\''+canvasId+'\')" style="padding:2px 8px;font-size:11px;border:1px solid #ccc;border-radius:4px;cursor:pointer;background:#fff;" title="ย้อนกลับ"><i class="fas fa-undo"></i> ย้อนกลับ</button>' +
            '<label style="margin:0;cursor:pointer;" title="เลือกสี"><input type="color" value="'+defColor+'" onchange="sketchSetColor(\''+canvasId+'\',this.value)" style="width:26px;height:22px;border:1px solid #aaa;border-radius:3px;padding:0;cursor:pointer;vertical-align:middle;"></label>' +
            '</span>';
    }
    return '<button type="button" class="btn btn-sm btn-outline-warning px-3" onclick="sketchUndo(\''+canvasId+'\')">' +
        '<i class="fas fa-undo me-1"></i> ย้อนกลับ</button>' +
        '<label class="btn btn-sm btn-outline-primary px-2 mb-0" title="เลือกสี" style="cursor:pointer;">' +
        '<i class="fas fa-palette me-1"></i>' +
        '<input type="color" value="'+defColor+'" onchange="sketchSetColor(\''+canvasId+'\',this.value)" style="width:0;height:0;padding:0;border:0;visibility:hidden;position:absolute;">' +
        '</label>';
}

console.log('[sketch-tools.js v8] loaded OK - Snapshot Undo + Palm rejection + Color + PenSize + Eraser');
