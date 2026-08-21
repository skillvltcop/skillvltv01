<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Enums;

enum LifecycleStatus: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case DEPRECATED = 'deprecated';
    case SUNSET = 'sunset';
}