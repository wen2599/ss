<?php

declare(strict_types=1);

function run_migration(mysqli $db_connection): void
{
    $sql = "ALTER TABLE `users` ADD COLUMN `username` VARCHAR(255) NOT NULL AFTER `id`;";
    if ($db_connection->query($sql) === FALSE) {
        throw new Exception("Failed to add username column: " . $db_connection->error);
    }

    $sql = "ALTER TABLE `users` ADD UNIQUE (`username`);";
    if ($db_connection->query($sql) === FALSE) {
        throw new Exception("Failed to add unique index to username column: " . $db_connection->error);
    }
}
