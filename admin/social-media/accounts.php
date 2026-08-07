<?php
$current_page = 'social-media/accounts.php';
require_once __DIR__ . '/../../config/DbConnection.php';
include_once __DIR__ . '/../admin_header.php';
?>
<link href="<?php echo SITE_URL; ?>/admin/social-media/assets/social-media.css" rel="stylesheet">
<div class="admin-content">
    <div class="container-fluid py-4">
        <h2 class="mb-4 fw-bold">Connected Accounts</h2>
        <div class="row">
            <?php 
            $platforms = [
                ['name' => 'Facebook', 'icon' => 'fab fa-facebook', 'color' => '#1877F2'],
                ['name' => 'Instagram', 'icon' => 'fab fa-instagram', 'color' => '#E4405F'],
                ['name' => 'Twitter', 'icon' => 'fab fa-x-twitter', 'color' => '#000000'],
                ['name' => 'LinkedIn', 'icon' => 'fab fa-linkedin', 'color' => '#0A66C2'],
                ['name' => 'Telegram', 'icon' => 'fab fa-telegram', 'color' => '#0088CC'],
                ['name' => 'Pinterest', 'icon' => 'fab fa-pinterest', 'color' => '#E60023']
            ];
            foreach ($platforms as $p):
            ?>
            <div class="col-xl-4 col-md-6 mb-4">
                <div class="card shadow h-100" style="border-radius: 15px; border: none; border-top: 5px solid <?php echo $p['color']; ?>;">
                    <div class="card-body text-center p-4">
                        <i class="<?php echo $p['icon']; ?> fa-4x mb-3" style="color: <?php echo $p['color']; ?>;"></i>
                        <h4 class="card-title fw-bold mb-3"><?php echo $p['name']; ?></h4>
                        <div class="mb-3">
                            <span class="badge bg-secondary rounded-pill">Disconnected</span>
                        </div>
                        <div class="d-grid gap-2">
                            <button class="btn mdb-ripple text-white" style="background-color: <?php echo $p['color']; ?>; border-radius: 30px;">
                                <i class="fas fa-plug me-2"></i> Connect
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<style>
.card { transition: all 0.3s ease; }
.card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important; }
</style>
<script></script>
<?php include_once __DIR__ . '/../admin_footer.php'; ?>