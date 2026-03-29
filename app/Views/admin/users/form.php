<?php
$formUser = $user ?? [];
$selectedGender = old('gender', (string) ($formUser['gender'] ?? 'unknown'));
$selectedBirthDate = old('birth_date', (string) ($formUser['birth_date'] ?? ''));
$isEditMode = isset($user);
?>
<div class="row g-3">
    <div class="col-md-6"><label class="form-label">Họ tên</label><input class="form-control" name="full_name" required value="<?= e(old('full_name', (string) ($formUser['full_name'] ?? ''))) ?>"></div>
    <div class="col-md-6"><label class="form-label">Username</label><input class="form-control" name="username" required value="<?= e(old('username', (string) ($formUser['username'] ?? ''))) ?>"></div>
    <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" name="email" required value="<?= e(old('email', (string) ($formUser['email'] ?? ''))) ?>"></div>
    <div class="col-md-6">
        <label class="form-label"><?= $isEditMode ? 'Mật khẩu mới' : 'Mật khẩu' ?><?= $isEditMode ? ' (để trống nếu không đổi)' : ' *' ?></label>
        <input type="password" class="form-control" name="password" <?= $isEditMode ? '' : 'required' ?> minlength="8" autocomplete="new-password">
        <div class="form-text">Mật khẩu cũ không thể xem lại vì hệ thống chỉ lưu bản mã hóa. Hãy nhập mật khẩu mới khi cần đổi hoặc reset.</div>
    </div>
    <div class="col-md-6">
        <label class="form-label"><?= $isEditMode ? 'Xác nhận mật khẩu mới' : 'Xác nhận mật khẩu *' ?></label>
        <input type="password" class="form-control" name="password_confirmation" <?= $isEditMode ? '' : 'required' ?> minlength="8" autocomplete="new-password">
    </div>
    <div class="col-md-6"><label class="form-label">Số điện thoại</label><input class="form-control" name="phone" value="<?= e(old('phone', (string) ($formUser['phone'] ?? ''))) ?>"></div>
    <div class="col-md-6"><label class="form-label">Địa chỉ</label><input class="form-control" name="address" value="<?= e(old('address', (string) ($formUser['address'] ?? ''))) ?>"></div>
    <div class="col-md-6">
        <label class="form-label">Giới tính</label>
        <select class="form-select" name="gender">
            <?php foreach (user_gender_options() as $genderKey => $genderLabel): ?>
                <option value="<?= e($genderKey) ?>" <?= normalize_user_gender($selectedGender) === $genderKey ? 'selected' : '' ?>><?= e($genderLabel) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-6"><label class="form-label">Ngày sinh</label><input type="date" class="form-control" name="birth_date" value="<?= e($selectedBirthDate) ?>" max="<?= e(date('Y-m-d')) ?>"></div>
    <div class="col-md-6"><label class="form-label">Vai trò</label><select class="form-select" name="role_id"><?php foreach ($roles as $roleItem): ?><option value="<?= (int)$roleItem['id'] ?>" <?= (string) old('role_id', (string) ($formUser['role_id'] ?? 2)) === (string) $roleItem['id'] ? 'selected' : '' ?>><?= e($roleItem['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-6"><label class="form-label">Trạng thái</label><select class="form-select" name="status"><option value="active" <?= old('status', (string) ($formUser['status'] ?? 'active')) === 'active' ? 'selected' : '' ?>>Active</option><option value="blocked" <?= old('status', (string) ($formUser['status'] ?? '')) === 'blocked' ? 'selected' : '' ?>>Blocked</option></select></div>
</div>
