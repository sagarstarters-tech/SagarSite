<?php
/**
 * Settings Class - Dynamic Settings Manager
 * Handles all site settings from the database
 */
class Settings {
    private $db;
    private static $cache = [];

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Get a setting value by key
     */
    public function get($key, $default = null) {
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $result = $this->db->fetch(
            "SELECT setting_value FROM settings WHERE setting_key = ?",
            [$key]
        );

        $value = $result ? $result['setting_value'] : $default;
        self::$cache[$key] = $value;
        return $value;
    }

    /**
     * Set a setting value
     */
    public function set($key, $value, $group = null, $type = null) {
        $exists = $this->db->exists('settings', 'setting_key = ?', [$key]);

        if ($exists) {
            $data = ['setting_value' => $value];
            if ($group) $data['setting_group'] = $group;
            if ($type) $data['setting_type'] = $type;
            $this->db->update('settings', $data, 'setting_key = ?', [$key]);
        } else {
            $this->db->insert('settings', [
                'setting_key' => $key,
                'setting_value' => $value,
                'setting_group' => $group ?? 'general',
                'setting_type' => $type ?? 'text'
            ]);
        }

        self::$cache[$key] = $value;
        return true;
    }

    /**
     * Get all settings by group
     */
    public function getByGroup($group) {
        return $this->db->fetchAll(
            "SELECT * FROM settings WHERE setting_group = ? ORDER BY setting_key",
            [$group]
        );
    }

    /**
     * Get all public settings (for frontend)
     */
    public function getPublicSettings() {
        $settings = $this->db->fetchAll(
            "SELECT setting_key, setting_value FROM settings WHERE is_public = 1"
        );

        $result = [];
        foreach ($settings as $s) {
            $result[$s['setting_key']] = $s['setting_value'];
            self::$cache[$s['setting_key']] = $s['setting_value'];
        }
        return $result;
    }

    /**
     * Get all settings as key-value pairs
     */
    public function getAll() {
        $settings = $this->db->fetchAll("SELECT setting_key, setting_value FROM settings");

        $result = [];
        foreach ($settings as $s) {
            $result[$s['setting_key']] = $s['setting_value'];
            self::$cache[$s['setting_key']] = $s['setting_value'];
        }
        return $result;
    }

    /**
     * Bulk update settings
     */
    public function bulkUpdate($settings) {
        foreach ($settings as $key => $value) {
            $this->set($key, $value);
        }
        return true;
    }

    /**
     * Delete a setting
     */
    public function delete($key) {
        $this->db->delete('settings', 'setting_key = ?', [$key]);
        unset(self::$cache[$key]);
        return true;
    }

    /**
     * Clear cache
     */
    public static function clearCache() {
        self::$cache = [];
    }
}
