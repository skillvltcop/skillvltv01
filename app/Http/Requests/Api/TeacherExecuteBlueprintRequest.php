<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Application\Execution\NumericValueComparator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class TeacherExecuteBlueprintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'input' => [
                'array',
            ],
            'context' => [
                'array',
            ],
        ];

        if ($this->route('slug') === 'assessment-results-analyzer') {
            return array_merge($rules, [
                'input.learners' => ['required', 'array', 'min:1'],
                'input.learners.*' => ['required', 'array'],
                'input.learners.*.name' => ['required', 'string', 'max:120'],
                'input.learners.*.score' => ['required', 'numeric', 'min:0'],
                'input.learners.*.max_score' => ['required', 'numeric', 'gt:0'],
            ]);
        }

        if ($this->route('slug') === 'assessment-score-calculator') {
            return array_merge($rules, [
                'input.correct' => ['required', 'integer', 'min:0'],
                'input.total' => ['required', 'integer', 'min:1'],
            ]);
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        if ($this->route('slug') === 'assessment-score-calculator') {
            $validator->after(function (Validator $validator): void {
                if (
                    $validator->errors()->has('input.correct')
                    || $validator->errors()->has('input.total')
                ) {
                    return;
                }

                $correct = $this->input('input.correct');
                $total = $this->input('input.total');

                if (NumericValueComparator::compare($correct, $total) > 0) {
                    $validator->errors()->add(
                        'input.correct',
                        'The number of correct answers may not exceed the total.',
                    );
                }
            });

            return;
        }

        if ($this->route('slug') !== 'assessment-results-analyzer') {
            return;
        }

        $validator->after(function (Validator $validator): void {
            $learners = $this->input('input.learners');

            if (! is_array($learners)) {
                return;
            }

            foreach ($learners as $index => $learner) {
                if (! is_array($learner)) {
                    continue;
                }

                $score = $learner['score'] ?? null;
                $maxScore = $learner['max_score'] ?? null;

                if (is_numeric($score) && ! is_finite((float) $score)) {
                    $validator->errors()->add(
                        "input.learners.{$index}.score",
                        'The score must be finite.',
                    );
                }

                if (is_numeric($maxScore) && ! is_finite((float) $maxScore)) {
                    $validator->errors()->add(
                        "input.learners.{$index}.max_score",
                        'The maximum score must be finite.',
                    );
                }

                if (
                    is_numeric($score)
                    && is_finite((float) $score)
                    && is_numeric($maxScore)
                    && is_finite((float) $maxScore)
                    && NumericValueComparator::compare($score, $maxScore) > 0
                ) {
                    $validator->errors()->add(
                        "input.learners.{$index}.score",
                        'The score may not exceed the maximum score.',
                    );
                }
            }
        });
    }
}
