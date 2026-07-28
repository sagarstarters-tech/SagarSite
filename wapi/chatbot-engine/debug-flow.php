<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * Debug Tool: Inspect Flow JSON structure for card nodes
 * Access: http://localhost/wapi/chatbot-engine/debug-flow.php
 * DELETE THIS FILE after debugging!
 */

// Need to start session for the main config to work
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Flow Debug</title>
<style>
  body { background:#1e1e1e; color:#d4d4d4; font-family:monospace; font-size:13px; padding:20px; }
  .node { background:#2d2d2d; border:1px solid #444; border-radius:6px; padding:12px; margin-bottom:12px; }
  .node-type-card { border-color:#007bff; }
  .node-type-interactive { border-color:#28a745; }
  .section-title { color:#569cd6; font-weight:bold; font-size:15px; margin:16px 0 8px; }
  .key { color:#9cdcfe; }
  .val { color:#ce9178; }
  .port { color:#b5cea8; }
  .connected { color:#4ec9b0; }
  .empty { color:#f44747; }
  .log { background:#111; padding:12px; border-radius:4px; white-space:pre-wrap; }
  h1 { color:#fff; font-size:18px; }
  .badge { display:inline-block; padding:2px 8px; border-radius:4px; font-size:11px; margin-left:6px; }
  .badge-card { background:#007bff; }
  .badge-interactive { background:#28a745; }
  .badge-other { background:#555; }
</style>
</head>
<body>

<h1>🔍 Chatbot Flow Debug Inspector</h1>

<?php
try {
    $db = Database::getInstance();
    echo '<p style="color:#6a9955;">✅ Database connected successfully</p>';
    
    // Show ALL flows summary first
    $allFlows = $db->fetchAll("SELECT id, name, LENGTH(flow_json) as json_size FROM chatbot_flows WHERE user_id IN (SELECT user_id FROM chatbot_flows) ORDER BY id DESC");
    echo '<div class="section-title">All Flows in Database</div>';
    echo '<table style="border-collapse:collapse;width:100%;margin-bottom:16px;">';
    echo '<tr style="color:#569cd6;"><th style="text-align:left;padding:4px 8px;border-bottom:1px solid #444;">ID</th><th style="text-align:left;padding:4px 8px;border-bottom:1px solid #444;">Name</th><th style="text-align:left;padding:4px 8px;border-bottom:1px solid #444;">JSON Size</th><th style="text-align:left;padding:4px 8px;border-bottom:1px solid #444;">Action</th></tr>';
    foreach ($allFlows as $f) {
        $isLatest = ($f === $allFlows[0]) ? ' style="color:#4ec9b0"' : '';
        echo "<tr$isLatest><td style='padding:4px 8px;'>{$f['id']}</td><td style='padding:4px 8px;'>" . htmlspecialchars($f['name'] ?? 'N/A') . "</td><td style='padding:4px 8px;'>{$f['json_size']} bytes</td><td style='padding:4px 8px;'><a href='?flow_id={$f['id']}' style='color:#007bff;'>Inspect</a></td></tr>";
    }
    echo '</table>';
    
    // Load specific flow if requested, else the largest one (most likely to have data)
    $requestedId = $_GET['flow_id'] ?? null;
    if ($requestedId) {
        $flow = $db->fetch("SELECT id, name, flow_json FROM chatbot_flows WHERE id = ?", [$requestedId]);
    } else {
        // Get the flow with the most data (largest JSON)
        $flow = $db->fetch("SELECT id, name, flow_json FROM chatbot_flows ORDER BY LENGTH(flow_json) DESC LIMIT 1");
    }
    
    echo '<div class="section-title">📋 Flow ID: ' . $flow['id'] . '</div>';
    
    // Show raw JSON snippet first
    $rawJson = $flow['flow_json'];
    echo '<p><strong>Raw JSON length:</strong> ' . strlen($rawJson) . ' bytes</p>';
    echo '<p><strong>JSON preview (first 500 chars):</strong></p>';
    echo '<div class="log" style="max-height:120px;overflow:auto;">' . htmlspecialchars(substr($rawJson, 0, 500)) . '</div>';
    
    $data = json_decode($rawJson, true);
    if (!$data) {
        echo '<p style="color:#f44747;">❌ Failed to parse flow_json! JSON error: ' . json_last_error_msg() . '</p>';
        exit;
    }
    
    // Show top-level keys
    echo '<p><strong>Top-level JSON keys:</strong> ' . htmlspecialchars(implode(', ', array_keys($data))) . '</p>';
    
    // Check drawflow structure
    if (isset($data['drawflow'])) {
        echo '<p><strong>drawflow keys:</strong> ' . htmlspecialchars(implode(', ', array_keys($data['drawflow']))) . '</p>';
        foreach ($data['drawflow'] as $moduleKey => $moduleData) {
            $dataKeys = array_keys($moduleData['data'] ?? []);
            echo '<p><strong>Module "' . $moduleKey . '" → data keys:</strong> ' . (empty($dataKeys) ? '<span style="color:#f44747">EMPTY!</span>' : implode(', ', $dataKeys)) . '</p>';
        }
    } else {
        echo '<p style="color:#f44747;">❌ No "drawflow" key in JSON! Structure is different.</p>';
        echo '<p>All keys: ' . htmlspecialchars(json_encode(array_keys($data))) . '</p>';
    }
    
    $nodes = $data['drawflow']['Home']['data'] ?? $data['drawflow']['home']['data'] ?? $data['drawflow']['Home']['data'] ?? [];
    
    // Also try other possible structures
    if (empty($nodes)) {
        foreach (($data['drawflow'] ?? []) as $mk => $mv) {
            if (!empty($mv['data'])) {
                $nodes = $mv['data'];
                echo '<p style="color:#dcdcaa;">⚠️ Found nodes under module key: "' . $mk . '"</p>';
                break;
            }
        }
    }
    
    echo '<p>Total nodes: <strong>' . count($nodes) . '</strong></p>';
    
    foreach ($nodes as $nodeId => $node) {
        $type = $node['name'];
        $badgeClass = in_array($type, ['card','interactive','text-cta']) ? "badge-$type" : 'badge-other';
        
        echo "<div class='node node-type-$type'>";
        echo "<strong>Node <span class='key'>$nodeId</span></strong>";
        echo " <span class='badge $badgeClass'>$type</span><br><br>";
        
        // Show button data
        $hasBtns = false;
        foreach ($node['data'] as $k => $v) {
            if (strpos($k, 'btn-') === 0) {
                $hasBtns = true;
                $portIndex = str_replace('btn-', '', $k);
                $expectedOutput = 'output_' . ($portIndex + 2);
                $buttonId = "flow_btn_{$nodeId}_{$portIndex}";
                echo "  <span class='key'>$k</span> = <span class='val'>'" . htmlspecialchars($v) . "'</span> ";
                echo "→ ID sent to WA: <span class='connected'>$buttonId</span> ";
                echo "→ expects port: <span class='port'>$expectedOutput</span><br>";
            }
        }
        if (!$hasBtns && in_array($type, ['card','interactive','text-cta'])) {
            echo "  <span class='empty'>⚠️ No buttons (btn-0/btn-1/btn-2) in node data!</span><br>";
        }
        
        // Show outputs
        echo "<br><strong>Output Ports:</strong><br>";
        $outputs = $node['outputs'] ?? [];
        if (empty($outputs)) {
            echo "  <span class='empty'>No outputs!</span><br>";
        }
        foreach ($outputs as $opName => $opData) {
            $conns = $opData['connections'] ?? [];
            if (empty($conns)) {
                echo "  <span class='port'>$opName</span> → <span class='empty'>⛔ no connection</span><br>";
            } else {
                foreach ($conns as $c) {
                    echo "  <span class='port'>$opName</span> → <span class='connected'>✅ Node " . $c['node'] . "</span><br>";
                }
            }
        }
        
        // For button nodes: show mapping summary
        if (in_array($type, ['card','interactive','text-cta'])) {
            echo "<br><strong>Expected Button→Port Mapping:</strong><br>";
            echo "  <span class='key'>output_1</span> = Next (auto-advance)<br>";
            foreach ($node['data'] as $k => $v) {
                if (strpos($k, 'btn-') === 0 && !empty(trim($v))) {
                    $portIndex = str_replace('btn-', '', $k);
                    $expectedOutput = 'output_' . ($portIndex + 2);
                    $conns = $node['outputs'][$expectedOutput]['connections'] ?? [];
                    $connStatus = empty($conns) 
                        ? "<span class='empty'>⛔ NOT CONNECTED!</span>" 
                        : "<span class='connected'>✅ → Node " . $conns[0]['node'] . "</span>";
                    echo "  <span class='key'>$expectedOutput</span> (Btn " . ($portIndex+1) . " - '" . htmlspecialchars($v) . "') = $connStatus<br>";
                }
            }
        }
        
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo '<p style="color:#f44747;">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
?>

<div class="section-title">📄 webhook_debug.log (Last 40 lines)</div>
<?php
$logFile = __DIR__ . '/webhook_debug.log';
echo "<div class='log'>";
if (file_exists($logFile) && filesize($logFile) > 0) {
    $lines = file($logFile);
    $last = array_slice($lines, -40);
    foreach ($last as $line) {
        $colored = htmlspecialchars($line);
        if (strpos($line, 'ERROR') !== false) $colored = "<span style='color:#f44747'>$colored</span>";
        elseif (strpos($line, 'Routing to') !== false) $colored = "<span style='color:#4ec9b0'>$colored</span>";
        elseif (strpos($line, 'BUTTON CLICKED') !== false) $colored = "<span style='color:#dcdcaa'>$colored</span>";
        echo $colored;
    }
} else {
    echo "(empty — no button clicks received yet, or log was cleared)\n";
}
echo "</div>";

echo '<br><p style="color:#555;font-size:11px;">⚠️ Delete this file after debugging: chatbot-engine/debug-flow.php</p>';
?>
</body>
</html>
