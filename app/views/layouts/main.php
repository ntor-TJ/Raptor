<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($title) ? htmlspecialchars($title) : 'Raptor CRM'; ?></title>
    <!-- Inter Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Charting & UI Library CSS -->
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">

    <!-- Script Libraries loaded in Head to support inline view scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script>
        (function () {
            var savedTheme = localStorage.getItem('raptor_theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme === 'dark' ? 'dark' : 'light');
        })();
    </script>

    <style>
        :root {
            color-scheme: light;
            --bg-dark: #f5f9ff;
            --panel-dark: #ffffff;
            --surface-muted: #eef6ff;
            --surface-soft: #f8fbff;
            --border-color: rgba(21, 94, 239, 0.12);
            --border-strong: rgba(21, 94, 239, 0.22);

            --text-primary: #0f172a;
            --text-secondary: #52647a;
            --text-muted: #7890a8;

            --primary: #0d6efd;
            --primary-strong: #0757d3;
            --primary-soft: #e7f1ff;
            --primary-glow: rgba(13, 110, 253, 0.14);
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #06b6d4;
            --shadow-soft: 0 12px 32px rgba(15, 82, 186, 0.08);
            --shadow-hover: 0 18px 40px rgba(15, 82, 186, 0.13);

            --sidebar-width: 260px;
            --topbar-height: 70px;
        }

        html[data-theme="dark"] {
            color-scheme: dark;
            --bg-dark: #090f1d;
            --panel-dark: #111827;
            --surface-muted: #172033;
            --surface-soft: #0f172a;
            --border-color: rgba(255, 255, 255, 0.08);
            --border-strong: rgba(255, 255, 255, 0.16);

            --text-primary: #f8fafc;
            --text-secondary: #a8b3c7;
            --text-muted: #718096;

            --primary: #38bdf8;
            --primary-strong: #0ea5e9;
            --primary-soft: rgba(56, 189, 248, 0.13);
            --primary-glow: rgba(56, 189, 248, 0.18);
            --shadow-soft: 0 12px 32px rgba(0, 0, 0, 0.22);
            --shadow-hover: 0 18px 42px rgba(0, 0, 0, 0.32);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-dark);
            background-image:
                radial-gradient(circle at 12% 8%, var(--primary-glow) 0, transparent 34%),
                linear-gradient(180deg, rgba(255,255,255,0.78), rgba(255,255,255,0));
            color: var(--text-primary);
            overflow-x: hidden;
            margin: 0;
        }

        html[data-theme="dark"] body {
            background-image:
                radial-gradient(circle at 12% 8%, var(--primary-glow) 0, transparent 36%),
                linear-gradient(180deg, rgba(14, 23, 42, 0.55), rgba(14, 23, 42, 0));
        }

        /* Layout Grid */
        #wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styling */
        #sidebar {
            width: var(--sidebar-width);
            background-color: var(--panel-dark);
            border-right: 1px solid var(--border-color);
            transition: all 0.3s ease;
            z-index: 100;
            display: flex;
            flex-direction: column;
        }

        #sidebar.collapsed {
            margin-left: calc(-1 * var(--sidebar-width));
        }

        .sidebar-brand {
            height: var(--topbar-height);
            display: flex;
            align-items: center;
            padding: 0 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .sidebar-brand-icon {
            font-size: 1.5rem;
            background: linear-gradient(135deg, var(--primary) 0%, #00b4d8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-right: 0.75rem;
        }

        .sidebar-brand-text {
            font-weight: 700;
            font-size: 1.15rem;
            letter-spacing: -0.5px;
        }

        .sidebar-menu {
            list-style: none;
            padding: 1.5rem 0.75rem;
            margin: 0;
            flex-grow: 1;
        }

        .menu-header {
            color: var(--text-muted);
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 0.5rem 0.75rem;
            margin-top: 1rem;
            margin-bottom: 0.5rem;
        }

        .menu-item a {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
            margin-bottom: 0.25rem;
        }

        .menu-item a i {
            margin-right: 0.85rem;
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }

        .menu-item a:hover {
            color: var(--text-primary);
            background-color: var(--primary-soft);
        }

        .menu-item.active a {
            color: var(--primary-strong);
            background: linear-gradient(90deg, var(--primary-soft) 0%, rgba(255,255,255,0) 100%);
            border-left: 3px solid var(--primary);
            padding-left: calc(1rem - 3px);
        }

        .sidebar-footer {
            padding: 1rem;
            border-top: 1px solid var(--border-color);
            background-color: var(--surface-soft);
        }

        .user-widget {
            display: flex;
            align-items: center;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-right: 0.75rem;
        }

        .user-name {
            font-weight: 600;
            font-size: 0.85rem;
            margin-bottom: 0.15rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-role {
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-transform: capitalize;
        }

        /* Topbar Styling */
        #content-wrapper {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        #topbar {
            height: var(--topbar-height);
            background-color: var(--panel-dark);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.5rem;
        }

        .topbar-left {
            display: flex;
            align-items: center;
        }

        .btn-toggle-sidebar {
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 1.25rem;
            margin-right: 1.5rem;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .btn-toggle-sidebar:hover {
            color: var(--text-primary);
        }

        .page-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .filter-select {
            background-color: var(--surface-soft);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            border-radius: 8px;
            font-size: 0.85rem;
            padding: 0.45rem 1rem;
        }

        .filter-select:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 4px var(--primary-glow);
        }

        /* Content Container */
        .main-content {
            padding: 1.5rem;
            flex-grow: 1;
        }

        /* Axiora Pulse Card Styling */
        .pulse-card {
            background-color: var(--panel-dark);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--shadow-soft);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .pulse-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        .card-glow::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary) 0%, #00b4d8 100%);
        }

        .card-title {
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }

        /* Alerts and Toasts */
        .toast-banner {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(22, 25, 41, 0.95);
            backdrop-filter: blur(10px);
            border-top: 1px solid rgba(239, 68, 68, 0.2);
            padding: 1rem;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* Theme-aware Bootstrap utility remap.
           Existing views used dark utility classes heavily; these semantic
           overrides make the whole frontend follow the white/blue theme while
           still supporting dark mode from one toggle. */
        .text-white { color: var(--text-primary) !important; }
        .text-secondary, .text-secondary-emphasis, .text-muted { color: var(--text-secondary) !important; }
        .bg-dark { background-color: var(--surface-muted) !important; }
        .bg-opacity-25, .bg-opacity-35, .bg-opacity-50 { --bs-bg-opacity: 1; }
        .border-secondary, .border-secondary-subtle { border-color: var(--border-color) !important; }
        .bg-secondary-subtle { background-color: var(--surface-muted) !important; }
        .text-primary { color: var(--primary) !important; }
        .btn-primary {
            --bs-btn-bg: var(--primary);
            --bs-btn-border-color: var(--primary);
            --bs-btn-hover-bg: var(--primary-strong);
            --bs-btn-hover-border-color: var(--primary-strong);
        }
        .btn-outline-light, .btn-outline-secondary {
            --bs-btn-color: var(--primary);
            --bs-btn-border-color: var(--border-strong);
            --bs-btn-hover-color: #fff;
            --bs-btn-hover-bg: var(--primary);
            --bs-btn-hover-border-color: var(--primary);
        }
        .form-control, .form-select {
            background-color: var(--surface-soft) !important;
            border-color: var(--border-color) !important;
            color: var(--text-primary) !important;
            border-radius: 8px;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary) !important;
            box-shadow: 0 0 0 4px var(--primary-glow) !important;
        }
        .form-control::placeholder { color: var(--text-muted); }
        .table, .table-dark {
            --bs-table-bg: var(--panel-dark);
            --bs-table-color: var(--text-primary);
            --bs-table-border-color: var(--border-color);
            --bs-table-striped-bg: var(--surface-soft);
            --bs-table-hover-bg: var(--primary-soft);
            color: var(--text-primary);
        }
        .list-group-item {
            color: var(--text-primary) !important;
            border-color: var(--border-color) !important;
        }
        .dropdown-menu, .modal-content {
            background-color: var(--panel-dark);
            color: var(--text-primary);
            border-color: var(--border-color);
        }
        .theme-toggle {
            border: 1px solid var(--border-color);
            background: var(--surface-soft);
            color: var(--text-primary);
            border-radius: 999px;
            height: 38px;
            min-width: 42px;
            padding: 0 0.8rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.45rem;
        }
        .theme-toggle:hover { color: var(--primary); border-color: var(--primary); }

        /* Mobile drawer overlay (hidden on desktop) */
        #sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
            z-index: 99;
        }

        /* Bottom navigation (mobile only) */
        #bottom-nav {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 60px;
            background-color: var(--panel-dark);
            border-top: 1px solid var(--border-color);
            z-index: 90;
            justify-content: space-around;
            align-items: center;
        }
        #bottom-nav a {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 2px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.65rem;
            flex: 1;
            padding: 4px 0;
        }
        #bottom-nav a i { font-size: 1.15rem; }
        #bottom-nav a.active { color: var(--primary); }

        /* Responsive helper: any wide table/element scrolls inside its box
           instead of breaking the page width. Wrap tables in .table-scroll. */
        .table-scroll { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }

        /* ===================== RESPONSIVE BREAKPOINTS ===================== */

        /* Tablet and below: tighten paddings */
        @media (max-width: 991.98px) {
            /* Sidebar becomes an off-canvas drawer */
            #sidebar {
                position: fixed;
                top: 0;
                left: 0;
                bottom: 0;
                margin-left: calc(-1 * var(--sidebar-width));
                box-shadow: 4px 0 30px rgba(0,0,0,0.4);
            }
            #sidebar.mobile-open { margin-left: 0; }
            #sidebar.mobile-open + #sidebar-overlay,
            body.drawer-open #sidebar-overlay { display: block; }

            #content-wrapper { width: 100%; }
            .main-content { padding: 1rem; padding-bottom: 80px; /* clear bottom nav */ }

            #bottom-nav { display: flex; }

            /* Hide desktop-only topbar filters to save space */
            .topbar-right { gap: 0.5rem; }
            #client-selector, #date-range { max-width: 130px; }
        }

        /* Phones */
        @media (max-width: 575.98px) {
            :root { --topbar-height: 60px; }
            .page-title { font-size: 1.05rem; }
            .topbar-right { display: none; } /* filters move into page content on phones */
            .pulse-card { padding: 1.1rem; border-radius: 12px; }
            /* Stack Bootstrap tables into cards when marked .table-stack */
            table.table-stack thead { display: none; }
            table.table-stack, table.table-stack tbody, table.table-stack tr, table.table-stack td {
                display: block; width: 100%;
            }
            table.table-stack tr {
                margin-bottom: 0.75rem;
                border: 1px solid var(--border-color);
                border-radius: 10px;
                padding: 0.5rem 0.75rem;
                background: var(--panel-dark);
            }
            table.table-stack td {
                display: flex; justify-content: space-between; gap: 1rem;
                border: none; padding: 0.35rem 0; text-align: right;
            }
            table.table-stack td::before {
                content: attr(data-label);
                font-weight: 600;
                color: var(--text-secondary);
                text-align: left;
            }
        }

        /* Desktop: never show mobile chrome */
        @media (min-width: 992px) {
            #bottom-nav, #sidebar-overlay { display: none !important; }
        }
    </style>
</head>
<body>
    <?php
        $role = $_SESSION['user_role'] ?? '';
        $isEmployee = Policy::isEmployee();
        $salesRoles = ['admin', 'manager', 'team_leader', 'employee', 'sales_person'];
    ?>
    <div id="wrapper">
        <!-- Sidebar Navigation -->
        <nav id="sidebar">
            <div class="sidebar-brand">
                <i class="fa-solid fa-chart-line sidebar-brand-icon"></i>
                <span class="sidebar-brand-text">Raptor CRM</span>
            </div>
            
            <ul class="sidebar-menu">
                <li class="menu-header">Dashboards</li>
                <li class="menu-item <?php echo (isset($active_tab) && $active_tab === 'dashboard_module') ? 'active' : ''; ?>">
                    <a href="index.php?route=dashboard/index">
                        <i class="fa-solid fa-table-columns"></i>Dashboard Module
                    </a>
                </li>
                <?php if (Policy::canCreateDashboardTemplate()): ?>
                <li class="menu-item <?php echo ($data['title'] === 'Dashboard Templates | Raptor CRM') ? 'active' : ''; ?>">
                    <a href="index.php?route=dashboard/templates">
                        <i class="fa-solid fa-sliders"></i>Dashboard Templates
                    </a>
                </li>
                <?php endif; ?>
                <li class="menu-item <?php echo ($data['title'] === 'Reports Center | Raptor CRM') ? 'active' : ''; ?>">
                    <a href="index.php?route=reports/index">
                        <i class="fa-solid fa-chart-pie"></i>Reports Module
                    </a>
                </li>
                
                <!-- Executive Marketing Overview (Employer, Manager, Admin) -->
                <?php if (in_array($_SESSION['user_role'], ['admin', 'manager', 'employer'])): ?>
                <li class="menu-item <?php echo ($active_tab === 'executive') ? 'active' : ''; ?>">
                    <a href="index.php?route=dashboard/executive">
                        <i class="fa-solid fa-house-chimney"></i>Executive Overview
                    </a>
                </li>
                <?php endif; ?>

                <!-- Channel & Campaign Performance (Admin, Manager, Analyst) -->
                <?php if (in_array($_SESSION['user_role'], ['admin', 'manager', 'analyst'])): ?>
                <li class="menu-item <?php echo ($active_tab === 'channels') ? 'active' : ''; ?>">
                    <a href="index.php?route=dashboard/channels">
                        <i class="fa-solid fa-chart-simple"></i>Campaign Performance
                    </a>
                </li>
                <?php endif; ?>

                <!-- Customer Intelligence (Admin, Manager, Analyst) -->
                <?php if (in_array($_SESSION['user_role'], ['admin', 'manager', 'analyst'])): ?>
                <li class="menu-item <?php echo ($active_tab === 'customer') ? 'active' : ''; ?>">
                    <a href="index.php?route=dashboard/customer">
                        <i class="fa-solid fa-users-viewfinder"></i>Customer Intelligence
                    </a>
                </li>
                <?php endif; ?>

                <li class="menu-item <?php echo (isset($active_tab) && $active_tab === 'notifications') ? 'active' : ''; ?>">
                    <a href="index.php?route=notifications/index">
                        <i class="fa-solid fa-bell"></i>Notifications
                    </a>
                </li>

                <!-- My Day (self-service): sales-side + oversight roles -->
                <?php if (in_array($role, $salesRoles, true)): ?>
                <li class="menu-header">My Day</li>
                <li class="menu-item <?php echo (isset($active_tab) && $active_tab === 'attendance') ? 'active' : ''; ?>">
                    <a href="index.php?route=attendance/index">
                        <i class="fa-solid fa-fingerprint"></i>My Attendance
                    </a>
                </li>
                <li class="menu-item <?php echo (isset($active_tab) && $active_tab === 'followups') ? 'active' : ''; ?>">
                    <a href="index.php?route=followups/index">
                        <i class="fa-solid fa-bell"></i>My Follow-ups
                    </a>
                </li>
                <li class="menu-item <?php echo (isset($active_tab) && $active_tab === 'my_payslips') ? 'active' : ''; ?>">
                    <a href="index.php?route=payroll/myPayslips">
                        <i class="fa-solid fa-money-bill-wave"></i>My Payslips
                    </a>
                </li>
                <?php if ($isEmployee || $role === 'team_leader'): ?>
                <li class="menu-item <?php echo (isset($active_tab) && $active_tab === 'myroute') ? 'active' : ''; ?>">
                    <a href="index.php?route=location/myday">
                        <i class="fa-solid fa-route"></i>My Route
                    </a>
                </li>
                <?php endif; ?>
                <?php endif; ?>

                <!-- Monitoring (oversight roles) -->
                <?php if (in_array($_SESSION['user_role'], ['admin', 'manager', 'team_leader'])): ?>
                <li class="menu-header">Monitoring</li>
                <li class="menu-item <?php echo (isset($active_tab) && $active_tab === 'monitoring_dashboard') ? 'active' : ''; ?>">
                    <a href="index.php?route=dashboard/monitoring">
                        <i class="fa-solid fa-tower-observation"></i>Command Center
                    </a>
                </li>
                <li class="menu-item <?php echo (isset($active_tab) && $active_tab === 'attendance_approvals') ? 'active' : ''; ?>">
                    <a href="index.php?route=attendance/approvals">
                        <i class="fa-solid fa-user-check"></i>Attendance Approvals
                    </a>
                </li>
                <li class="menu-item <?php echo (isset($active_tab) && $active_tab === 'attendance_report') ? 'active' : ''; ?>">
                    <a href="index.php?route=attendance/report">
                        <i class="fa-solid fa-calendar-check"></i>Attendance Report
                    </a>
                </li>
                <?php endif; ?>

                <!-- Business Operations -->
                <?php if ($_SESSION['user_role'] !== 'employer'): ?>
                <li class="menu-header">Operations</li>

                <?php /* Marketing-side operations: admin, manager, analyst */ ?>
                <?php if (in_array($_SESSION['user_role'], ['admin', 'manager', 'analyst'])): ?>
                <li class="menu-item">
                    <a href="index.php?route=clients/index">
                        <i class="fa-solid fa-briefcase"></i>Clients Directory
                    </a>
                </li>
                <li class="menu-item">
                    <a href="index.php?route=campaigns/index">
                        <i class="fa-solid fa-bullhorn"></i>Campaign Registry
                    </a>
                </li>
                <li class="menu-item">
                    <a href="#">
                        <i class="fa-solid fa-calendar-days"></i>Content Calendar
                    </a>
                </li>
                <?php endif; ?>

                <?php /* Sales-side operations: admin, manager, team_leader, employee */ ?>
                <?php if (in_array($role, $salesRoles, true)): ?>
                <li class="menu-item">
                    <a href="index.php?route=leads/index">
                        <i class="fa-solid fa-address-book"></i>Leads Manager
                    </a>
                </li>
                <li class="menu-item <?php echo (isset($active_tab) && $active_tab === 'communications') ? 'active' : ''; ?>">
                    <a href="index.php?route=communications/index">
                        <i class="fa-solid fa-comments"></i>Communications
                    </a>
                </li>
                <li class="menu-item <?php echo (isset($active_tab) && $active_tab === 'meetings') ? 'active' : ''; ?>">
                    <a href="index.php?route=meetings/index">
                        <i class="fa-solid fa-handshake"></i>Meetings & Demos
                    </a>
                </li>
                <?php endif; ?>

                <li class="menu-item <?php echo ($data['title'] === 'Task Board | Raptor CRM') ? 'active' : ''; ?>">
                    <a href="index.php?route=tasks/index">
                        <i class="fa-solid fa-list-check"></i>Task Board
                    </a>
                </li>
                <?php if (in_array($role, $salesRoles, true)): ?>
                <li class="menu-item <?php echo (isset($active_tab) && $active_tab === 'targets') ? 'active' : ''; ?>">
                    <a href="index.php?route=targets/index">
                        <i class="fa-solid fa-bullseye"></i>Targets
                    </a>
                </li>
                <li class="menu-item <?php echo (isset($active_tab) && $active_tab === 'performance') ? 'active' : ''; ?>">
                    <a href="index.php?route=performance/index">
                        <i class="fa-solid fa-ranking-star"></i>Performance
                    </a>
                </li>
                <?php endif; ?>
                <?php endif; ?>

                <!-- Finance & Audit (Admin, Manager) -->
                <?php if (in_array($_SESSION['user_role'], ['admin', 'manager'])): ?>
                <li class="menu-header">Finance</li>
                <li class="menu-item <?php echo (strpos($data['title'], 'Invoice') !== false || $data['title'] === 'Billing Ledger | Raptor CRM') ? 'active' : ''; ?>">
                    <a href="index.php?route=invoices/index">
                        <i class="fa-solid fa-file-invoice-dollar"></i>Invoicing
                    </a>
                </li>
                <?php endif; ?>

                <!-- Payroll & Compensation (Admin, HR) -->
                <?php if (Policy::canManageEmployees() || in_array($_SESSION['user_role'], ['admin', 'hr'])): ?>
                <li class="menu-header">Payroll</li>
                <li class="menu-item <?php echo (isset($active_tab) && $active_tab === 'payroll') ? 'active' : ''; ?>">
                    <a href="index.php?route=payroll/index">
                        <i class="fa-solid fa-calculator"></i>Payroll Runs
                    </a>
                </li>
                <li class="menu-item <?php echo (isset($active_tab) && $active_tab === 'salary_structures') ? 'active' : ''; ?>">
                    <a href="index.php?route=payroll/structures">
                        <i class="fa-solid fa-id-card-clip"></i>Salary Structures
                    </a>
                </li>
                <?php endif; ?>

                <!-- System-level configuration (Admin, HR, Manager governance) -->
                <?php if (in_array($role, ['admin', 'manager', 'team_leader', 'hr'], true)): ?>
                <li class="menu-header">System</li>
                <?php if (Policy::canManageEmployees()): ?>
                <li class="menu-item <?php echo ($data['title'] === 'Employee Management | Raptor CRM') ? 'active' : ''; ?>">
                    <a href="index.php?route=users/index">
                        <i class="fa-solid fa-users"></i>Employee Management
                    </a>
                </li>
                <?php endif; ?>
                <?php if (Policy::canApproveDataEdit() || Policy::canRequestDataEdit()): ?>
                <li class="menu-item <?php echo (isset($active_tab) && $active_tab === 'edit_requests') ? 'active' : ''; ?>">
                    <a href="index.php?route=editrequests/index">
                        <i class="fa-solid fa-file-pen"></i>Data Edit Requests
                    </a>
                </li>
                <?php endif; ?>
                <?php if (in_array($role, ['admin', 'manager'], true)): ?>
                <li class="menu-item <?php echo ($data['title'] === 'Organization | Raptor CRM') ? 'active' : ''; ?>">
                    <a href="index.php?route=teams/index">
                        <i class="fa-solid fa-sitemap"></i>Organization
                    </a>
                </li>
                <?php endif; ?>
                <?php endif; ?>

                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                <li class="menu-item <?php echo ($data['title'] === 'Global Settings | Raptor CRM') ? 'active' : ''; ?>">
                    <a href="index.php?route=settings/index">
                        <i class="fa-solid fa-sliders"></i>Global Settings
                    </a>
                </li>
                <?php endif; ?>
            </ul>

            <div class="sidebar-footer">
                <div class="user-widget">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['user_name'], 0, 2)); ?>
                    </div>
                    <div class="flex-grow-1 overflow-hidden" style="margin-right: 0.5rem;">
                        <div class="user-name"><?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
                        <div class="user-role"><?php echo htmlspecialchars(Policy::roleLabel()); ?></div>
                    </div>
                    <a href="index.php?route=auth/logout" class="text-danger" title="Logout" style="font-size: 1.1rem;">
                        <i class="fa-solid fa-right-from-bracket"></i>
                    </a>
                </div>
            </div>
        </nav>

        <!-- Mobile drawer overlay (tap to close) -->
        <div id="sidebar-overlay"></div>

        <!-- Main Content Area -->
        <div id="content-wrapper">
            <!-- Top Header Bar -->
            <header id="topbar">
                <div class="topbar-left">
                    <button class="btn-toggle-sidebar" id="toggle-btn">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    <h2 class="page-title"><?php echo isset($title) ? htmlspecialchars($title) : 'Dashboard'; ?></h2>
                </div>

                <button type="button" class="theme-toggle" id="theme-toggle" title="Toggle dark theme">
                    <i class="fa-solid fa-moon"></i><span class="d-none d-xl-inline">Theme</span>
                </button>

                <div class="topbar-right">
                    <!-- Global Filters -->
                    <select class="filter-select" id="client-selector">
                        <option value="all">All Clients</option>
                        <option value="1">Axiora Tech</option>
                    </select>

                    <input type="text" class="filter-select" id="date-range" placeholder="Select Date Range" value="Last 30 Days">
                </div>
            </header>

            <!-- Inner View Content -->
            <main class="main-content">
                <?php echo $content; ?>
            </main>
        </div>
    </div>

    <?php
        // Role-aware default dashboard target for the bottom nav "Home".
        $homeRoute = Policy::isEmployee()
            ? 'attendance/index'
            : 'dashboard/index';
        $isOps = $role !== 'employer';
        $canLeads = in_array($role, $salesRoles, true);
        $salesSide = in_array($role, $salesRoles, true);
    ?>
    <!-- Bottom navigation (mobile only) -->
    <nav id="bottom-nav">
        <a href="index.php?route=<?php echo $homeRoute; ?>">
            <i class="fa-solid fa-house-chimney"></i><span>Home</span>
        </a>
        <?php if ($salesSide): ?>
        <a href="index.php?route=attendance/index">
            <i class="fa-solid fa-fingerprint"></i><span>Attend</span>
        </a>
        <?php endif; ?>
        <?php if ($canLeads): ?>
        <a href="index.php?route=leads/index">
            <i class="fa-solid fa-address-book"></i><span>Leads</span>
        </a>
        <?php endif; ?>
        <?php if ($isOps): ?>
        <a href="index.php?route=tasks/index">
            <i class="fa-solid fa-list-check"></i><span>Tasks</span>
        </a>
        <?php endif; ?>
        <?php if ($salesSide): ?>
        <a href="index.php?route=targets/index">
            <i class="fa-solid fa-bullseye"></i><span>Targets</span>
        </a>
        <?php endif; ?>
        <?php if (!Policy::isEmployee()): ?>
        <a href="index.php?route=reports/index">
            <i class="fa-solid fa-chart-pie"></i><span>Reports</span>
        </a>
        <?php endif; ?>
        <a href="#" id="bottom-nav-more">
            <i class="fa-solid fa-bars"></i><span>More</span>
        </a>
    </nav>

    <script>
        $(document).ready(function() {
            var isMobile = function () { return window.matchMedia('(max-width: 991.98px)').matches; };

            function openDrawer() { $('#sidebar').addClass('mobile-open'); $('body').addClass('drawer-open'); }
            function closeDrawer() { $('#sidebar').removeClass('mobile-open'); $('body').removeClass('drawer-open'); }

            // Topbar hamburger: on mobile open the drawer, on desktop collapse.
            $('#toggle-btn').on('click', function() {
                if (isMobile()) {
                    $('#sidebar').hasClass('mobile-open') ? closeDrawer() : openDrawer();
                } else {
                    $('#sidebar').toggleClass('collapsed');
                }
            });

            // Bottom-nav "More" opens the full menu drawer.
            $('#bottom-nav-more').on('click', function(e) { e.preventDefault(); openDrawer(); });

            // Tap overlay or a menu link closes the drawer.
            $('#sidebar-overlay').on('click', closeDrawer);
            $('#sidebar .menu-item a').on('click', function() { if (isMobile()) closeDrawer(); });

            // Reset drawer state when crossing the breakpoint.
            $(window).on('resize', function() { if (!isMobile()) closeDrawer(); });

            function syncThemeButton() {
                var theme = document.documentElement.getAttribute('data-theme') || 'light';
                $('#theme-toggle i')
                    .toggleClass('fa-moon', theme !== 'dark')
                    .toggleClass('fa-sun', theme === 'dark');
            }

            syncThemeButton();
            $('#theme-toggle').on('click', function() {
                var current = document.documentElement.getAttribute('data-theme') || 'light';
                var next = current === 'dark' ? 'light' : 'dark';
                document.documentElement.setAttribute('data-theme', next);
                localStorage.setItem('raptor_theme', next);
                syncThemeButton();
            });

            // Initialize Date Picker
            flatpickr("#date-range", {
                mode: "range",
                dateFormat: "Y-m-d",
                defaultDate: [
                    new Date(new Date().setDate(new Date().getDate() - 30)),
                    new Date()
                ]
            });
        });
    </script>

    <?php if (Policy::isEmployee() || $role === 'team_leader'): ?>
    <!-- Foreground location tracker (field roles). Stores only while on duty;
         server is authoritative and tells us to stop when the shift ends. -->
    <script>
    (function () {
        if (!navigator.geolocation) return;
        var CSRF = <?php echo json_encode($_SESSION['csrf_token']); ?>;
        var FLUSH_MS = 120000;      // send batched points every 2 minutes
        var buffer = [], watchId = null, timer = null, stopped = false;

        function stop() {
            stopped = true;
            if (watchId !== null) { navigator.geolocation.clearWatch(watchId); watchId = null; }
            if (timer) { clearInterval(timer); timer = null; }
        }

        function flush() {
            if (stopped || buffer.length === 0) return;
            var batch = buffer.splice(0, buffer.length);
            fetch('index.php?route=location/ping', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                body: JSON.stringify({ points: batch })
            })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                // Server says off duty (not checked in / checked out) → stop tracking.
                if (d && d.data && d.data.on_duty === false) { stop(); }
            })
            .catch(function () { /* keep buffering; retry next flush */ });
        }

        watchId = navigator.geolocation.watchPosition(function (pos) {
            buffer.push({
                lat: +pos.coords.latitude.toFixed(7),
                lng: +pos.coords.longitude.toFixed(7),
                accuracy: Math.round(pos.coords.accuracy),
                ts: pos.timestamp,
                source: 'periodic'
            });
            if (buffer.length > 60) buffer.splice(0, buffer.length - 60);
        }, function () { /* permission denied / unavailable — do nothing */ },
        { enableHighAccuracy: true, maximumAge: 30000, timeout: 20000 });

        setTimeout(flush, 20000);   // early first flush so the map shows a fix soon
        timer = setInterval(flush, FLUSH_MS);
        window.addEventListener('pagehide', flush);
    })();
    </script>
    <?php endif; ?>
</body>
</html>
