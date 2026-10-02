<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Auth extends Controller
{
    private function api()
    {
        return $this->call->library('api');
    }

    public function health()
    {
        $this->api()->respond(['status' => 'ok']);
    }

    public function preflight()
    {
        $this->api()->respond(['status' => 'ok']);
    }

    public function register()
    {
        $api = $this->api();
        $api->require_method('POST');
        $api->rate_limit();
        $input = $api->body();
        $username = trim(htmlspecialchars_decode((string) ($input['username'] ?? ''), ENT_QUOTES));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = htmlspecialchars_decode((string) ($input['password'] ?? ''), ENT_QUOTES);
        $role = strtolower(trim((string) ($input['role'] ?? 'user')));

        if (strlen($username) < 2 || strlen($username) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8 || !in_array($role, ['admin', 'moderator', 'user'], true)) {
            $api->respond_error('Enter a username, valid email, and password with at least 8 characters.', 422);
        }

        $db = $this->call->database();
        $exists = $db->raw('SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1', [$email, $username])->fetch();
        if ($exists) {
            $api->respond_error('An account with that email or username already exists.', 409);
        }

        $db->raw('INSERT INTO users (username, email, password, role, is_active, created_at) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)', [
            $username,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $role,
            1,
        ]);

        $api->respond([
            'message' => 'Account created. You can now log in.',
            'user' => [
                'username' => $username,
                'email' => $email,
                'role' => $role,
            ],
        ], 201);
    }

    public function login()
    {
        $api = $this->api();
        $api->require_method('POST');
        $api->rate_limit();
        $input = $api->body();
        $identifier = trim(htmlspecialchars_decode((string) ($input['identifier'] ?? $input['email'] ?? ''), ENT_QUOTES));
        $password = htmlspecialchars_decode((string) ($input['password'] ?? ''), ENT_QUOTES);

        if ($identifier === '' || $password === '') {
            $api->respond_error('Email or username and password are required.', 422);
        }

        $user = $this->call->database()->raw(
            'SELECT id, username, email, password, role, is_active FROM users WHERE email = ? OR username = ? LIMIT 1',
            [$identifier, $identifier]
        )->fetch();

        if (!$user || (int) $user['is_active'] === 0 || !password_verify($password, $user['password'])) {
            $api->respond_error('Invalid credentials.', 401);
        }

        $tokens = $api->issue_tokens([
            'id' => (int) $user['id'],
            'role' => $user['role'],
            'scopes' => ['read', 'write'],
        ]);

        $api->respond([
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'token_type' => 'Bearer',
            'expires_in' => $tokens['expires_in'],
            'user' => [
                'id' => (int) $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role'],
            ],
        ]);
    }

    public function refresh()
    {
        $api = $this->api();
        $api->require_method('POST');
        $input = $api->body();
        $refresh_token = trim((string) ($input['refresh_token'] ?? ''));

        if ($refresh_token === '') {
            $refresh_token = trim((string) ($api->get_bearer_token() ?? ''));
        }

        if ($refresh_token === '') {
            $api->respond_error('Refresh token is required.', 422);
        }

        $api->refresh_access_token($refresh_token);
    }

    public function me()
    {
        $api = $this->api();
        $api->require_method('GET');
        $auth = $api->require_jwt();

        $db = $this->call->database();
        $user = $db->raw(
            'SELECT id, username, email, role, is_active, created_at FROM users WHERE id = ? LIMIT 1',
            [(int) ($auth['sub'] ?? 0)]
        )->fetch();

        if (!$user) {
            $api->respond_error('User not found.', 404);
        }

        $api->respond([
            'user' => [
                'id' => (int) $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role'],
                'is_active' => (int) $user['is_active'],
                'created_at' => $user['created_at'],
            ],
        ]);
    }

    public function users()
    {
        $api = $this->api();
        $api->require_method('GET');
        $api->require_jwt();

        $users = $this->call->database()->raw(
            'SELECT id, username, email, role, is_active, created_at FROM users ORDER BY created_at DESC, id DESC'
        )->fetchAll();

        $api->respond([
            'users' => array_map(function ($user) {
                return [
                    'id' => (int) $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'is_active' => (int) $user['is_active'],
                    'created_at' => $user['created_at'],
                ];
            }, $users),
        ]);
    }

    public function update_user($id)
    {
        $api = $this->api();
        $api->require_method('PUT');
        $auth = $api->require_jwt();
        $input = $api->body();
        $target_id = (int) $id;

        if ($target_id <= 0) {
            $api->respond_error('Invalid user id.', 422);
        }

        $db = $this->call->database();
        $user = $db->raw('SELECT id, username, email, role FROM users WHERE id = ? LIMIT 1', [$target_id])->fetch();
        if (!$user) {
            $api->respond_error('User not found.', 404);
        }

        if ((int) ($auth['sub'] ?? 0) !== $target_id && ($auth['role'] ?? '') !== 'admin') {
            $api->respond_error('Forbidden.', 403);
        }

        $username = trim((string) ($input['username'] ?? $user['username']));
        $email = strtolower(trim((string) ($input['email'] ?? $user['email'])));
        $role = trim((string) ($input['role'] ?? $user['role']));

        if ($username === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($role, ['admin', 'moderator', 'user'], true)) {
            $api->respond_error('Valid username, email, and role are required.', 422);
        }

        $db->raw('UPDATE users SET username = ?, email = ?, role = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?', [$username, $email, $role, $target_id]);

        $api->respond([
            'message' => 'User updated.',
            'user' => [
                'id' => $target_id,
                'username' => $username,
                'email' => $email,
                'role' => $role,
            ],
        ]);
    }

    public function delete_user($id)
    {
        $api = $this->api();
        $api->require_method('DELETE');
        $auth = $api->require_jwt();
        $target_id = (int) $id;

        if ($target_id <= 0) {
            $api->respond_error('Invalid user id.', 422);
        }

        $db = $this->call->database();
        $user = $db->raw('SELECT id FROM users WHERE id = ? LIMIT 1', [$target_id])->fetch();
        if (!$user) {
            $api->respond_error('User not found.', 404);
        }

        if ((int) ($auth['sub'] ?? 0) !== $target_id && ($auth['role'] ?? '') !== 'admin') {
            $api->respond_error('Forbidden.', 403);
        }

        $db->raw('DELETE FROM users WHERE id = ?', [$target_id]);
        $api->respond(['message' => 'User deleted.']);
    }

    public function logout()
    {
        $api = $this->api();
        $api->require_method('POST');
        $api->require_jwt();
        $api->respond(['message' => 'Logged out.']);
    }
}