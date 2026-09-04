<?php
require_once __DIR__ . '/../../core/BaseController.php';
require_once __DIR__ . '/DashboardService.php';

class DashboardController extends BaseController
{
    public function handle(): void
    {
        $service = new DashboardService($this->conn);

        $data = [
            'stats'         => $service->getStats(),
            'sales_chart'   => $service->getWeeklySales(),
            'recent_orders' => $service->getRecentOrders(),
        ];

        include_once __DIR__ . '/../../admin_header.php';
        $this->render(__DIR__ . '/views/index.php', $data);
        include_once __DIR__ . '/../../admin_footer.php';
    }
}
