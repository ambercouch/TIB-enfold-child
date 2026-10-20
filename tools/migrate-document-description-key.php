<?php
/** Run with wp eval-file; dry-run by default. No frontend hooks or automatic migration. */
if (!defined('WP_CLI') || !WP_CLI) {
    if (PHP_SAPI !== 'cli') {
        http_response_code(404);
    }
    exit;
}

(function ($arguments) {
    global $wpdb;
    $mode = isset($arguments[0]) ? $arguments[0] : 'dry-run';
    if (!in_array($mode, array('dry-run', 'apply'), true)) {
        WP_CLI::error('Usage: wp eval-file <script> [dry-run|apply] [absolute-backup-path]');
    }
    $old_key = 'field_short_description';
    $new_key = 'field_document_short_description';
    $owners = array(8245 => array('documents', 0), 8246 => array('documents', 0), 8470 => array('documents', 0), 8474 => array('documents', 0), 10429 => array('revision', 8474));
    $read = function () use ($wpdb, $owners, $old_key, $new_key) {
        $snapshot = array('owners' => array(), 'metadata' => array());
        foreach ($owners as $id => $expected) {
            $post = $wpdb->get_row($wpdb->prepare("SELECT ID, post_type, post_parent FROM {$wpdb->posts} WHERE ID = %d", $id), ARRAY_A);
            if (!$post || $post['post_type'] !== $expected[0] || (int) $post['post_parent'] !== $expected[1]) {
                throw new RuntimeException('Ownership mismatch for ' . $id . '; no automatic ID remapping.');
            }
            $rows = $wpdb->get_results($wpdb->prepare("SELECT meta_id, post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key IN ('short_description','_short_description') ORDER BY meta_id", $id), ARRAY_A);
            $references = array_values(array_filter($rows, function ($row) { return $row['meta_key'] === '_short_description'; }));
            if (count($references) !== 1 || !in_array($references[0]['meta_value'], array($old_key, $new_key), true)) {
                throw new RuntimeException('Expected exactly one old or already-migrated reference for ' . $id);
            }
            $snapshot['owners'][$id] = $post;
            $snapshot['metadata'][$id] = $rows;
        }
        return $snapshot;
    };
    try {
        $snapshot = $read();
        $changes = array();
        foreach ($snapshot['metadata'] as $id => $rows) {
            foreach ($rows as $row) {
                if ($row['meta_key'] === '_short_description' && $row['meta_value'] === $old_key) {
                    $changes[] = $row;
                    WP_CLI::log($id . ': ' . $old_key . ' -> ' . $new_key);
                }
            }
        }
        if (!$changes) {
            WP_CLI::success('Already migrated: all five owned references use the document key. No writes.');
            return;
        }
        if ($mode === 'dry-run') {
            WP_CLI::success('Dry-run: ' . count($changes) . ' references would change. No writes.');
            return;
        }
        $field = function_exists('acf_get_field') ? acf_get_field($new_key) : false;
        if (!$field || $field['name'] !== 'short_description' || $field['parent'] !== 'group_document_fields') {
            throw new RuntimeException('Deploy the Document Fields key change before applying this migration.');
        }
        foreach (array($wpdb->posts, $wpdb->postmeta) as $table) {
            $engine = $wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table));
            if (strtolower((string) $engine) !== 'innodb') {
                throw new RuntimeException('Transactional InnoDB tables required: ' . $table);
            }
        }
        $backup = isset($arguments[1]) ? $arguments[1] : '';
        $parent = $backup ? realpath(dirname($backup)) : false;
        $docroot = rtrim(realpath(ABSPATH), DIRECTORY_SEPARATOR);
        if (!$backup || $backup[0] !== '/' || !$parent || $parent === $docroot || strpos($parent, $docroot . DIRECTORY_SEPARATOR) === 0) {
            throw new RuntimeException('Provide a new absolute backup path in an existing directory outside the WordPress docroot.');
        }
        $json = wp_json_encode(array('created_utc' => gmdate('c'), 'site' => home_url(), 'old_key' => $old_key, 'new_key' => $new_key, 'snapshot' => $snapshot), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Cannot encode metadata backup.');
        }
        $handle = @fopen($backup, 'x');
        if (!$handle) {
            throw new RuntimeException('Cannot create backup or path already exists.');
        }
        $secured = chmod($backup, 0600);
        $written = $secured ? fwrite($handle, $json . "\n") : false;
        fclose($handle);
        if (!$secured || $written !== strlen($json . "\n") || file_get_contents($backup) !== $json . "\n") {
            throw new RuntimeException('Backup verification failed; no metadata updated.');
        }
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Cannot start transaction.');
        }
        try {
            // Lock ownership and all target metadata before rechecking the backed-up snapshot.
            $ids = implode(',', array_keys($owners));
            $wpdb->get_results("SELECT ID FROM {$wpdb->posts} WHERE ID IN ($ids) FOR UPDATE");
            $wpdb->get_results("SELECT meta_id FROM {$wpdb->postmeta} WHERE post_id IN ($ids) AND meta_key IN ('short_description','_short_description') FOR UPDATE");
            if ($read() !== $snapshot) {
                throw new RuntimeException('Records changed since backup; migration stopped.');
            }
            foreach ($changes as $row) {
                // Verify ownership and the old reference again immediately before each update.
                $post = $wpdb->get_row($wpdb->prepare("SELECT post_type, post_parent FROM {$wpdb->posts} WHERE ID = %d", $row['post_id']), ARRAY_A);
                $expected = $owners[(int) $row['post_id']];
                if ($post['post_type'] !== $expected[0] || (int) $post['post_parent'] !== $expected[1]) {
                    throw new RuntimeException('Ownership changed for ' . $row['post_id']);
                }
                $changed = $wpdb->query($wpdb->prepare("UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE meta_id = %d AND post_id = %d AND meta_key = '_short_description' AND BINARY meta_value = BINARY %s", $new_key, $row['meta_id'], $row['post_id'], $old_key));
                if ($changed !== 1) {
                    throw new RuntimeException('Old-reference verification/update failed for ' . $row['post_id']);
                }
            }
            $after = $read();
            $expected = $snapshot;
            foreach ($expected['metadata'] as &$rows) {
                foreach ($rows as &$row) {
                    if ($row['meta_key'] === '_short_description') {
                        $row['meta_value'] = $new_key;
                    }
                }
                unset($row);
            }
            unset($rows);
            if ($after !== $expected || $wpdb->query('COMMIT') === false) {
                throw new RuntimeException('Post-update verification or commit failed.');
            }
        } catch (Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
        foreach (array_keys($owners) as $id) {
            wp_cache_delete($id, 'post_meta');
            wp_cache_delete('acf/post_id=' . $id, 'acf');
            if (function_exists('acf_flush_value_cache')) {
                acf_flush_value_cache($id, 'short_description');
            }
        }
        WP_CLI::success('Updated ' . count($changes) . ' references only. Backup: ' . $backup);
    } catch (Throwable $error) {
        WP_CLI::error($error->getMessage());
    }
})(isset($args) ? $args : array());
