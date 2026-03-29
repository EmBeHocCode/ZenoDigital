<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\User;
use App\Services\ModuleHealthGuardService;

class UserController extends Controller
{
    public function index(): void
    {
        $this->requireAdmin();
        $this->ensureUsersModuleHealthy('read');

        $model = new User($this->config);
        $search = sanitize_text((string) ($_GET['q'] ?? ''), 120);
        $role = sanitize_text((string) ($_GET['role'] ?? ''), 50);
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $result = $model->paginated($search, $role, $page, 10);

        $this->view('admin/users/index', [
            'users' => $result['data'],
            'meta' => $result['meta'],
            'search' => $search,
            'role' => $role,
            'roles' => $model->allRoles(),
        ], 'admin');
    }

    public function create(): void
    {
        $this->requireAdmin();
        $this->ensureUsersModuleHealthy('write');
        $model = new User($this->config);
        $this->view('admin/users/create', ['roles' => $model->allRoles()], 'admin');
    }

    public function store(): void
    {
        $this->requireAdmin();
        $this->requirePostWithCsrf('admin/users/create');
        $this->ensureUsersModuleHealthy('write');

        $fullName = sanitize_text((string) ($_POST['full_name'] ?? ''), 120);
        $username = sanitize_text((string) ($_POST['username'] ?? ''), 60);
        $email = sanitize_text((string) ($_POST['email'] ?? ''), 190);
        $password = $_POST['password'] ?? '';
        $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');
        $gender = normalize_user_gender((string) ($_POST['gender'] ?? 'unknown'));
        $birthDateInput = (string) ($_POST['birth_date'] ?? '');
        $birthDate = normalize_birth_date($birthDateInput);

        if ($fullName === '' || $username === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('danger', 'Thông tin người dùng không hợp lệ.');
            redirect('admin/users/create');
        }

        if (!preg_match('/^[a-zA-Z0-9_.-]{3,30}$/', $username)) {
            flash('danger', 'Username không hợp lệ.');
            redirect('admin/users/create');
        }

        $passwordError = validate_password_strength($password);
        if ($passwordError !== null) {
            flash('danger', $passwordError);
            redirect('admin/users/create');
        }

        if ($password !== $passwordConfirmation) {
            flash('danger', 'Mật khẩu xác nhận không khớp.');
            redirect('admin/users/create');
        }

        if (trim($birthDateInput) !== '' && $birthDate === null) {
            flash('danger', 'Ngày sinh không hợp lệ hoặc nằm ngoài khoảng cho phép.');
            redirect('admin/users/create');
        }

        $roleId = validate_int_range($_POST['role_id'] ?? null, 1, 10, 2);
        $status = validate_enum((string) ($_POST['status'] ?? ''), ['active', 'blocked'], 'active');
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');
        $phone = sanitize_text((string) ($_POST['phone'] ?? ''), 20);
        $address = sanitize_text((string) ($_POST['address'] ?? ''), 255);

        set_old([
            'full_name' => $fullName,
            'username' => $username,
            'email' => $email,
            'phone' => $phone,
            'address' => $address,
            'gender' => $gender,
            'birth_date' => $birthDate ?? '',
            'role_id' => (string) $roleId,
            'status' => $status,
        ]);

        if ($phone !== '' && !preg_match('/^[0-9+\-\s]{8,20}$/', $phone)) {
            flash('danger', 'Số điện thoại không hợp lệ.');
            redirect('admin/users/create');
        }

        $model = new User($this->config);
        if ($model->findByEmail($email)) {
            flash('danger', 'Email đã tồn tại.');
            redirect('admin/users/create');
        }

        $ok = $model->create([
            'role_id' => $roleId,
            'full_name' => $fullName,
            'username' => $username,
            'email' => $email,
            'phone' => $phone,
            'address' => $address,
            'gender' => $gender,
            'birth_date' => $birthDate,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'avatar' => null,
            'status' => $status,
        ]);

        if ($ok) {
            clear_old();
            admin_audit('create', 'user', null, [
                'email' => $email,
                'username' => $username,
                'role_id' => $roleId,
                'status' => $status,
            ]);
        }

        flash($ok ? 'success' : 'danger', $ok ? 'Thêm người dùng thành công.' : 'Thêm người dùng thất bại.');
        redirect('admin/users');
    }

    public function edit(int $id): void
    {
        $this->requireAdmin();
        $this->ensureUsersModuleHealthy('write');

        $model = new User($this->config);
        $user = $model->find($id);
        if (!$user) {
            flash('danger', 'Người dùng không tồn tại.');
            redirect('admin/users');
        }

        $this->view('admin/users/edit', [
            'user' => $user,
            'roles' => $model->allRoles(),
        ], 'admin');
    }

    public function update(int $id): void
    {
        $this->requireAdmin();
        $this->requirePostWithCsrf('admin/users/edit/' . $id);
        $this->ensureUsersModuleHealthy('write');

        $model = new User($this->config);

        $fullName = sanitize_text((string) ($_POST['full_name'] ?? ''), 120);
        $username = sanitize_text((string) ($_POST['username'] ?? ''), 60);
        $email = sanitize_text((string) ($_POST['email'] ?? ''), 190);
        $phone = sanitize_text((string) ($_POST['phone'] ?? ''), 20);
        $address = sanitize_text((string) ($_POST['address'] ?? ''), 255);
        $gender = normalize_user_gender((string) ($_POST['gender'] ?? 'unknown'));
        $birthDateInput = (string) ($_POST['birth_date'] ?? '');
        $birthDate = normalize_birth_date($birthDateInput);
        $roleId = validate_int_range($_POST['role_id'] ?? null, 1, 10, 2);
        $status = validate_enum((string) ($_POST['status'] ?? ''), ['active', 'blocked'], 'active');

        set_old([
            'full_name' => $fullName,
            'username' => $username,
            'email' => $email,
            'phone' => $phone,
            'address' => $address,
            'gender' => $gender,
            'birth_date' => $birthDate ?? '',
            'role_id' => (string) $roleId,
            'status' => $status,
        ]);

        if ($fullName === '' || $username === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('danger', 'Thông tin người dùng không hợp lệ.');
            redirect('admin/users/edit/' . $id);
        }

        if ($phone !== '' && !preg_match('/^[0-9+\-\s]{8,20}$/', $phone)) {
            flash('danger', 'Số điện thoại không hợp lệ.');
            redirect('admin/users/edit/' . $id);
        }

        if (trim($birthDateInput) !== '' && $birthDate === null) {
            flash('danger', 'Ngày sinh không hợp lệ hoặc nằm ngoài khoảng cho phép.');
            redirect('admin/users/edit/' . $id);
        }

        if ($password !== '' && $password !== $passwordConfirmation) {
            flash('danger', 'Mật khẩu xác nhận không khớp.');
            redirect('admin/users/edit/' . $id);
        }

        $ok = $model->updateByAdmin($id, [
            'role_id' => $roleId,
            'full_name' => $fullName,
            'username' => $username,
            'email' => $email,
            'phone' => $phone,
            'address' => $address,
            'gender' => $gender,
            'birth_date' => $birthDate,
            'status' => $status,
        ]);

        $passwordUpdated = true;
        if ($password !== '') {
            $passwordError = validate_password_strength($password);
            if ($passwordError !== null) {
                flash('danger', $passwordError);
                redirect('admin/users/edit/' . $id);
            }

            $passwordUpdated = $model->updatePassword($id, password_hash($password, PASSWORD_DEFAULT));
        }

        $ok = $ok && $passwordUpdated;

        if (Auth::id() === $id) {
            $updated = $model->find($id);
            Auth::login($updated);
        }

        if ($ok) {
            clear_old();
            admin_audit('update', 'user', $id, [
                'email' => $email,
                'username' => $username,
                'role_id' => $roleId,
                'status' => $status,
            ]);
        }

        flash($ok ? 'success' : 'danger', $ok ? 'Cập nhật người dùng thành công.' : 'Cập nhật người dùng thất bại.');
        redirect('admin/users');
    }

    public function delete(int $id): void
    {
        $this->requireAdmin();
        $this->requirePostWithCsrf('admin/users');
        $this->ensureUsersModuleHealthy('write');

        if (Auth::id() === $id) {
            flash('danger', 'Không thể xóa tài khoản đang đăng nhập.');
            redirect('admin/users');
        }

        $model = new User($this->config);
        $ok = $model->delete($id);

        if ($ok) {
            admin_audit('delete', 'user', $id);
        }

        flash($ok ? 'success' : 'danger', $ok ? 'Xóa người dùng thành công.' : 'Xóa người dùng thất bại.');
        redirect('admin/users');
    }

    public function toggleStatus(int $id): void
    {
        $this->requireAdmin();
        $this->requirePostWithCsrf('admin/users');
        $this->ensureUsersModuleHealthy('write');

        if (Auth::id() === $id) {
            flash('danger', 'Không thể khóa tài khoản đang đăng nhập.');
            redirect('admin/users');
        }

        $model = new User($this->config);
        $ok = $model->toggleStatus($id);

        if ($ok) {
            admin_audit('toggle_status', 'user', $id);
        }

        flash($ok ? 'success' : 'danger', $ok ? 'Đã cập nhật trạng thái tài khoản.' : 'Cập nhật trạng thái thất bại.');
        redirect('admin/users');
    }

    public function resetPassword(int $id): void
    {
        $this->requireAdmin();
        $this->requirePostWithCsrf('admin/users');
        $this->ensureUsersModuleHealthy('write');

        $model = new User($this->config);
        $user = $model->find($id);
        if (!$user) {
            flash('danger', 'Người dùng không tồn tại.');
            redirect('admin/users');
        }

        $temporaryPassword = $this->generateTemporaryPassword();
        $ok = $model->updatePassword($id, password_hash($temporaryPassword, PASSWORD_DEFAULT));

        if ($ok) {
            admin_audit('reset_password', 'user', $id, [
                'email' => (string) ($user['email'] ?? ''),
                'username' => (string) ($user['username'] ?? ''),
            ]);

            flash('success', 'Đã tạo mật khẩu tạm mới cho ' . (string) ($user['email'] ?? 'user') . ': ' . $temporaryPassword);
            redirect('admin/users');
        }

        flash('danger', 'Không thể đặt lại mật khẩu lúc này.');
        redirect('admin/users');
    }

    private function ensureUsersModuleHealthy(string $intent): void
    {
        $healthGuard = new ModuleHealthGuardService($this->config);
        if ($healthGuard->isHealthy('users')) {
            return;
        }

        $healthGuard->logBlockedAction('users', $intent, [
            'controller' => __CLASS__,
            'intent' => $intent,
        ]);
        flash('danger', $healthGuard->messageFor('users', $intent));
        redirect('admin');
    }

    private function generateTemporaryPassword(int $length = 12): string
    {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789@#$%';
        $maxIndex = strlen($characters) - 1;
        $password = '';

        for ($i = 0; $i < max(12, $length); $i++) {
            $password .= $characters[random_int(0, $maxIndex)];
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $password[0] = 'A';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $password[1] = 'b';
        }
        if (!preg_match('/\d/', $password)) {
            $password[2] = '7';
        }

        return $password;
    }
}
