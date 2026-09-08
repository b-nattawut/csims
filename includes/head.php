<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="css/bootstrap.min.css">
<link rel="stylesheet" href="css/font-awesome.all.min.css">

<link rel="stylesheet" href="css/select2.min.css">
<link rel="stylesheet" href="css/select2-bootstrap-5-theme.min.css">
<link rel="stylesheet" href="css/style.css?v=<?php echo filemtime('css/style.css'); ?>">

<style>
    .clickable-row:hover {
        background-color: rgba(0, 123, 255, 0.05) !important;
        transition: background-color 0.2s ease;
    }

    .cursor-pointer {
        cursor: pointer;
    }

    .btn-hw-open {
        transition: all 0.2s;
    }

    .btn-hw-open:hover {
        transform: scale(1.1);
    }

    #hwModal {
        /* ทำให้พื้นหลังของ Modal นี้มืดลงกว่าปกติเล็กน้อย เพื่อขับให้ตัวมันเด่นขึ้น */
        background-color: rgba(0, 0, 0, 0.2);
        /* เพิ่มเอฟเฟกต์กระจกฝ้าที่พื้นหลัง */
        backdrop-filter: blur(2px);
        -webkit-backdrop-filter: blur(2px);
    }

    /* 2. ตัวกล่อง Modal Content */
    #hwModal .modal-content {
        /* เพิ่มเงาที่ "ลึก" และ "กว้าง" ขึ้น (Elevation) */
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4) !important;

        /* เพิ่มเส้นขอบบางๆ สีขาว/เทา เพื่อให้ตัดกับพื้นหลัง */
        border: 1px solid rgba(255, 255, 255, 0.2) !important;

        /* ปรับความมนให้ดูทันสมัย */
        border-radius: 20px !important;
    }

    /* 3. ปรับ Header ให้ดูพรีเมียมและแยกส่วนชัดเจน */
    #hwModal .modal-header {
        background: linear-gradient(135deg, #4f46e5, #7c3aed) !important;
        /* ปรับสีให้เข้มขึ้นนิดนึง */
        border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        padding: 1.2rem 1.5rem;
    }

    /* 4. เพิ่มเส้นขอบเงา (Inner Shadow) ให้กับพื้นที่เขียน */
    #hwCanvasArea {
        box-shadow: inset 0 2px 4px 0 rgba(0, 0, 0, 0.05);
        border: 2px solid #e5e7eb !important;
    }

    /* 5. ปรับ Footer ให้มีเส้นกั้นบางๆ */
    #hwModal .modal-footer {
        background-color: #f9fafb;
        border-top: 1px solid #f3f4f6 !important;
        padding: 1rem 1.5rem;
    }

    #hwCanvasArea {
        position: relative;
        border: 2px solid #d1d5db;
        border-radius: 12px;
        background: #fff;
        overflow: hidden;
    }

    #hwCanvas {
        display: block;
        cursor: crosshair;
        touch-action: none;
    }

    .hw-canvas-placeholder {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        color: #d1d5db;
        font-size: 1.1rem;
        pointer-events: none;
        transition: opacity .3s;
    }

    .hw-canvas-placeholder.hidden {
        opacity: 0;
    }

    /* --- ปรับแต่ง Badge ผลลัพธ์ลายมือ --- */
    .hw-result-badge {
        display: inline-block;
        padding: 8px 20px;
        margin: 5px;
        border-radius: 50px;
        /* เพิ่มความมนให้ชัดเจน */
        background: #fff;
        border: 2px solid #dee2e6;
        /* เพิ่มความหนาเป็น 2px ให้ดูชัดขึ้น */
        cursor: pointer;
        font-size: 1rem;
        color: #4b5563;
        transition: all 0.2s ease-in-out;
        /* ปรับ Transition ให้สมูท */
        /* ป้องกันการเด้งด้วยการล็อกขนาดตัวอักษรและ Line-height */
        line-height: 1;
        vertical-align: middle;
    }

    .hw-result-badge:hover {
        background: #0d6efd;
        color: #fff;
        border-color: #0d6efd;
        transform: translateY(-2px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    .hw-result-badge.selected {
        background: #0d6efd;
        color: #fff;
        border-color: #0a58ca;
        box-shadow: inset 0 2px 4px 0 rgba(0, 0, 0, 0.1);
    }

    .hw-pen-size-group .btn.active {
        background: #6366f1 !important;
        border-color: #6366f1 !important;
        color: #fff !important;
    }

    .input-group-seamless {
        border: 1px solid #ced4da;
        border-radius: 8px;
        /* ปรับความมนตามใจชอบ */
        overflow: hidden;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        background-color: #fff;
        display: flex;
        align-items: stretch;
    }

    /* เมื่อ Focus หรือ Active (เปิด Modal) ให้แสดงสีน้ำเงินเหมือน Input ปกติ */
    .input-group-seamless:focus-within,
    .input-group-seamless.hw-active {
        border-color: #86b7fe !important;
        outline: 0;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25) !important;
    }

    .input-group-seamless .form-control {
        border: none !important;
        /* เอาขอบในออก */
        box-shadow: none !important;
        /* เอาเงาเดิมออก */
    }

    .input-group-seamless .btn-hw-open {
        border: none !important;
        /* เอาขอบปุ่มออก */
        background-color: transparent !important;
        color: #0d6efd;
        padding-right: 12px;
    }

    /* style สำหรับ input group text ที่เป็น child โดยตรงของ input-group-seamless เท่านั้น */
    .input-group-seamless>.input-group-text {
        /* 1. ล้างค่าพื้นฐาน เอา default ของ Bootstrap ออก */
        border: none !important;
        border-radius: 0 !important;
        box-shadow: none !important;

        /* 2. กำหนด Style ใหม่ */
        background-color: #f8f9fa;
        color: #495057;
        font-weight: 400;
        padding-left: 15px;
        padding-right: 15px;
        /* ใส่เฉพาะเส้นแบ่งด้านขวา เพื่อแยกเลขออกจากช่องกรอก */
        border-right: 2px solid #ced4da !important;
    }

    /* --- จัดการสีขอบตอน Validation --- */

    /* 1. กรณี Invalid (แดง) */
    .input-group-seamless:has(.is-invalid),
    .was-validated .input-group-seamless:has(:invalid) {
        border-color: #dc3545 !important;
        /* สีแดง */
    }

    /* เมื่อ Focus หรือเปิด Modal ในฟิลด์ที่ Invalid (แดง) */
    .input-group-seamless:has(.is-invalid):focus-within,
    .was-validated .input-group-seamless:has(:invalid):focus-within,
    .input-group-seamless.hw-active:has(.is-invalid),
    .was-validated .input-group-seamless.hw-active:has(:invalid) {
        box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25) !important;
    }

    /* 2. กรณีถูก (Valid) */
    .input-group-seamless:has(.is-valid),
    .was-validated .input-group-seamless:has(:valid) {
        border-color: #198754 !important;
        /* สีเขียว */
    }

    /* เมื่อ Focus หรือเปิด Modal ในฟิลด์ที่ Valid (เขียว) */
    .input-group-seamless:has(.is-valid):focus-within,
    .was-validated .input-group-seamless:has(:valid):focus-within,
    .input-group-seamless.hw-active:has(.is-valid),
    .was-validated .input-group-seamless.hw-active:has(:valid) {
        box-shadow: 0 0 0 0.25rem rgba(25, 135, 84, 0.25) !important;
    }

    /* 3. จัดการข้อความ Invalid Feedback ที่อยู่นอกกลุ่ม */
    /* ปกติ Bootstrap จะ hide มันไว้ เราต้องบังคับให้โชว์เมื่อตัวข้างหน้ามัน (seamless) มีความผิดปกติ */
    .input-group-seamless+.invalid-feedback,
    .input-group-seamless~.invalid-feedback {
        display: none;
        /* ปิดไว้ก่อน */
    }

    /* แสดงข้อความสีแดงเมื่อตรวจพบ .is-invalid หรือ :invalid ภายในกลุ่ม seamless */
    .input-group-seamless:has(.is-invalid)+.invalid-feedback,
    .was-validated .input-group-seamless:has(:invalid)+.invalid-feedback {
        display: block !important;
    }

    /* --- ปิด Icon Validation เฉพาะช่อง Remark ในหน้า Maintenance Plan --- */
    .table-maintenance-plan .input-group-seamless:has(input[name*="[remark]"]) .form-control.is-valid,
    .was-validated .table-maintenance-plan .input-group-seamless:has(input[name*="[remark]"]) .form-control:valid {
        background-image: none !important;
        padding-right: 0.75rem !important;
    }
</style>