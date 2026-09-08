<!-- Modal: การตรวจร่างรายงาน (สไตล์เดียวกับ checklist / รับแจ้งเหตุ) -->
<div class="modal fade" id="modalReportReview" tabindex="-1" aria-labelledby="modalReportReviewLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="modalReportReviewLabel">
                    <i class="fas fa-user-check me-2"></i> การตรวจร่างรายงาน
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body bg-light p-4">
                <input type="hidden" id="rr_incident_id" value="">
                <input type="hidden" id="rr_active_seq" value="">

                <div id="rr_creator_hint" class="alert alert-warning py-2 px-3 small d-none mb-3">
                    <i class="fas fa-info-circle me-1"></i>
                    เฉพาะคนสร้างร่างเท่านั้นที่เลือกผู้ตรวจและอัปโหลดไฟล์ได้
                </div>

                <!-- 1. ข้อมูลรายงาน -->
                <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                    <legend class="fieldset-header">1. ข้อมูลรายงาน</legend>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small text-muted">เลขที่เอกสาร</label>
                            <input type="text" class="form-control" id="rr_doc_no" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted">เลขที่รายงาน</label>
                            <input type="text" class="form-control" id="rr_report_no" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted">สถานะปัจจุบัน</label>
                            <div class="pt-1">
                                <span class="badge bg-secondary" id="rr_seq_label">รอดำเนินการ</span>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small text-muted">อัปโหลดไฟล์รายงาน</label>
                            <input type="file" class="form-control" id="rr_file_input" accept=".pdf,.doc,.docx,application/pdf">
                        </div>
                    </div>
                </fieldset>

                <!-- 2. กำหนดผู้ตรวจ -->
                <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                    <legend class="fieldset-header">2. กำหนดผู้ตรวจร่างรายงาน</legend>
                    <p class="text-muted small mb-3" id="rr_assign_help">เลือกผู้ตรวจทั้ง 3 คน แล้วกดบันทึก — เซ็นได้ทีละขั้นตามลำดับ</p>
                    <div class="row g-3">
                        <div class="col-md-4" id="rr_step_1" data-seq="1">
                            <label class="form-label small text-muted">
                                ผู้ตรวจร่างรายงาน 1
                                <span class="badge bg-secondary ms-1" id="rr_badge_1">ยังไม่เลือก</span>
                            </label>
                            <select class="form-select rr-user-select" id="rr_reviewer1"></select>
                            <div class="mt-2 d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-outline-primary btn-sm rr-open-sign" data-seq="1">
                                    <i class="fas fa-pen me-1"></i>เซ็นตรวจ
                                </button>
                            </div>
                        </div>
                        <div class="col-md-4" id="rr_step_2" data-seq="2">
                            <label class="form-label small text-muted">
                                ผู้ตรวจร่างรายงาน 2
                                <span class="badge bg-secondary ms-1" id="rr_badge_2">ยังไม่เลือก</span>
                            </label>
                            <select class="form-select rr-user-select" id="rr_reviewer2"></select>
                            <div class="mt-2 d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-outline-primary btn-sm rr-open-sign" data-seq="2">
                                    <i class="fas fa-pen me-1"></i>เซ็นตรวจ
                                </button>
                                <button type="button" class="btn btn-warning btn-sm text-dark rr-rollback-btn d-none" data-seq="2">
                                    <i class="fas fa-undo me-1"></i>ย้อนกลับ
                                </button>
                            </div>
                        </div>
                        <div class="col-md-4" id="rr_step_3" data-seq="3">
                            <label class="form-label small text-muted">
                                ผู้อนุมัติรายงาน
                                <span class="badge bg-secondary ms-1" id="rr_badge_3">ยังไม่เลือก</span>
                            </label>
                            <select class="form-select rr-user-select" id="rr_approver"></select>
                            <div class="mt-2 d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-outline-primary btn-sm rr-open-sign" data-seq="3">
                                    <i class="fas fa-pen me-1"></i>เซ็นตรวจ
                                </button>
                                <button type="button" class="btn btn-warning btn-sm text-dark rr-rollback-btn d-none" data-seq="3">
                                    <i class="fas fa-undo me-1"></i>ย้อนกลับ
                                </button>
                            </div>
                        </div>
                    </div>
                </fieldset>

                <!-- 3. ยืนยันผลการตรวจสอบ -->
                <fieldset class="mb-2 bg-white p-4 rounded-3 shadow-sm border d-none" id="rr_sign_section">
                    <legend class="fieldset-header" id="rr_sign_title">3. ยืนยันผลการตรวจสอบ</legend>
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label small text-muted">หมายเหตุ</label>
                            <textarea class="form-control" id="rr_remark" rows="3" placeholder="ระบุหมายเหตุ (ถ้ามี)"></textarea>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small text-muted">ลายเซ็น</label>
                            <div class="border rounded bg-white" style="touch-action: none;">
                                <canvas id="rr_sig_canvas" width="1000" height="170" style="width:100%; height:170px;"></canvas>
                            </div>
                            <div class="mt-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="rr_btn_clear_sig">
                                    <i class="fas fa-eraser me-1"></i>ล้างลายเซ็น
                                </button>
                            </div>
                        </div>
                        <div class="col-md-12 d-none" id="rr_signed_preview">
                            <label class="form-label small text-muted">ลายเซ็นที่บันทึกแล้ว</label>
                            <div>
                                <img id="rr_signed_img" src="" alt="signature" class="img-fluid border rounded bg-white" style="max-height:110px;">
                            </div>
                        </div>
                        <div class="col-md-12 text-end">
                            <button type="button" class="btn btn-outline-secondary btn-sm me-2" id="rr_btn_close_sign">ปิดส่วนเซ็น</button>
                            <button type="button" class="btn btn-success btn-sm" id="rr_btn_confirm_sign">
                                <i class="fas fa-check me-1"></i>ยืนยันผลการตรวจสอบ
                            </button>
                        </div>
                    </div>
                </fieldset>
            </div>

            <div class="modal-footer justify-content-end">
                <button type="button" class="btn btn-success" id="rr_btn_save_assign">
                    <i class="fas fa-save me-2"></i> บันทึก
                </button>
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i> ยกเลิก
                </button>
            </div>
        </div>
    </div>
</div>
