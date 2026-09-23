<?php
require_once __DIR__ . '/../config.php';
lieo_require_role(['admin']);

$pageTitle = 'Bulk Upload Users';
$activeNav = 'matrix';

require_once __DIR__ . '/../includes/header.php';
?>

<h2 class="lieo-title mb-2">Bulk Upload Users</h2>
<p class="text-muted mb-4">
    Upload an Excel file shaped like <code>resources\HOD &amp; N+1.xlsx</code> — header row
    <code>Sl.No | Plant Name | Type | Department | SF Code | Company Email ID | Emp Name</code>, <code>Type</code>
    is <code>HOD</code> or <code>N+1</code>. Every sheet in the file is read. Rows are parsed in your browser, then
    each valid row is queued as a create (or replace) request needing the HR department HOD's approval — nothing is
    saved live.
</p>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <div class="form-row align-items-end">
            <div class="form-group col-md-4">
                <label>Plant *</label>
                <input type="text" id="bulkPlant" class="form-control" placeholder="e.g. RCP" autocomplete="off" required>
            </div>
            <div class="form-group col-md-5">
                <label>Excel file (.xlsx) *</label>
                <input type="file" id="bulkFile" class="form-control-file" accept=".xlsx,.xls">
            </div>
            <div class="form-group col-md-3">
                <button type="button" id="bulkParseBtn" class="btn btn-lieo btn-block">Parse file</button>
            </div>
        </div>
        <div id="bulkParseMsg" class="small text-muted"></div>
    </div>
</div>

<div id="bulkPreviewCard" class="card shadow-sm mb-4" style="display:none;">
    <div class="card-header bg-white font-weight-bold d-flex justify-content-between align-items-center">
        <span>Preview — <span id="bulkPreviewCount">0</span> rows</span>
        <button type="button" id="bulkSubmitBtn" class="btn btn-lieo btn-sm">Submit for approval</button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>#</th><th>Sheet</th><th>Type</th><th>Department</th><th>SF Code</th><th>Emp Name</th><th>Email</th><th>Status</th></tr></thead>
                <tbody id="bulkPreviewBody"></tbody>
            </table>
        </div>
    </div>
</div>

<div id="bulkResultsCard" class="card shadow-sm" style="display:none;">
    <div class="card-header bg-white font-weight-bold">Results</div>
    <div class="card-body">
        <p id="bulkResultsSummary" class="mb-3"></p>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>Row</th><th>Result</th></tr></thead>
                <tbody id="bulkResultsBody"></tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
(function () {
    var $plant = document.getElementById('bulkPlant');
    var $file = document.getElementById('bulkFile');
    var $parseBtn = document.getElementById('bulkParseBtn');
    var $parseMsg = document.getElementById('bulkParseMsg');
    var $previewCard = document.getElementById('bulkPreviewCard');
    var $previewBody = document.getElementById('bulkPreviewBody');
    var $previewCount = document.getElementById('bulkPreviewCount');
    var $submitBtn = document.getElementById('bulkSubmitBtn');
    var $resultsCard = document.getElementById('bulkResultsCard');
    var $resultsSummary = document.getElementById('bulkResultsSummary');
    var $resultsBody = document.getElementById('bulkResultsBody');

    var parsedRows = [];

    function esc(s) {
        return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function normHeader(h) {
        return String(h || '').trim().toLowerCase();
    }

    $parseBtn.addEventListener('click', function () {
        parsedRows = [];
        $parseMsg.textContent = '';
        $previewCard.style.display = 'none';
        $resultsCard.style.display = 'none';

        if (!$plant.value.trim()) {
            $parseMsg.textContent = 'Enter a plant first.';
            $parseMsg.className = 'small text-danger';
            return;
        }
        var file = $file.files && $file.files[0];
        if (!file) {
            $parseMsg.textContent = 'Choose an Excel file first.';
            $parseMsg.className = 'small text-danger';
            return;
        }

        var reader = new FileReader();
        reader.onload = function (e) {
            var wb;
            try {
                wb = XLSX.read(new Uint8Array(e.target.result), { type: 'array' });
            } catch (err) {
                $parseMsg.textContent = 'Could not read file: ' + err.message;
                $parseMsg.className = 'small text-danger';
                return;
            }

            var rows = [];
            wb.SheetNames.forEach(function (sheetName) {
                var sheet = wb.Sheets[sheetName];
                var grid = XLSX.utils.sheet_to_json(sheet, { header: 1, defval: '' });
                if (!grid.length) return;

                // Find the header row (has "Type" and "SF Code" columns).
                var headerIdx = -1, colIdx = {};
                for (var r = 0; r < Math.min(grid.length, 5); r++) {
                    var cols = {};
                    grid[r].forEach(function (cell, c) { cols[normHeader(cell)] = c; });
                    if ('type' in cols && ('sf code' in cols)) {
                        headerIdx = r;
                        colIdx = cols;
                        break;
                    }
                }
                if (headerIdx < 0) return;

                for (var i = headerIdx + 1; i < grid.length; i++) {
                    var row = grid[i];
                    if (!row || !row.length) continue;
                    var type = row[colIdx['type']];
                    if (!type) continue;
                    rows.push({
                        sheet: sheetName,
                        type: String(type).trim(),
                        department: String(row[colIdx['department']] || '').trim(),
                        emp_code: String(row[colIdx['sf code']] || '').trim(),
                        emp_email: String(row[colIdx['company email id']] || '').trim(),
                        emp_name: String(row[colIdx['emp name']] || '').trim(),
                    });
                }
            });

            if (!rows.length) {
                $parseMsg.textContent = 'No data rows found. Expected header columns: Type, Department, SF Code, Company Email ID, Emp Name.';
                $parseMsg.className = 'small text-danger';
                return;
            }

            parsedRows = rows;
            renderPreview();
        };
        reader.readAsArrayBuffer(file);
    });

    function renderPreview() {
        var html = '';
        var validCount = 0;
        parsedRows.forEach(function (r, i) {
            var typeOk = /^HOD$|^N\+?1$/i.test(r.type);
            var ok = typeOk && r.emp_code && r.emp_email && r.emp_name;
            if (ok) validCount++;
            html += '<tr' + (ok ? '' : ' class="table-warning"') + '>'
                + '<td>' + (i + 1) + '</td>'
                + '<td>' + esc(r.sheet) + '</td>'
                + '<td>' + esc(r.type) + '</td>'
                + '<td>' + esc(r.department) + '</td>'
                + '<td>' + esc(r.emp_code) + '</td>'
                + '<td>' + esc(r.emp_name) + '</td>'
                + '<td>' + esc(r.emp_email) + '</td>'
                + '<td>' + (ok ? '<span class="text-success">Ready</span>' : '<span class="text-warning">Check fields</span>') + '</td>'
                + '</tr>';
        });
        $previewBody.innerHTML = html;
        $previewCount.textContent = parsedRows.length + ' (' + validCount + ' look valid)';
        $previewCard.style.display = '';
        $parseMsg.textContent = 'Parsed ' + parsedRows.length + ' row(s) from ' + new Set(parsedRows.map(function (r) { return r.sheet; })).size + ' sheet(s).';
        $parseMsg.className = 'small text-muted';
    }

    $submitBtn.addEventListener('click', function () {
        if (!parsedRows.length) return;
        $submitBtn.disabled = true;
        $submitBtn.textContent = 'Submitting…';
        fetch('../api/bulk_add_users.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ plant: $plant.value.trim(), rows: parsedRows })
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                $submitBtn.disabled = false;
                $submitBtn.textContent = 'Submit for approval';
                if (!data.ok) {
                    $resultsSummary.textContent = data.message || 'Submit failed.';
                    $resultsCard.style.display = '';
                    return;
                }
                $resultsSummary.textContent = data.summary;
                var html = '';
                (data.results || []).forEach(function (r) {
                    html += '<tr><td>' + r.row + '</td><td class="' + (r.ok ? 'text-success' : 'text-danger') + '">' + esc(r.message) + '</td></tr>';
                });
                $resultsBody.innerHTML = html;
                $resultsCard.style.display = '';
            })
            .catch(function (err) {
                $submitBtn.disabled = false;
                $submitBtn.textContent = 'Submit for approval';
                $resultsSummary.textContent = 'Submit failed: ' + err.message;
                $resultsCard.style.display = '';
            });
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
