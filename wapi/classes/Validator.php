<?php
/**
 * Validator Class - Input Validation
 * Validates and sanitizes user inputs
 */
class Validator {
    private $errors = [];
    private $data = [];

    /**
     * Validate data against rules
     */
    public function validate($data, $rules) {
        $this->errors = [];
        $this->data = $data;

        foreach ($rules as $field => $ruleString) {
            $fieldRules = explode('|', $ruleString);
            $value = $data[$field] ?? null;
            $label = ucfirst(str_replace('_', ' ', $field));

            foreach ($fieldRules as $rule) {
                $params = [];
                if (strpos($rule, ':') !== false) {
                    [$rule, $paramStr] = explode(':', $rule, 2);
                    $params = explode(',', $paramStr);
                }

                $method = 'rule' . ucfirst($rule);
                if (method_exists($this, $method)) {
                    $error = $this->$method($field, $value, $label, $params);
                    if ($error) {
                        $this->errors[$field] = $error;
                        break; // Stop on first error for this field
                    }
                }
            }
        }

        return empty($this->errors);
    }

    /**
     * Get validation errors
     */
    public function getErrors() {
        return $this->errors;
    }

    /**
     * Get first error message
     */
    public function getFirstError() {
        return !empty($this->errors) ? reset($this->errors) : null;
    }

    // ========== Validation Rules ==========

    private function ruleRequired($field, $value, $label, $params) {
        if ($value === null || $value === '' || $value === []) {
            return "{$label} is required.";
        }
        return null;
    }

    private function ruleEmail($field, $value, $label, $params) {
        if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return "{$label} must be a valid email address.";
        }
        return null;
    }

    private function ruleMin($field, $value, $label, $params) {
        if ($value && strlen($value) < (int)$params[0]) {
            return "{$label} must be at least {$params[0]} characters.";
        }
        return null;
    }

    private function ruleMax($field, $value, $label, $params) {
        if ($value && strlen($value) > (int)$params[0]) {
            return "{$label} must not exceed {$params[0]} characters.";
        }
        return null;
    }

    private function ruleNumeric($field, $value, $label, $params) {
        if ($value && !is_numeric($value)) {
            return "{$label} must be a number.";
        }
        return null;
    }

    private function rulePhone($field, $value, $label, $params) {
        if ($value && !preg_match('/^\+?[1-9]\d{7,14}$/', preg_replace('/[\s\-\(\)]/', '', $value))) {
            return "{$label} must be a valid phone number.";
        }
        return null;
    }

    private function ruleMatch($field, $value, $label, $params) {
        $matchField = $params[0];
        $matchLabel = ucfirst(str_replace('_', ' ', $matchField));
        if ($value !== ($this->data[$matchField] ?? null)) {
            return "{$label} must match {$matchLabel}.";
        }
        return null;
    }

    private function ruleAlpha($field, $value, $label, $params) {
        if ($value && !preg_match('/^[a-zA-Z\s]+$/', $value)) {
            return "{$label} must only contain letters and spaces.";
        }
        return null;
    }

    private function ruleAlphanumeric($field, $value, $label, $params) {
        if ($value && !preg_match('/^[a-zA-Z0-9\s]+$/', $value)) {
            return "{$label} must only contain letters, numbers, and spaces.";
        }
        return null;
    }

    private function ruleUrl($field, $value, $label, $params) {
        if ($value && !filter_var($value, FILTER_VALIDATE_URL)) {
            return "{$label} must be a valid URL.";
        }
        return null;
    }

    private function ruleIn($field, $value, $label, $params) {
        if ($value && !in_array($value, $params)) {
            return "{$label} must be one of: " . implode(', ', $params);
        }
        return null;
    }

    private function ruleUnique($field, $value, $label, $params) {
        if ($value) {
            $table = $params[0];
            $column = $params[1] ?? $field;
            $exceptId = $params[2] ?? null;

            $db = Database::getInstance();
            $sql = "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?";
            $sqlParams = [$value];

            if ($exceptId) {
                $sql .= " AND id != ?";
                $sqlParams[] = $exceptId;
            }

            if ($db->fetchColumn($sql, $sqlParams) > 0) {
                return "{$label} is already taken.";
            }
        }
        return null;
    }

    /**
     * Sanitize input string
     */
    public static function sanitize($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitize'], $input);
        }
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Sanitize for HTML output
     */
    public static function escape($value) {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}
