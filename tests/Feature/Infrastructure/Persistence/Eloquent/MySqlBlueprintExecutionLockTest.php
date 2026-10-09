<?php

use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Infrastructure\Persistence\Eloquent\EloquentBlueprintRepository;
use App\Models\Blueprint as BlueprintModel;
use Illuminate\Support\Str;
use PDO;
use PDOException;
use Illuminate\Support\Facades\DB;

it('locks the blueprint row while loading the execution snapshot on MySQL', function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        test()->markTestSkipped('This concurrency test requires MySQL row locks.');
    }

    $id = (string) Str::ulid();

    BlueprintModel::query()->create([
        'id' => $id,
        'canonical_name' => 'execution-lock-test',
        'namespace' => 'skillvlt.test.execution',
        'owner_type' => 'system',
        'owner_id' => 'skillvlt',
        'lifecycle_status' => 'draft',
    ]);

    $connectionConfig = config('database.connections.mysql');
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $connectionConfig['host'],
        $connectionConfig['port'] ?? 3306,
        $connectionConfig['database'],
        $connectionConfig['charset'] ?? 'utf8mb4',
    );

    $writer = new PDO(
        $dsn,
        $connectionConfig['username'],
        $connectionConfig['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );

    $writer->exec('SET SESSION innodb_lock_wait_timeout = 1');

    $connection = DB::connection();
    $connection->beginTransaction();

    try {
        $loaded = (new EloquentBlueprintRepository())->findForExecution(
            new BlueprintId($id),
        );

        expect($loaded)->not->toBeNull()
            ->and((string) $loaded->id())->toBe($id);

        $lockException = null;

        try {
            $statement = $writer->prepare(
                "UPDATE blueprints SET lifecycle_status = 'deprecated' WHERE id = ?",
            );
            $statement->execute([$id]);
        } catch (PDOException $exception) {
            $lockException = $exception;
        }

        expect($lockException)->toBeInstanceOf(PDOException::class)
            ->and($lockException->errorInfo[1] ?? null)->toBe(1205);
    } finally {
        if ($connection->transactionLevel() > 0) {
            $connection->rollBack();
        }

        $writer = null;
    }

    $updated = DB::table('blueprints')->where('id', $id)->value('lifecycle_status');

    expect($updated)->toBe('draft');
});
