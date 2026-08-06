<?php if (basename($_SERVER['PHP_SELF']) !== 'admin_login.php'): ?>
            </div> <!-- End p-4 -->
        </div> <!-- End Main Content col-md-10 -->
    </div> <!-- End row -->
</div> <!-- End container-fluid -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Show Password Toggle Logic
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('show-password-toggle')) {
            const parent = e.target.closest('.mb-3') || e.target.closest('.mb-4') || e.target.parentElement;
            const input = parent.querySelector('input[type="password"], input[data-show-pw="true"]');
            if (input) {
                if (e.target.checked) {
                    input.type = 'text';
                    input.setAttribute('data-show-pw', 'true');
                } else {
                    input.type = 'password';
                }
            }
        }
    });
});
</script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.querySelector('.admin-sidebar');
        const backdrop = document.getElementById('sidebarBackdrop');
        const sidebarLinks = document.querySelectorAll('.admin-sidebar .list-group-item[href]');
        
        if (sidebarToggle && sidebar && backdrop) {
            function openSidebar() {
                sidebar.classList.add('show');
                backdrop.classList.add('show');
                document.body.classList.add('sidebar-open');
                document.body.style.overflow = 'hidden';
            }
            
            function closeSidebar() {
                sidebar.classList.remove('show');
                backdrop.classList.remove('show');
                document.body.classList.remove('sidebar-open');
                document.body.style.overflow = '';
            }
            
            function toggleSidebar() {
                if (sidebar.classList.contains('show')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            }
            
            sidebarToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleSidebar();
            });
            
            backdrop.addEventListener('click', closeSidebar);
            
            // Handle sidebar items on mobile
            document.querySelectorAll('.admin-sidebar .list-group-item').forEach(item => {
                item.addEventListener('click', function(e) {
                    // If it's a collapse toggle (dropdown), don't close the sidebar
                    if (this.hasAttribute('data-mdb-toggle') || this.getAttribute('href') === '#') {
                        e.stopPropagation();
                        return;
                    }
                    
                    // If it's a real link and we are on mobile, close the sidebar after a delay
                    if (window.innerWidth < 992 && this.hasAttribute('href')) {
                        setTimeout(closeSidebar, 200);
                    }
                });
            });

            // Prevent dropdown clicks from closing sidebar
            document.querySelectorAll('.dropdown-toggle, .dropdown-menu').forEach(el => {
                el.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            });

            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(e) {
                if (window.innerWidth < 992 && 
                    sidebar.classList.contains('show') && 
                    !sidebar.contains(e.target) && 
                    !sidebarToggle.contains(e.target)) {
                    closeSidebar();
                }
            });
        }
    });
</script>
<?php endif; ?>

<!-- Upload Source Chooser Modal -->
<div class="modal fade" id="uploadSourceModal" tabindex="-1" aria-labelledby="uploadSourceModalLabel" aria-hidden="true" style="z-index: 1065;">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content rounded-4 border-0 shadow">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold" id="uploadSourceModalLabel">Choose Source</h5>
        <button type="button" class="btn-close" data-mdb-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center p-4">
        <button type="button" class="btn btn-primary w-100 mb-3 btn-custom py-3" id="btnUploadComputer">
            <i class="fas fa-desktop fs-4 mb-2 d-block"></i> Upload from Computer
        </button>
        <button type="button" class="btn btn-info w-100 btn-custom py-3 text-white" id="btnUploadGallery">
            <i class="fas fa-images fs-4 mb-2 d-block"></i> Choose from Gallery
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Media Gallery Selector Modal -->
<div class="modal fade" id="mediaGallerySelectorModal" tabindex="-1" aria-labelledby="mediaGallerySelectorModalLabel" aria-hidden="true" style="z-index: 1060;">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content rounded-4 border-0 shadow">
      <div class="modal-header">
        <h5 class="modal-title fw-bold" id="mediaGallerySelectorModalLabel"><i class="fas fa-images me-2 text-primary"></i>Media Gallery</h5>
        <button type="button" class="btn-close" data-mdb-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body bg-light p-4">
         <div class="row g-3" id="mediaGallerySelectorGrid">
             <div class="col-12 text-center text-muted py-5"><div class="spinner-border text-primary" role="status"></div><div class="mt-2">Loading gallery...</div></div>
         </div>
      </div>
    </div>
  </div>
</div>

<style>
.gallery-select-item {
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
    height: 150px;
    object-fit: cover;
    border-radius: 8px;
    border: 3px solid transparent;
}
.gallery-select-item:hover {
    transform: scale(1.03);
    box-shadow: 0 4px 10px rgba(0,0,0,0.15);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let currentFileInput = null;
    let bypassClick = false;
    let uploadSourceModalInstance = null;
    let mediaGallerySelectorModalInstance = null;

    // Delegate click for file inputs
    document.addEventListener('click', function(e) {
        let target = e.target;
        if(target.tagName !== 'INPUT' || target.type !== 'file') {
            return;
        }

        // Skip interception on the Media Library page itself
        if (window.location.pathname.includes('manage_media.php')) {
            return;
        }

        // If it's a file input, we intercept it unless bypassClick is true
        if(!bypassClick) {
            e.preventDefault();
            currentFileInput = target;
            if(!uploadSourceModalInstance) {
                uploadSourceModalInstance = new mdb.Modal(document.getElementById('uploadSourceModal'));
            }
            uploadSourceModalInstance.show();
        }
    });

    document.getElementById('btnUploadComputer').addEventListener('click', function() {
        if(currentFileInput) {
            bypassClick = true;
            currentFileInput.click();
            bypassClick = false; // Reset immediately
        }
        uploadSourceModalInstance.hide();
    });

    document.getElementById('btnUploadGallery').addEventListener('click', function() {
        uploadSourceModalInstance.hide();
        if(!mediaGallerySelectorModalInstance) {
            mediaGallerySelectorModalInstance = new mdb.Modal(document.getElementById('mediaGallerySelectorModal'));
        }
        mediaGallerySelectorModalInstance.show();
        loadMediaGallerySelector();
    });

    function loadMediaGallerySelector() {
        const grid = document.getElementById('mediaGallerySelectorGrid');
        grid.innerHTML = '<div class="col-12 text-center text-muted py-5"><div class="spinner-border text-primary" role="status"></div><div class="mt-2">Loading gallery...</div></div>';
        
        fetch('ajax_get_media.php')
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    grid.innerHTML = '';
                    if(data.data.length === 0) {
                        grid.innerHTML = '<div class="col-12 text-center text-muted py-5">No images found in gallery.</div>';
                        return;
                    }
                    data.data.forEach(item => {
                        if (!item.file_url || item.file_url.includes('placeholder') || item.file_url.includes('no-image')) {
                            return;
                        }
                        const col = document.createElement('div');
                        col.className = 'col-6 col-md-4 col-lg-3 col-xl-2';
                        col.innerHTML = `
                            <div class="card h-100 border-0 shadow-sm overflow-hidden bg-white">
                                <img src="${item.file_url}" class="card-img-top gallery-select-item w-100" data-url="${item.file_url}" data-name="${item.file_name}" alt="${item.original_name}" onerror="this.closest('.col-6').remove();">
                            </div>
                        `;
                        grid.appendChild(col);
                    });
                } else {
                    grid.innerHTML = `<div class="col-12 text-danger py-3 text-center">Failed to load media: ${data.error || 'Unknown error'}</div>`;
                }
            })
            .catch(err => {
                grid.innerHTML = `<div class="col-12 text-danger py-3 text-center">Error loading media: ${err.message}</div>`;
            });
    }

    document.getElementById('mediaGallerySelectorGrid').addEventListener('click', function(e) {
        if(e.target.classList.contains('gallery-select-item')) {
            const url = e.target.getAttribute('data-url');
            const fileName = e.target.getAttribute('data-name');
            selectImageFromGallery(url, fileName, e.target);
        }
    });

    async function selectImageFromGallery(url, fileName, imgElement) {
        if(!currentFileInput) return;
        
        // Visual feedback
        const oldBorder = imgElement.style.border;
        imgElement.style.border = '3px solid #3b71ca';
        imgElement.style.opacity = '0.7';

        // Add a small loading spinner over the image or button
        const overlay = document.createElement('div');
        overlay.className = 'position-absolute top-50 start-50 translate-middle';
        overlay.innerHTML = '<div class="spinner-border text-primary" role="status"></div>';
        imgElement.parentElement.style.position = 'relative';
        imgElement.parentElement.appendChild(overlay);

        try {
            // Also update any corresponding hidden path input in the form
            if (currentFileInput && currentFileInput.form) {
                let hiddenInput = currentFileInput.form.querySelector(`input[name="${currentFileInput.name}_path"]`) ||
                                  currentFileInput.form.querySelector(`input[name="${currentFileInput.id}_path"]`);
                if (hiddenInput) {
                    hiddenInput.value = url;
                }
            }

            // Immediately update live image preview on screen
            const container = currentFileInput.closest('.card-body') || currentFileInput.closest('.card') || currentFileInput.parentElement;
            if (container) {
                let imgPreview = container.querySelector('img');
                if (imgPreview) {
                    imgPreview.src = url;
                    imgPreview.style.display = 'inline-block';
                }
                const placeholderDiv = container.querySelector('.border-dashed');
                if (placeholderDiv) placeholderDiv.style.display = 'none';
            }

            // Fetch the image as a Blob to populate file input
            const response = await fetch(url);
            if(response.ok) {
                const blob = await response.blob();
                const file = new File([blob], fileName, { type: blob.type || 'image/jpeg' });
                const dt = new DataTransfer();
                dt.items.add(file);
                currentFileInput.files = dt.files;
            }
            
            // Trigger change event
            currentFileInput.dispatchEvent(new Event('change', { bubbles: true }));
            
            // Close modal
            mediaGallerySelectorModalInstance.hide();
        } catch(err) {
            console.error("Error fetching gallery image:", err);
            // Even if blob fetch failed (e.g. CORS), hidden path and live preview are set
            mediaGallerySelectorModalInstance.hide();
        } finally {
            imgElement.style.border = oldBorder;
            imgElement.style.opacity = '1';
            if (overlay.parentElement) overlay.remove();
        }
    }

    // Generic live preview listener for local computer file uploads
    document.addEventListener('change', function(e) {
        if (e.target.tagName === 'INPUT' && e.target.type === 'file' && e.target.files && e.target.files[0]) {
            const file = e.target.files[0];
            const container = e.target.closest('.card-body') || e.target.closest('.card') || e.target.parentElement;
            if (container) {
                let imgPreview = container.querySelector('img');
                if (imgPreview) {
                    imgPreview.src = URL.createObjectURL(file);
                    imgPreview.style.display = 'inline-block';
                }
                const placeholderDiv = container.querySelector('.border-dashed');
                if (placeholderDiv) placeholderDiv.style.display = 'none';
            }
        }
    });
});
</script>

<!-- MDB JS -->
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.0/mdb.min.js"></script>
</body>
</html>
