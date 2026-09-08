<style>
    .fvl-photo-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 12px;
        min-height: 120px;
    }
    .fvl-photo-card {
        border: 1px solid #dee2e6;
        border-radius: 8px;
        overflow: hidden;
        background: #fff;
        transition: box-shadow .2s;
    }
    .fvl-photo-card:hover {
        box-shadow: 0 2px 8px rgba(0,0,0,.12);
    }
    .fvl-photo-img-wrap {
        position: relative;
        width: 100%;
        padding-top: 100%;
        overflow: hidden;
        background: #f8f9fa;
    }
    .fvl-photo-img-wrap img {
        position: absolute;
        top: 0; left: 0;
        width: 100%; height: 100%;
        object-fit: cover;
    }
    .fvl-photo-remove {
        position: absolute;
        top: 4px; right: 4px;
        width: 24px; height: 24px;
        border-radius: 50%;
        border: none;
        background: rgba(220, 53, 69, .85);
        color: #fff;
        font-size: 14px;
        line-height: 1;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background .2s;
    }
    .fvl-photo-remove:hover {
        background: #dc3545;
    }
    .fvl-photo-number {
        position: absolute;
        bottom: 4px; left: 4px;
        background: rgba(0,0,0,.55);
        color: #fff;
        font-size: 11px;
        padding: 1px 7px;
        border-radius: 10px;
    }
    .fvl-photo-card input[type="text"] {
        border-radius: 0 0 8px 8px;
        border: none;
        border-top: 1px solid #dee2e6;
        font-size: 12px;
        padding: 6px 8px;
    }
    .fvl-add-photo-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        min-height: 160px;
        border: 2px dashed #adb5bd;
        border-radius: 8px;
        background: #f8f9fa;
        color: #6c757d;
        cursor: pointer;
        transition: all .2s;
    }
    .fvl-add-photo-btn:hover {
        border-color: #0d6efd;
        color: #0d6efd;
        background: #e7f1ff;
    }
    .fvl-section-title {
        font-size: 13px;
        font-weight: 600;
        color: #495057;
        border-bottom: 2px solid #0d6efd;
        padding-bottom: 6px;
        margin-bottom: 12px;
    }
</style>

<!-- Modal: บันทึกภาคสนาม -->
<div class="modal fade" id="modalFieldVisit" tabindex="-1" aria-labelledby="modalFieldVisitLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">

            <!-- Header -->
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h6 class="modal-title mb-0" id="modalFieldVisitLabel">
                    <i class="fas fa-plus me-2"></i>เพิ่มรายการ
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- Body -->
            <div class="modal-body p-4">
                <form id="formFieldVisit" autocomplete="off">
                    <input type="hidden" id="fvl_id" name="fvl_id" value="">

                    <!-- ข้อมูลทั่วไป -->
                    <div class="fvl-section-title"><i class="fas fa-info-circle me-1"></i> ข้อมูลทั่วไป</div>

                    <div class="row g-3 mb-4">
                        <!-- แถวที่ 1: เลขที่รายงาน | วันที่ | ผู้บันทึก -->
                        <div class="col-md-4">
                            <label class="form-label mb-1" style="font-size:12px;">เลขที่รายงาน</label>
                            <input type="hidden" id="fvl_report_no" name="fvl_report_no" value="">
                            <input type="text" class="form-control form-control-sm" id="fvl_report_no_display" name="fvl_report_no_display" placeholder="เลขที่รายงาน">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label mb-1" style="font-size:12px;">วันที่บันทึก <span class="text-danger">*</span></label>
                            <input type="date" class="form-control form-control-sm" id="fvl_visit_date" name="fvl_visit_date" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label mb-1" style="font-size:12px;">ผู้บันทึก</label>
                            <input type="text" class="form-control form-control-sm bg-light" id="fvl_recorder_name" name="fvl_recorder_name" readonly>
                        </div>

                        <!-- แถวที่ 2: สภ./สน. | จังหวัด | เรื่อง/คดีเหตุ -->
                        <div class="col-md-4">
                            <label class="form-label mb-1" style="font-size:12px;">สภ./สน. <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="fvl_station" name="fvl_station" required>
                                <option value="" selected disabled>กรุณาเลือก</option>
                                <?php foreach ($policeStations as $ps): ?>
                                    <option value="<?= (int)$ps['id'] ?>" data-province="<?= (int)($ps['province_id'] ?? '') ?>"><?= htmlspecialchars($ps['station_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label mb-1" style="font-size:12px;">จังหวัด <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="fvl_province" name="fvl_province" required>
                                <option value="" selected disabled>กรุณาเลือก</option>
                                <option value="95">ยะลา</option>
                                <option value="94">ปัตตานี</option>
                                <option value="96">นราธิวาส</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label mb-1" style="font-size:12px;">เรื่อง / คดีเหตุ</label>
                            <select class="form-select form-select-sm" id="fvl_case_title" name="fvl_case_title">
                                <option value="" selected disabled>กรุณาเลือก</option>
                                <option value="01">ทรัพย์</option>
                                <option value="02">ชีวิต</option>
                                <option value="03">ระเบิด</option>
                                <option value="04">เพลิงไหม้</option>
                                <option value="05">จราจร</option>
                                <option value="06">ตรวจเก็บวัตถุพยาน(ลายนิ้วมือแฝง)</option>
                                <option value="07">ตรวจเก็บวัตถุพยานที่เกิดเหตุ</option>
                                <option value="08">ตรวจเก็บวัตถุพยานบุคคล</option>
                            </select>
                        </div>

                        <!-- แถวที่ 3: สถานที่ (เต็มแถว) -->
                        <div class="col-md-12">
                            <label class="form-label mb-1" style="font-size:12px;">สถานที่ <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="fvl_location" name="fvl_location" placeholder="ระบุสถานที่ไปตรวจ" required>
                        </div>

                        <!-- แถวที่ 4: รายละเอียด (เต็มแถว) -->
                        <div class="col-12">
                            <label class="form-label mb-1" style="font-size:12px;">รายละเอียด / บันทึก</label>
                            <textarea class="form-control form-control-sm" id="fvl_description" name="fvl_description" rows="3" placeholder="รายละเอียดเพิ่มเติม..."></textarea>
                        </div>
                    </div>

                    <!-- ไฟล์แนบ -->
                    <div class="fvl-section-title"><i class="fas fa-paperclip me-1"></i> ไฟล์แนบ</div>

                    <input type="file" id="fvl_file_input" multiple style="display:none;">

                    <div class="border rounded" id="fvl_file_preview" style="min-height:60px;">
                        <div class="text-center text-muted py-3"><i class="fas fa-paperclip fa-2x mb-2 d-block opacity-50"></i>ยังไม่มีไฟล์แนบ</div>
                    </div>

                    <div class="mt-3 text-center">
                        <button type="button" class="btn btn-outline-secondary btn-sm px-4" onclick="document.getElementById('fvl_file_input').click();">
                            <i class="fas fa-plus-circle me-1"></i> เพิ่มไฟล์แนบ
                        </button>
                    </div>

                    <!-- รูปภาพ -->
                    <div class="fvl-section-title mt-4"><i class="fas fa-camera me-1"></i> รูปภาพ</div>

                    <input type="file" id="fvl_photo_gallery" accept="image/*" multiple style="display:none;">
                    <input type="file" id="fvl_photo_camera" accept="image/*" capture="environment" multiple style="display:none;">

                    <div class="fvl-photo-grid" id="fvl_photo_preview">
                        <div class="text-center text-muted py-4 w-100"><i class="fas fa-image fa-2x mb-2 d-block opacity-50"></i>ยังไม่มีรูปภาพ</div>
                    </div>

                    <div class="mt-3 text-center">
                        <button type="button" class="btn btn-outline-primary btn-sm px-4" onclick="fvlChoosePhotoSource()">
                            <i class="fas fa-plus-circle me-1"></i> เพิ่มรูปภาพ
                        </button>
                    </div>

                </form>
            </div>

            <!-- Footer -->
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-success btn-sm" id="btn_save_field_visit">
                    <i class="fas fa-save me-1"></i> บันทึกข้อมูล
                </button>
                <button type="button" class="btn btn-danger btn-sm" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> ยกเลิก
                </button>
            </div>
        </div>
    </div>
</div>
