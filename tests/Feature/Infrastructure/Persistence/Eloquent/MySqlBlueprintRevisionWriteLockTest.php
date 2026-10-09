<?php

use App\Domain\Blueprint\Entities\Blueprint as DomainBlueprint;
use App\Domain\Blueprint\ValueObjects\BehaviorDigest;
use App\Domain\Blueprint\ValueObjects\BlueprintNamespace;
use App\Domain\Blueprint\ValueObjects\CanonicalName;
use App\Domain\Blueprint\ValueObjects\RevisionNumber;
use App\Infrastructure\Persistence\Eloquent\EloquentBlueprintRepository;
use App\Models\Blueprint as BlueprintModel;
use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;

it('locks the blueprint row while saving revision history on MySQL', function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        test()->markTestSkipped('This concurrency test requires MySQL row locks.');
    }

    $blueprint = DomainBlueprint::create(
        canonicalName: new CanonicalName('mysql-revision-write-lock'),
        namespace: new BlueprintNamespace('skillvlt.test.mysql-lock'),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $blueprint->addRevision(
        number: new RevisionNumber('1.0.0'),
        behaviorDigest: new BehaviorDigest('sha256:' . str_repeat('a', 64)),
        contracts: [],
        logic: [],
        outputs: [],
        policies: [],
    );

    $repository = new EloquentBlueprintRepository();
    $repository->save($blueprint);

    $writerSnapshot = $repository->find($blueprint->id());

    expect($writerSnapshot)->not->toBeNull();

    $connectionConfig = config('database.connections.mysql');
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $connectionConfig['host'],
        $connectionConfig['port'] ?? 3306,
        $connectionConfig['database'],
        $connectionConfig['charset'] ?? 'utf8mb4',
    );

    $competingWriter = new PDO(
        $dsn,
        $connectionConfig['username'],
        $connectionConfig['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );
    $competingWriter->exec('SET SESSION innodb_lock_wait_timeout = 1');

    $connection = DB::connection();
    $connection->beginTransaction();

    try {
        $writerSnapshot->addRevision(
            number: new RevisionNumber('1.1.0'),
            behaviorDigest: new BehaviorDigest('sha256:' . str_repeat('b', 64)),
            contracts: [],
            logic: [],
            outputs: [],
            policies: [],
        );

        // save() runs a nested transaction but must retain its row lock
        // until the surrounding transaction commits or rolls back.
        $repository->save($writerSnapshot);

        $lockException = null;

        try {
            $statement = $competingWriter->prepare(
                "UPDATE blueprints SET lifecycle_status = 'deprecated' WHERE id = ?",
            );
            $statement->execute([(string) $blueprint->id()]);
        } catch (PDOException $exception) {
            $lockException = $exception;
        }

        expect($lockException)->toBeInstanceOf(PDOException::class)
            ->and($lockException->errorInfo[1] ?? null)->toBe(1205);
    } finally {
        if ($connection->transactionLevel() > 0) {
            $connection->rollBack();
        }

        $competingWriter = null;
    }

    expect(
        BlueprintModel::query()
            ->whereKey((string) $blueprint->id())
            ->value('lifecycle_status')
    )->toBe('draft');

    expect(
        DB::table('blueprint_revisions')
            ->where('blueprint_id', (string) $blueprint->id())
            ->count()
    )->toBe(1);
});
