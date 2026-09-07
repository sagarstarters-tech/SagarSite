<?php
include 'includes/header.php';

// Sensible defaults
$about_hero_title = $global_settings['about_hero_title'] ?? 'About Us';
$about_hero_subtitle = $global_settings['about_hero_subtitle'] ?? 'Learn more about our journey and values.';
$about_who_title = $global_settings['about_who_title'] ?? 'Who We Are';
$about_who_desc1 = $global_settings['about_who_desc1'] ?? 'Welcome to Sagar Starter\'s. We are dedicated to providing you the very best of products, with an emphasis on quality, customer service, and uniqueness.';
$about_who_desc2 = $global_settings['about_who_desc2'] ?? 'Founded with a passion for modern aesthetics and functional design, we have come a long way from our beginnings.';
$about_who_image = $global_settings['about_who_image'] ?? '';

// Design Settings
$c_heading_color = $global_settings['about_heading_color'] ?? '#0d6efd';
$c_heading_fs    = intval($global_settings['about_heading_font_size'] ?? 32);
$c_body_fs       = intval($global_settings['about_body_font_size'] ?? 16);
$c_icon_color    = $global_settings['about_icon_color'] ?? '#0d6efd';
$c_card_bg       = $global_settings['about_card_bg'] ?? '#ffffff';

// Legal Documents Settings
$about_docs_enabled  = $global_settings['about_docs_enabled'] ?? '1';
$about_docs_title    = $global_settings['about_docs_title'] ?? 'Government Certifications & Legal Documents';
$about_docs_subtitle = $global_settings['about_docs_subtitle'] ?? 'Official compliance, registration certificates, and quality standards of Sagar Starters.';

// Fetch active legal documents
$legal_docs = [];
if ($about_docs_enabled === '1') {
    $docs_q = $conn->query("SELECT * FROM documents WHERE status = 1 ORDER BY sort_order ASC, id ASC");
    if ($docs_q && $docs_q->num_rows > 0) {
        while ($d = $docs_q->fetch_assoc()) {
            $legal_docs[] = $d;
        }
    }
}

$features = [
    [
        'icon' => $global_settings['about_f_icon1'] ?? 'fas fa-truck',
        'title' => $global_settings['about_f_title1'] ?? 'Fast Delivery',
        'desc' => $global_settings['about_f_desc1'] ?? 'We ensure your packages arrive on time, every time safely to your doorstep.'
    ],
    [
        'icon' => $global_settings['about_f_icon2'] ?? 'fas fa-hand-holding-heart',
        'title' => $global_settings['about_f_title2'] ?? 'Quality Promise',
        'desc' => $global_settings['about_f_desc2'] ?? 'Every item is carefully inspected to meet our strict quality and design standards.'
    ],
    [
        'icon' => $global_settings['about_f_icon3'] ?? 'fas fa-headset',
        'title' => $global_settings['about_f_title3'] ?? '24/7 Support',
        'desc' => $global_settings['about_f_desc3'] ?? 'Our dedicated customer service team is always here to help you when needed.'
    ]
];
?>

<style>
/* Legal Documents Frontend Styling */
.legal-card {
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    border: 1px solid rgba(0,0,0,0.08);
}
.legal-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 14px 28px rgba(0,0,0,0.1), 0 10px 10px rgba(0,0,0,0.04) !important;
    border-color: rgba(13, 110, 253, 0.3);
}
.doc-img-container {
    position: relative;
    overflow: hidden;
    background: #f8f9fa;
    cursor: pointer;
    border-top-left-radius: 1rem;
    border-top-right-radius: 1rem;
}
.doc-img-container img {
    transition: transform 0.4s ease;
}
.doc-img-container:hover img {
    transform: scale(1.04);
}
.doc-overlay {
    position: absolute;
    inset: 0;
    background: rgba(15, 23, 42, 0.45);
    backdrop-filter: blur(2px);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}
.doc-img-container:hover .doc-overlay {
    opacity: 1;
}
.badge-verified-seal {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: #fff;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.35rem 0.65rem;
    border-radius: 50rem;
    box-shadow: 0 2px 6px rgba(16, 185, 129, 0.3);
}
.copy-badge-btn {
    cursor: pointer;
    transition: color 0.2s;
}
.copy-badge-btn:hover {
    color: #0d6efd !important;
}
</style>

<?php 
$hero_bg_style = "background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);";
if (!empty($global_settings['hero_banner_about'])) {
    $img_url = htmlspecialchars(resolve_image_url($global_settings['hero_banner_about']));
    if (!empty($img_url) && strpos($img_url, 'placeholder') === false) {
        $hero_bg_style = "background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('{$img_url}') center/cover no-repeat !important;";
    }
}
?>

<!-- Hero Section -->
<div class="bg-primary text-white py-5 mb-5" style="<?php echo $hero_bg_style; ?>">
    <div class="container py-5 text-center">
        <h1 class="display-4 fw-bold mb-3 montserrat"><?php echo htmlspecialchars($about_hero_title); ?></h1>
        <p class="lead mb-0"><?php echo htmlspecialchars($about_hero_subtitle); ?></p>
    </div>
</div>

<div class="container mb-5">
    <!-- Who We Are Section -->
    <div class="row align-items-center mb-5 pb-4">
        <div class="col-md-6 mb-4 mb-md-0">
            <?php if (!empty($about_who_image)): ?>
                <img src="<?php echo htmlspecialchars(resolve_image_url($about_who_image)); ?>" alt="Our Team" class="img-fluid rounded-4 shadow-lg w-100" style="max-height: 440px; object-fit: cover;">
            <?php else: ?>
                <img src="https://images.unsplash.com/photo-1522071820081-009f0129c71c?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" alt="Our Team" class="img-fluid rounded-4 shadow-lg w-100" style="max-height: 440px; object-fit: cover;">
            <?php endif; ?>
        </div>
        <div class="col-md-6 px-md-5">
            <h2 class="fw-bold mb-4 montserrat" style="color:<?php echo htmlspecialchars($c_heading_color); ?> !important; font-size:<?php echo $c_heading_fs; ?>px !important;"><?php echo htmlspecialchars($about_who_title); ?></h2>
            <div class="text-muted lh-lg mb-4" style="font-size:<?php echo $c_body_fs; ?>px !important;">
                <?php echo nl2br(htmlspecialchars($about_who_desc1)); ?>
            </div>
            <div class="text-muted lh-lg" style="font-size:<?php echo $c_body_fs; ?>px !important;">
                <?php echo nl2br(htmlspecialchars($about_who_desc2)); ?>
            </div>
        </div>
    </div>
    
    <!-- Core Values / Features Section -->
    <div class="row text-center mb-5 pb-4">
        <?php foreach ($features as $f): ?>
        <div class="col-md-4 mb-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-4" style="background-color:<?php echo htmlspecialchars($c_card_bg); ?> !important;">
                <i class="<?php echo htmlspecialchars($f['icon']); ?> fa-3x mb-4" style="color:<?php echo htmlspecialchars($c_icon_color); ?> !important;"></i>
                <h4 class="fw-bold mb-3"><?php echo htmlspecialchars($f['title']); ?></h4>
                <p class="text-muted mb-0" style="font-size:<?php echo $c_body_fs; ?>px !important;"><?php echo htmlspecialchars($f['desc']); ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ── Company Legal Documents & Certifications Section ────── -->
    <?php if ($about_docs_enabled === '1' && !empty($legal_docs)): ?>
    <div class="mt-5 pt-4 border-top">
        <div class="text-center mx-auto mb-5" style="max-width: 760px;">
            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 py-2 rounded-pill fw-bold mb-3 d-inline-flex align-items-center gap-1">
                <i class="fas fa-shield-alt text-primary"></i> 100% Certified &amp; Regulatory Compliance
            </span>
            <h2 class="fw-bold montserrat mb-3" style="color:<?php echo htmlspecialchars($c_heading_color); ?> !important; font-size:<?php echo $c_heading_fs; ?>px !important;">
                <?php echo htmlspecialchars($about_docs_title); ?>
            </h2>
            <p class="text-muted" style="font-size:<?php echo $c_body_fs; ?>px !important;">
                <?php echo htmlspecialchars($about_docs_subtitle); ?>
            </p>
        </div>

        <div class="row g-4 justify-content-center">
            <?php foreach ($legal_docs as $doc): 
                $doc_img_url = resolve_image_url($doc['image']);
            ?>
            <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
                <div class="card h-100 legal-card rounded-4 bg-white shadow-sm overflow-hidden d-flex flex-column">
                    <!-- Thumbnail Container with Zoom Overlay -->
                    <div class="doc-img-container p-3 text-center border-bottom"
                         onclick="openFrontendDocModal('<?php echo htmlspecialchars(addslashes($doc['title'])); ?>', '<?php echo htmlspecialchars(addslashes($doc_img_url)); ?>', '<?php echo htmlspecialchars(addslashes($doc['doc_number'] ?? '')); ?>', '<?php echo htmlspecialchars(addslashes($doc['description'] ?? '')); ?>')">
                        <div class="ratio ratio-4x3 bg-white rounded-3 border overflow-hidden shadow-2xs d-flex align-items-center justify-content-center">
                            <img src="<?php echo htmlspecialchars($doc_img_url); ?>" 
                                 alt="<?php echo htmlspecialchars($doc['title']); ?>" 
                                 class="img-fluid w-100 h-100" 
                                 style="object-fit: contain;"
                                 loading="lazy"
                                 onerror="this.onerror=null; this.src='<?php echo ASSETS_URL; ?>/images/placeholder.svg';">
                        </div>
                        <div class="doc-overlay">
                            <span class="btn btn-light btn-sm rounded-pill px-3 shadow fw-bold">
                                <i class="fas fa-search-plus me-1 text-primary"></i> Inspect
                            </span>
                        </div>
                    </div>

                    <!-- Card Body -->
                    <div class="card-body p-3 d-flex flex-column">
                        <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                            <h6 class="fw-bold text-dark mb-0" style="font-size: 1rem; line-height: 1.35;">
                                <?php echo htmlspecialchars($doc['title']); ?>
                            </h6>
                            <span class="badge-verified-seal flex-shrink-0" title="Government / Officially Verified">
                                <i class="fas fa-check me-1"></i>Verified
                            </span>
                        </div>

                        <?php if (!empty($doc['doc_number'])): ?>
                            <div class="d-flex align-items-center justify-content-between p-2 rounded-3 bg-light border mb-2 small">
                                <span class="font-monospace fw-bold text-secondary text-truncate me-2" title="<?php echo htmlspecialchars($doc['doc_number']); ?>">
                                    <i class="fas fa-id-badge text-primary me-1"></i><?php echo htmlspecialchars($doc['doc_number']); ?>
                                </span>
                                <button type="button" class="btn btn-link p-0 text-muted copy-badge-btn" 
                                        onclick="copyDocText('<?php echo htmlspecialchars(addslashes($doc['doc_number'])); ?>', this)" 
                                        title="Copy Registration Number">
                                    <i class="far fa-copy"></i>
                                </button>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($doc['description'])): ?>
                            <p class="text-muted small mb-3 flex-grow-1" style="line-height: 1.4;">
                                <?php echo htmlspecialchars($doc['description']); ?>
                            </p>
                        <?php else: ?>
                            <div class="flex-grow-1"></div>
                        <?php endif; ?>

                        <div class="pt-2 mt-auto border-top">
                            <button type="button" class="btn btn-outline-primary btn-sm rounded-pill w-100 fw-semibold"
                                    onclick="openFrontendDocModal('<?php echo htmlspecialchars(addslashes($doc['title'])); ?>', '<?php echo htmlspecialchars(addslashes($doc_img_url)); ?>', '<?php echo htmlspecialchars(addslashes($doc['doc_number'] ?? '')); ?>', '<?php echo htmlspecialchars(addslashes($doc['description'] ?? '')); ?>')">
                                <i class="fas fa-eye me-1"></i> View Full Document
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Frontend Document Lightbox Modal -->
    <div class="modal fade" id="frontendDocModal" tabindex="-1" aria-labelledby="feDocTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content rounded-4 border-0 shadow-lg overflow-hidden">
                <div class="modal-header border-0 pb-0 pt-3 px-4 d-flex justify-content-between align-items-start">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill py-1 px-2 small">
                                <i class="fas fa-shield-alt me-1"></i>Official Verified Document
                            </span>
                            <span id="feDocNumBadge" class="badge bg-light text-dark border font-monospace py-1 px-2 small"></span>
                        </div>
                        <h5 class="modal-title fw-bold text-dark" id="feDocTitle"></h5>
                    </div>
                    <button type="button" class="btn-close" data-mdb-dismiss="modal" data-bs-dismiss="modal" onclick="hideModalSafely('frontendDocModal')" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3 p-md-4 text-center bg-light">
                    <div class="p-2 bg-white rounded-3 border shadow-sm d-inline-block w-100" style="max-height: 70vh; overflow: auto;">
                        <img src="" id="feDocImg" class="img-fluid rounded" alt="Document Certificate" style="max-height: 65vh; object-fit: contain;">
                    </div>
                    <div id="feDocDesc" class="mt-3 text-muted small text-start px-2"></div>
                </div>
                <div class="modal-footer border-0 pt-0 pb-3 px-4 d-flex justify-content-between align-items-center">
                    <span class="small text-muted"><i class="fas fa-check-circle text-success me-1"></i> Registered Sagar Starters compliance</span>
                    <div class="d-flex gap-2">
                        <a href="#" id="feDocOpenBtn" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                            <i class="fas fa-external-link-alt me-1"></i> Open Original Image
                        </a>
                        <button type="button" class="btn btn-secondary btn-sm rounded-pill px-3" data-mdb-dismiss="modal" data-bs-dismiss="modal" onclick="hideModalSafely('frontendDocModal')">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function showModalSafely(modalId) {
        var el = document.getElementById(modalId);
        if (!el) return;
        if (typeof mdb !== 'undefined' && mdb.Modal) {
            var inst = mdb.Modal.getInstance(el) || new mdb.Modal(el);
            inst.show();
            return;
        }
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            var inst = bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el);
            inst.show();
            return;
        }
        el.classList.add('show');
        el.style.display = 'block';
        el.removeAttribute('aria-hidden');
        el.setAttribute('aria-modal', 'true');
        var bd = document.getElementById(modalId + '_bd');
        if (!bd) {
            bd = document.createElement('div');
            bd.className = 'modal-backdrop fade show';
            bd.id = modalId + '_bd';
            bd.onclick = function() { hideModalSafely(modalId); };
            document.body.appendChild(bd);
        }
        document.body.classList.add('modal-open');
    }

    function hideModalSafely(modalId) {
        var el = document.getElementById(modalId);
        if (!el) return;
        if (typeof mdb !== 'undefined' && mdb.Modal) {
            var inst = mdb.Modal.getInstance(el);
            if (inst) { inst.hide(); return; }
        }
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            var inst = bootstrap.Modal.getInstance(el);
            if (inst) { inst.hide(); return; }
        }
        el.classList.remove('show');
        el.style.display = 'none';
        el.setAttribute('aria-hidden', 'true');
        el.removeAttribute('aria-modal');
        var bd = document.getElementById(modalId + '_bd');
        if (bd) bd.remove();
        document.body.classList.remove('modal-open');
    }

    function openFrontendDocModal(title, imgUrl, docNum, desc) {
        document.getElementById('feDocTitle').textContent = title;
        document.getElementById('feDocImg').src = imgUrl;
        document.getElementById('feDocOpenBtn').href = imgUrl;
        
        var numBadge = document.getElementById('feDocNumBadge');
        if (docNum && docNum.trim() !== '') {
            numBadge.textContent = 'Reg No: ' + docNum;
            numBadge.style.display = 'inline-block';
        } else {
            numBadge.style.display = 'none';
        }

        var descEl = document.getElementById('feDocDesc');
        if (desc && desc.trim() !== '') {
            descEl.textContent = desc;
            descEl.style.display = 'block';
        } else {
            descEl.style.display = 'none';
        }

        showModalSafely('frontendDocModal');
    }

    function copyDocText(text, btnEl) {
        if (!text) return;
        navigator.clipboard.writeText(text).then(function() {
            var icon = btnEl.querySelector('i');
            if (icon) {
                icon.className = 'fas fa-check text-success';
                setTimeout(function() {
                    icon.className = 'far fa-copy';
                }, 2000);
            }
        }).catch(function(err) {
            console.error('Failed to copy text: ', err);
        });
    }
    </script>
    <?php endif; ?>

</div>

<?php
include 'includes/footer.php';
?>
