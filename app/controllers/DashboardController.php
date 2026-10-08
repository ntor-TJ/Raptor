<?php
// Raptor CRM Dashboard Controller

class DashboardController extends Controller {
    private $monitoringModel;
    private $dashboardModuleModel;

    public function __construct() {
        // Enforce user authentication for all dashboard routes
        $this->requireAuth();
        $this->monitoringModel = $this->model('Monitoring');
        $this->dashboardModuleModel = $this->model('DashboardModule');
    }

    // Dedicated dashboard module landing page
    public function index() {
        $dashboards = $this->dashboardModuleModel->dashboardsForRole($_SESSION['user_role']);
        $data = [
            'title' => 'Dashboard Module | Raptor CRM',
            'active_tab' => 'dashboard_module',
            'dashboards' => $dashboards,
        ];

        $this->viewWithLayout('dashboard/index', 'main', $data);
    }

    public function show($key = 'sales_command') {
        $dashboard = $this->dashboardModuleModel->getDashboard($key, $_SESSION['user_role']);
        if (!$dashboard) {
            $fallback = array_key_first($this->dashboardModuleModel->dashboardsForRole($_SESSION['user_role']));
            $this->redirect('index.php?route=dashboard/show/' . ($fallback ?: 'executive'));
        }

        $prefs = $this->dashboardModuleModel->preferences((int) $_SESSION['user_id'], $key);
        $data = [
            'title' => $dashboard['label'] . ' | Dashboard Module',
            'active_tab' => 'dashboard_module',
            'dashboard' => $dashboard,
            'dashboards' => $this->dashboardModuleModel->dashboardsForRole($_SESSION['user_role']),
            'prefs' => $prefs,
            'widgets' => $this->dashboardModuleModel->visibleWidgets($dashboard, $prefs),
            'widget_meta' => $this->dashboardModuleModel->widgetMeta(),
            'dashboard_data' => $this->dashboardModuleModel->data($key, $prefs, $this->visibleUserIds()),
        ];

        $this->viewWithLayout('dashboard/module', 'main', $data);
    }

    public function configure($key = 'sales_command') {
        $dashboard = $this->dashboardModuleModel->getDashboard($key, $_SESSION['user_role']);
        if (!$dashboard) {
            $this->redirect('index.php?route=dashboard/index');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->dashboardModuleModel->savePreferences(
                (int) $_SESSION['user_id'],
                $key,
                $_POST,
                $dashboard['widgets']
            );
            $this->audit('Updated dashboard preferences: ' . $key, 'dashboard_preferences');
        }
        $this->redirect('index.php?route=dashboard/show/' . $key);
    }

    public function templates() {
        if (!Policy::canCreateDashboardTemplate()) {
            $this->viewWithLayout('errors/403', 'main', [
                'title' => 'Access Denied',
                'message' => 'Only analysts and admins can create dashboard templates.'
            ]);
            return;
        }

        $this->viewWithLayout('dashboard/templates', 'main', [
            'title' => 'Dashboard Templates | Raptor CRM',
            'active_tab' => 'dashboard_module',
            'dashboards' => $this->dashboardModuleModel->dashboardsForRole($_SESSION['user_role']),
            'templates' => $this->dashboardModuleModel->templatesForUser((int) $_SESSION['user_id'], $_SESSION['user_role']),
            'widget_meta' => $this->dashboardModuleModel->widgetMeta(),
        ]);
    }

    public function createTemplate() {
        if (!Policy::canCreateDashboardTemplate()) {
            $this->redirect('index.php?route=dashboard/index');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
            $this->dashboardModuleModel->createTemplate($_POST, (int) $_SESSION['user_id'], $_SESSION['user_role']);
            $this->audit('Created dashboard template', 'dashboard_templates');
        }

        $this->redirect('index.php?route=dashboard/templates');
    }

    // Executive Marketing Overview Dashboard
    public function executive() {
        // Employers, Managers, and Admins can access
        $allowedRoles = ['admin', 'manager', 'employer'];
        if (!in_array($_SESSION['user_role'], $allowedRoles)) {
            $this->redirect('index.php?route=dashboard/index');
        }

        $data = [
            'title' => 'Executive Marketing Overview | Raptor CRM',
            'active_tab' => 'executive',
            'is_readonly' => ($_SESSION['user_role'] === 'employer')
        ];

        // Render dashboard layout with content view
        $this->viewWithLayout('dashboard/executive', 'main', $data);
    }

    // Channel & Campaign Performance Dashboard
    public function channels() {
        // Analysts, Managers, and Admins can access
        $allowedRoles = ['admin', 'manager', 'analyst'];
        if (!in_array($_SESSION['user_role'], $allowedRoles)) {
            $this->redirect('index.php?route=dashboard/index');
        }

        $data = [
            'title' => 'Channel & Campaign Performance | Raptor CRM',
            'active_tab' => 'channels'
        ];

        $this->viewWithLayout('dashboard/channels', 'main', $data);
    }

    // Customer Intelligence & AI Analytics Dashboard
    public function customer() {
        // Analysts, Managers, and Admins can access
        $allowedRoles = ['admin', 'manager', 'analyst'];
        if (!in_array($_SESSION['user_role'], $allowedRoles)) {
            $this->redirect('index.php?route=dashboard/index');
        }

        $data = [
            'title' => 'Customer Intelligence & Analytics | Raptor CRM',
            'active_tab' => 'customer'
        ];

        $this->viewWithLayout('dashboard/customer', 'main', $data);
    }

    public function monitoring() {
        if (!in_array($_SESSION['user_role'], ['admin', 'manager', 'team_leader'])) {
            $this->redirect('index.php?route=attendance/index');
        }

        $scope = $this->visibleUserIds();
        $data = [
            'title' => 'Sales Monitoring Command Center | Raptor CRM',
            'active_tab' => 'monitoring_dashboard',
            'live_board' => $this->monitoringModel->liveBoard($scope),
            'rollup' => $this->monitoringModel->todayRollup($scope),
            'pipeline' => $this->monitoringModel->pipelineForecast($scope),
        ];

        $this->viewWithLayout('dashboard/monitoring', 'main', $data);
    }

    public function day($userId = 0) {
        if (!in_array($_SESSION['user_role'], ['admin', 'manager', 'team_leader'])) {
            $this->redirect('index.php?route=attendance/index');
        }
        $userId = (int) $userId;
        $scope = $this->visibleUserIds();
        if ($scope !== null && !in_array($userId, $scope, true)) {
            $this->viewWithLayout('errors/403', 'main', [
                'title' => 'Access Denied',
                'message' => 'This employee is outside your team scope.'
            ]);
            return;
        }

        $date = (isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'])) ? $_GET['date'] : date('Y-m-d');
        $data = array_merge($this->monitoringModel->employeeDay($userId, $date), [
            'title' => 'Employee Day Drill-down | Raptor CRM',
            'active_tab' => 'monitoring_dashboard',
            'date' => $date,
            'user_id' => $userId,
        ]);

        $this->viewWithLayout('dashboard/day', 'main', $data);
    }
}
