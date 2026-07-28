<?php
$pdo = new PDO('mysql:host=localhost;dbname=wapi_saas', 'root', '');
$stmt = $pdo->query('SELECT id, flow_json FROM chatbot_flows');
$out = "";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $out .= "ID: " . $row['id'] . "\n" . $row['flow_json'] . "\n\n";
}
file_put_contents('dumped_data.json', $out);
