<?php
/**
 * Admin Sidebar Partial
 *
 * Data-driven sidebar rendered from config/menu.php.
 * Expects these variables available in scope:
 *   $menu          - array from config/menu.php
 *   $current_page  - basename($_SERVER['PHP_SELF'])
 *   $global_settings - app settings array
 */

if (!function_exists('admin_url')) {
    function admin_url(string $url): string {
        if (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0) {
            return $url;
        }
        $base = defined('ADMIN_BASE_URL') ? ADMIN_BASE_URL : '/admin/';
        return $base . ltrim($url, '/');
    }
}

/**
 * Helper: Determine if a menu item is "active" (expanded / highlighted).
 * Returns true if the current page is in the item's pages array.
 *
 * For child items with params, additionally checks GET params.
 */
function admin_menu_is_active(array $item): bool
{
    global $current_page;
    $cp = $current_page ?? basename($_SERVER['PHP_SELF']);
    $pages = $item['pages'] ?? [];

    $match = false;
    foreach ($pages as $p) {
        if ($cp === $p || basename($_SERVER['PHP_SELF']) === $p) {
            $match = true;
            break;
        }
    }

    if (!$match) {
        return false;
    }

    // If no param restriction, match on page alone
    if (empty($item['params'])) {
        return true;
    }

    // Check each required GET param
    foreach ($item['params'] as $key => $allowed_values) {
        $current_value = $_GET[$key] ?? null;
        if (!in_array($current_value, $allowed_values, true)) {
            return false;
        }
    }

    return true;
}

/**
 * Helper: Is any child of this group active?
 */
function admin_group_is_active(array $item): bool
{
    global $current_page;
    $cp = $current_page ?? basename($_SERVER['PHP_SELF']);

    if (!empty($item['url']) && empty($item['children'])) {
        return admin_menu_is_active($item);
    }
    foreach ($item['children'] ?? [] as $child) {
        if (!empty($child['children'])) {
            if (admin_group_is_active($child)) return true;
        } elseif (admin_menu_is_active($child)) {
            return true;
        }
    }
    // Also check top-level pages for the group
    $pages = $item['pages'] ?? [];
    return in_array($cp, $pages, true) || in_array(basename($_SERVER['PHP_SELF']), $pages, true);
}

$current_page_file = basename($_SERVER['PHP_SELF']);

// Fetch Orders Count for Notifications (Pending & Processing)
$pending_count = 0;
$processing_count = 0;
if (isset($conn)) {
    // Single query for both or separate for clarity
    $po_res = $conn->query("SELECT status, COUNT(*) as c FROM orders WHERE status IN ('pending', 'processing') GROUP BY status");
    if ($po_res) {
        while ($row = $po_res->fetch_assoc()) {
            if ($row['status'] === 'pending') $pending_count = (int)$row['c'];
            if ($row['status'] === 'processing') $processing_count = (int)$row['c'];
        }
    }
}

// Fetch New Orders Count (Received after last seen by admin)
$new_orders_count = 0;
if (isset($conn)) {
    $last_seen_order_id = isset($global_settings['admin_last_seen_order_id']) ? (int)$global_settings['admin_last_seen_order_id'] : -1;
    
    // If admin is currently viewing Orders page (manage_orders.php or order_details.php), mark all as seen
    if (in_array($current_page_file, ['manage_orders.php', 'order_details.php'], true)) {
        $max_o_res = $conn->query("SELECT MAX(id) as max_id FROM orders");
        if ($max_o_res && $row = $max_o_res->fetch_assoc()) {
            $latest_order_id = (int)($row['max_id'] ?? 0);
            if ($latest_order_id !== $last_seen_order_id) {
                $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('admin_last_seen_order_id', '$latest_order_id') ON DUPLICATE KEY UPDATE setting_value = '$latest_order_id'");
                $global_settings['admin_last_seen_order_id'] = $latest_order_id;
                $last_seen_order_id = $latest_order_id;
            }
        }
    }
    
    if ($last_seen_order_id === -1) {
        // Setting never initialized: initialize to current max id so historic orders don't flood badge
        $max_o_res = $conn->query("SELECT MAX(id) as max_id FROM orders");
        if ($max_o_res && $row = $max_o_res->fetch_assoc()) {
            $latest_order_id = (int)($row['max_id'] ?? 0);
            $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('admin_last_seen_order_id', '$latest_order_id') ON DUPLICATE KEY UPDATE setting_value = '$latest_order_id'");
            $global_settings['admin_last_seen_order_id'] = $latest_order_id;
            $last_seen_order_id = $latest_order_id;
        }
    }

    if ($last_seen_order_id >= 0 && !in_array($current_page_file, ['manage_orders.php', 'order_details.php'], true)) {
        $no_res = $conn->query("SELECT COUNT(*) as c FROM orders WHERE id > $last_seen_order_id");
        if ($no_res && $row = $no_res->fetch_assoc()) {
            $new_orders_count = (int)$row['c'];
        }
    }
}

// Fetch New Customers Count (Joined after last seen by admin)
$new_customers_count = 0;
if (isset($conn)) {
    $last_seen_cust_id = isset($global_settings['admin_last_seen_customer_id']) ? (int)$global_settings['admin_last_seen_customer_id'] : -1;
    
    // If admin is currently viewing Customers page (manage_users.php), mark all as seen
    if ($current_page_file === 'manage_users.php') {
        $max_res = $conn->query("SELECT MAX(id) as max_id FROM users WHERE role = 'user'");
        if ($max_res && $row = $max_res->fetch_assoc()) {
            $latest_id = (int)($row['max_id'] ?? 0);
            if ($latest_id !== $last_seen_cust_id) {
                $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('admin_last_seen_customer_id', '$latest_id') ON DUPLICATE KEY UPDATE setting_value = '$latest_id'");
                $global_settings['admin_last_seen_customer_id'] = $latest_id;
                $last_seen_cust_id = $latest_id;
            }
        }
    }
    
    if ($last_seen_cust_id === -1) {
        // Setting never initialized: initialize to current max id so historic customers don't flood badge
        $max_res = $conn->query("SELECT MAX(id) as max_id FROM users WHERE role = 'user'");
        if ($max_res && $row = $max_res->fetch_assoc()) {
            $latest_id = (int)($row['max_id'] ?? 0);
            $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('admin_last_seen_customer_id', '$latest_id') ON DUPLICATE KEY UPDATE setting_value = '$latest_id'");
            $global_settings['admin_last_seen_customer_id'] = $latest_id;
            $last_seen_cust_id = $latest_id;
        }
    }

    if ($last_seen_cust_id >= 0 && $current_page_file !== 'manage_users.php') {
        $nc_res = $conn->query("SELECT COUNT(*) as c FROM users WHERE role = 'user' AND id > $last_seen_cust_id");
        if ($nc_res && $row = $nc_res->fetch_assoc()) {
            $new_customers_count = (int)$row['c'];
        }
    }
}
?>
<div class="list-group list-group-flush mt-3 pb-5">

<?php foreach ($menu as $index => $item): ?>

    <?php if (!empty($item['divider'])): ?>
        <!-- Divider -->
        <div class="sidebar-divider my-3 mx-4" style="height: 1px; background: rgba(255,255,255,0.05);"></div>
        <?php continue; ?>
    <?php endif; ?>

    <?php
    $has_children = !empty($item['children']);
    $group_active = admin_group_is_active($item);
    $collapse_id  = 'menuCollapse_' . $index;
    $icon_full    = (strpos($item['icon'], 'fa-') === 0)
                    ? 'fas ' . $item['icon']
                    : $item['icon'];
    ?>

    <?php if ($has_children): ?>
        <!-- Collapsible Group -->
        <a class="list-group-item list-group-item-action <?php echo $group_active ? 'active' : ''; ?>"
           data-mdb-toggle="collapse"
           data-mdb-collapse-init
           href="#<?php echo $collapse_id; ?>"
           role="button"
           aria-expanded="<?php echo $group_active ? 'true' : 'false'; ?>"
           <?php if ($item['label'] === 'Orders'): ?>data-order-nav="true"<?php endif; ?>
           <?php if ($item['label'] === 'Customers'): ?>data-customer-nav="true"<?php endif; ?>>
            <i class="<?php echo $icon_full; ?>"></i>
            <span><?php echo $item['label']; ?></span>
            <?php if ($item['label'] === 'Orders' && $new_orders_count > 0): ?>
                <span class="badge rounded-pill bg-danger ms-2 order-notif-badge" style="font-size: 0.65rem; padding: 0.35em 0.65em;"><?php echo $new_orders_count; ?></span>
            <?php endif; ?>
            <?php if ($item['label'] === 'Customers' && $new_customers_count > 0): ?>
                <span class="badge rounded-pill bg-info ms-2 customer-notif-badge" style="font-size: 0.65rem; padding: 0.35em 0.65em;"><?php echo $new_customers_count; ?></span>
            <?php endif; ?>
            <i class="fas fa-chevron-down ms-auto" style="font-size:0.75rem;"></i>
        </a>

        <div class="collapse<?php echo $group_active ? ' show' : ''; ?>" id="<?php echo $collapse_id; ?>">
            <ul class="list-unstyled mb-0">
            <?php foreach ($item['children'] as $child_index => $child): ?>
                <?php
                $child_has_children = !empty($child['children']);
                $child_active = $child_has_children ? admin_group_is_active($child) : admin_menu_is_active($child);
                $child_collapse_id = $collapse_id . '_' . $child_index;
                $child_icon   = isset($child['icon'])
                    ? ((strpos($child['icon'], 'fab ') === 0 || strpos($child['icon'], 'fas ') === 0)
                        ? $child['icon']
                        : 'fas ' . $child['icon'])
                    : '';
                ?>
                
                <?php if ($child_has_children): ?>
                    <li>
                        <a class="list-group-item list-group-item-action <?php echo $child_active ? 'active' : ''; ?>"
                           data-mdb-toggle="collapse"
                           data-mdb-collapse-init
                           href="#<?php echo $child_collapse_id; ?>"
                           role="button"
                           aria-expanded="<?php echo $child_active ? 'true' : 'false'; ?>">
                            <?php if ($child_icon): ?><i class="<?php echo $child_icon; ?>"></i><?php endif; ?>
                            <span><?php echo $child['label']; ?></span>
                            <i class="fas fa-chevron-down ms-auto" style="font-size:0.7rem;"></i>
                        </a>
                        <div class="collapse<?php echo $child_active ? ' show' : ''; ?>" id="<?php echo $child_collapse_id; ?>">
                            <ul class="list-unstyled mb-0">
                                <?php foreach ($child['children'] as $subchild): ?>
                                <?php
                                $subchild_active = admin_menu_is_active($subchild);
                                ?>
                                <li>
                                    <a href="<?php echo htmlspecialchars(admin_url($subchild['url'])); ?>"
                                       class="list-group-item list-group-item-action <?php echo $subchild_active ? 'active' : ''; ?>">
                                        <span><?php echo $subchild['label']; ?></span>
                                    </a>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </li>
                <?php else: ?>
                    <li>
                        <a href="<?php echo htmlspecialchars(admin_url($child['url'])); ?>"
                           class="list-group-item list-group-item-action <?php echo $child_active ? 'active' : ''; ?>"
                           <?php if ($child['label'] === 'All Orders'): ?>data-order-nav="true"<?php endif; ?>
                           <?php if ($child['label'] === 'All Customers'): ?>data-customer-nav="true"<?php endif; ?>>
                            <?php if ($child_icon): ?><i class="<?php echo $child_icon; ?>"></i><?php endif; ?>
                            <span><?php echo $child['label']; ?></span>
                            <?php if ($child['label'] === 'All Orders' && $new_orders_count > 0): ?>
                                <span class="badge rounded-pill bg-danger ms-2 order-notif-badge" style="font-size: 0.6rem; padding: 0.25em 0.5em; text-white"><?php echo $new_orders_count; ?></span>
                            <?php endif; ?>
                            <?php if ($child['label'] === 'Pending' && $pending_count > 0): ?>
                                <span class="badge rounded-pill bg-danger ms-2" style="font-size: 0.6rem; padding: 0.25em 0.5em;"><?php echo $pending_count; ?></span>
                            <?php endif; ?>
                            <?php if ($child['label'] === 'Processing' && $processing_count > 0): ?>
                                <span class="badge rounded-pill bg-warning text-dark ms-2" style="font-size: 0.6rem; padding: 0.25em 0.5em;"><?php echo $processing_count; ?></span>
                            <?php endif; ?>
                            <?php if ($child['label'] === 'All Customers' && $new_customers_count > 0): ?>
                                <span class="badge rounded-pill bg-info ms-2 customer-notif-badge" style="font-size: 0.6rem; padding: 0.25em 0.5em; text-white"><?php echo $new_customers_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
            </ul>
        </div>

    <?php else: ?>
        <!-- Direct Link -->
        <a href="<?php echo htmlspecialchars(admin_url($item['url'])); ?>"
           class="list-group-item list-group-item-action <?php echo $group_active ? 'active' : ''; ?>">
            <i class="<?php echo $icon_full; ?>"></i>
            <span><?php echo $item['label']; ?></span>
        </a>
    <?php endif; ?>

<?php endforeach; ?>

    <div class="sidebar-divider my-3 mx-4" style="height: 1px; background: rgba(255,255,255,0.05);"></div>

    <!-- Bottom Links -->
    <a href="<?php echo defined('STORE_BASE_URL') ? htmlspecialchars(STORE_BASE_URL) : '/'; ?>" class="list-group-item list-group-item-action">
        <i class="fas fa-external-link-alt"></i>
        <span>View Store</span>
    </a>
    <a href="../includes/auth.php?action=logout" class="list-group-item list-group-item-action text-danger">
        <i class="fas fa-sign-out-alt"></i>
        <span>Logout</span>
    </a>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Orders Click-to-dismiss
    var orderNavs = document.querySelectorAll('[data-order-nav="true"]');
    if (orderNavs.length > 0) {
        orderNavs.forEach(function(el) {
            el.addEventListener('click', function() {
                var badges = document.querySelectorAll('.order-notif-badge');
                badges.forEach(function(b) {
                    b.style.display = 'none';
                });
                try {
                    fetch('<?php echo defined("ADMIN_BASE_URL") ? ADMIN_BASE_URL : "/admin/"; ?>ajax_mark_orders_seen.php', {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                } catch(e) {}
            });
        });
    }

    // Customers Click-to-dismiss
    var custNavs = document.querySelectorAll('[data-customer-nav="true"]');
    if (custNavs.length > 0) {
        custNavs.forEach(function(el) {
            el.addEventListener('click', function() {
                var badges = document.querySelectorAll('.customer-notif-badge');
                badges.forEach(function(b) {
                    b.style.display = 'none';
                });
                try {
                    fetch('<?php echo defined("ADMIN_BASE_URL") ? ADMIN_BASE_URL : "/admin/"; ?>ajax_mark_customers_seen.php', {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                } catch(e) {}
            });
        });
    }
});
</script>
