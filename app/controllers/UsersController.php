<?php
// Raptor CRM Employee Management Controller (Admin and HR)

class UsersController extends Controller {
    private $userModel;

    public function __construct() {
        $this->requireAuth();
        
        // Employee account management is reserved for Admin and HR.
        if (!Policy::canManageEmployees()) {
            $this->redirect('index.php?route=dashboard/index');
        }

        $this->userModel = $this->model('User');
    }

    // List all team users
    public function index() {
        $users = $this->userModel->getUsers();
        $roles = $this->userModel->getRoles();
        if (Policy::isHr()) {
            $roles = array_values(array_filter($roles, function ($role) {
                return !in_array($role->role_name, ['admin'], true);
            }));
        }

        $data = [
            'title' => 'Employee Management | Raptor CRM',
            'active_tab' => 'system',
            'users' => $users,
            'roles' => $roles
        ];

        $this->viewWithLayout('users/index', 'main', $data);
    }

    // Add user action
    public function add() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS);

            $data = [
                'role_id' => (int)trim($_POST['role_id']),
                'name' => trim($_POST['name']),
                'email' => trim($_POST['email']),
                'password' => trim($_POST['password']),
                'status' => trim($_POST['status'])
            ];

            if (!empty($data['name']) && !empty($data['email']) && !empty($data['password']) && $data['role_id'] > 0) {
                if (Policy::isHr() && $this->userModel->getRoleNameById($data['role_id']) === 'admin') {
                    $this->redirect('index.php?route=users/index');
                }
                if ($this->userModel->addUser($data)) {
                    $this->redirect('index.php?route=users/index');
                } else {
                    die('Something went wrong.');
                }
            }
        }
        $this->redirect('index.php?route=users/index');
    }

    // Edit user action
    public function edit() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS);

            $data = [
                'user_id' => (int)trim($_POST['user_id']),
                'role_id' => (int)trim($_POST['role_id']),
                'name' => trim($_POST['name']),
                'email' => trim($_POST['email']),
                'password' => trim($_POST['password']), // optional password override
                'status' => trim($_POST['status'])
            ];

            if ($data['user_id'] > 0 && !empty($data['name']) && !empty($data['email']) && $data['role_id'] > 0) {
                if (Policy::isHr() && $this->userModel->getRoleNameById($data['role_id']) === 'admin') {
                    $this->redirect('index.php?route=users/index');
                }
                if ($this->userModel->updateUser($data)) {
                    $this->redirect('index.php?route=users/index');
                } else {
                    die('Something went wrong.');
                }
            }
        }
        $this->redirect('index.php?route=users/index');
    }

    // Deactivate user action; physical deletion is disabled by policy.
    public function deactivate($id) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->userModel->deactivateUser((int) $id);
            $this->audit('Deactivated employee account', 'users', (int) $id);
        }
        $this->redirect('index.php?route=users/index');
    }
}
