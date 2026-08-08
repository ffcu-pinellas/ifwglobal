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

<?php
// Inject chat widget based on admin settings - only for non-internal and non-admin-chat pages
$_footer_chat_provider = isset($pdo) ? get_setting($pdo, 'chat_provider', 'none') : 'none';
$_footer_current_page = basename($_SERVER['PHP_SELF']);
$_footer_is_chat_page = in_array($_footer_current_page, ['chat.php', 'chat_internal.php']);

if (!$_footer_is_chat_page):
    if ($_footer_chat_provider === 'tawkto'):
        $tawk_code = isset($pdo) ? get_setting($pdo, 'tawkto_property_id', '') : '';
        if ($tawk_code):
            // Strip any HTML tags, script wrappers, comments from stored value to get clean property ID
            $clean_id = $tawk_code;
            $clean_id = strip_tags($clean_id);                             // Remove any <script> tags
            $clean_id = preg_replace('/<!--.*?-->/s', '', $clean_id);      // Remove HTML comments
            $clean_id = preg_replace('/var\s+Tawk_API[\s\S]*?embed\.tawk\.to\//i', '', $clean_id); // Strip JS preamble
            $clean_id = preg_replace('/[\'"];.*$/s', '', $clean_id);       // Remove trailing JS
            $clean_id = trim($clean_id, " \t\n\r;'\"/");
            
            if (strpos($clean_id, 'embed.tawk.to/') !== false) {
                $clean_id = preg_replace('/.*embed\.tawk\.to\//', '', $clean_id);
                $clean_id = trim($clean_id, " \t\n\r;'\"");
            }
            
            // Validate: should look like "XXXXXXXX/YYYYY" or "hash/default"
            if (!empty($clean_id) && preg_match('/^[a-zA-Z0-9_\/\-]{10,}$/', $clean_id)):
                $tawkto_src = 'https://embed.tawk.to/' . $clean_id;
?>
<!--Start of Tawk.to Script-->
<script type="text/javascript">
var Tawk_API=Tawk_API||{}, Tawk_LoadStart=new Date();
(function(){
var s1=document.createElement("script"),s0=document.getElementsByTagName("script")[0];
s1.async=true;
s1.src='<?php echo htmlspecialchars($tawkto_src, ENT_QUOTES); ?>';
s1.charset='UTF-8';
s1.setAttribute('crossorigin','*');
s0.parentNode.insertBefore(s1,s0);
})();
</script>
<!--End of Tawk.to Script-->
<?php
            endif;
        endif;

    elseif ($_footer_chat_provider === 'manychat'):
        $manychat_code = isset($pdo) ? get_setting($pdo, 'manychat_script_code', '') : '';
        if ($manychat_code):
            echo $manychat_code;
        endif;

    elseif ($_footer_chat_provider === 'custom'):
        $custom_code = isset($pdo) ? get_setting($pdo, 'custom_chat_code', '') : '';
        if ($custom_code):
            echo $custom_code;
        endif;
    endif;
endif;
?>
</body>
</html>
