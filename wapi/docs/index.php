<?php
/**
 * WAPI SaaS - API Documentation
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';

$pageTitle = 'API Documentation';
include __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="row">
        <div class="col-lg-12">
            <h1 class="display-5 fw-bold mb-4">API Documentation</h1>
            <p class="lead text-secondary mb-5">
                Integrate our powerful WhatsApp API into your own applications. Follow our guide to get started.
            </p>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h4 class="fw-bold mb-3">Authentication</h4>
                    <p>All API requests must include your API Key in the <code>X-API-Key</code> header.</p>
                    <pre class="bg-light p-3 rounded"><code>X-API-Key: YOUR_API_KEY</code></pre>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h4 class="fw-bold mb-3">Send Text Message</h4>
                    <p>Send a simple text message via WhatsApp.</p>
                    <div class="badge bg-primary mb-3">POST</div>
                    <code><?= baseUrl('api/send-message.php'); ?></code>
                    
                    <h6 class="mt-4 fw-bold">Request Body (JSON)</h6>
                    <pre class="bg-light p-3 rounded"><code>{
    "to": "919876543210",
    "type": "text",
    "content": "Hello World!"
}</code></pre>
                </div>
            </div>

            <div class="alert alert-info">
                <strong>More coming soon!</strong> We are currently expanding our API documentation. Please check back later for more endpoints.
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
