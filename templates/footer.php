<?php
// templates/footer.php
?>
    </div> <!-- This closes the .container-fluid from the top of index.php -->

    <footer class="mt-5 mb-3 text-muted text-center">
        <p>Identity Management Tool &copy; <?= date('Y') ?> | v1.0.0 | Last sync: <?= $lastSyncTimeForFooter ?? 'N/A' ?></p>
    </footer>

    <!-- JAVASCRIPT LIBRARIES (Correctly placed at the end of the body) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- YOUR CUSTOM JAVASCRIPT -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // This is the modern, jQuery-free way to initialize Bootstrap components
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Auto-dismiss alerts after 5 seconds
            setTimeout(function() {
                var alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    new bootstrap.Alert(alert).close();
                });
            }, 5000);

            // Source type switcher logic (if needed on other pages)
            var sourceTypeSelect = document.getElementById('source-type');
            if (sourceTypeSelect) {
                sourceTypeSelect.addEventListener('change', function() {
                    document.querySelectorAll('.source-config').forEach(el => {
                        el.style.display = 'none';
                    });
                    const configEl = document.getElementById(this.value.toLowerCase() + '-config');
                    if (configEl) configEl.style.display = 'block';
                });
                // Trigger change on load
                if (sourceTypeSelect.value) {
                    sourceTypeSelect.dispatchEvent(new Event('change'));
                }
            }
        });
    </script>
</body>
</html>
