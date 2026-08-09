<?php
// admin/content_managers.php
require_once '../config.php';
require_once '../includes/functions.php';
require_superadmin();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'update_page') {
        $page_key = trim($_POST['page_key']);
        $content = $_POST['page_content'];
        set_setting($pdo, "page_" . $page_key, $content);
        header("Location: content_managers.php?success=1");
        exit;
    }
}

$about_content = get_setting($pdo, 'page_about_us', '');
$privacy_content = get_setting($pdo, 'page_privacy', '');
$terms_content = get_setting($pdo, 'page_terms', '');
?>

<?php require_once '../includes/admin_header.php'; ?>
<?php require_once '../includes/admin_sidebar.php'; ?>

<div class="row">
    <div class="col-12 mb-4 d-flex align-items-center justify-content-between">
        <div>
            <h3 class="text-warning font-weight-bold mb-1"><i class="fas fa-file-alt mr-2"></i>Content & Page Manager</h3>
            <p class="text-muted mb-0">Edit static website pages, legal disclosures, and dynamic content areas.</p>
        </div>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success bg-success text-white border-0 shadow-sm mb-4">
        <i class="fas fa-check-circle mr-2"></i>Page content updated successfully!
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-12 mb-4">
        <div class="card shadow-sm border-secondary">
            <div class="card-header bg-dark text-warning border-secondary font-weight-bold">
                Edit Website Pages
            </div>
            <div class="card-body bg-dark text-white">
                <ul class="nav nav-tabs border-secondary mb-4" id="pageTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active text-warning font-weight-bold" id="about-tab" data-toggle="tab" href="#about" role="tab">About Us Page</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-warning font-weight-bold" id="privacy-tab" data-toggle="tab" href="#privacy" role="tab">Privacy Policy</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-warning font-weight-bold" id="terms-tab" data-toggle="tab" href="#terms" role="tab">Terms of Service</a>
                    </li>
                </ul>

                <div class="tab-content" id="pageTabsContent">
                    <!-- About Us Tab -->
                    <div class="tab-pane fade show active" id="about" role="tabpanel">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_page">
                            <input type="hidden" name="page_key" value="about_us">
                            <div class="form-group mb-3">
                                <label class="font-weight-bold text-light">About Us Page HTML Content</label>
                                <textarea name="page_content" class="form-control bg-secondary text-white border-0 summernote" rows="12"><?= htmlspecialchars($about_content) ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-warning font-weight-bold text-dark">Save About Us Page</button>
                        </form>
                    </div>

                    <!-- Privacy Policy Tab -->
                    <div class="tab-pane fade" id="privacy" role="tabpanel">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_page">
                            <input type="hidden" name="page_key" value="privacy">
                            <div class="form-group mb-3">
                                <label class="font-weight-bold text-light">Privacy Policy HTML Content</label>
                                <textarea name="page_content" class="form-control bg-secondary text-white border-0 summernote" rows="12"><?= htmlspecialchars($privacy_content) ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-warning font-weight-bold text-dark">Save Privacy Policy</button>
                        </form>
                    </div>

                    <!-- Terms Tab -->
                    <div class="tab-pane fade" id="terms" role="tabpanel">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_page">
                            <input type="hidden" name="page_key" value="terms">
                            <div class="form-group mb-3">
                                <label class="font-weight-bold text-light">Terms of Service HTML Content</label>
                                <textarea name="page_content" class="form-control bg-secondary text-white border-0 summernote" rows="12"><?= htmlspecialchars($terms_content) ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-warning font-weight-bold text-dark">Save Terms of Service</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/admin_footer.php'; ?>
