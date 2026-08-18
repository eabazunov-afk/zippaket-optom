<?php
use PHPUnit\Framework\TestCase;

// permissions.php — чистый модуль без БД: объявляет $ROLE_HIERARCHY, $PERMISSIONS,
// $PAGE_PERMISSIONS и функции проверки прав
require_once __DIR__ . '/../admin/includes/permissions.php';

class AdminPermissionsTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SESSION['admin_role']);
    }

    // ===== Иерархия ролей =====

    public function testRoleLevelOrder(): void
    {
        $this->assertGreaterThan(roleLevel('admin'), roleLevel('superadmin'));
        $this->assertGreaterThan(roleLevel('manager'), roleLevel('admin'));
        $this->assertGreaterThan(roleLevel('viewer'), roleLevel('manager'));
        $this->assertGreaterThan(roleLevel('guest'), roleLevel('viewer'));
    }

    public function testUnknownRoleIsLowest(): void
    {
        $this->assertSame(0, roleLevel('wizard'));
        $this->assertSame(0, roleLevel(''));
        $this->assertSame(0, roleLevel(null));
    }

    // ===== Кто кем может управлять (эскалация привилегий) =====

    public function testAdminCannotManageSuperadmin(): void
    {
        // Ключевая уязвимость: admin правил/удалял суперадмина
        $this->assertFalse(canManageRole('admin', 'superadmin'));
        $this->assertFalse(canManageRole('manager', 'superadmin'));
        $this->assertFalse(canManageRole('viewer', 'admin'));
    }

    public function testSuperadminManagesEveryone(): void
    {
        // Зеркальный дефект: суперадмин не мог редактировать админов
        foreach (['superadmin', 'admin', 'manager', 'viewer'] as $target) {
            $this->assertTrue(canManageRole('superadmin', $target), "superadmin → $target");
        }
    }

    public function testAdminManagesOwnLevelAndBelow(): void
    {
        $this->assertTrue(canManageRole('admin', 'admin'));
        $this->assertTrue(canManageRole('admin', 'manager'));
        $this->assertTrue(canManageRole('admin', 'viewer'));
    }

    // ===== Назначение ролей =====

    public function testCannotAssignRoleAboveOwn(): void
    {
        $this->assertFalse(canAssignRole('admin', 'superadmin'));
        $this->assertFalse(canAssignRole('manager', 'admin'));
        $this->assertFalse(canAssignRole('viewer', 'manager'));
    }

    public function testCanAssignOwnRoleAndBelow(): void
    {
        $this->assertTrue(canAssignRole('superadmin', 'superadmin'));
        $this->assertTrue(canAssignRole('admin', 'admin'));
        $this->assertTrue(canAssignRole('admin', 'viewer'));
    }

    public function testCannotAssignUnknownOrServiceRole(): void
    {
        $this->assertFalse(canAssignRole('superadmin', 'root'));
        $this->assertFalse(canAssignRole('superadmin', ''));
        $this->assertFalse(canAssignRole('superadmin', 'guest'));
    }

    // ===== Матрица прав =====

    public function testViewerCannotChangeAnything(): void
    {
        $_SESSION['admin_role'] = 'viewer';
        $this->assertTrue(checkPermission('view_leads'));
        $this->assertFalse(checkPermission('edit_leads'));
        $this->assertFalse(checkPermission('delete_leads'));
        $this->assertFalse(checkPermission('edit_orders'));      // подтверждение оплаты
        $this->assertFalse(checkPermission('moderate_reviews')); // удаление отзывов
        $this->assertFalse(checkPermission('view_users'));
    }

    public function testOnlySuperadminManagesUsers(): void
    {
        $_SESSION['admin_role'] = 'admin';
        $this->assertTrue(checkPermission('view_users'));
        $this->assertFalse(checkPermission('edit_users'));
        $this->assertFalse(checkPermission('delete_users'));

        $_SESSION['admin_role'] = 'superadmin';
        $this->assertTrue(checkPermission('edit_users'));
        $this->assertTrue(checkPermission('delete_users'));
    }

    public function testUnknownRoleGetsNothing(): void
    {
        $_SESSION['admin_role'] = 'wizard';
        $this->assertFalse(checkPermission('view_leads'));
        $this->assertFalse(checkPermission('edit_leads'));
    }

    public function testMatrixCoversEveryPermissionForEveryRole(): void
    {
        global $PERMISSIONS, $ROLE_HIERARCHY;

        $keys = array_keys($PERMISSIONS['superadmin']);
        foreach ($PERMISSIONS as $role => $perms) {
            foreach ($keys as $key) {
                $this->assertArrayHasKey($key, $perms, "роль $role: не описано право $key");
            }
        }

        // Каждая боевая роль иерархии имеет строку в матрице
        foreach (array_keys($ROLE_HIERARCHY) as $role) {
            if ($role === 'guest') continue;
            $this->assertArrayHasKey($role, $PERMISSIONS, "роль $role отсутствует в матрице");
        }
    }

    // ===== Карта страниц (deny by default) =====

    public function testEveryAdminPageIsListed(): void
    {
        global $PAGE_PERMISSIONS;

        // login.php/logout.php доступны без прав, остальное должно быть в карте
        $public = ['login.php', 'logout.php'];

        $files = array_merge(
            glob(__DIR__ . '/../admin/*.php'),
            glob(__DIR__ . '/../admin/api/*.php')
        );

        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $public, true)) continue;
            $this->assertArrayHasKey($name, $PAGE_PERMISSIONS, "страница $name не описана в карте прав");
        }
    }

    public function testPagePermissionsExistInMatrix(): void
    {
        global $PAGE_PERMISSIONS, $PERMISSIONS;

        foreach ($PAGE_PERMISSIONS as $page => $permission) {
            $this->assertArrayHasKey(
                $permission,
                $PERMISSIONS['superadmin'],
                "страница $page требует несуществующее право $permission"
            );
        }
    }

    public function testUnknownPageHasNoPermission(): void
    {
        global $PAGE_PERMISSIONS;

        // Deny by default: новой страницы в карте нет, значит доступ закрыт
        $this->assertArrayNotHasKey('new_secret_page.php', $PAGE_PERMISSIONS);
    }
}
