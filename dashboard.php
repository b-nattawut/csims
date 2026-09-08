<?php
// **********************************************
// ********** Dashboard Overview Test ***********
// **********************************************

require_once 'db_config.php';

// ============================================
// ดึงข้อมูลจริงจาก Database (Single Query)
// ============================================
$sqlTable = "SELECT 
    SUM(province = 'ยะลา' AND statusDelete = 0 AND complaints_type = '01') AS yala_wealth,
    SUM(province = 'ยะลา' AND statusDelete = 0 AND complaints_type = '02') AS yala_life,
    SUM(province = 'ยะลา' AND statusDelete = 0 AND complaints_type = '03') AS yala_bom,
    SUM(province = 'ยะลา' AND statusDelete = 0 AND complaints_type = '04') AS yala_fire,
    SUM(province = 'ยะลา' AND statusDelete = 0 AND complaints_type = '05') AS yala_traffic,
    SUM(province = 'ยะลา' AND statusDelete = 0 AND complaints_type = '06') AS yala_evidence,
    SUM(province = 'ยะลา' AND statusDelete = 0 AND complaints_type = '07') AS yala_crime_scene,
    SUM(province = 'ยะลา' AND statusDelete = 0 AND complaints_type = '08') AS yala_evidence_person,
    
    SUM(province = 'นราธิวาส' AND statusDelete = 0 AND complaints_type = '01') AS narateewat_wealth,
    SUM(province = 'นราธิวาส' AND statusDelete = 0 AND complaints_type = '02') AS narateewat_life,
    SUM(province = 'นราธิวาส' AND statusDelete = 0 AND complaints_type = '03') AS narateewat_bom,
    SUM(province = 'นราธิวาส' AND statusDelete = 0 AND complaints_type = '04') AS narateewat_fire,
    SUM(province = 'นราธิวาส' AND statusDelete = 0 AND complaints_type = '05') AS narateewat_traffic,
    SUM(province = 'นราธิวาส' AND statusDelete = 0 AND complaints_type = '06') AS narateewat_evidence,
    SUM(province = 'นราธิวาส' AND statusDelete = 0 AND complaints_type = '07') AS narateewat_crime_scene,
    SUM(province = 'นราธิวาส' AND statusDelete = 0 AND complaints_type = '08') AS narateewat_evidence_person,
    
    SUM(province = 'ปัตตานี' AND statusDelete = 0 AND complaints_type = '01') AS pattanee_wealth,
    SUM(province = 'ปัตตานี' AND statusDelete = 0 AND complaints_type = '02') AS pattanee_life,
    SUM(province = 'ปัตตานี' AND statusDelete = 0 AND complaints_type = '03') AS pattanee_bom,
    SUM(province = 'ปัตตานี' AND statusDelete = 0 AND complaints_type = '04') AS pattanee_fire,
    SUM(province = 'ปัตตานี' AND statusDelete = 0 AND complaints_type = '05') AS pattanee_traffic,
    SUM(province = 'ปัตตานี' AND statusDelete = 0 AND complaints_type = '06') AS pattanee_evidence,
    SUM(province = 'ปัตตานี' AND statusDelete = 0 AND complaints_type = '07') AS pattanee_crime_scene,
    SUM(province = 'ปัตตานี' AND statusDelete = 0 AND complaints_type = '08') AS pattanee_evidence_person
    FROM rn_ReceiveNoti";

$stmtTable = $pdo->prepare($sqlTable);
$stmtTable->execute();
$tableRaw = $stmtTable->fetch(PDO::FETCH_ASSOC);

// สถิติคดีแยกตามจังหวัดและประเภทคดี
$province_case_data = [
    [
        'province' => 'ยะลา',
        'LC' => (int)($tableRaw['yala_wealth'] ?? 0),
        'MD' => (int)($tableRaw['yala_life'] ?? 0),
        'BM' => (int)($tableRaw['yala_bom'] ?? 0),
        'FR' => (int)($tableRaw['yala_fire'] ?? 0),
        'TF' => (int)($tableRaw['yala_traffic'] ?? 0),
        'EV' => (int)($tableRaw['yala_evidence'] ?? 0),
        'CM' => (int)($tableRaw['yala_crime_scene'] ?? 0),
        'PS' => (int)($tableRaw['yala_evidence_person'] ?? 0)
    ],
    [
        'province' => 'ปัตตานี',
        'LC' => (int)($tableRaw['pattanee_wealth'] ?? 0),
        'MD' => (int)($tableRaw['pattanee_life'] ?? 0),
        'BM' => (int)($tableRaw['pattanee_bom'] ?? 0),
        'FR' => (int)($tableRaw['pattanee_fire'] ?? 0),
        'TF' => (int)($tableRaw['pattanee_traffic'] ?? 0),
        'EV' => (int)($tableRaw['pattanee_evidence'] ?? 0),
        'CM' => (int)($tableRaw['pattanee_crime_scene'] ?? 0),
        'PS' => (int)($tableRaw['pattanee_evidence_person'] ?? 0)
    ],
    [
        'province' => 'นราธิวาส',
        'LC' => (int)($tableRaw['narateewat_wealth'] ?? 0),
        'MD' => (int)($tableRaw['narateewat_life'] ?? 0),
        'BM' => (int)($tableRaw['narateewat_bom'] ?? 0),
        'FR' => (int)($tableRaw['narateewat_fire'] ?? 0),
        'TF' => (int)($tableRaw['narateewat_traffic'] ?? 0),
        'EV' => (int)($tableRaw['narateewat_evidence'] ?? 0),
        'CM' => (int)($tableRaw['narateewat_crime_scene'] ?? 0),
        'PS' => (int)($tableRaw['narateewat_evidence_person'] ?? 0)
    ]
];

// Pie chart data: คำนวณจาก province_case_data (ไม่ต้อง query เพิ่ม)
$pie_yala = 0; $pie_pattanee = 0; $pie_narateewat = 0;
foreach ($province_case_data as $p) {
    $sum = $p['LC'] + $p['MD'] + $p['BM'] + $p['FR'] + $p['TF'] + $p['EV'] + $p['CM'] + $p['PS'];
    if ($p['province'] === 'ยะลา') $pie_yala = $sum;
    elseif ($p['province'] === 'ปัตตานี') $pie_pattanee = $sum;
    elseif ($p['province'] === 'นราธิวาส') $pie_narateewat = $sum;
}

// สถิติตามประเภทคดี (8 ประเภท) สำหรับ Column Chart — คำนวณจาก province_case_data
$colKeys = ['LC', 'MD', 'BM', 'FR', 'TF', 'EV', 'CM', 'PS'];
$colLabels = ['คดีทรัพย์', 'คดีชีวิต', 'คดีระเบิด', 'คดีเพลิงไหม้', 'คดีจราจร', 'วัตถุพยาน(ลายนิ้วมือแฝง)', 'วัตถุพยานที่เกิดเหตุ', 'วัตถุพยานบุคคล'];
$case_types = [];
foreach ($colKeys as $idx => $key) {
    $case_types[] = ['type' => $colLabels[$idx], 'count' => array_sum(array_column($province_case_data, $key)), 'code' => $key];
}

ob_start();
?>

<style>
    .dashboard-panel {
        background: #fff;
        border: none;
        border-radius: 12px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        height: 100%;
        box-shadow: 0 2px 8px rgba(59, 130, 246, 0.15), 0 4px 20px rgba(0, 0, 0, 0.08);
    }
    
    .panel-header {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: #fff;
        padding: 14px 18px;
        font-weight: 600;
        font-size: 0.95rem;
        letter-spacing: 0.3px;
        text-align: center;
    }
    
    .panel-body {
        padding: 20px;
        background: #ffffff;
        flex: 1;
        display: flex;
        flex-direction: column;
    }
    
    .pie-chart-wrapper {
        width: 100%;
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    #pieChart {
        width: 100% !important;
        height: 100% !important;
    }
    
    .bar-chart-wrapper {
        width: 100%;
        height: 420px;
    }
    
    #barChart3D {
        width: 100% !important;
        height: 100% !important;
    }
    
    .legend-inline {
        display: flex;
        gap: 20px;
        justify-content: center;
        margin-top: 10px;
        font-size: 0.8rem;
    }
    
    .legend-dot {
        width: 12px;
        height: 12px;
        border-radius: 2px;
        display: inline-block;
    }
    
    .row-equal-height {
        display: flex;
        flex-wrap: wrap;
    }
    
    .row-equal-height > [class*='col-'] {
        display: flex;
        flex-direction: column;
    }
    
    .row-equal-height .dashboard-panel,
    .row-equal-height .table-wrapper {
        flex: 1;
    }
    
    .table-wrapper {
        background: #fff;
        border: none;
        border-radius: 12px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        height: 100%;
        box-shadow: 0 2px 8px rgba(59, 130, 246, 0.15), 0 4px 20px rgba(0, 0, 0, 0.08);
    }
    
    .table-header {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: #fff;
        padding: 14px 18px;
        font-weight: 600;
        font-size: 0.95rem;
        letter-spacing: 0.3px;
        text-align: center;
    }
    
    .province-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
        flex: 1;
        display: flex;
        flex-direction: column;
        border: 1px solid #fff;
    }
    
    .province-table thead,
    .province-table tbody,
    .province-table tfoot {
        display: contents;
    }
    
    .province-table tr {
        display: flex;
        flex: 1;
    }
    
    .province-table th,
    .province-table td {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 8px 4px;
        border: 1px solid #fff;
        text-align: center;
    }
    
    .province-table th:first-child,
    .province-table td:first-child {
        flex: 1;
        justify-content: center;
        padding-left: 8px;
    }
    
    .province-table th {
        background: #60a5fa;
        color: #fff;
        font-weight: 600;
        font-size: 0.8rem;
    }
    
    .province-table td:first-child {
        background: #e0f2fe;
        color: #000;
        font-weight: 700;
    }
    
    .province-table td {
        background: #e0f2fe;
        color: #000;
        font-weight: 700;
    }
    
    .province-table td.total-cell {
        background: #e0f2fe;
        font-weight: 700;
        color: #000;
    }
    
    .province-table tfoot td {
        background: #60a5fa !important;
        color: #fff !important;
        font-weight: 700;
    }
    
    .chart-tooltip {
        position: absolute;
        background: rgba(0, 0, 0, 0.8);
        color: #fff;
        padding: 8px 12px;
        border-radius: 6px;
        font-size: 0.85rem;
        pointer-events: none;
        opacity: 0;
        transition: opacity 0.2s ease;
        z-index: 100;
        white-space: nowrap;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }
    
    .chart-tooltip.show {
        opacity: 1;
    }
    
    .legend-inline span {
        display: flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        padding: 4px 8px;
        border-radius: 4px;
        transition: all 0.2s ease;
    }
    
    .legend-inline span:hover {
        background: #f3f4f6;
    }
    
    .legend-inline span.disabled {
        opacity: 0.4;
    }
    
    .legend-inline span.disabled .legend-dot {
        background: #ccc !important;
    }
</style>

<!-- Export Button -->
<div class="d-flex justify-content-end mb-3 gap-2">
    <a href="/csims/webfonts/User Manual/คู่มือ CSIMS.pdf" download class="btn" style="background-color:#7c4dff;color:#fff;">
        <i class="fas fa-download me-2"></i>คู่มือการใช้งาน
    </a>
    <button type="button" class="btn btn-success" id="btnExportDashboard" onclick="window.location.href='/csims/api/ReceiveNoti/exportDashboardExcel.php'">
        <i class="fas fa-file-excel me-2"></i>Export Excel
    </button>
</div>

<!-- Row 1: Pie + Summary Table -->
<div class="row row-equal-height g-3 mb-3">
    <!-- Pie Chart -->
    <div class="col-lg-5 col-md-6">
        <div class="dashboard-panel h-100">
            <div class="panel-header">
                <i class="fas fa-chart-pie me-2"></i>สัดส่วนคดีแยกตามจังหวัด
            </div>
            <div class="panel-body d-flex flex-column justify-content-center flex-grow-1" style="position:relative;">
                <div class="legend-inline mb-2" id="pieLegend">
                    <span data-index="0"><span class="legend-dot" style="background:#ec4899;"></span> ยะลา</span>
                    <span data-index="1"><span class="legend-dot" style="background:#f59e0b;"></span> ปัตตานี</span>
                    <span data-index="2"><span class="legend-dot" style="background:#10b981;"></span> นราธิวาส</span>
                </div>
                <div class="pie-chart-wrapper">
                    <canvas id="pieChart"></canvas>
                </div>
                <div class="chart-tooltip" id="pieTooltip"></div>
            </div>
        </div>
    </div>
    
    <!-- Summary Table -->
    <div class="col-lg-7 col-md-6">
        <div class="table-wrapper">
            <div class="table-header">
                <i class="fas fa-calendar-alt me-2"></i>สรุปคดีที่เกิดเหตุ (แยกประเภท)
            </div>
            <table class="province-table">
                <thead>
                    <tr>
                        <th>จังหวัด</th>
                        <th>คดีทรัพย์</th>
                        <th>คดีชีวิต</th>
                        <th>คดีระเบิด</th>
                        <th>เพลิงไหม้</th>
                        <th>จราจร</th>
                        <th>ตรวจเก็บวัตถุพยาน(ลายนิ้วมือแฝง)</th>
                        <th>ตรวจเก็บวัตถุพยานที่เกิดเหตุ</th>
                        <th>ตรวจเก็บวัตถุพยานบุคคล</th>
                        <th>รวม</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($province_case_data as $item): 
                    $row_total = $item['LC'] + $item['MD'] + $item['BM'] + $item['FR'] + $item['TF'] + $item['EV'] + $item['CM'] + $item['PS'];
                ?>
                <tr>
                    <td><?php echo $item['province']; ?></td>
                    <td><?php echo number_format($item['LC']); ?></td>
                    <td><?php echo number_format($item['MD']); ?></td>
                    <td><?php echo number_format($item['BM']); ?></td>
                    <td><?php echo number_format($item['FR']); ?></td>
                    <td><?php echo number_format($item['TF']); ?></td>
                    <td><?php echo number_format($item['EV']); ?></td>
                    <td><?php echo number_format($item['CM']); ?></td>
                    <td><?php echo number_format($item['PS']); ?></td>
                    <td class="total-cell"><?php echo number_format($row_total); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td>รวม</td>
                        <td><?php echo number_format(array_sum(array_column($province_case_data, 'LC'))); ?></td>
                        <td><?php echo number_format(array_sum(array_column($province_case_data, 'MD'))); ?></td>
                        <td><?php echo number_format(array_sum(array_column($province_case_data, 'BM'))); ?></td>
                        <td><?php echo number_format(array_sum(array_column($province_case_data, 'FR'))); ?></td>
                        <td><?php echo number_format(array_sum(array_column($province_case_data, 'TF'))); ?></td>
                        <td><?php echo number_format(array_sum(array_column($province_case_data, 'EV'))); ?></td>
                        <td><?php echo number_format(array_sum(array_column($province_case_data, 'CM'))); ?></td>
                        <td><?php echo number_format(array_sum(array_column($province_case_data, 'PS'))); ?></td>
                        <td class="total-cell"><?php 
                            $grand_total = 0;
                            foreach($province_case_data as $p) {
                                $grand_total += $p['LC'] + $p['MD'] + $p['BM'] + $p['FR'] + $p['TF'] + $p['EV'] + $p['CM'] + $p['PS'];
                            }
                            echo number_format($grand_total); 
                        ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- Row 2: Bar Chart Full Width -->
<div class="row g-3">
    <div class="col-12">
        <div class="dashboard-panel">
            <div class="panel-header">
                <i class="fas fa-chart-bar me-2"></i>สถิติตามประเภทคดี (8 ประเภท)
            </div>
            <div class="panel-body" style="position:relative;">
                <div class="legend-inline mb-2 flex-wrap" id="barLegend">
                    <span data-index="0"><span class="legend-dot" style="background:#3b82f6;"></span> คดีทรัพย์</span>
                    <span data-index="1"><span class="legend-dot" style="background:#ef4444;"></span> คดีชีวิต</span>
                    <span data-index="2"><span class="legend-dot" style="background:#f59e0b;"></span> คดีระเบิด</span>
                    <span data-index="3"><span class="legend-dot" style="background:#ec4899;"></span> คดีเพลิงไหม้</span>
                    <span data-index="4"><span class="legend-dot" style="background:#10b981;"></span> คดีจราจร</span>
                    <span data-index="5"><span class="legend-dot" style="background:#8b5cf6;"></span> ตรวจเก็บวัตถุพยาน(ลายนิ้วมือแฝง)</span>
                    <span data-index="6"><span class="legend-dot" style="background:#06b6d4;"></span> ตรวจเก็บวัตถุพยานที่เกิดเหตุ</span>
                    <span data-index="7"><span class="legend-dot" style="background:#84cc16;"></span> ตรวจเก็บวัตถุพยานบุคคล</span>
                </div>
                <div class="bar-chart-wrapper">
                    <canvas id="barChart3D"></canvas>
                </div>
                <div class="chart-tooltip" id="barTooltip"></div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$title = "สรุปภาพรวมสถานการณ์การเกิดเหตุ";

$extra_scripts = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    
    // ============ Pie Chart Data & State ============
    const pieCanvas = document.getElementById("pieChart");
    const pieTooltip = document.getElementById("pieTooltip");
    const pieLegend = document.getElementById("pieLegend");
    
    let pieData = [' . $pie_yala . ', ' . $pie_pattanee . ', ' . $pie_narateewat . '];
    const pieLabels = ["ยะลา", "ปัตตานี", "นราธิวาส"];
    const pieColors = ["#ec4899", "#f59e0b", "#10b981"];
    const pieDarkColors = ["#db2777", "#d97706", "#059669"];
    let pieVisible = [true, true, true];
    let pieAngles = [];
    let pieCenterX, pieCenterY, pieRadiusX, pieRadiusY;
    let hoveredSlice = -1;
    const baseExplodeOffset = 8;
    const hoverOffset = 12;
    
    // ============ Bar Chart Data ============
    let caseCounts = ' . json_encode(array_column($case_types, 'count')) . ';
    const caseTypes = ' . json_encode(array_column($case_types, 'type')) . ';
    const colKeys = ["LC", "MD", "BM", "FR", "TF", "EV", "CM", "PS"];
    
    // ============ Auto Refresh Function (ทุก 3 นาที) ============
    const REFRESH_INTERVAL = 3 * 60 * 1000; // 3 minutes in milliseconds
    
    async function fetchDashboardData() {
        try {
            const response = await fetch("api/ReceiveNoti/getDashboardStats.php");
            const result = await response.json();
            
            if (result.status === "success") {
                const data = result.data;
                
                // Update Pie Chart Data
                pieData[0] = data.pieChart[0].count; // ยะลา
                pieData[1] = data.pieChart[1].count; // ปัตตานี
                pieData[2] = data.pieChart[2].count; // นราธิวาส
                
                // Update Bar Chart Data
                caseCounts = data.columnChart.map(item => item.count);
                
                // Update HTML table
                if (data.tableData) {
                    const tbody = document.querySelector(".province-table tbody");
                    const tfoot = document.querySelector(".province-table tfoot");
                    if (tbody && tfoot) {
                        let tbodyHtml = "";
                        const totals = {};
                        colKeys.forEach(k => totals[k] = 0);
                        data.tableData.forEach(row => {
                            let rowTotal = 0;
                            colKeys.forEach(k => { rowTotal += (row[k] || 0); totals[k] += (row[k] || 0); });
                            tbodyHtml += "<tr><td>" + row.province + "</td>";
                            colKeys.forEach(k => { tbodyHtml += "<td>" + (row[k] || 0).toLocaleString() + "</td>"; });
                            tbodyHtml += \'<td class="total-cell">\' + rowTotal.toLocaleString() + "</td></tr>";
                        });
                        tbody.innerHTML = tbodyHtml;
                        let grandTotal = 0;
                        let tfootHtml = "<tr><td>รวม</td>";
                        colKeys.forEach(k => { tfootHtml += "<td>" + totals[k].toLocaleString() + "</td>"; grandTotal += totals[k]; });
                        tfootHtml += \'<td class="total-cell">\' + grandTotal.toLocaleString() + "</td></tr>";
                        tfoot.innerHTML = tfootHtml;
                    }
                }
                
                // Redraw charts
                draw3DPie();
                draw3DBar();
            }
        } catch (error) {
            // silent fail for auto-refresh
        }
    }
    
    // Set interval to refresh data every 3 minutes
    setInterval(fetchDashboardData, REFRESH_INTERVAL);
    
    function draw3DPie() {
        const container = pieCanvas.parentElement;
        const containerWidth = container.offsetWidth;
        const containerHeight = container.offsetHeight;
        
        // Set canvas size for high DPI
        pieCanvas.width = containerWidth * 2;
        pieCanvas.height = containerHeight * 2;
        pieCanvas.style.width = containerWidth + "px";
        pieCanvas.style.height = containerHeight + "px";
        
        const ctx = pieCanvas.getContext("2d");
        ctx.scale(2, 2);
        
        const width = containerWidth;
        const height = containerHeight;
        pieCenterX = width / 2;
        pieCenterY = height / 2 - 10;
        pieRadiusX = Math.min(width, height) * 0.42;
        pieRadiusY = pieRadiusX * 0.5;
        const depth = 30;
        
        // Filter visible data
        const visibleData = pieData.map((v, i) => pieVisible[i] ? v : 0);
        const total = visibleData.reduce((a, b) => a + b, 0);
        
        ctx.clearRect(0, 0, width, height);
        
        if (total === 0) return;
        
        // Calculate angles (no gaps in angle, use offset for separation)
        let startAngle = -Math.PI / 2;
        pieAngles = [];
        
        visibleData.forEach((value, i) => {
            const sliceAngle = (value / total) * 2 * Math.PI;
            pieAngles.push({ 
                start: startAngle, 
                end: startAngle + sliceAngle, 
                color: pieColors[i], 
                dark: pieDarkColors[i], 
                value: pieData[i], 
                label: pieLabels[i], 
                visible: pieVisible[i],
                index: i
            });
            startAngle += sliceAngle;
        });
        
        // Function to get offset for a slice (base explode + hover effect)
        function getSliceOffset(sliceIndex) {
            const slice = pieAngles[sliceIndex];
            const midAngle = (slice.start + slice.end) / 2;
            
            // Base offset for symmetrical gap (always applied)
            let offsetX = Math.cos(midAngle) * baseExplodeOffset;
            let offsetY = Math.sin(midAngle) * baseExplodeOffset * (pieRadiusY / pieRadiusX);
            
            // Additional offset when hovered
            if (sliceIndex === hoveredSlice) {
                offsetX += Math.cos(midAngle) * hoverOffset;
                offsetY += Math.sin(midAngle) * hoverOffset * (pieRadiusY / pieRadiusX);
            }
            
            return { x: offsetX, y: offsetY };
        }
        
        // Function to get color with dimming effect for non-hovered slices
        function getSliceColor(originalColor, darkColor, sliceIndex, isTop) {
            if (hoveredSlice === -1) {
                return isTop ? originalColor : darkColor;
            }
            if (sliceIndex === hoveredSlice) {
                // Brighten the hovered slice slightly
                return isTop ? originalColor : darkColor;
            }
            // Dim non-hovered slices
            return isTop ? dimColor(originalColor, 0.3) : dimColor(darkColor, 0.3);
        }
        
        // Helper function to dim a color
        function dimColor(hex, factor) {
            const r = parseInt(hex.slice(1, 3), 16);
            const g = parseInt(hex.slice(3, 5), 16);
            const b = parseInt(hex.slice(5, 7), 16);
            const newR = Math.round(r * factor);
            const newG = Math.round(g * factor);
            const newB = Math.round(b * factor);
            return "#" + [newR, newG, newB].map(x => x.toString(16).padStart(2, "0")).join("");
        }
        
        // Draw 3D sides (depth) - draw as solid shapes instead of layers
        pieAngles.forEach((slice, i) => {
            if (!slice.visible) return;
            const offset = getSliceOffset(i);
            // Only draw side for the portion that is visible (front facing)
            const visibleStart = Math.max(slice.start, 0);
            const visibleEnd = Math.min(slice.end, Math.PI);
            
            if (visibleStart < visibleEnd) {
                // Draw the side as a filled polygon
                ctx.beginPath();
                
                // Top arc
                ctx.ellipse(pieCenterX + offset.x, pieCenterY + offset.y, pieRadiusX, pieRadiusY, 0, visibleStart, visibleEnd);
                
                // Right edge going down
                ctx.lineTo(pieCenterX + offset.x + pieRadiusX * Math.cos(visibleEnd), pieCenterY + offset.y + depth + pieRadiusY * Math.sin(visibleEnd));
                
                // Bottom arc (reverse)
                ctx.ellipse(pieCenterX + offset.x, pieCenterY + offset.y + depth, pieRadiusX, pieRadiusY, 0, visibleEnd, visibleStart, true);
                
                // Left edge going up
                ctx.lineTo(pieCenterX + offset.x + pieRadiusX * Math.cos(visibleStart), pieCenterY + offset.y + pieRadiusY * Math.sin(visibleStart));
                
                ctx.closePath();
                ctx.fillStyle = getSliceColor(slice.color, slice.dark, i, false);
                ctx.fill();
            }
        });
        
        // Draw top ellipse (main pie)
        pieAngles.forEach((slice, i) => {
            if (!slice.visible) return;
            const offset = getSliceOffset(i);
            ctx.beginPath();
            ctx.moveTo(pieCenterX + offset.x, pieCenterY + offset.y);
            ctx.ellipse(pieCenterX + offset.x, pieCenterY + offset.y, pieRadiusX, pieRadiusY, 0, slice.start, slice.end);
            ctx.closePath();
            ctx.fillStyle = getSliceColor(slice.color, slice.dark, i, true);
            ctx.fill();
        });
        
        // Draw labels with lines only for hovered slice or all if no hover
        const lineStartRadius = pieRadiusX * 0.95; // Where line starts (slightly inside pie edge)
        
        // Calculate dynamic font size based on pie size - smaller fonts
        const baseFontSize = Math.max(9, Math.min(11, pieRadiusX * 0.09));
        const percentFontSize = Math.max(10, Math.min(12, pieRadiusX * 0.10));
        
        // Sort slices by angle to process labels in order (helps with overlap detection)
        const sortedSlices = pieAngles.map((s, i) => ({...s, originalIndex: i}))
            .filter(s => s.visible && s.value > 0)
            .sort((a, b) => a.start - b.start);
        
        // Collect label positions to avoid overlap
        let labelPositions = [];
        
        sortedSlices.forEach((slice) => {
            const i = slice.originalIndex;
            
            // Only show label for hovered slice, or all if no hover
            if (hoveredSlice !== -1 && hoveredSlice !== i) return;
            
            const offset = getSliceOffset(i);
            const midAngle = (slice.start + slice.end) / 2;
            
            // Calculate percentage
            const visibleTotal = pieData.reduce((sum, v, idx) => pieVisible[idx] ? sum + v : sum, 0);
            const percentage = ((slice.value / visibleTotal) * 100).toFixed(1);
            const percentValue = parseFloat(percentage);
            
            // Dynamic positioning based on percentage value - shorter lines
            let lineEndRadius, horizontalLengthBase, verticalOffset = 0;
            
            if (percentValue >= 30) {
                // Large slice - normal positioning
                lineEndRadius = pieRadiusX * 1.15;
                horizontalLengthBase = 25;
            } else if (percentValue >= 15) {
                // Medium slice
                lineEndRadius = pieRadiusX * 1.25;
                horizontalLengthBase = 35;
            } else if (percentValue >= 8) {
                // Small slice
                lineEndRadius = pieRadiusX * 1.35;
                horizontalLengthBase = 45;
                verticalOffset = (midAngle < 0) ? -15 : 10;
            } else {
                // Very small slice
                lineEndRadius = pieRadiusX * 1.45;
                horizontalLengthBase = 55;
                verticalOffset = (midAngle < 0) ? -25 : 18;
            }
            
            // Line start point (on the pie edge)
            const lineStartX = pieCenterX + offset.x + Math.cos(midAngle) * lineStartRadius;
            const lineStartY = pieCenterY + offset.y + Math.sin(midAngle) * (lineStartRadius * pieRadiusY / pieRadiusX);
            
            // Line end point (outside the pie) with vertical offset
            const lineEndX = pieCenterX + offset.x + Math.cos(midAngle) * lineEndRadius;
            let lineEndY = pieCenterY + offset.y + Math.sin(midAngle) * (lineEndRadius * pieRadiusY / pieRadiusX) + verticalOffset;
            
            // Check for overlap with existing labels and adjust more aggressively
            const labelHeight = 22;
            let attempts = 0;
            let hasOverlap = true;
            while (hasOverlap && attempts < 5) {
                hasOverlap = false;
                for (let pos of labelPositions) {
                    if (Math.abs(lineEndY - pos.y) < labelHeight) {
                        hasOverlap = true;
                        // Shift this label up or down to avoid overlap
                        if (midAngle < 0) {
                            lineEndY = pos.y - labelHeight - 3;
                        } else {
                            lineEndY = pos.y + labelHeight + 3;
                        }
                        break;
                    }
                }
                attempts++;
            }
            
            // Store this label position
            labelPositions.push({ x: lineEndX, y: lineEndY });
            
            // Horizontal line end point
            const horizontalLength = horizontalLengthBase;
            const isRightSide = Math.cos(midAngle) >= 0;
            const horizontalEndX = lineEndX + (isRightSide ? horizontalLength : -horizontalLength);
            
            // Draw line from pie to label (with bend if needed)
            ctx.beginPath();
            ctx.moveTo(lineStartX, lineStartY);
            ctx.lineTo(lineEndX, lineEndY);
            ctx.lineTo(horizontalEndX, lineEndY);
            ctx.strokeStyle = slice.color;
            ctx.lineWidth = 2;
            ctx.stroke();
            
            // Draw dot at line start
            ctx.beginPath();
            ctx.arc(lineStartX, lineStartY, 4, 0, Math.PI * 2);
            ctx.fillStyle = slice.color;
            ctx.fill();
            
            // Draw label text with dynamic font size
            ctx.font = "bold " + baseFontSize + "px Sarabun, sans-serif";
            ctx.fillStyle = slice.color;
            ctx.textAlign = isRightSide ? "left" : "right";
            ctx.textBaseline = "bottom";
            ctx.fillText(slice.label, horizontalEndX + (isRightSide ? 8 : -8), lineEndY - 4);
            
            // Draw percentage with slice color and dynamic font size
            ctx.font = "bold " + percentFontSize + "px Sarabun, sans-serif";
            ctx.fillStyle = slice.color;
            ctx.textBaseline = "top";
            ctx.fillText(percentage + "%", horizontalEndX + (isRightSide ? 8 : -8), lineEndY + 4);
        });
    }
    
    // Pie Chart Mouse Events
    pieCanvas.addEventListener("mousemove", function(e) {
        const rect = pieCanvas.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        
        let newHoveredSlice = -1;
        
        // Check each slice for hover (accounting for offset)
        pieAngles.forEach((slice, i) => {
            if (!slice.visible) return;
            
            // Calculate offset for this slice if it was hovered
            const midAngle = (slice.start + slice.end) / 2;
            const checkOffsetX = (hoveredSlice === i) ? Math.cos(midAngle) * hoverOffset : 0;
            const checkOffsetY = (hoveredSlice === i) ? Math.sin(midAngle) * hoverOffset * (pieRadiusY / pieRadiusX) : 0;
            
            const dx = x - (pieCenterX + checkOffsetX);
            const dy = (y - (pieCenterY + checkOffsetY)) / (pieRadiusY / pieRadiusX);
            const dist = Math.sqrt(dx * dx + dy * dy);
            
            if (dist <= pieRadiusX) {
                const angle = Math.atan2(y - (pieCenterY + checkOffsetY), x - (pieCenterX + checkOffsetX));
                let a = angle;
                if (a < -Math.PI/2) a += Math.PI * 2;
                
                if (a >= slice.start && a < slice.end) {
                    newHoveredSlice = i;
                    pieTooltip.innerHTML = "<strong>" + slice.label + "</strong><br>" + slice.value.toLocaleString() + " รายการ";
                    pieTooltip.style.left = (e.clientX - rect.left + 15) + "px";
                    pieTooltip.style.top = (e.clientY - rect.top - 10) + "px";
                    pieTooltip.classList.add("show");
                    pieCanvas.style.cursor = "pointer";
                }
            }
        });
        
        // Update hover state and redraw if changed
        if (newHoveredSlice !== hoveredSlice) {
            hoveredSlice = newHoveredSlice;
            draw3DPie();
        }
        
        if (newHoveredSlice === -1) {
            pieTooltip.classList.remove("show");
            pieCanvas.style.cursor = "default";
        }
    });
    
    pieCanvas.addEventListener("mouseleave", function() {
        pieTooltip.classList.remove("show");
        if (hoveredSlice !== -1) {
            hoveredSlice = -1;
            draw3DPie();
        }
    });
    
    // Pie Legend Click
    pieLegend.querySelectorAll("span[data-index]").forEach(function(el) {
        el.addEventListener("click", function() {
            const idx = parseInt(this.dataset.index);
            pieVisible[idx] = !pieVisible[idx];
            this.classList.toggle("disabled");
            draw3DPie();
        });
    });
    
    // ============ 3D Bar Chart (Custom Canvas Drawing) ============
    const barCanvas = document.getElementById("barChart3D");
    const barTooltip = document.getElementById("barTooltip");
    const barLegend = document.getElementById("barLegend");
    let barVisible = [true, true, true, true, true, true, true, true];
    let barRects = [];
    
    const barColors = [
        { front: "#3b82f6", side: "#2563eb", top: "#60a5fa" },
        { front: "#ef4444", side: "#dc2626", top: "#f87171" },
        { front: "#f59e0b", side: "#d97706", top: "#fbbf24" },
        { front: "#ec4899", side: "#db2777", top: "#f472b6" },
        { front: "#10b981", side: "#059669", top: "#34d399" },
        { front: "#8b5cf6", side: "#7c3aed", top: "#a78bfa" },
        { front: "#06b6d4", side: "#0891b2", top: "#22d3ee" },
        { front: "#84cc16", side: "#65a30d", top: "#a3e635" }
    ];
    
    function draw3DBar() {
        const container = barCanvas.parentElement;
        const containerWidth = container.offsetWidth;
        const containerHeight = container.offsetHeight;
        
        // Set canvas size for high DPI
        barCanvas.width = containerWidth * 2;
        barCanvas.height = containerHeight * 2;
        barCanvas.style.width = containerWidth + "px";
        barCanvas.style.height = containerHeight + "px";
        
        const ctx = barCanvas.getContext("2d");
        ctx.scale(2, 2);
        
        const width = containerWidth;
        const height = containerHeight;
        
        const padding = { top: 30, right: 40, bottom: 55, left: 50 };
        const chartWidth = width - padding.left - padding.right;
        const chartHeight = height - padding.top - padding.bottom;
        
        // Filter visible data
        const visibleCounts = caseCounts.map((v, i) => barVisible[i] ? v : 0);
        const maxVal = Math.max(...visibleCounts, 1);
        const barGroupWidth = chartWidth / caseTypes.length;
        const barWidth = barGroupWidth * 0.25;
        const depth3D = 6;
        const offsetX = 4;
        
        ctx.clearRect(0, 0, width, height);
        barRects = [];
        
        // Draw grid lines
        ctx.strokeStyle = "#e0e0e0";
        ctx.lineWidth = 0.5;
        for (let i = 0; i <= 5; i++) {
            const y = padding.top + (chartHeight / 5) * i;
            ctx.beginPath();
            ctx.moveTo(padding.left, y);
            ctx.lineTo(width - padding.right, y);
            ctx.stroke();
        }
        
        // Draw Y axis labels
        ctx.fillStyle = "#666";
        ctx.font = "11px sans-serif";
        ctx.textAlign = "right";
        for (let i = 0; i <= 5; i++) {
            const val = Math.round(maxVal - (maxVal / 5) * i);
            const y = padding.top + (chartHeight / 5) * i + 4;
            ctx.fillText(val, padding.left - 8, y);
        }
        
        // Draw bars (one bar per case type)
        caseTypes.forEach((caseType, i) => {
            if (!barVisible[i]) return;
            
            const x = padding.left + i * barGroupWidth + (barGroupWidth - barWidth) / 2;
            const barHeight = (caseCounts[i] / maxVal) * chartHeight;
            const barY = padding.top + chartHeight - barHeight;
            
            draw3DBox(ctx, x, barY, barWidth, barHeight, depth3D, offsetX, barColors[i]);
            barRects.push({ x: x, y: barY, w: barWidth + offsetX, h: barHeight, label: caseType, value: caseCounts[i] });
        });
        
        // Draw X axis labels - single line
        ctx.fillStyle = "#333";
        ctx.font = "12px sans-serif";
        ctx.textAlign = "center";
        caseTypes.forEach((caseType, i) => {
            const x = padding.left + i * barGroupWidth + barGroupWidth / 2;
            ctx.fillText(caseType, x, height - padding.bottom + 25);
        });
    }
    
    function draw3DBox(ctx, x, y, w, h, d, ox, colors) {
        if (h <= 0) return;
        
        // Front face
        ctx.fillStyle = colors.front;
        ctx.fillRect(x, y, w, h);
        ctx.strokeStyle = "#fff";
        ctx.lineWidth = 0.5;
        ctx.strokeRect(x, y, w, h);
        
        // Top face
        ctx.beginPath();
        ctx.moveTo(x, y);
        ctx.lineTo(x + ox, y - d);
        ctx.lineTo(x + w + ox, y - d);
        ctx.lineTo(x + w, y);
        ctx.closePath();
        ctx.fillStyle = colors.top;
        ctx.fill();
        ctx.stroke();
        
        // Right side face
        ctx.beginPath();
        ctx.moveTo(x + w, y);
        ctx.lineTo(x + w + ox, y - d);
        ctx.lineTo(x + w + ox, y + h - d);
        ctx.lineTo(x + w, y + h);
        ctx.closePath();
        ctx.fillStyle = colors.side;
        ctx.fill();
        ctx.stroke();
    }
    
    // Initial draw
    // Bar Chart Mouse Events
    barCanvas.addEventListener("mousemove", function(e) {
        const rect = barCanvas.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        
        let found = false;
        barRects.forEach(function(bar) {
            if (x >= bar.x && x <= bar.x + bar.w && y >= bar.y && y <= bar.y + bar.h) {
                barTooltip.innerHTML = "<strong>" + bar.label + "</strong><br>" + bar.value.toLocaleString() + " คดี";
                barTooltip.style.left = (e.clientX - rect.left + 15) + "px";
                barTooltip.style.top = (e.clientY - rect.top - 10) + "px";
                barTooltip.classList.add("show");
                barCanvas.style.cursor = "pointer";
                found = true;
            }
        });
        
        if (!found) {
            barTooltip.classList.remove("show");
            barCanvas.style.cursor = "default";
        }
    });
    
    barCanvas.addEventListener("mouseleave", function() {
        barTooltip.classList.remove("show");
    });
    
    // Bar Legend Click
    barLegend.querySelectorAll("span[data-index]").forEach(function(el) {
        el.addEventListener("click", function() {
            const idx = parseInt(this.dataset.index);
            barVisible[idx] = !barVisible[idx];
            this.classList.toggle("disabled");
            draw3DBar();
        });
    });
    
    // Initial draw
    setTimeout(function() {
        draw3DPie();
        draw3DBar();
    }, 100);
    
    // Redraw on resize
    let resizeTimer;
    window.addEventListener("resize", function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            draw3DPie();
            draw3DBar();
        }, 200);
    });
});
</script>
';

include 'layout.php';
?>
