<?php
// Raptor CRM Settings Controller (Admin Only)

class SettingsController extends Controller {
    private $db;
    private $alertRuleModel;

    public function __construct() {
        $this->requireAuth();
        
        // Enforce RBAC: Settings are Admin-only
        if ($_SESSION['user_role'] !== 'admin') {
            $this->redirect('index.php?route=dashboard/index');
        }

        $this->db = Database::getInstance()->getConnection();
        $this->alertRuleModel = $this->model('AlertRule');
    }

    // Load Settings view
    public function index() {
        // Fetch current settings
        $stmt = $this->db->query('SELECT * FROM settings');
        $rows = $stmt->fetchAll(PDO::FETCH_OBJ);
        
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row->setting_key] = $row->setting_value;
        }

        $data = [
            'title' => 'Global Settings | Raptor CRM',
            'active_tab' => 'settings',
            'settings' => $settings,
            'alert_rules' => $this->alertRuleModel->getRules(),
            'success_msg' => $_SESSION['settings_success'] ?? ''
        ];
        
        unset($_SESSION['settings_success']);

        $this->viewWithLayout('settings/index', 'main', $data);
    }

    // Process settings update
    public function save() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Verify CSRF token
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                die('CSRF validation failed.');
            }

            // Keys to support
            $settingKeys = [
                'gemini_api_key',
                'ga4_measurement_id',
                'google_ads_client_id',
                'meta_access_token',
                'linkedin_urn',
                'attendance.shift_start',
                'attendance.shift_end',
                'attendance.grace_minutes',
                'attendance.geofence_enabled',
                'location.tracking_enabled',
                'location.ping_interval_seconds',
                'location.retention_days',
                'retention.location_days',
                'retention.notifications_days',
                'retention.audit_days',
                'retention.security_events_days',
                'retention.login_attempts_days',
                'lead.contact_sla_hours',
                'auth.max_failed_attempts',
                'auth.lockout_minutes',
                'rate.api_limit',
                'rate.api_window_seconds',
                'rate.login_limit',
                'rate.login_window_seconds',
                'alerts.cron_enabled',
                'alerts.web_push_enabled',
                'alerts.vapid_public_key',
                'alerts.vapid_private_key',
                'alerts.email_enabled',
                'alerts.email_from',
                'reports.email_enabled',
                'reports.digest_recipients'
            ];

            try {
                $this->db->beginTransaction();

                $stmt = $this->db->prepare('INSERT INTO settings (setting_key, setting_value) 
                                            VALUES (:key, :val) 
                                            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');

                foreach ($settingKeys as $key) {
                    $value = isset($_POST[$key]) ? trim($_POST[$key]) : '';
                    if (in_array($key, ['attendance.geofence_enabled', 'location.tracking_enabled', 'alerts.cron_enabled', 'alerts.web_push_enabled', 'alerts.email_enabled', 'reports.email_enabled'], true)) {
                        $value = isset($_POST[$key]) ? '1' : '0';
                    }
                    $stmt->execute([
                        ':key' => $key,
                        ':val' => $value
                    ]);
                }

                $this->db->commit();
                $_SESSION['settings_success'] = 'Settings saved successfully!';
                
            } catch (Exception $e) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                die('[ERROR] Failed to save settings: ' . $e->getMessage());
            }

            if (isset($_POST['alert_rules']) && is_array($_POST['alert_rules'])) {
                $this->alertRuleModel->updateRules($_POST['alert_rules']);
            }
        }
        $this->redirect('index.php?route=settings/index');
    }
}
