    </div> <!-- End container-fluid -->
</div> <!-- End wrapper-content -->
</div> <!-- End wrapper -->

<!-- JAVASCRIPT -->
<!-- JQUERY -->
<script src="/admin_assets/libraries/jquery/jquery.min.js"></script>
<!-- JQUERY UI -->
<script src="/admin_assets/libraries/jquery-ui/jquery-ui.min.js"></script>
<!-- POPPER -->
<script src="/admin_assets/libraries/popper/popper.min.js"></script>
<!-- BOOTSTRAP - V 4.0.0 -->
<script src="/admin_assets/dist/js/bootstrap.min.js"></script>
<!-- CHARTJS -->
<script src="/admin_assets/plugin/chartJs/Chart.bundle.min.js"></script>
<!-- SUMO SELECT -->
<script src="/admin_assets/plugin/sumoselect/jquery.sumoselect.min.js"></script>
<!-- OVERLAYSCROLLBARS -->
<script src="/admin_assets/plugin/OverlayScrollbars/js/jquery.overlayScrollbars.min.js"></script>
<!-- DATA TABLES -->
<script src="/admin_assets/plugin/DataTables/1.10.16/js/jquery.dataTables.min.js"></script>
<script src="/admin_assets/plugin/DataTables/1.10.16/js/dataTables.bootstrap4.min.js"></script>
<!-- JQUERY-NOTIFY -->
<script src="/admin_assets/plugin/notify/js/notify.js"></script>
<!-- PLUGINS -->
<script src="/admin_assets/js/plugin.js"></script>
<!-- toastr alert -->
<script src="/notification_assets/js/toastr.min.js"></script>
<!-- sweet alert -->
<script src="/notification_assets/js/sweetalert.min.js"></script>
<!-- FUNCTIONS -->
<script src="/admin_assets/js/functions.js"></script>
<script>
    $(document).ready(function() {
        if ($('.datatable').length) {
            $('.datatable').DataTable({
                "pageLength": 10,
                "responsive": true
            });
        }
    });
</script>
</body>
</html>
