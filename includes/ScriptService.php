<?php
require_once __DIR__ . '/ScriptRepository.php';

class ScriptService {
    private $repo;
    private $scripts;

    public function __construct($conn) {
        $this->repo = new ScriptRepository($conn);
        $this->scripts = $this->repo->getScripts();
    }

    /**
     * Get scripts designated for the head section.
     */
    public function getHeaderScripts() {
        $output = '';
        
        // Verification tags
        if (!empty($this->scripts['google_verification'])) {
            $code = htmlspecialchars($this->scripts['google_verification']);
            $output .= "<!-- Google Search Console -->\n<meta name=\"google-site-verification\" content=\"$code\">\n";
        }
        
        if (!empty($this->scripts['bing_verification'])) {
            $code = htmlspecialchars($this->scripts['bing_verification']);
            $output .= "<!-- Bing Webmaster -->\n<meta name=\"msvalidate.01\" content=\"$code\">\n";
        }
        
        if (!empty($this->scripts['custom_verification'])) {
            $output .= "<!-- Custom Verification -->\n" . $this->scripts['custom_verification'] . "\n";
        }
        
        // Header code
        if (!empty($this->scripts['header_code'])) {
            $output .= "<!-- Custom Header Code -->\n" . $this->scripts['header_code'] . "\n";
        }
        
        return $output;
    }

    /**
     * Get scripts designated for the body section.
     */
    public function getBodyScripts() {
        if (!empty($this->scripts['body_code'])) {
            return "<!-- Custom Body Code -->\n" . $this->scripts['body_code'] . "\n";
        }
        return '';
    }

    /**
     * Get scripts designated for the footer section.
     */
    public function getFooterScripts() {
        if (!empty($this->scripts['footer_code'])) {
            return "<!-- Custom Footer Code -->\n" . $this->scripts['footer_code'] . "\n";
        }
        return '';
    }

    /**
     * Get raw script data for admin form.
     */
    public function getRawScripts() {
        return $this->scripts;
    }
}
