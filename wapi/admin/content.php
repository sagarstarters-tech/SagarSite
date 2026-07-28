<?php
/**
 * WAPI SaaS - Admin CMS Content Management
 * Manage features, FAQs, testimonials dynamically
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
Auth::requireAdmin();

$db = Database::getInstance();
$settings = new Settings();

$hideNav = true; // Prevents landing page nav from appearing in admin

$section = sanitize($_GET['section'] ?? 'features');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && CSRF::validateToken()) {
    $action = $_POST['action'] ?? '';

    if ($section === 'features') {
        if ($action === 'save') {
            $data = [
                'title' => sanitize($_POST['title']),
                'description' => sanitize($_POST['description']),
                'icon' => sanitize($_POST['icon']),
                'sort_order' => sanitizeInt($_POST['sort_order']),
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ];
            $id = sanitizeInt($_POST['item_id'] ?? 0);
            if ($id > 0) $db->update('features', $data, 'id = ?', [$id]);
            else $db->insert('features', $data);
            setFlash('success', 'Feature saved.');
        } elseif ($action === 'delete') {
            $db->delete('features', 'id = ?', [sanitizeInt($_POST['item_id'])]);
            setFlash('success', 'Feature deleted.');
        }
    } elseif ($section === 'faqs') {
        if ($action === 'save') {
            $data = [
                'question' => sanitize($_POST['question']),
                'answer' => sanitize($_POST['answer']),
                'sort_order' => sanitizeInt($_POST['sort_order']),
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ];
            $id = sanitizeInt($_POST['item_id'] ?? 0);
            if ($id > 0) $db->update('faqs', $data, 'id = ?', [$id]);
            else $db->insert('faqs', $data);
            setFlash('success', 'FAQ saved.');
        } elseif ($action === 'delete') {
            $db->delete('faqs', 'id = ?', [sanitizeInt($_POST['item_id'])]);
            setFlash('success', 'FAQ deleted.');
        }
    } elseif ($section === 'testimonials') {
        if ($action === 'save') {
            $data = [
                'name' => sanitize($_POST['name']),
                'designation' => sanitize($_POST['designation']),
                'company' => sanitize($_POST['company']),
                'content' => sanitize($_POST['content']),
                'rating' => sanitizeInt($_POST['rating']),
                'sort_order' => sanitizeInt($_POST['sort_order']),
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ];
            $id = sanitizeInt($_POST['item_id'] ?? 0);
            if ($id > 0) $db->update('testimonials', $data, 'id = ?', [$id]);
            else $db->insert('testimonials', $data);
            setFlash('success', 'Testimonial saved.');
        } elseif ($action === 'delete') {
            $db->delete('testimonials', 'id = ?', [sanitizeInt($_POST['item_id'])]);
            setFlash('success', 'Testimonial deleted.');
        }
    }
    redirect('admin/content.php?section=' . $section);
}

$items = [];
if ($section === 'features') $items = $db->fetchAll("SELECT * FROM features ORDER BY sort_order ASC");
elseif ($section === 'faqs') $items = $db->fetchAll("SELECT * FROM faqs ORDER BY sort_order ASC");
elseif ($section === 'testimonials') $items = $db->fetchAll("SELECT * FROM testimonials ORDER BY sort_order ASC");

$pageTitle = 'Content Management';
$extraCss = [asset('assets/css/dashboard.css')];
$extraJs = [asset('assets/js/admin.js')];
include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include __DIR__ . '/../includes/admin-sidebar.php'; ?>
    <main class="main-content">
        <div class="dash-header">
            <div>
                <h1 class="dash-title">Content Management</h1>
                <div class="dash-breadcrumb"><a href="<?= baseUrl('admin/'); ?>">Admin</a><i class="bi bi-chevron-right"></i><span>CMS</span></div>
            </div>
            <button class="btn btn-outline-primary btn-sm d-lg-none" id="mobileSidebarToggle"><i class="bi bi-list"></i></button>
        </div>

        <?php $flash = getFlash(); if ($flash): ?>
            <div class="alert alert-<?= $flash['type']; ?>"><?= e($flash['message']); ?></div>
        <?php endif; ?>

        <!-- Section Tabs -->
        <ul class="nav nav-pills mb-4 gap-2">
            <li><a class="nav-link <?= $section === 'features' ? 'active' : ''; ?> btn-sm" href="?section=features" style="border-radius: 8px;">Features</a></li>
            <li><a class="nav-link <?= $section === 'faqs' ? 'active' : ''; ?> btn-sm" href="?section=faqs" style="border-radius: 8px;">FAQs</a></li>
            <li><a class="nav-link <?= $section === 'testimonials' ? 'active' : ''; ?> btn-sm" href="?section=testimonials" style="border-radius: 8px;">Testimonials</a></li>
        </ul>

        <!-- Add New Item -->
        <div class="card mb-4" style="border-radius: var(--border-radius);">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-3">Add / Edit <?= ucfirst(rtrim($section, 's')); ?></h5>
                <form method="POST" id="cmsForm">
                    <?= CSRF::tokenField(); ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="item_id" id="editItemId" value="0">
                    <div class="row g-3">
                        <?php if ($section === 'features'): ?>
                        <div class="col-md-4"><label class="form-label">Title</label><input type="text" name="title" id="f_title" class="form-control" required></div>
                        <div class="col-md-4"><label class="form-label">Icon (Bootstrap)</label><input type="text" name="icon" id="f_icon" class="form-control" placeholder="bi-send-fill"></div>
                        <div class="col-md-4"><label class="form-label">Sort Order</label><input type="number" name="sort_order" id="f_sort" class="form-control" value="0"></div>
                        <div class="col-12"><label class="form-label">Description</label><textarea name="description" id="f_desc" class="form-control" rows="2"></textarea></div>
                        <?php elseif ($section === 'faqs'): ?>
                        <div class="col-12"><label class="form-label">Question</label><input type="text" name="question" id="f_question" class="form-control" required></div>
                        <div class="col-12"><label class="form-label">Answer</label><textarea name="answer" id="f_answer" class="form-control" rows="3" required></textarea></div>
                        <div class="col-md-4"><label class="form-label">Sort Order</label><input type="number" name="sort_order" id="f_sort" class="form-control" value="0"></div>
                        <?php elseif ($section === 'testimonials'): ?>
                        <div class="col-md-4"><label class="form-label">Name</label><input type="text" name="name" id="f_name" class="form-control" required></div>
                        <div class="col-md-4"><label class="form-label">Designation</label><input type="text" name="designation" id="f_designation" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label">Company</label><input type="text" name="company" id="f_company" class="form-control"></div>
                        <div class="col-md-8"><label class="form-label">Content</label><textarea name="content" id="f_content" class="form-control" rows="2" required></textarea></div>
                        <div class="col-md-2"><label class="form-label">Rating</label><input type="number" name="rating" id="f_rating" class="form-control" min="1" max="5" value="5"></div>
                        <div class="col-md-2"><label class="form-label">Sort Order</label><input type="number" name="sort_order" id="f_sort" class="form-control" value="0"></div>
                        <?php endif; ?>
                        <div class="col-12 d-flex align-items-center gap-3">
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" id="f_active" checked><label class="form-check-label" for="f_active">Active</label></div>
                            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg"></i> Save</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Items List -->
        <div class="data-table">
            <div class="data-table-header"><h5 class="data-table-title mb-0"><?= ucfirst($section); ?> (<?= count($items); ?>)</h5></div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <?php if ($section === 'features'): ?><th>Icon</th><th>Title</th><th>Description</th>
                            <?php elseif ($section === 'faqs'): ?><th>Question</th><th>Answer</th>
                            <?php else: ?><th>Name</th><th>Content</th><th>Rating</th>
                            <?php endif; ?>
                            <th>Status</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= $item['sort_order']; ?></td>
                            <?php if ($section === 'features'): ?>
                            <td><i class="bi <?= e($item['icon']); ?> text-primary" style="font-size: 1.25rem;"></i></td>
                            <td class="fw-bold"><?= e($item['title']); ?></td>
                            <td style="max-width: 250px; font-size: 0.875rem;" class="text-truncate"><?= e($item['description']); ?></td>
                            <?php elseif ($section === 'faqs'): ?>
                            <td class="fw-bold"><?= e($item['question']); ?></td>
                            <td style="max-width: 300px; font-size: 0.875rem;" class="text-truncate"><?= e($item['answer']); ?></td>
                            <?php else: ?>
                            <td class="fw-bold"><?= e($item['name']); ?></td>
                            <td style="max-width: 250px; font-size: 0.875rem;" class="text-truncate"><?= e($item['content']); ?></td>
                            <td><?php for($i=0;$i<($item['rating'] ?? 5);$i++): ?><i class="bi bi-star-fill text-warning" style="font-size: 0.75rem;"></i><?php endfor; ?></td>
                            <?php endif; ?>
                            <td><span class="status-badge status-<?= $item['is_active'] ? 'active' : 'inactive'; ?>"><?= $item['is_active'] ? 'Active' : 'Hidden'; ?></span></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button class="btn btn-icon btn-sm" style="background: var(--bg-secondary); border: 1px solid var(--border-color);" onclick="editItem(<?= htmlspecialchars(json_encode($item)); ?>)"><i class="bi bi-pencil"></i></button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete?')"><?= CSRF::tokenField(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="item_id" value="<?= $item['id']; ?>"><button class="btn btn-icon btn-sm" style="background: rgba(239,68,68,0.1); color: var(--danger); border: 1px solid rgba(239,68,68,0.2);"><i class="bi bi-trash3"></i></button></form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<script>
function editItem(item) {
    document.getElementById('editItemId').value = item.id;
    const section = '<?= $section; ?>';
    if (section === 'features') {
        document.getElementById('f_title').value = item.title;
        document.getElementById('f_icon').value = item.icon;
        document.getElementById('f_desc').value = item.description;
    } else if (section === 'faqs') {
        document.getElementById('f_question').value = item.question;
        document.getElementById('f_answer').value = item.answer;
    } else {
        document.getElementById('f_name').value = item.name;
        document.getElementById('f_designation').value = item.designation || '';
        document.getElementById('f_company').value = item.company || '';
        document.getElementById('f_content').value = item.content;
        document.getElementById('f_rating').value = item.rating || 5;
    }
    document.getElementById('f_sort').value = item.sort_order;
    document.getElementById('f_active').checked = item.is_active == 1;
    window.scrollTo({top: 0, behavior: 'smooth'});
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
