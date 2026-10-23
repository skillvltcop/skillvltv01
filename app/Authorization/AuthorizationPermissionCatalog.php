<?php

declare(strict_types=1);

namespace App\Authorization;

/**
 * Canonical catalog of grantable permissions.
 *
 * Keep API validation, policies, the administrative UI, and documentation
 * aligned with this list. A catalog entry only has effect where a policy
 * explicitly checks it.
 */
final class AuthorizationPermissionCatalog
{
    public const BLUEPRINT_SYSTEM_CREATE = 'blueprint.system.create';

    public const BLUEPRINT_SYSTEM_READ_PRIVATE = 'blueprint.system.read_private';

    public const BLUEPRINT_SYSTEM_EXECUTE_PRIVATE = 'blueprint.system.execute_private';

    public const BLUEPRINT_SYSTEM_MANAGE_REVISIONS = 'blueprint.system.manage_revisions';

    public const BLUEPRINT_SYSTEM_MANAGE_LIFECYCLE = 'blueprint.system.manage_lifecycle';

    public const BLUEPRINT_USER_READ_PRIVATE = 'blueprint.user.read_private';

    public const BLUEPRINT_USER_EXECUTE_PRIVATE = 'blueprint.user.execute_private';

    public const TEACHER_TOOLS_MANAGE_REGISTRY = 'teacher_tools.manage_registry';

    public const EXECUTION_SUPPORT_READ = 'execution.support_read';

    /**
     * @return array<string, string> Permission identifier => description.
     */
    public static function definitions(): array
    {
        return [
            self::BLUEPRINT_SYSTEM_CREATE => 'إنشاء Blueprint مملوك للنظام.',
            self::BLUEPRINT_SYSTEM_READ_PRIVATE => 'قراءة التفاصيل الداخلية لـBlueprints النظام ومراجعاتها.',
            self::BLUEPRINT_SYSTEM_EXECUTE_PRIVATE => 'تنفيذ Blueprints النظام غير العامة عندما تسمح قواعد المجال.',
            self::BLUEPRINT_SYSTEM_MANAGE_REVISIONS => 'إضافة مراجعات Blueprints النظام وتجميدها وترقيتها.',
            self::BLUEPRINT_SYSTEM_MANAGE_LIFECYCLE => 'تغيير حالة دورة حياة Blueprints النظام.',
            self::BLUEPRINT_USER_READ_PRIVATE => 'قراءة تفاصيل Blueprint خاص لمستخدم آخر مع تسجيل التدقيق.',
            self::BLUEPRINT_USER_EXECUTE_PRIVATE => 'تنفيذ Blueprint خاص لمستخدم آخر مع تسجيل التدقيق.',
            self::TEACHER_TOOLS_MANAGE_REGISTRY => 'استعراض سجل أدوات المعلم وتعديله.',
            self::EXECUTION_SUPPORT_READ => 'قراءة سجلات التنفيذ لأغراض الدعم مع تسجيل التدقيق.',
        ];
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_keys(self::definitions());
    }
}
