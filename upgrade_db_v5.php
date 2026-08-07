<?php
require_once 'config.php';

try {
    // Check if the service field exists
    $stmt = $pdo->prepare("SELECT id FROM IFW_form_fields WHERE field_name = 'service' OR field_name = 'service_type'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        // Shift existing fields display_order down
        $pdo->exec("UPDATE IFW_form_fields SET display_order = display_order + 1 WHERE display_order >= 4");
        
        // Insert the Service dropdown
        $stmt = $pdo->prepare("INSERT INTO IFW_form_fields (field_name, field_label, field_type, field_options, is_required, display_order) 
                               VALUES ('service', 'Service', 'select', 'Asset Recovery,Cybercrime Investigation,Fraud Investigation,Other', 0, 4)");
        $stmt->execute();
        echo "Service dropdown added successfully.\\n";
    } else {
        echo "Service dropdown already exists.\\n";
    }
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
