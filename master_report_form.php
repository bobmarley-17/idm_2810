<?php
include 'templates/header.php';
?>
<div class="container mt-4">
    <h2>Master Identity Report</h2>
    <form action="master_report_generate.php" method="GET" class="mb-4">
        <div class="mb-3">
            <label for="start_date" class="form-label">Start Date</label>
            <input type="text" id="start_date" name="start_date" class="form-control datepicker" required>
        </div>
        <div class="mb-3">
            <label for="end_date" class="form-label">End Date</label>
            <input type="text" id="end_date" name="end_date" class="form-control datepicker" required>
        </div>
        <button type="submit" class="btn btn-primary">Generate Report</button>
    </form>
</div>

<!-- ✅ Flatpickr -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<!-- Optional Theme -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_blue.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    flatpickr(".datepicker", {
        dateFormat: "Y-m-d",
        allowInput: true,
        monthSelectorType: "dropdown",  // Already default
        yearSelectorType: "dropdown"    // ✅ Enable year dropdown
    });
</script>

<?php
include 'templates/footer.php';
?>

