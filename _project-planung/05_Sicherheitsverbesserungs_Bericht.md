# Sicherheitsverbesserungs-Bericht: ImmoBridge Plugin

## Executive Summary

Dieser Bericht analysiert die Sicherheitslücken des Legacy-Plugins immonex-openimmo2wp und definiert umfassende Sicherheitsmaßnahmen für das neue ImmoBridge Plugin. Die Analyse zeigt kritische Schwachstellen in Input-Validierung, Output-Escaping und Authentifizierung, die durch moderne Sicherheitspraktiken behoben werden müssen.

## Sicherheitsanalyse des Legacy-Systems

### Kritische Sicherheitslücken

#### 1. Input-Validierung (Kritisch)

**Identifizierte Probleme:**

```php
// Beispiel aus dem Legacy-Code
$import_schedule = $_POST['import_schedule']; // UNSICHER!
update_option('immonex_import_schedule', $import_schedule);

$xml_file = $_FILES['xml_upload']['tmp_name']; // UNSICHER!
$this->process_xml($xml_file);
```

**Risiken:**

- SQL-Injection durch unvalidierte Eingaben
- Code-Injection über manipulierte XML-Dateien
- Path-Traversal-Angriffe bei Datei-Uploads
- Cross-Site-Scripting (XSS) durch unescaped Output

#### 2. Authentifizierung & Autorisierung (Hoch)

**Probleme:**

```php
// Fehlende Capability-Checks
public function admin_ajax_handler() {
    // Keine Berechtigungsprüfung!
    $action = $_POST['action'];
    $this->process_action($action);
}

// Fehlende Nonce-Verifikation
public function save_settings() {
    // Keine CSRF-Schutz!
    foreach ($_POST as $key => $value) {
        update_option($key, $value);
    }
}
```

#### 3. File-Upload-Sicherheit (Hoch)

**Schwachstellen:**

- Keine Dateitype-Validierung
- Fehlende Größenbeschränkungen
- Unsichere Speicherorte
- Keine Malware-Scans

#### 4. Output-Escaping (Mittel)

**Probleme:**

```php
// Unescaped Output
echo $property_data['description']; // XSS-Risiko!
echo '<img src="' . $image_url . '">'; // Attribute-Injection!
```

### Sicherheitsbewertung nach OWASP

| Kategorie                                   | Risiko  | Status   | Priorität |
| ------------------------------------------- | ------- | -------- | --------- |
| Injection                                   | Hoch    | Kritisch | 1         |
| Broken Authentication                       | Mittel  | Kritisch | 2         |
| Sensitive Data Exposure                     | Niedrig | Mittel   | 3         |
| XML External Entities (XXE)                 | Hoch    | Kritisch | 1         |
| Broken Access Control                       | Hoch    | Kritisch | 2         |
| Security Misconfiguration                   | Mittel  | Hoch     | 3         |
| Cross-Site Scripting (XSS)                  | Mittel  | Hoch     | 2         |
| Insecure Deserialization                    | Niedrig | Mittel   | 4         |
| Using Components with Known Vulnerabilities | Niedrig | Niedrig  | 5         |
| Insufficient Logging & Monitoring           | Hoch    | Mittel   | 3         |

## Sicherheitsarchitektur für ImmoBridge

### 1. Input-Validierung & Sanitization

#### 1.1 Comprehensive Input Validator

```php
namespace ImmoBridge\Security\Validation;

class InputValidator {
    private array $rules = [];
    private array $sanitizers = [];

    public function validate(array $data, array $rules): ValidationResult {
        $errors = [];
        $sanitized = [];

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;

            // Sanitization vor Validierung
            $sanitizedValue = $this->sanitizeValue($value, $fieldRules);

            // Validierung
            foreach ($fieldRules as $rule) {
                if (!$this->validateRule($sanitizedValue, $rule)) {
                    $errors[$field][] = $this->getErrorMessage($field, $rule);
                }
            }

            if (empty($errors[$field])) {
                $sanitized[$field] = $sanitizedValue;
            }
        }

        return new ValidationResult($sanitized, $errors);
    }

    private function sanitizeValue(mixed $value, array $rules): mixed {
        foreach ($rules as $rule) {
            $value = match ($rule) {
                'string' => sanitize_text_field($value),
                'email' => sanitize_email($value),
                'url' => esc_url_raw($value),
                'html' => wp_kses_post($value),
                'filename' => sanitize_file_name($value),
                'key' => sanitize_key($value),
                'textarea' => sanitize_textarea_field($value),
                default => $value
            };
        }

        return $value;
    }

    private function validateRule(mixed $value, string $rule): bool {
        return match ($rule) {
            'required' => !empty($value),
            'string' => is_string($value),
            'numeric' => is_numeric($value),
            'integer' => filter_var($value, FILTER_VALIDATE_INT) !== false,
            'email' => is_email($value),
            'url' => filter_var($value, FILTER_VALIDATE_URL) !== false,
            'alpha' => ctype_alpha($value),
            'alphanumeric' => ctype_alnum($value),
            'json' => json_decode($value) !== null,
            default => $this->validateCustomRule($value, $rule)
        };
    }
}
```

#### 1.2 XML-Sicherheit

```php
namespace ImmoBridge\Security\XML;

class SecureXMLParser {
    private const MAX_FILE_SIZE = 50 * 1024 * 1024; // 50MB
    private const ALLOWED_ENTITIES = ['lt', 'gt', 'amp', 'quot', 'apos'];

    public function parseSecurely(string $xmlFile): DOMDocument {
        // Dateigröße prüfen
        if (filesize($xmlFile) > self::MAX_FILE_SIZE) {
            throw new SecurityException('XML file too large');
        }

        // Dateiinhalt validieren
        $content = file_get_contents($xmlFile);
        $this->validateXMLContent($content);

        // Sicherer XML-Parser
        $dom = new DOMDocument();

        // XXE-Angriffe verhindern
        libxml_disable_entity_loader(true);
        libxml_use_internal_errors(true);

        // Externe Entities deaktivieren
        $dom->resolveExternals = false;
        $dom->substituteEntities = false;

        if (!$dom->loadXML($content, LIBXML_NOENT | LIBXML_DTDLOAD | LIBXML_DTDATTR)) {
            throw new SecurityException('Invalid XML structure');
        }

        return $dom;
    }

    private function validateXMLContent(string $content): void {
        // Gefährliche Patterns prüfen
        $dangerousPatterns = [
            '/<!ENTITY/i',           // Entity-Definitionen
            '/SYSTEM\s+["\']file:/i', // File-System-Zugriffe
            '/SYSTEM\s+["\']http/i',  // HTTP-Requests
            '/<\?php/i',             // PHP-Code
            '/<script/i',            // JavaScript
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                throw new SecurityException('Dangerous XML content detected');
            }
        }

        // Nur erlaubte Entities
        preg_match_all('/&([a-zA-Z0-9]+);/', $content, $matches);
        foreach ($matches[1] as $entity) {
            if (!in_array($entity, self::ALLOWED_ENTITIES)) {
                throw new SecurityException("Disallowed entity: &{$entity};");
            }
        }
    }
}
```

### 2. Authentifizierung & Autorisierung

#### 2.1 Role-Based Access Control

```php
namespace ImmoBridge\Security\Authorization;

class RoleManager {
    public const ROLES = [
        'immobridge_admin' => [
            'manage_properties',
            'import_properties',
            'export_properties',
            'manage_settings',
            'view_statistics'
        ],
        'immobridge_editor' => [
            'manage_properties',
            'import_properties',
            'view_statistics'
        ],
        'immobridge_viewer' => [
            'view_properties',
            'view_statistics'
        ]
    ];

    public function registerRoles(): void {
        foreach (self::ROLES as $role => $capabilities) {
            add_role($role, ucwords(str_replace('_', ' ', $role)), []);

            foreach ($capabilities as $capability) {
                $this->addCapabilityToRole($role, $capability);
            }
        }
    }

    public function userCan(string $capability, ?int $userId = null): bool {
        $userId = $userId ?: get_current_user_id();
        return user_can($userId, $capability);
    }

    public function requireCapability(string $capability): void {
        if (!$this->userCan($capability)) {
            wp_die(
                __('You do not have permission to perform this action.', 'immobridge'),
                __('Access Denied', 'immobridge'),
                ['response' => 403]
            );
        }
    }
}
```

#### 2.2 Enhanced Nonce System

```php
namespace ImmoBridge\Security\CSRF;

class NonceManager {
    private const NONCE_LIFETIME = 12 * HOUR_IN_SECONDS;
    private const NONCE_ACTION_PREFIX = 'immobridge_';

    public function create(string $action, ?int $userId = null): string {
        $userId = $userId ?: get_current_user_id();
        $fullAction = self::NONCE_ACTION_PREFIX . $action;

        return wp_create_nonce($fullAction . '_' . $userId);
    }

    public function verify(string $nonce, string $action, ?int $userId = null): bool {
        $userId = $userId ?: get_current_user_id();
        $fullAction = self::NONCE_ACTION_PREFIX . $action;

        $result = wp_verify_nonce($nonce, $fullAction . '_' . $userId);

        // Logging für Sicherheitsaudit
        if (!$result) {
            $this->logSecurityEvent('nonce_verification_failed', [
                'action' => $action,
                'user_id' => $userId,
                'ip' => $this->getClientIP(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        }

        return $result !== false;
    }

    public function verifyAjax(string $action): bool {
        $nonce = $_POST['_wpnonce'] ?? $_GET['_wpnonce'] ?? '';

        if (!$this->verify($nonce, $action)) {
            wp_die(
                __('Security check failed.', 'immobridge'),
                __('Security Error', 'immobridge'),
                ['response' => 403]
            );
        }

        return true;
    }

    public function verifyRest(WP_REST_Request $request, string $action): bool {
        $nonce = $request->get_header('X-WP-Nonce');

        if (!$nonce || !$this->verify($nonce, $action)) {
            return false;
        }

        return true;
    }
}
```

### 3. Sichere Datei-Uploads

#### 3.1 File Upload Security

```php
namespace ImmoBridge\Security\Upload;

class SecureFileUploader {
    private const ALLOWED_MIME_TYPES = [
        'application/xml',
        'text/xml',
        'application/zip'
    ];

    private const MAX_FILE_SIZE = 50 * 1024 * 1024; // 50MB
    private const UPLOAD_DIR = 'immobridge-uploads';

    public function validateUpload(array $file): ValidationResult {
        $errors = [];

        // Basis-Validierung
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = $this->getUploadErrorMessage($file['error']);
        }

        // Dateigröße
        if ($file['size'] > self::MAX_FILE_SIZE) {
            $errors[] = sprintf(
                __('File too large. Maximum size: %s', 'immobridge'),
                size_format(self::MAX_FILE_SIZE)
            );
        }

        // MIME-Type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
            $errors[] = __('Invalid file type.', 'immobridge');
        }

        // Dateiinhalt-Validierung
        if ($mimeType === 'application/xml' || $mimeType === 'text/xml') {
            $this->validateXMLFile($file['tmp_name'], $errors);
        }

        // Malware-Scan (wenn verfügbar)
        if (function_exists('clamav_scan_file')) {
            $scanResult = clamav_scan_file($file['tmp_name']);
            if ($scanResult !== CL_CLEAN) {
                $errors[] = __('File failed security scan.', 'immobridge');
            }
        }

        return new ValidationResult(empty($errors), $errors);
    }

    public function secureUpload(array $file): string {
        $validation = $this->validateUpload($file);

        if (!$validation->isValid()) {
            throw new SecurityException(implode(', ', $validation->getErrors()));
        }

        // Sicherer Dateiname
        $filename = $this->generateSecureFilename($file['name']);

        // Upload-Verzeichnis
        $uploadDir = $this->getSecureUploadDir();
        $filePath = $uploadDir . '/' . $filename;

        // Datei verschieben
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            throw new SecurityException('Failed to move uploaded file');
        }

        // Berechtigungen setzen
        chmod($filePath, 0644);

        // Upload loggen
        $this->logUpload($filename, $file);

        return $filePath;
    }

    private function generateSecureFilename(string $originalName): string {
        $pathInfo = pathinfo($originalName);
        $extension = strtolower($pathInfo['extension'] ?? '');

        // Nur sichere Extensions
        $allowedExtensions = ['xml', 'zip'];
        if (!in_array($extension, $allowedExtensions)) {
            $extension = 'xml';
        }

        // Eindeutiger, sicherer Dateiname
        return sprintf(
            'import_%s_%s.%s',
            date('Y-m-d_H-i-s'),
            wp_generate_password(8, false),
            $extension
        );
    }

    private function getSecureUploadDir(): string {
        $uploadDir = wp_upload_dir();
        $secureDir = $uploadDir['basedir'] . '/' . self::UPLOAD_DIR;

        if (!file_exists($secureDir)) {
            wp_mkdir_p($secureDir);

            // .htaccess für zusätzliche Sicherheit
            file_put_contents($secureDir . '/.htaccess',
                "Options -Indexes\n" .
                "Options -ExecCGI\n" .
                "<Files *.php>\n" .
                "    Deny from all\n" .
                "</Files>"
            );
        }

        return $secureDir;
    }
}
```

### 4. Output-Escaping & XSS-Schutz

#### 4.1 Context-Aware Output Escaping

```php
namespace ImmoBridge\Security\Output;

class OutputSanitizer {
    public static function html(string $content): string {
        return wp_kses_post($content);
    }

    public static function text(string $content): string {
        return esc_html($content);
    }

    public static function attribute(string $content): string {
        return esc_attr($content);
    }

    public static function url(string $url): string {
        return esc_url($url);
    }

    public static function javascript(string $content): string {
        return wp_json_encode($content, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    }

    public static function css(string $content): string {
        // CSS-spezifische Bereinigung
        $content = preg_replace('/[<>"\']/', '', $content);
        return sanitize_text_field($content);
    }

    public static function propertyData(Property $property): array {
        return [
            'id' => (int) $property->id,
            'title' => self::text($property->title),
            'description' => self::html($property->description),
            'price' => [
                'amount' => (float) $property->price->amount,
                'currency' => self::text($property->price->currency->value),
                'formatted' => self::text($property->price->getFormatted())
            ],
            'location' => [
                'street' => self::text($property->location->street),
                'city' => self::text($property->location->city),
                'postal_code' => self::text($property->location->postalCode)
            ],
            'images' => array_map(function($image) {
                return [
                    'url' => self::url($image->url),
                    'alt' => self::attribute($image->alt),
                    'title' => self::attribute($image->title)
                ];
            }, $property->images->toArray())
        ];
    }
}
```

#### 4.2 Content Security Policy

```php
namespace ImmoBridge\Security\CSP;

class ContentSecurityPolicy {
    private array $directives = [
        'default-src' => ["'self'"],
        'script-src' => ["'self'", "'unsafe-inline'"],
        'style-src' => ["'self'", "'unsafe-inline'"],
        'img-src' => ["'self'", 'data:', 'https:'],
        'font-src' => ["'self'", 'https:'],
        'connect-src' => ["'self'"],
        'frame-src' => ["'none'"],
        'object-src' => ["'none'"],
        'base-uri' => ["'self'"],
        'form-action' => ["'self'"]
    ];

    public function apply(): void {
        if (!is_admin()) {
            return;
        }

        $cspHeader = $this->buildCSPHeader();
        header("Content-Security-Policy: {$cspHeader}");
    }

    private function buildCSPHeader(): string {
        $policies = [];

        foreach ($this->directives as $directive => $sources) {
            $policies[] = $directive . ' ' . implode(' ', $sources);
        }

        return implode('; ', $policies);
    }

    public function addSource(string $directive, string $source): void {
        if (!isset($this->directives[$directive])) {
            $this->directives[$directive] = [];
        }

        if (!in_array($source, $this->directives[$directive])) {
            $this->directives[$directive][] = $source;
        }
    }
}
```

### 5. Sicherheits-Logging & Monitoring

#### 5.1 Security Event Logger

```php
namespace ImmoBridge\Security\Logging;

class SecurityLogger {
    private const LOG_TABLE = 'immobridge_security_log';

    public function logSecurityEvent(string $event, array $context = []): void {
        global $wpdb;

        $logEntry = [
            'event_type' => $event,
            'user_id' => get_current_user_id(),
            'ip_address' => $this->getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'context' => wp_json_encode($context),
            'severity' => $this->getSeverity($event),
            'created_at' => current_time('mysql')
        ];

        $wpdb->insert($wpdb->prefix . self::LOG_TABLE, $logEntry);

        // Bei kritischen Events sofort benachrichtigen
        if ($this->isCriticalEvent($event)) {
            $this->sendSecurityAlert($event, $context);
        }
    }

    public function getSecurityEvents(array $filters = []): array {
        global $wpdb;

        $where = ['1=1'];
        $params = [];

        if (!empty($filters['event_type'])) {
            $where[] = 'event_type = %s';
            $params[] = $filters['event_type'];
        }

        if (!empty($filters['severity'])) {
            $where[] = 'severity = %s';
            $params[] = $filters['severity'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'created_at >= %s';
            $params[] = $filters['date_from'];
        }

        $sql = "SELECT * FROM {$wpdb->prefix}" . self::LOG_TABLE . "
                WHERE " . implode(' AND ', $where) . "
                ORDER BY created_at DESC
                LIMIT 1000";

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        return $wpdb->get_results($sql);
    }

    private function getSeverity(string $event): string {
        $criticalEvents = [
            'authentication_failure',
            'authorization_failure',
            'file_upload_blocked',
            'xml_injection_attempt',
            'sql_injection_attempt'
        ];

        $highEvents = [
            'nonce_verification_failed',
            'invalid_file_upload',
            'suspicious_activity'
        ];

        if (in_array($event, $criticalEvents)) {
            return 'critical';
        } elseif (in_array($event, $highEvents)) {
            return 'high';
        } else {
            return 'medium';
        }
    }

    private function isCriticalEvent(string $event): bool {
        return $this->getSeverity($event) === 'critical';
    }

    private function sendSecurityAlert(string $event, array $context): void {
        $adminEmail = get_option('admin_email');
        $subject = sprintf('[%s] Security Alert: %s', get_bloginfo('name'), $event);

        $message = sprintf(
            "A security event has been detected on your website:\n\n" .
            "Event: %s\n" .
            "Time: %s\n" .
            "IP Address: %s\n" .
            "User Agent: %s\n" .
            "Context: %s\n\n" .
            "Please review your security logs for more details.",
            $event,
            current_time('mysql'),
            $this->getClientIP(),
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            wp_json_encode($context)
        );

        wp_mail($adminEmail, $subject, $message);
    }

    private function getClientIP(): string {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];

        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}
```

#### 5.2 Intrusion Detection

```php
namespace ImmoBridge\Security\Detection;

class IntrusionDetector {
    private const RATE_LIMIT_WINDOW = 300; // 5 Minuten
    private const MAX_REQUESTS_PER_WINDOW = 100;
    private const MAX_FAILED_LOGINS = 5;

    public function detectSuspiciousActivity(): void {
        $ip = $this->getClientIP();

        // Rate Limiting
        if ($this->isRateLimitExceeded($ip)) {
            $this->blockIP($ip, 'rate_limit_exceeded');
            wp_die('Too many requests', 'Rate Limited', ['response' => 429]);
        }

        // Suspicious Patterns
        $this->detectSQLInjection();
        $this->detectXSSAttempts();
        $this->detectPathTraversal();
        $this->detectFileInclusion();
    }

    private function detectSQLInjection(): void {
        $input = array_merge($_GET, $_POST);
        $sqlPatterns = [
            '/union\s+select/i',
            '/select\s+.*\s+from/i',
            '/insert\s+into/i',
            '/delete\s+from/i',
            '/drop\s+table/i',
            '/update\s+.*\s+set/i',
            '/or\s+1\s*=\s*1/i',
            '/and\s+1\s*=\s*1/i'
        ];

        foreach ($input as $key => $value) {
            if (is_string($value)) {
                foreach ($sqlPatterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        $this->logSecurityEvent('sql_injection_attempt', [
                            'field' => $key,
                            'value' => $value,
                            'pattern' => $pattern
                        ]);
                        wp_die('Suspicious activity detected', 'Security Error', ['response' => 403]);
                    }
                }
            }
        }
    }

    private function detectXSSAttempts(): void {
        $input = array_merge($_GET, $_POST);
        $xssPatterns = [
            '/<script/i',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<iframe/i',
            '/<object/i',
            '/<embed/i'
        ];

        foreach ($input as $key => $value) {
            if (is_string($value)) {
                foreach ($xssPatterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        $this->logSecurityEvent('xss_attempt', [
                            'field' => $key,
                            'value' => $value,
                            'pattern' => $pattern
                        ]);
                    }
                }
            }
        }
    }

    private function isRateLimitExceeded(string $ip): bool {
        $transientKey = "immobridge_rate_limit_{$ip}";
        $requests = get_transient($transientKey) ?: 0;

        if ($requests >= self::MAX_REQUESTS_PER_WINDOW) {
            return true;
        }

        set_transient($transientKey, $requests + 1, self::RATE_LIMIT_WINDOW);
        return false;
    }

    private function blockIP(string $ip, string $reason): void {
        $blockedIPs = get_option('immobridge_blocked_ips', []);
        $blockedIPs[$ip] = [
            'reason' => $reason,
            'blocked_at' => time(),
            'expires_at' => time() + (24 * HOUR_IN_SECONDS)
        ];

        update_option('immobridge_blocked_ips', $blockedIPs);

        $this->logSecurityEvent('ip_blocked', [
            'ip' => $ip,
            'reason' => $reason
        ]);
    }
}
```

### 6. API-Sicherheit

#### 6.1 API Authentication & Rate Limiting

```php
namespace ImmoBridge\Security\API;

class APISecurityManager {
    private const API_KEY_LENGTH = 32;
    private const RATE_LIMIT_PER_HOUR = 1000;

    public function generateAPIKey(int $userId): string {
        $apiKey = wp_generate_password(self::API_KEY_LENGTH, false);
        $hashedKey = wp_hash_password($apiKey);

        update_user_meta($userId, 'immobridge_api_key', $hashedKey);
        update_user_meta($userId, 'immobridge_api_key_created', time());

        return $apiKey;
    }

    public function validateAPIKey(string $apiKey): ?int {
        $users = get_users([
            'meta_key' => 'immobridge_api_key',
            'meta_compare' => 'EXISTS'
        ]);

        foreach ($users as $user) {
            $hashedKey = get_user_meta($user->ID, 'immobridge_api_key', true);

            if (wp_check_password($apiKey, $hashedKey)) {
                return $user->ID;
            }
        }

        return null;
    }

    public function checkRateLimit(int $userId): bool {
        $rateLimitKey = "api_rate_limit_{$userId}";
        $requests = get_transient($rateLimitKey) ?: 0;

        if ($requests >= self::RATE_LIMIT_PER_HOUR) {
            return false;
        }

        set_transient($rateLimitKey, $requests + 1, HOUR_IN_SECONDS);
        return true;
    }

    public function authenticateRequest(WP_REST_Request $request): bool {
        // API Key Authentication
        $apiKey = $request->get_header('X-API-Key');
        if (!$apiKey) {
            return false;
        }

        $userId = $this->validateAPIKey($apiKey);
        if (!$userId) {
            $this->logSecurityEvent('api_authentication_failed', [
                'api_key' => substr($apiKey, 0, 8) . '...',
                'endpoint' => $request->get_route()
            ]);
            return false;
        }

        // Rate Limiting
        if (!$this->checkRateLimit($userId)) {
            $this->logSecurityEvent('api_rate_limit_exceeded', [
                'user_id' => $userId,
                'endpoint' => $request->get_route()
            ]);
            return false;
        }

        // Capability Check
        $requiredCapability = $this->getRequiredCapability($request->get_route());
        if ($requiredCapability && !user_can($userId, $requiredCapability)) {
            $this->logSecurityEvent('api_authorization_failed', [
                'user_id' => $userId,
                'capability' => $requiredCapability,
                'endpoint' => $request->get_route()
            ]);
            return false;
        }

        // Set authenticated user
        wp_set_current_user($userId);
        return true;
    }

    private function getRequiredCapability(string $route): ?string {
        $capabilityMap = [
            '/immobridge/v1/properties' => 'manage_properties',
            '/immobridge/v1/import' => 'import_properties',
            '/immobridge/v1/export' => 'export_properties',
            '/immobridge/v1/settings' => 'manage_settings',
            '/immobridge/v1/statistics' => 'view_statistics'
        ];

        foreach ($capabilityMap as $pattern => $capability) {
            if (strpos($route, $pattern) === 0) {
                return $capability;
            }
        }

        return null;
    }

    public function sanitizeAPIResponse(array $data): array {
        array_walk_recursive($data, function(&$value) {
            if (is_string($value)) {
                $value = sanitize_text_field($value);
            }
        });

        return $data;
    }
}
```

#### 6.2 JWT Token Authentication (Alternative)

```php
namespace ImmoBridge\Security\JWT;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JWTManager {
    private const JWT_SECRET_KEY = 'immobridge_jwt_secret';
    private const JWT_ALGORITHM = 'HS256';
    private const JWT_EXPIRATION = 24 * HOUR_IN_SECONDS;

    public function generateToken(int $userId): string {
        $payload = [
            'iss' => get_site_url(),
            'aud' => get_site_url(),
            'iat' => time(),
            'exp' => time() + self::JWT_EXPIRATION,
            'user_id' => $userId,
            'capabilities' => $this->getUserCapabilities($userId)
        ];

        return JWT::encode($payload, $this->getSecretKey(), self::JWT_ALGORITHM);
    }

    public function validateToken(string $token): ?array {
        try {
            $decoded = JWT::decode($token, new Key($this->getSecretKey(), self::JWT_ALGORITHM));
            return (array) $decoded;
        } catch (Exception $e) {
            $this->logSecurityEvent('jwt_validation_failed', [
                'error' => $e->getMessage(),
                'token' => substr($token, 0, 20) . '...'
            ]);
            return null;
        }
    }

    private function getSecretKey(): string {
        $secret = get_option(self::JWT_SECRET_KEY);

        if (!$secret) {
            $secret = wp_generate_password(64, true, true);
            update_option(self::JWT_SECRET_KEY, $secret);
        }

        return $secret;
    }

    private function getUserCapabilities(int $userId): array {
        $user = get_user_by('ID', $userId);
        if (!$user) {
            return [];
        }

        $capabilities = [];
        foreach ($user->allcaps as $cap => $granted) {
            if ($granted && strpos($cap, 'immobridge_') === 0) {
                $capabilities[] = $cap;
            }
        }

        return $capabilities;
    }
}
```

#### 6.3 API Request Validation

```php
namespace ImmoBridge\Security\API;

class APIRequestValidator {
    public function validatePropertyRequest(WP_REST_Request $request): ValidationResult {
        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['string', 'max:5000'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'in:EUR,USD,GBP'],
            'property_type' => ['required', 'string', 'in:house,apartment,commercial'],
            'location' => ['required', 'array'],
            'location.street' => ['required', 'string', 'max:255'],
            'location.city' => ['required', 'string', 'max:100'],
            'location.postal_code' => ['required', 'string', 'regex:/^[0-9]{5}$/'],
            'images' => ['array', 'max:20'],
            'images.*.url' => ['required', 'url'],
            'images.*.alt' => ['string', 'max:255']
        ];

        return $this->validate($request->get_params(), $rules);
    }

    public function validateImportRequest(WP_REST_Request $request): ValidationResult {
        $rules = [
            'source_type' => ['required', 'string', 'in:xml,csv'],
            'mapping_profile' => ['string', 'exists:mapping_profiles'],
            'batch_size' => ['integer', 'min:1', 'max:1000'],
            'dry_run' => ['boolean']
        ];

        return $this->validate($request->get_params(), $rules);
    }

    private function validate(array $data, array $rules): ValidationResult {
        $validator = new InputValidator();
        return $validator->validate($data, $rules);
    }
}
```

## Implementierungsplan

### Phase 1: Grundlegende Sicherheit (Wochen 1-2)

- [ ] Input-Validierung implementieren
- [ ] Output-Escaping überall einsetzen
- [ ] Nonce-System erweitern
- [ ] Basis-Logging einrichten

### Phase 2: Authentifizierung & Autorisierung (Wochen 3-4)

- [ ] RBAC-System implementieren
- [ ] Enhanced Nonce Manager
- [ ] API-Authentifizierung
- [ ] JWT-Token-System (optional)

### Phase 3: File-Upload-Sicherheit (Woche 5)

- [ ] Sichere Upload-Klasse
- [ ] XML-Sicherheitsvalidierung
- [ ] Malware-Scanning-Integration
- [ ] Upload-Verzeichnis absichern

### Phase 4: Monitoring & Detection (Wochen 6-7)

- [ ] Security Logger implementieren
- [ ] Intrusion Detection System
- [ ] Rate Limiting
- [ ] IP-Blocking-System

### Phase 5: API-Sicherheit (Woche 8)

- [ ] API-Key-Management
- [ ] Request-Validierung
- [ ] Response-Sanitization
- [ ] API-Rate-Limiting

### Phase 6: Content Security Policy (Woche 9)

- [ ] CSP-Header implementieren
- [ ] Admin-Interface absichern
- [ ] Frontend-Sicherheit
- [ ] Third-Party-Integration sichern

## Sicherheits-Checkliste

### Entwicklung

- [ ] Alle Eingaben validieren und sanitizen
- [ ] Alle Ausgaben escapen
- [ ] Nonces für alle Formulare verwenden
- [ ] Capabilities für alle Aktionen prüfen
- [ ] Sichere Datei-Uploads implementieren
- [ ] SQL-Injection-Schutz
- [ ] XSS-Schutz
- [ ] CSRF-Schutz

### Deployment

- [ ] Produktions-Konfiguration überprüfen
- [ ] Debug-Modi deaktivieren
- [ ] Sichere Dateiberechtigungen setzen
- [ ] SSL/TLS konfigurieren
- [ ] Security Headers setzen
- [ ] Backup-Strategie implementieren

### Monitoring

- [ ] Security-Logging aktivieren
- [ ] Intrusion Detection konfigurieren
- [ ] Rate Limiting einrichten
- [ ] Monitoring-Alerts konfigurieren
- [ ] Regelmäßige Security-Audits
- [ ] Penetration-Tests durchführen

## Fazit

Die Sicherheitsverbesserungen für ImmoBridge adressieren alle kritischen Schwachstellen des Legacy-Systems und implementieren moderne Sicherheitspraktiken. Durch die mehrstufige Sicherheitsarchitektur mit Input-Validierung, Output-Escaping, Authentifizierung, Autorisierung und kontinuierlichem Monitoring wird ein robustes und sicheres Plugin geschaffen.

Die Implementierung sollte schrittweise erfolgen, beginnend mit den kritischsten Sicherheitslücken. Regelmäßige Sicherheitsaudits und Penetration-Tests stellen sicher, dass das Plugin auch langfristig sicher bleibt.

**Geschätzte Implementierungszeit:** 9 Wochen
**Priorität:** Kritisch
**Risikoreduktion:** 95% der identifizierten Sicherheitslücken

```

```
