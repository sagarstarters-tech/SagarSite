<?php
/**
 * WAPI SaaS - Contacts Management
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
Auth::requireActivePlan();

$db = Database::getInstance();
$settings = new Settings();
$userId = $_SESSION['user_id'];

$hideNav = true; // Prevents landing page nav from appearing in dashboard

// Handle Export (CSV & VCF)
if (!empty($_GET['export'])) {
    $exportFormat = $_GET['export'];
    $allContacts = $db->fetchAll("SELECT name, phone, email, company, tags, notes FROM contacts WHERE user_id = ? ORDER BY id DESC", [$userId]);
    
    if ($exportFormat === 'vcf') {
        header('Content-Type: text/vcard; charset=utf-8');
        header('Content-Disposition: attachment; filename=wapi_contacts_' . date('Ymd_His') . '.vcf');
        $output = "";
        foreach ($allContacts as $c) {
            $output .= "BEGIN:VCARD\r\n";
            $output .= "VERSION:3.0\r\n";
            $output .= "FN:" . $c['name'] . "\r\n";
            if (!empty($c['phone'])) {
                $output .= "TEL;TYPE=CELL:" . $c['phone'] . "\r\n";
            }
            if (!empty($c['email'])) {
                $output .= "EMAIL;TYPE=WORK:" . $c['email'] . "\r\n";
            }
            if (!empty($c['company'])) {
                $output .= "ORG:" . $c['company'] . "\r\n";
            }
            if (!empty($c['notes'])) {
                $cleanNotes = str_replace(["\r\n", "\n", "\r"], "\\n", $c['notes']);
                $output .= "NOTE:" . $cleanNotes . "\r\n";
            }
            $output .= "END:VCARD\r\n";
        }
        echo $output;
        exit;
    } else {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=wapi_contacts_' . date('Ymd_His') . '.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Name', 'Phone', 'Email', 'Company', 'Tags', 'Notes']);
        foreach ($allContacts as $c) {
            fputcsv($output, [$c['name'], $c['phone'], $c['email'], $c['company'], $c['tags'], $c['notes']]);
        }
        fclose($output);
        exit;
    }
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && CSRF::validateToken()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $data = [
            'user_id' => $userId,
            'name' => sanitize($_POST['name']),
            'phone' => sanitize($_POST['phone']),
            'email' => sanitizeEmail($_POST['email'] ?? ''),
            'company' => sanitize($_POST['company'] ?? ''),
            'tags' => sanitize($_POST['tags'] ?? ''),
            'notes' => sanitize($_POST['notes'] ?? ''),
            'status' => sanitize($_POST['status'] ?? 'Lead'),
            'source' => sanitize($_POST['source'] ?? 'Direct'),
            'estimated_value' => sanitizeFloat($_POST['estimated_value'] ?? 0)
        ];

        if ($action === 'edit' && !empty($_POST['contact_id'])) {
            $db->update('contacts', $data, 'id = ? AND user_id = ?', [sanitizeInt($_POST['contact_id']), $userId]);
            setFlash('success', 'Contact updated.');
        } else {
            $db->insert('contacts', $data);
            setFlash('success', 'Contact added.');
        }
    } elseif ($action === 'delete') {
        $db->delete('contacts', 'id = ? AND user_id = ?', [sanitizeInt($_POST['contact_id']), $userId]);
        setFlash('success', 'Contact deleted.');
    } elseif ($action === 'bulk_delete' && !empty($_POST['contact_ids'])) {
        $ids = array_map('intval', $_POST['contact_ids']);
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $params = array_merge($ids, [$userId]);
            $db->run("DELETE FROM contacts WHERE id IN ($placeholders) AND user_id = ?", $params);
            setFlash('success', count($ids) . ' contacts deleted.');
        }
    } elseif ($action === 'import' && isset($_FILES['import_file'])) {
        $file = $_FILES['import_file']['tmp_name'];
        if (is_uploaded_file($file)) {
            $handle = fopen($file, "r");
            $header = fgetcsv($handle); // Skip header row
            $imported = 0;
            while (($row = fgetcsv($handle)) !== false) {
                $name = sanitize($row[0] ?? '');
                $phone = sanitize($row[1] ?? '');
                $cleanPhone = preg_replace('/[^0-9+]/', '', $phone);
                
                if (!empty($name) && !empty($cleanPhone)) {
                    if (!$db->exists('contacts', 'user_id = ? AND phone = ?', [$userId, $cleanPhone])) {
                        $db->insert('contacts', [
                            'user_id' => $userId,
                            'name' => $name,
                            'phone' => $cleanPhone,
                            'email' => sanitizeEmail($row[2] ?? ''),
                            'company' => sanitize($row[3] ?? ''),
                            'tags' => sanitize($row[4] ?? ''),
                            'notes' => sanitize($row[5] ?? '')
                        ]);
                        $imported++;
                    }
                }
            }
            fclose($handle);
            setFlash('success', "$imported contacts imported successfully.");
        } else {
            setFlash('danger', 'Failed to read the imported file.');
        }
    } elseif ($action === 'import_vcf' && isset($_FILES['import_file'])) {
        $file = $_FILES['import_file']['tmp_name'];
        if (is_uploaded_file($file)) {
            $vcardContent = file_get_contents($file);
            $lines = preg_split('/\r\n|\r|\n/', $vcardContent);
            $contact = null;
            $imported = 0;
            
            $logicalLines = [];
            $currentLine = '';
            foreach ($lines as $line) {
                if (preg_match('/^[ \t]+/', $line)) {
                    $currentLine .= ltrim($line);
                } else {
                    if ($currentLine !== '') $logicalLines[] = $currentLine;
                    $currentLine = $line;
                }
            }
            if ($currentLine !== '') $logicalLines[] = $currentLine;

            foreach ($logicalLines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                list($rawKey, $val) = array_pad(explode(':', $line, 2), 2, '');
                $keyParts = explode(';', strtoupper($rawKey));
                $keyGrouping = explode('.', $keyParts[0]);
                $keyMain = end($keyGrouping); 

                if ($keyMain === 'BEGIN' && strtoupper($val) === 'VCARD') {
                    $contact = ['name' => '', 'phone' => '', 'email' => '', 'company' => ''];
                } elseif ($keyMain === 'END' && strtoupper($val) === 'VCARD' && $contact !== null) {
                    if (empty($contact['name']) && !empty($contact['n_name'])) {
                         $contact['name'] = $contact['n_name'];
                    }
                    if (!empty($contact['name']) && !empty($contact['phone'])) {
                         $cleanPhone = preg_replace('/[^0-9+]/', '', $contact['phone']);
                         if (!$db->exists('contacts', 'user_id = ? AND phone = ?', [$userId, $cleanPhone])) {
                             $db->insert('contacts', [
                                'user_id' => $userId,
                                'name' => sanitize(trim($contact['name'])),
                                'phone' => $cleanPhone,
                                'email' => sanitizeEmail($contact['email']),
                                'company' => sanitize(trim($contact['company'])),
                                'tags' => '',
                                'notes' => ''
                            ]);
                            $imported++;
                         }
                    }
                    $contact = null;
                } elseif ($contact !== null) {
                    if ($keyMain === 'FN') {
                        $contact['name'] = str_replace('\;', ';', str_replace('\,', ',', $val));
                    } elseif ($keyMain === 'N' && empty($contact['name'])) {
                        $nameParts = explode(';', str_replace('\;', ';', $val));
                        $contact['n_name'] = trim(($nameParts[1] ?? '') . ' ' . ($nameParts[0] ?? ''));
                    } elseif ($keyMain === 'TEL' && empty($contact['phone'])) { 
                        $contact['phone'] = str_replace('tel:', '', strtolower($val));
                    } elseif ($keyMain === 'EMAIL' && empty($contact['email'])) {
                        $contact['email'] = $val;
                    } elseif ($keyMain === 'ORG') {
                        $company = str_replace('\;', ';', str_replace('\,', ',', $val));
                        $compParts = explode(';', $company);
                        $contact['company'] = $compParts[0] ?? '';
                    }
                }
            }
            setFlash('success', "$imported contacts imported successfully from VCF.");
        } else {
            setFlash('danger', 'Failed to read the imported VCF file.');
        }
    }
    redirect('dashboard/contacts.php');
}

// Pagination & search
$search = sanitize($_GET['search'] ?? '');
$page = max(1, sanitizeInt($_GET['page'] ?? 1));
$where = 'user_id = ?';
$params = [$userId];

if ($search) {
    $where .= " AND (name LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $params = array_merge($params, ["%{$search}%", "%{$search}%", "%{$search}%"]);
}

$totalContacts = $db->count('contacts', $where, $params);
$pagination = paginate($totalContacts, $page);
$contacts = $db->fetchAll("SELECT * FROM contacts WHERE {$where} ORDER BY created_at DESC LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}", $params);

$pageTitle = 'Contacts';
$extraCss = [asset('assets/css/dashboard.css')];
$extraJs = [asset('assets/js/admin.js')];
include __DIR__ . '/../includes/header.php';
?>

<style>
    .table tbody tr.row-selected td {
        background-color: rgba(108, 99, 255, 0.08) !important;
    }
    .table tbody tr.row-selected td:first-child {
        border-left: 3px solid var(--primary, #6c63ff);
    }
</style>

<div class="dashboard-wrapper">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="main-content">
        <div class="dash-header">
            <div>
                <h1 class="dash-title">Contacts</h1>
                <div class="dash-breadcrumb"><a href="<?= baseUrl('dashboard/'); ?>">Dashboard</a><i class="bi bi-chevron-right"></i><span>Contacts</span></div>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary btn-sm d-lg-none" id="mobileSidebarToggle"><i class="bi bi-list"></i></button>

                <div class="dropdown">
                    <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Export Contacts">
                        <i class="bi bi-download"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size: 14.5px;">
                        <li><a class="dropdown-item py-2" href="?export=csv"><i class="bi bi-filetype-csv me-2 text-primary"></i> Export CSV</a></li>
                        <li><a class="dropdown-item py-2" href="?export=vcf"><i class="bi bi-person-lines-fill me-2 text-success"></i> Export VCF</a></li>
                    </ul>
                </div>

                <div class="dropdown">
                    <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Import Contacts">
                        <i class="bi bi-upload"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size: 14.5px;">
                        <li><a class="dropdown-item py-2" href="#" data-bs-toggle="modal" data-bs-target="#importModal"><i class="bi bi-filetype-csv me-2 text-primary"></i> Import CSV</a></li>
                        <li><a class="dropdown-item py-2" href="#" data-bs-toggle="modal" data-bs-target="#importVcfModal"><i class="bi bi-person-lines-fill me-2 text-success"></i> Import VCF</a></li>
                    </ul>
                </div>

                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#contactModal" onclick="resetContactForm()"><i class="bi bi-plus-lg"></i> Add Contact</button>
            </div>
        </div>

        <?php $flash = getFlash(); if ($flash): ?>
            <div class="alert alert-<?= $flash['type']; ?> fade-in"><i class="bi bi-check-circle-fill"></i> <?= e($flash['message']); ?></div>
        <?php endif; ?>

        <div class="data-table">
            <div class="data-table-header">
                <div class="d-flex align-items-center gap-3">
                    <h5 class="data-table-title mb-0">All Contacts (<?= $totalContacts; ?>)</h5>
                    <button type="button" id="bulkDeleteBtn" class="btn btn-sm btn-outline-danger d-none" onclick="submitBulkDelete()"><i class="bi bi-trash3"></i> Delete Selected</button>
                </div>
                <form method="GET" class="d-flex gap-2">
                    <div class="search-box"><i class="bi bi-search"></i><input type="text" name="search" class="form-control" placeholder="Search contacts..." value="<?= e($search); ?>"></div>
                </form>
            </div>
            
            <form method="POST" id="bulkDeleteForm" class="d-none">
                <?= CSRF::tokenField(); ?>
                <input type="hidden" name="action" value="bulk_delete">
            </form>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th style="width: 40px;"><input class="form-check-input" type="checkbox" id="selectAll"></th><th>Name</th><th>Phone</th><th>Email</th><th>Tags</th><th>Added</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if (empty($contacts)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4"><i class="bi bi-people" style="font-size: 2rem;"></i><br>No contacts yet</td></tr>
                        <?php else: ?>
                        <?php foreach ($contacts as $contact): ?>
                        <tr>
                            <td><input class="form-check-input contact-checkbox" type="checkbox" name="contact_ids[]" value="<?= $contact['id']; ?>" form="bulkDeleteForm"></td>
                            <td><div class="user-info"><div class="user-avatar"><?= strtoupper(substr($contact['name'], 0, 1)); ?></div><div class="fw-bold"><?= e($contact['name']); ?></div></div></td>
                            <td><?= e($contact['phone']); ?></td>
                            <td style="font-size: 0.875rem;"><?= e($contact['email'] ?: '-'); ?></td>
                            <td><?php if ($contact['tags']): foreach (explode(',', $contact['tags']) as $tag): ?>
                                <span class="badge-custom" style="background: var(--primary-bg); color: var(--primary); margin: 1px;"><?= e(trim($tag)); ?></span>
                            <?php endforeach; endif; ?></td>
                            <td style="font-size: 0.8125rem; color: var(--text-muted);"><?= timeAgo($contact['created_at']); ?></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="<?= baseUrl('dashboard/messages.php?to=' . urlencode($contact['phone'])); ?>" class="btn btn-icon btn-sm" style="background: rgba(37,211,102,0.1); color: var(--whatsapp); border: 1px solid rgba(37,211,102,0.2);" title="Send Message"><i class="bi bi-send"></i></a>
                                    <button class="btn btn-icon btn-sm" style="background: var(--bg-secondary); border: 1px solid var(--border-color);" onclick="editContact(<?= htmlspecialchars(json_encode($contact)); ?>)" title="Edit"><i class="bi bi-pencil"></i></button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this contact?')">
                                        <?= CSRF::tokenField(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="contact_id" value="<?= $contact['id']; ?>">
                                        <button class="btn btn-icon btn-sm" style="background: rgba(239,68,68,0.1); color: var(--danger); border: 1px solid rgba(239,68,68,0.2);" title="Delete"><i class="bi bi-trash3"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="p-3"><?= renderPagination($pagination, '?search=' . urlencode($search) . '&page=%d'); ?></div>
        </div>
    </main>
</div>

<!-- Contact Modal -->
<div class="modal fade" id="contactModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="contactForm">
                <?= CSRF::tokenField(); ?>
                <input type="hidden" name="action" id="contactAction" value="add">
                <input type="hidden" name="contact_id" id="contactId" value="">
                <div class="modal-header"><h5 class="modal-title" id="contactModalTitle">Add Contact</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Name *</label><input type="text" name="name" id="contactName" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label">Phone *</label><input type="text" name="phone" id="contactPhone" class="form-control" placeholder="+919876543210" required></div>
                        <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" id="contactEmail" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">Company</label><input type="text" name="company" id="contactCompany" class="form-control"></div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" id="contactStatus" class="form-control">
                                <option value="Lead">Lead</option>
                                <option value="Contacted">Contacted</option>
                                <option value="Qualified">Qualified</option>
                                <option value="Proposal">Proposal</option>
                                <option value="Won">Won</option>
                                <option value="Lost">Lost</option>
                            </select>
                        </div>
                        <div class="col-md-6"><label class="form-label">Source</label><input type="text" name="source" id="contactSource" class="form-control" placeholder="Website, Ads, etc."></div>
                        <div class="col-md-6"><label class="form-label">Est. Value (₹)</label><input type="number" name="estimated_value" id="contactValue" class="form-control" value="0"></div>
                        <div class="col-12"><label class="form-label">Tags</label><input type="text" name="tags" id="contactTags" class="form-control" placeholder="vip, customer, lead"></div>
                        <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" id="contactNotes" class="form-control" rows="2"></textarea></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <?= CSRF::tokenField(); ?>
                <input type="hidden" name="action" value="import">
                <div class="modal-header"><h5 class="modal-title">Import Contacts (CSV)</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Upload CSV File</label>
                        <input type="file" name="import_file" class="form-control" accept=".csv" required>
                        <div class="form-text mt-2">
                            <strong>Required Format:</strong><br>
                            Ensure your CSV has exactly these columns in order:<br>
                            <code>Name, Phone, Email, Company, Tags, Notes</code><br>
                            <small class="text-muted">(Phone numbers should include country code without '+')</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i> Import</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Import VCF Modal -->
<div class="modal fade" id="importVcfModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <?= CSRF::tokenField(); ?>
                <input type="hidden" name="action" value="import_vcf">
                <div class="modal-header"><h5 class="modal-title">Import Contacts (VCF)</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Upload VCF File</label>
                        <input type="file" name="import_file" class="form-control" accept=".vcf,text/vcard" required>
                        <div class="form-text mt-2">
                            <strong>Supported Format:</strong><br>
                            Standard vCard (.vcf) format. Needs Name (FN/N) and Phone number (TEL) to be correctly imported.
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i> Import VCF</button></div>
            </form>
        </div>
    </div>
</div>

<script>
function resetContactForm() {
    document.getElementById('contactAction').value = 'add';
    document.getElementById('contactId').value = '';
    document.getElementById('contactModalTitle').textContent = 'Add Contact';
    document.getElementById('contactForm').reset();
}

function editContact(c) {
    document.getElementById('contactAction').value = 'edit';
    document.getElementById('contactId').value = c.id;
    document.getElementById('contactModalTitle').textContent = 'Edit Contact';
    document.getElementById('contactName').value = c.name;
    document.getElementById('contactPhone').value = c.phone;
    document.getElementById('contactEmail').value = c.email || '';
    document.getElementById('contactCompany').value = c.company || '';
    document.getElementById('contactTags').value = c.tags || '';
    document.getElementById('contactNotes').value = c.notes || '';
    document.getElementById('contactStatus').value = c.status || 'Lead';
    document.getElementById('contactSource').value = c.source || 'Direct';
    document.getElementById('contactValue').value = c.estimated_value || 0;
    new bootstrap.Modal(document.getElementById('contactModal')).show();
}

document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.contact-checkbox');
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');

    function toggleBulkBtn() {
        const checkedCount = document.querySelectorAll('.contact-checkbox:checked').length;
        if (checkedCount > 0) {
            bulkDeleteBtn.classList.remove('d-none');
            bulkDeleteBtn.innerHTML = '<i class="bi bi-trash3"></i> Delete Selected (' + checkedCount + ')';
        } else {
            bulkDeleteBtn.classList.add('d-none');
        }
    }

    if(selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(cb => {
                cb.checked = this.checked;
                if (this.checked) {
                    cb.closest('tr').classList.add('row-selected');
                } else {
                    cb.closest('tr').classList.remove('row-selected');
                }
            });
            toggleBulkBtn();
        });
        
        checkboxes.forEach(cb => {
            cb.addEventListener('change', function() {
                if (!this.checked) selectAll.checked = false;
                if (document.querySelectorAll('.contact-checkbox:checked').length === checkboxes.length) {
                    selectAll.checked = true;
                }
                
                if (this.checked) {
                    this.closest('tr').classList.add('row-selected');
                } else {
                    this.closest('tr').classList.remove('row-selected');
                }
                
                toggleBulkBtn();
            });
        });
    }
});

function submitBulkDelete() {
    if (confirm('Are you sure you want to delete these selected contacts?')) {
        document.getElementById('bulkDeleteForm').submit();
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
