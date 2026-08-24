<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Blueprint\Commands\AddBlueprintRevision;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Domain\Blueprint\Repositories\BlueprintRepository;
use App\Domain\Blueprint\ValueObjects\BlueprintId;

final class AddBlueprintRevisionController
{
public function __construct(
    private AddBlueprintRevision $command,
    private BlueprintRepository $repository,
) {
}

    public function __invoke(
        Request $request,
        string $blueprint,
    ): JsonResponse {
        $validated = Validator::make(
            $request->all(),
            [
                'number' => [
                    'required',
                    'string',
                    'max:255',
                ],
                'behavior_digest' => [
                    'required',
                    'string',
                    'max:255',
                ],
                'contracts' => [
                    'required',
                    'array',
                ],
                'logic' => [
                    'required',
                    'array',
                ],
                'outputs' => [
                    'required',
                    'array',
                ],
                'policies' => [
                    'required',
                    'array',
                ],
            ],
        )->validate();

        $blueprintEntity = $this->repository->find(
            new BlueprintId($blueprint),
        );

        if ($blueprintEntity === null) {
            return response()->json([
                'message' => 'Blueprint not found.',
            ], 404);
        }

        $ownership = $blueprintEntity->ownership();

        $user = $request->user();

        $isOwner =
            $ownership['type'] === 'user'
            && (string) $ownership['id'] === (string) $user->id;

        if (! $isOwner) {
            return response()->json([
                'message' => 'Forbidden.',
            ], 403);
        }

        try {
            $revision = $this->command->handle(
                blueprintId: $blueprint,
                number: $validated['number'],
                behaviorDigest: $validated['behavior_digest'],
                contracts: $validated['contracts'],
                logic: $validated['logic'],
                outputs: $validated['outputs'],
                policies: $validated['policies'],
            );
        } catch (\RuntimeException $exception) {
            if ($exception->getMessage() === 'Blueprint not found.') {
                return response()->json([
                    'message' => 'Blueprint not found.',
                ], 404);
            }

            throw $exception;
        }

        return response()->json([
            'id' => (string) $revision->id(),
            'blueprint_id' => $blueprint,
            'number' => (string) $revision->number(),
            'behavior_digest' => (string) $revision->behaviorDigest(),
            'contracts' => $revision->contracts(),
            'logic' => $revision->logic(),
            'outputs' => $revision->outputs(),
            'policies' => $revision->policies(),
            'frozen' => $revision->isFrozen(),
        ], 201);
    }
}