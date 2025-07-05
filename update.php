<?php
require 'db.php';

$sql = "ALTER TABLE domains
    ADD COLUMN IF NOT EXISTS check_interval_days INT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS alert_threshold_percent INT DEFAULT NULL";

if ($conn->query($sql) === TRUE) {
    echo "Domains table updated.";
} else {
    echo "Error updating domains table: " . $conn->error;
}
