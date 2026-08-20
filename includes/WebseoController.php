<?php
require_once 'SeoRepository.php';
require_once 'SitemapGenerator.php';

class WebseoController {
    private $conn;
    private $repo;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->repo = new SeoRepository($conn);
    }

    /**
     * Save global SEO settings.
     */
    public function saveGlobalSettings($settings) {
        foreach ($settings as $key => $value) {
            $this->repo->updateGlobalSetting($key, $value);
        }
        return ['success' => true];
    }

    /**
     * Save entity-specific SEO metadata.
     */
    public function saveMetadata($data) {
        if ($this->repo->saveMetadata($data)) {
            return ['success' => true];
        }
        return ['success' => false, 'error' => $this->conn->error];
    }

    /**
     * Get SEO Audit Report (Optimized single-query JOINs).
     */
    public function getSeoAudit() {
        $report = [
            'missing_title' => [],
            'missing_description' => [],
            'total_indexed' => 0
        ];

        // 1. Audit Pages via single query
        $pages = $this->conn->query("
            SELECT p.id, p.title, m.meta_title, m.meta_description 
            FROM pages p
            LEFT JOIN seo_metadata m ON m.entity_type = 'page' AND m.entity_id = p.id
        ");
        if ($pages) {
            while ($p = $pages->fetch_assoc()) {
                $hasTitle = !empty(trim($p['meta_title'] ?? ''));
                $hasDesc = !empty(trim($p['meta_description'] ?? ''));
                if (!$hasTitle && count($report['missing_title']) < 50) {
                    $report['missing_title'][] = 'Page: ' . ($p['title'] ?: 'ID #' . $p['id']);
                }
                if (!$hasDesc && count($report['missing_description']) < 50) {
                    $report['missing_description'][] = 'Page: ' . ($p['title'] ?: 'ID #' . $p['id']);
                }
                $report['total_indexed']++;
            }
        }

        // 2. Audit Products via single query
        $prods = $this->conn->query("
            SELECT pr.id, pr.name, m.meta_title, m.meta_description 
            FROM products pr
            LEFT JOIN seo_metadata m ON m.entity_type = 'product' AND m.entity_id = pr.id
        ");
        if ($prods) {
            while ($p = $prods->fetch_assoc()) {
                $hasTitle = !empty(trim($p['meta_title'] ?? ''));
                $hasDesc = !empty(trim($p['meta_description'] ?? ''));
                if (!$hasTitle && count($report['missing_title']) < 50) {
                    $report['missing_title'][] = 'Product: ' . ($p['name'] ?: 'ID #' . $p['id']);
                }
                if (!$hasDesc && count($report['missing_description']) < 50) {
                    $report['missing_description'][] = 'Product: ' . ($p['name'] ?: 'ID #' . $p['id']);
                }
                $report['total_indexed']++;
            }
        }

        return $report;
    }

    /**
     * Save robots.txt content.
     */
    public function saveRobotsTxt($content) {
        $filePath = BASE_PATH . '/robots.txt';
        if (file_put_contents($filePath, $content) !== false) {
            return ['success' => true];
        }
        return ['success' => false, 'error' => 'Could not write to robots.txt'];
    }

    /**
     * Get robots.txt content.
     */
    public function getRobotsTxt() {
        $filePath = BASE_PATH . '/robots.txt';
        if (file_exists($filePath)) {
            return file_get_contents($filePath);
        }
        return "User-agent: *\nDisallow: /admin/\nSitemap: https://" . $_SERVER['HTTP_HOST'] . "/sitemap.xml";
    }

    /**
     * Generate fresh XML Sitemap.
     */
    public function generateSitemap() {
        $generator = new SitemapGenerator($this->conn);
        return $generator->generate();
    }
}
