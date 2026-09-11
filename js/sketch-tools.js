// ============================================================================
// sketch-tools.js v9 — Undo + Palm rejection + Color + Pen Size + Eraser
//
// v9 แก้แผนผังฟอร์ม PDF (ระเบิด/เพลิงไหม้/ชีวิต/ทรัพย์):
//  1) ปุ่ม "ย้อนกลับ" กดแล้วไม่มีผล
//     ฟอร์ม PDF วาดด้วย canvas เอง ไม่ใช้ SignaturePad แต่ undo ไปอ่าน pad
//     หรืออ่านอาเรย์เส้นที่ยังว่าง เพราะเส้นฝ่ามือยังลากไม่จบ
//     v9 รวม undo ทุกโหมด: ยกเลิกเส้นที่กำลังลาก + ย้อนเส้นล่าสุด + snapshot ของ SignaturePad
//  2) วางฝ่ามือบนจอแล้วเกิดเส้นลากอัตโนมัติ
//     รับเฉพาะ pointer หลัก, ตัดสัมผัสที่สอง, ถ้ามีปากกาแล้วไม่รับนิ้ว,
//     และถ้าฝ่ามือมาก่อนแล้วปากกามาทีหลัง จะทิ้งเส้นฝ่ามือแล้วให้ปากกาต่อ
// ============================================================================
window.signaturePads = window.signaturePads || {};
window._sketchColor  = {};
window._sketchOrigColor = {};
window._sketchPenSize = {};
window._sketchEraser = {};
window._bpfSketchStrokes = window._bpfSketchStrokes || {};

var _sketchHistory   = {};
var _sketchHooked    = {};
var _sketchPenSeen   = {};
var _sketchPalmGuard = {};
var _sketchFreehand  = {};
var _sketchLive      = {};
var SKETCH_HISTORY_MAX = 25;
var PALM_CONTACT_PX = 40;
var PALM_TOUCH_DELAY_MS = 45;

function _isPalmContact(e) {
    var w = e.width || 0;
    var h = e.height || 0;
    if (w > PALM_CONTACT_PX || h > PALM_CONTACT_PX) return true;
    var rx = e.radiusX || 0;
    var ry = e.radiusY || 0;
    return (rx * 2) > PALM_CONTACT_PX || (ry * 2) > PALM_CONTACT_PX;
}

function _pointerPos(canvas, e) {
    var rect = canvas.getBoundingClientRect();
    if (!rect.width || !rect.height) return { x: 0, y: 0 };
    return {
        x: (e.clientX - rect.left) * (canvas.width / rect.width),
        y: (e.clientY - rect.top) * (canvas.height / rect.height)
    };
}

function _applyStrokeStyle(ctx, canvasId, isEraser) {
    var w = window._sketchPenSize[canvasId] || 2;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.globalCompositeOperation = isEraser ? 'destination-out' : 'source-over';
    if (!isEraser) ctx.strokeStyle = window._sketchColor[canvasId] || '#000';
    ctx.lineWidth = isEraser ? Math.max(20, w * 4) : w;
}

function _drawStroke(ctx, s) {
    if (!s || !s.points || !s.points.length) return;
    ctx.beginPath();
    ctx.moveTo(s.points[0].x, s.points[0].y);
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.lineWidth = s.width;
    ctx.globalCompositeOperation = s.eraser ? 'destination-out' : 'source-over';
    if (!s.eraser) ctx.strokeStyle = s.color;
    if (s.points.length === 1) {
        ctx.arc(s.points[0].x, s.points[0].y, Math.max(0.5, s.width / 2), 0, Math.PI * 2);
        ctx.fillStyle = s.eraser ? 'rgba(0,0,0,1)' : s.color;
        ctx.fill();
        return;
    }
    for (var i = 1; i < s.points.length; i++) ctx.lineTo(s.points[i].x, s.points[i].y);
    ctx.stroke();
}

function _redrawFreehand(canvasId) {
    var canvas = document.getElementById(canvasId);
    if (!canvas) return;
    var ctx = canvas.getContext('2d');
    ctx.save();
    ctx.globalCompositeOperation = 'source-over';
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    var strokes = window._bpfSketchStrokes[canvasId] || [];
    strokes.forEach(function (s) { _drawStroke(ctx, s); });
    ctx.restore();
    ctx.globalCompositeOperation = 'source-over';
}

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
        console.warn('[sketch-tools] snapshot failed for ' + canvasId, e);
    }
}

function sketchResetHistory(canvasId) {
    _sketchHistory[canvasId] = [];
    if (window._bpfSketchStrokes) window._bpfSketchStrokes[canvasId] = [];
    var live = _sketchLive[canvasId];
    if (live && live.timer) clearTimeout(live.timer);
    _sketchLive[canvasId] = null;
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

function _cancelLiveFreehand(canvasId) {
    var live = _sketchLive[canvasId];
    if (!live) return false;
    if (live.timer) clearTimeout(live.timer);
    _sketchLive[canvasId] = null;
    if (live.snapshot) {
        _restoreSnapshot(canvasId, live.snapshot);
        return true;
    }
    _redrawFreehand(canvasId);
    return true;
}

// ============================================================================
// Freehand engine (PDF sketch pages — ไม่ใช้ SignaturePad)
// ============================================================================
function initFreehandCanvas(canvasId) {
    var canvas = document.getElementById(canvasId);
    if (!canvas || canvas.dataset.freehandInit === '1') return;
    canvas.dataset.freehandInit = '1';
    canvas.style.touchAction = 'none';
    canvas.style.msTouchAction = 'none';

    var ctx = canvas.getContext('2d');
    window._sketchColor[canvasId] = window._sketchColor[canvasId] || '#000';
    window._sketchOrigColor[canvasId] = window._sketchOrigColor[canvasId] || window._sketchColor[canvasId];
    window._sketchPenSize[canvasId] = window._sketchPenSize[canvasId] || 2;
    window._sketchEraser[canvasId] = !!window._sketchEraser[canvasId];
    if (!window._bpfSketchStrokes[canvasId]) window._bpfSketchStrokes[canvasId] = [];

    _sketchFreehand[canvasId] = true;

    function beginStroke(e) {
        var p = _pointerPos(canvas, e);
        var isEraser = !!window._sketchEraser[canvasId];
        var color = window._sketchColor[canvasId] || '#000';
        var w = window._sketchPenSize[canvasId] || 2;
        var snapshot = null;
        try { snapshot = canvas.toDataURL('image/png'); } catch (err) { snapshot = null; }
        _sketchLive[canvasId] = {
            pointerId: e.pointerId,
            type: e.pointerType,
            snapshot: snapshot,
            stroke: { eraser: isEraser, color: color, width: isEraser ? Math.max(20, w * 4) : w, points: [p] }
        };
        try { canvas.setPointerCapture(e.pointerId); } catch (err) { /* ignore */ }
        _applyStrokeStyle(ctx, canvasId, isEraser);
        ctx.beginPath();
        ctx.moveTo(p.x, p.y);
    }

    function moveStroke(e) {
        var live = _sketchLive[canvasId];
        if (!live || live.pointerId !== e.pointerId || !live.stroke) return;
        var p = _pointerPos(canvas, e);
        live.stroke.points.push(p);
        ctx.lineTo(p.x, p.y);
        ctx.stroke();
        ctx.beginPath();
        ctx.moveTo(p.x, p.y);
    }

    function endStroke(e) {
        var live = _sketchLive[canvasId];
        if (!live || (e && live.pointerId !== e.pointerId)) return;
        if (live.timer) clearTimeout(live.timer);
        ctx.globalCompositeOperation = 'source-over';
        if (live.stroke && live.stroke.points.length > 0) {
            window._bpfSketchStrokes[canvasId].push(live.stroke);
            if (live.snapshot) {
                if (!_sketchHistory[canvasId]) _sketchHistory[canvasId] = [];
                _sketchHistory[canvasId].push(live.snapshot);
                if (_sketchHistory[canvasId].length > SKETCH_HISTORY_MAX) {
                    _sketchHistory[canvasId].shift();
                }
            }
        }
        _sketchLive[canvasId] = null;
    }

    canvas.addEventListener('pointerdown', function (e) {
        if (e.button != null && e.button !== 0) return;
        e.preventDefault();
        e.stopPropagation();

        if (e.pointerType === 'pen') _sketchPenSeen[canvasId] = true;
        try { canvas.setPointerCapture(e.pointerId); } catch (err) { /* ignore */ }

        var live = _sketchLive[canvasId];
        if (live) {
            if (e.pointerType === 'pen' && live.type === 'touch') {
                _cancelLiveFreehand(canvasId);
            } else {
                return;
            }
        }

        if (e.pointerType === 'touch' && _sketchPenSeen[canvasId]) return;
        if (e.pointerType === 'touch' && _isPalmContact(e)) return;

        if (e.pointerType === 'touch') {
            var pending = {
                pointerId: e.pointerId,
                type: e.pointerType,
                timer: null,
                stroke: null,
                snapshot: null
            };
            _sketchLive[canvasId] = pending;
            pending.timer = setTimeout(function () {
                if (_sketchLive[canvasId] !== pending) return;
                if (_isPalmContact(e)) {
                    _sketchLive[canvasId] = null;
                    return;
                }
                beginStroke(e);
            }, PALM_TOUCH_DELAY_MS);
            return;
        }

        beginStroke(e);
    });

    canvas.addEventListener('pointermove', function (e) {
        var live = _sketchLive[canvasId];
        if (!live || live.pointerId !== e.pointerId) return;
        e.preventDefault();
        if (!live.stroke) return;
        moveStroke(e);
    });

    function onPointerUp(e) {
        var live = _sketchLive[canvasId];
        if (!live || live.pointerId !== e.pointerId) return;
        e.preventDefault();
        if (!live.stroke) {
            if (live.timer) clearTimeout(live.timer);
            _sketchLive[canvasId] = null;
            return;
        }
        endStroke(e);
    }

    canvas.addEventListener('pointerup', onPointerUp);
    canvas.addEventListener('pointercancel', function (e) {
        var live = _sketchLive[canvasId];
        if (!live || live.pointerId !== e.pointerId) return;
        _cancelLiveFreehand(canvasId);
    });
}
window.initFreehandCanvas = initFreehandCanvas;

function _canvasHasPixels(canvas) {
    if (!canvas || !canvas.width || !canvas.height) return false;
    try {
        var px = canvas.getContext('2d').getImageData(0, 0, canvas.width, canvas.height).data;
        for (var i = 3; i < px.length; i += 4) {
            if (px[i] > 0) return true;
        }
    } catch (e) { /* tainted */ }
    return false;
}

function sketchHasInk(canvasId) {
    var live = _sketchLive[canvasId];
    if (live && live.stroke && live.stroke.points && live.stroke.points.length) return true;
    var strokes = window._bpfSketchStrokes && window._bpfSketchStrokes[canvasId];
    if (strokes && strokes.length > 0) return true;
    return _canvasHasPixels(document.getElementById(canvasId));
}

function exportSketchDataUrl(canvasId, bgImage) {
    var src = document.getElementById(canvasId);
    var w = (src && src.width) ? src.width : 1120;
    var h = (src && src.height) ? src.height : 660;
    var out = document.createElement('canvas');
    out.width = w;
    out.height = h;
    var ctx = out.getContext('2d');
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, w, h);
    if (bgImage) {
        try { ctx.drawImage(bgImage, 0, 0, w, h); } catch (e) { /* ignore */ }
    }
    if (_canvasHasPixels(src)) {
        try { ctx.drawImage(src, 0, 0); } catch (e) { /* ignore */ }
    } else {
        var strokes = (window._bpfSketchStrokes && window._bpfSketchStrokes[canvasId]) || [];
        strokes.forEach(function (s) { _drawStroke(ctx, s); });
    }
    var live = _sketchLive[canvasId];
    if (live && live.stroke) _drawStroke(ctx, live.stroke);
    try { return out.toDataURL('image/png'); } catch (e) { return ''; }
}
window.sketchHasInk = sketchHasInk;
window.exportSketchDataUrl = exportSketchDataUrl;

// ============================================================================
// Undo
// ============================================================================
function sketchUndo(canvasId) {
    if (_cancelLiveFreehand(canvasId)) return;

    var strokes = window._bpfSketchStrokes && window._bpfSketchStrokes[canvasId];
    if (strokes && strokes.length > 0) {
        strokes.pop();
        if (_sketchHistory[canvasId] && _sketchHistory[canvasId].length > 0) {
            _sketchHistory[canvasId].pop();
        }
        _redrawFreehand(canvasId);
        return;
    }

    var hist = _sketchHistory[canvasId] || [];
    if (hist.length > 0) {
        var prev = hist.pop();
        var pad = window.signaturePads[canvasId];
        _restoreSnapshot(canvasId, prev, function () {
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

    var pad = window.signaturePads[canvasId];
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
// Palm rejection for SignaturePad canvases
// ============================================================================
function _installPalmGuard(canvasId) {
    if (_sketchPalmGuard[canvasId]) return;
    var canvas = document.getElementById(canvasId);
    if (!canvas) return;
    _sketchPalmGuard[canvasId] = true;
    canvas.style.touchAction = 'none';

    var activeId = null;
    var activeType = null;

    function block(e) {
        e.stopImmediatePropagation();
        e.preventDefault();
    }

    function restoreLastSnapshot() {
        var hist = _sketchHistory[canvasId] || [];
        if (!hist.length) return;
        var prev = hist.pop();
        _restoreSnapshot(canvasId, prev);
        var pad = window.signaturePads[canvasId];
        if (pad) {
            try {
                var d = pad.toData();
                if (d && d.length) { d.pop(); pad._data = d; }
            } catch (err) { /* ignore */ }
        }
    }

    canvas.addEventListener('pointerdown', function (e) {
        if (e.pointerType === 'pen' || e.pointerType === 'mouse') {
            if (e.pointerType === 'pen') _sketchPenSeen[canvasId] = true;
            if (activeId != null && activeId !== e.pointerId && e.pointerType === 'pen' && activeType === 'touch') {
                restoreLastSnapshot();
                activeId = e.pointerId;
                activeType = 'pen';
                return;
            }
            if (activeId != null && activeId !== e.pointerId) { block(e); return; }
            activeId = e.pointerId;
            activeType = e.pointerType;
            return;
        }
        if (_sketchPenSeen[canvasId]) { block(e); return; }
        if (activeId != null && activeId !== e.pointerId) { block(e); return; }
        if (_isPalmContact(e)) { block(e); return; }
        activeId = e.pointerId;
        activeType = 'touch';
    }, true);

    canvas.addEventListener('pointerup', function (e) {
        if (e.pointerId === activeId) { activeId = null; activeType = null; }
    }, true);
    canvas.addEventListener('pointercancel', function (e) {
        if (e.pointerId === activeId) { activeId = null; activeType = null; }
    }, true);

    canvas.addEventListener('touchstart', function (e) {
        var n = e.touches ? e.touches.length : 1;
        if (_sketchPenSeen[canvasId] || n > 1) { block(e); return; }
        var t = e.touches && e.touches[0];
        if (t && (((t.radiusX || 0) * 2) > PALM_CONTACT_PX || ((t.radiusY || 0) * 2) > PALM_CONTACT_PX)) {
            block(e);
        }
    }, true);

    canvas.addEventListener('touchmove', function (e) {
        var n = e.touches ? e.touches.length : 1;
        if (n > 1 || _sketchPenSeen[canvasId]) block(e);
    }, true);
}

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

    if (!_sketchHooked[canvasId]) {
        _sketchHooked[canvasId] = true;
        var snap = function () { _sketchSnapshot(canvasId); };
        if (typeof pad.addEventListener === 'function') {
            pad.addEventListener('beginStroke', snap);
        } else {
            var prevOnBegin = pad.onBegin;
            pad.onBegin = function () {
                snap();
                if (typeof prevOnBegin === 'function') prevOnBegin.apply(this, arguments);
            };
        }
    }
}

setInterval(function () {
    for (var id in window.signaturePads) {
        if (!_sketchHooked[id] && window.signaturePads[id]) _patchPad(id);
    }
}, 800);

function sketchSetColor(canvasId, color) {
    window._sketchColor[canvasId] = color;
    window._sketchEraser[canvasId] = false;
    _updateEraserBtn(canvasId, false);
    var pad = window.signaturePads[canvasId];
    if (pad) { _patchPad(canvasId); }
}

function sketchSetPenSize(canvasId, size) {
    window._sketchPenSize[canvasId] = parseFloat(size) || 2;
    var pad = window.signaturePads[canvasId];
    if (pad) {
        pad.minWidth = window._sketchPenSize[canvasId] * 0.5;
        pad.maxWidth = window._sketchPenSize[canvasId];
    }
}

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

console.log('[sketch-tools.js v9] loaded OK - Freehand Undo + Palm rejection + Color + PenSize + Eraser');
