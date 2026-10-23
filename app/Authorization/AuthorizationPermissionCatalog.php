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
            self::BLUEPRINT_SYSTEM_CREATE => 'Create system-owned Blueprints.',
            self::BLUEPRINT_SYSTEM_READ_PRIVATE => 'Read internal details of system Blueprints and revisions.',
            self::BLUEPRINT_SYSTEM_EXECUTE_PRIVATE => 'Execute non-public system Blueprints when domain rules permit.',
            self::BLUEPRINT_SYSTEM_MANAGE_REVISIONS => 'Add, freeze, and promote revisions of system Blueprints.',
            self::BLUEPRINT_SYSTEM_MANAGE_LIFECYCLE => 'Change lifecycle state of system Blueprints.',
            self::BLUEPRINT_USER_READ_PRIVATE => 'Audited support access to another user’s private Blueprint details.',
            self::BLUEPRINT_USER_EXECUTE_PRIVATE => 'Audited support execution of another user’s private Blueprint.',
            self::TEACHER_TOOLS_MANAGE_REGISTRY => 'List and change the teacher tool registry.',
            self::EXECUTION_SUPPORT_READ => 'Audited support access to execution records.',
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
