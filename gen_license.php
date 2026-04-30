<?php
// gen_license.php — run once, then DELETE this file!
// Place in: F:\xampp\htdocs\pos\  (project root, NOT public)

$data = [
    'tenant_id' => 1,                              // your tenant ID
    'issued'    => date('Y-m-d'),
    'expiry'    => date('Y-m-d', strtotime('+1 year')),  // change as needed
    'note'      => 'Renewed manually - ' . date('d M Y'),
    'issued_by' => 'Admin',
];

$encoded = base64_encode(json_encode($data));

// Write to public/yylic.txt
file_put_contents(__DIR__ . '/public/yylic.txt', $encoded);

echo "<pre>";
echo "✅ License written to public/yylic.txt\n\n";
echo "Tenant ID : " . $data['tenant_id'] . "\n";
echo "Issued    : " . $data['issued'] . "\n";
echo "Expiry    : " . $data['expiry'] . "\n";
echo "Content   : " . $encoded . "\n";
echo "</pre>";
echo "<b style='color:red'>DELETE this file now!</b>";