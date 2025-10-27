<?php
namespace VeriBits\Utils;

class Validator {
    private array $errors = [];
    private array $data = [];

    public function __construct(array $data) {
        $this->data = $data;
    }

    public function required(string $field, string $message = null): self {
        if (!isset($this->data[$field]) || $this->data[$field] === '' || $this->data[$field] === null) {
            $this->errors[$field][] = $message ?? "The $field field is required";
        }
        return $this;
    }

    public function email(string $field, string $message = null): self {
        if (isset($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field][] = $message ?? "The $field must be a valid email address";
        }
        return $this;
    }

    public function string(string $field, int $min = 0, int $max = 255, string $message = null): self {
        if (isset($this->data[$field])) {
            $value = $this->data[$field];
            if (!is_string($value)) {
                $this->errors[$field][] = $message ?? "The $field must be a string";
            } elseif (strlen($value) < $min) {
                $this->errors[$field][] = $message ?? "The $field must be at least $min characters";
            } elseif (strlen($value) > $max) {
                $this->errors[$field][] = $message ?? "The $field must not exceed $max characters";
            }
        }
        return $this;
    }

    public function url(string $field, string $message = null): self {
        if (isset($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_URL)) {
            $this->errors[$field][] = $message ?? "The $field must be a valid URL";
        }
        return $this;
    }

    public function sha256(string $field, string $message = null): self {
        if (isset($this->data[$field])) {
            $value = $this->data[$field];
            if (!preg_match('/^[a-f0-9]{64}$/i', $value)) {
                $this->errors[$field][] = $message ?? "The $field must be a valid SHA256 hash";
            }
        }
        return $this;
    }

    public function alphanumeric(string $field, string $message = null): self {
        if (isset($this->data[$field])) {
            $value = $this->data[$field];
            if (!preg_match('/^[a-zA-Z0-9]+$/', $value)) {
                $this->errors[$field][] = $message ?? "The $field must contain only letters and numbers";
            }
        }
        return $this;
    }

    public function in(string $field, array $values, string $message = null): self {
        if (isset($this->data[$field]) && !in_array($this->data[$field], $values)) {
            $allowed = implode(', ', $values);
            $this->errors[$field][] = $message ?? "The $field must be one of: $allowed";
        }
        return $this;
    }

    public function sanitize(string $field): string {
        return htmlspecialchars(trim($this->data[$field] ?? ''), ENT_QUOTES, 'UTF-8');
    }

    public function isValid(): bool {
        return empty($this->errors);
    }

    public function getErrors(): array {
        return $this->errors;
    }

    public function getFirstError(): string {
        if (empty($this->errors)) return '';
        $firstField = array_key_first($this->errors);
        return $this->errors[$firstField][0];
    }

    /**
     * Validate IP address (IPv4 or IPv6)
     */
    public function ip(string $field, string $message = null): self {
        if (isset($this->data[$field])) {
            $value = $this->data[$field];
            if (!filter_var($value, FILTER_VALIDATE_IP)) {
                $this->errors[$field][] = $message ?? "The $field must be a valid IP address";
            }
        }
        return $this;
    }

    /**
     * Validate IPv4 address specifically
     */
    public function ipv4(string $field, string $message = null): self {
        if (isset($this->data[$field])) {
            $value = $this->data[$field];
            if (!filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $this->errors[$field][] = $message ?? "The $field must be a valid IPv4 address";
            }
        }
        return $this;
    }

    /**
     * Validate IPv6 address specifically
     */
    public function ipv6(string $field, string $message = null): self {
        if (isset($this->data[$field])) {
            $value = $this->data[$field];
            if (!filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $this->errors[$field][] = $message ?? "The $field must be a valid IPv6 address";
            }
        }
        return $this;
    }

    /**
     * Validate domain name
     */
    public function domain(string $field, string $message = null): self {
        if (isset($this->data[$field])) {
            $value = $this->data[$field];
            // Remove protocol if present
            $value = preg_replace('#^https?://#i', '', $value);
            // Remove path if present
            $value = explode('/', $value)[0];

            if (!preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $value)) {
                $this->errors[$field][] = $message ?? "The $field must be a valid domain name";
            }
        }
        return $this;
    }

    /**
     * Validate hostname (domain or IP)
     */
    public function hostname(string $field, string $message = null): self {
        if (isset($this->data[$field])) {
            $value = $this->data[$field];
            $isValidDomain = preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $value);
            $isValidIp = filter_var($value, FILTER_VALIDATE_IP);

            if (!$isValidDomain && !$isValidIp) {
                $this->errors[$field][] = $message ?? "The $field must be a valid hostname or IP address";
            }
        }
        return $this;
    }

    /**
     * Validate file extension
     */
    public function fileExtension(string $field, array $allowedExtensions, string $message = null): self {
        if (isset($this->data[$field])) {
            $value = $this->data[$field];
            $extension = strtolower(pathinfo($value, PATHINFO_EXTENSION));

            if (!in_array($extension, array_map('strtolower', $allowedExtensions))) {
                $allowed = implode(', ', $allowedExtensions);
                $this->errors[$field][] = $message ?? "The $field must have one of these extensions: $allowed";
            }
        }
        return $this;
    }

    /**
     * Validate integer
     */
    public function integer(string $field, ?int $min = null, ?int $max = null, string $message = null): self {
        if (isset($this->data[$field])) {
            $value = $this->data[$field];

            if (!filter_var($value, FILTER_VALIDATE_INT)) {
                $this->errors[$field][] = $message ?? "The $field must be an integer";
            } elseif ($min !== null && (int)$value < $min) {
                $this->errors[$field][] = $message ?? "The $field must be at least $min";
            } elseif ($max !== null && (int)$value > $max) {
                $this->errors[$field][] = $message ?? "The $field must not exceed $max";
            }
        }
        return $this;
    }

    /**
     * Validate boolean
     */
    public function boolean(string $field, string $message = null): self {
        if (isset($this->data[$field])) {
            $value = $this->data[$field];

            if (!is_bool($value) && !in_array($value, [0, 1, '0', '1', 'true', 'false'], true)) {
                $this->errors[$field][] = $message ?? "The $field must be a boolean value";
            }
        }
        return $this;
    }

    /**
     * Validate JSON
     */
    public function json(string $field, string $message = null): self {
        if (isset($this->data[$field])) {
            $value = $this->data[$field];
            json_decode($value);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->errors[$field][] = $message ?? "The $field must be valid JSON";
            }
        }
        return $this;
    }

    /**
     * Validate regex pattern
     */
    public function regex(string $field, string $pattern, string $message = null): self {
        if (isset($this->data[$field])) {
            $value = $this->data[$field];

            if (!preg_match($pattern, $value)) {
                $this->errors[$field][] = $message ?? "The $field format is invalid";
            }
        }
        return $this;
    }

    /**
     * Validate port number
     */
    public function port(string $field, string $message = null): self {
        if (isset($this->data[$field])) {
            $value = $this->data[$field];

            if (!filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]])) {
                $this->errors[$field][] = $message ?? "The $field must be a valid port number (1-65535)";
            }
        }
        return $this;
    }

    /**
     * Validate UUID
     */
    public function uuid(string $field, string $message = null): self {
        if (isset($this->data[$field])) {
            $value = $this->data[$field];

            if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value)) {
                $this->errors[$field][] = $message ?? "The $field must be a valid UUID";
            }
        }
        return $this;
    }

    /**
     * Validate base64 encoding
     */
    public function base64(string $field, string $message = null): self {
        if (isset($this->data[$field])) {
            $value = $this->data[$field];

            if (!preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $value) || !base64_decode($value, true)) {
                $this->errors[$field][] = $message ?? "The $field must be valid base64";
            }
        }
        return $this;
    }

    /**
     * Validate CIDR notation
     */
    public function cidr(string $field, string $message = null): self {
        if (isset($this->data[$field])) {
            $value = $this->data[$field];

            if (!preg_match('/^(\d{1,3}\.){3}\d{1,3}\/\d{1,2}$/', $value)) {
                $this->errors[$field][] = $message ?? "The $field must be in valid CIDR notation (e.g., 192.168.1.0/24)";
            }
        }
        return $this;
    }
}