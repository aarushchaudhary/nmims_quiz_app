<?php
/**
 * AuthTest.php
 * Tests for:  api/auth.php
 */
class AuthTest
{
    private TestClient $http;

    public function __construct(TestClient $http)
    {
        $this->http = $http;
    }

    public function run(): array
    {
        return [
            test('Auth: missing credentials returns 401',
                fn() => $this->testMissingCredentials()),
            test('Auth: wrong password returns 401',
                fn() => $this->testWrongPassword()),
            test('Auth: valid admin login returns success',
                fn() => $this->testValidAdminLogin()),
            test('Auth: valid faculty login returns success',
                fn() => $this->testValidFacultyLogin()),
            test('Auth: valid student login returns success',
                fn() => $this->testValidStudentLogin()),
        ];
    }

    private function testMissingCredentials(): void
    {
        $this->http->clearSession();
        $res = $this->http->postJson('api/auth.php', []);
        assert_eq($res['status'], 401, 'Missing credentials should return 401');
        assert_key($res['json'], 'status', 'Response should have status key');
        assert_eq($res['json']['status'], 'error', 'Status should be error');
    }

    private function testWrongPassword(): void
    {
        $this->http->clearSession();
        $res = $this->http->postJson('api/auth.php', [
            'email'    => $GLOBALS['TEST']['admin_email'],
            'password' => 'wrong_password_xyz',
        ]);
        assert_eq($res['status'], 401, 'Wrong password should return 401');
        assert_eq($res['json']['status'], 'error', 'Status should be error');
    }

    private function testValidAdminLogin(): void
    {
        $this->http->clearSession();
        $res = $this->http->postJson('api/auth.php', [
            'email'    => $GLOBALS['TEST']['admin_email'],
            'password' => $GLOBALS['TEST']['admin_password'],
        ]);
        assert_eq($res['status'], 200, 'Valid admin login should return 200');
        assert_eq($res['json']['status'], 'success', 'Status should be success');
    }

    private function testValidFacultyLogin(): void
    {
        $this->http->clearSession();
        $res = $this->http->postJson('api/auth.php', [
            'email'    => $GLOBALS['TEST']['faculty_email'],
            'password' => $GLOBALS['TEST']['faculty_password'],
        ]);
        assert_eq($res['status'], 200, 'Valid faculty login should return 200');
        assert_eq($res['json']['status'], 'success', 'Status should be success');
    }

    private function testValidStudentLogin(): void
    {
        $this->http->clearSession();
        $res = $this->http->postJson('api/auth.php', [
            'email'    => $GLOBALS['TEST']['student_email'],
            'password' => $GLOBALS['TEST']['student_password'],
        ]);
        assert_eq($res['status'], 200, 'Valid student login should return 200');
        assert_eq($res['json']['status'], 'success', 'Status should be success');
    }
}
