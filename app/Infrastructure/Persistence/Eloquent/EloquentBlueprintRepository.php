<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Blueprint\Entities\Blueprint;
use App\Domain\Blueprint\Repositories\BlueprintRepository;
use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Domain\Blueprint\ValueObjects\BlueprintNamespace;
use App\Domain\Blueprint\ValueObjects\CanonicalName;
use App\Domain\Blueprint\Entities\BlueprintRevision as DomainBlueprintRevision;
use App\Domain\Blueprint\Exceptions\ConcurrentBlueprintRevisionException;
use App\Domain\Blueprint\ValueObjects\BehaviorDigest;
use App\Domain\Blueprint\ValueObjects\RevisionId;
use App\Domain\Blueprint\ValueObjects\RevisionNumber;
use App\Models\Blueprint as BlueprintModel;
use Illuminate\Support\Facades\DB;

final class EloquentBlueprintRepository
{
    public function discover(): array
    {
        return BlueprintModel::query()
            ->with([
                'revisions.parentRevision',
                'currentRevision',
            ])
            ->where('owner_type', 'system')
            ->where('owner_id', 'skillvlt')
            ->where('lifecycle_status', 'active')
            ->whereHas(
                'currentRevision',
                static fn ($query) => $query
                    ->where('frozen', true)
                    ->where('policies->visibility', 'public'),
            )
            ->orderBy('created_at')
            ->get()
            ->map(
                fn (BlueprintModel $model): Blueprint =>
                    $this->toDomain($model),
            )
            ->all();
    }
}
