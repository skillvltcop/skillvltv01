# Behavior runtime and logic contract

This document describes the declarative behavior format currently implemented by the runtime. It is a practical reference for authoring revision logic; the validator and tests remain the final source of truth.

## Root structure

A behavior definition must be an object with:

- type set to steps.
- version set to integer 1.
- steps set to a non-empty list of step objects.
- exactly one return step, and it must be the final step in the sequence.

Each step must use a supported type. The behavior contract validator rejects unknown step types, invalid paths, invalid operators, invalid identifiers, malformed nested maps, and return steps in invalid positions.

A simple behavior definition:

    {
      "type": "steps",
      "version": 1,
      "steps": [
        {
          "type": "calculate",
          "operation": "add",
          "left": "input.first",
          "right": "input.second",
          "assign_to": "total"
        },
        {
          "type": "return",
          "data": {
            "total": "{state.total}"
          }
        }
      ]
    }

The example adds two input values, stores the result in runtime state, and returns it. JSON shown here is illustrative; authoring requests must satisfy the validator and the revision's input contract.

## Value paths and state

The ValueResolver supports dotted paths rooted at:

- input — the execution input object.
- context — extra execution context supplied by the caller.
- state — values assigned by earlier steps.
- item — the current object inside a map step only.

Examples: input.score, context.locale, state.percentage, and item.name.

Path segments must use letters, digits, or underscores and cannot contain arbitrary expressions. A missing or invalid path raises a domain exception; the runtime does not evaluate PHP, JavaScript, or arbitrary code from a Blueprint.

The assign_to field stores a step result in state. Identifiers must match a simple identifier pattern beginning with a letter or underscore.

## Supported step types

### calculate

Performs one arithmetic operation and stores its result in state.

- operation: add, subtract, multiply, or divide.
- left and right: finite numeric literals or valid value paths.
- assign_to: target state variable.

Division and numeric operations can fail when their inputs are invalid or the operation is undefined. Those failures become a failed Execution.

### evaluate_rule

Compares a resolved field with a static value and assigns one of two values.

- condition.field: a valid path.
- condition.operator: eq, neq, gt, gte, lt, or lte.
- condition.value: a static comparison value; numeric operators require a finite numeric value.
- assign_to: target state variable.
- true_value and false_value: values to assign depending on the comparison.

### format_template

Resolves placeholders in a string template and stores the resulting string in state. Placeholders use braces around valid paths, for example: Learner: {input.name}. Invalid or unresolved placeholders are rejected by validation or fail at runtime.

### map

Runs a nested step sequence for each object in a source array.

- source: path resolving to an array.
- steps: a non-empty nested sequence.
- assign_to: state variable receiving the array of mapped results.

Each source item must be an object/array. Within nested steps, item.name or another item path accesses the current item. Each item receives its own nested state. A map's nested sequence must return a result.

### aggregate

Aggregates a source array of objects and assigns the result to state.

Supported operations:
- count — number of source entries.
- sum — sum of values at the configured field path.
- average — average of values at the configured field path.
- count_matching — number of entries whose field matches a configured operator and value.

For sum, average, and count_matching, field is a dotted path within each array entry. count_matching supports eq, neq, gt, gte, lt, and lte. Source and entries must have the expected array/object shape; missing fields or incompatible values raise domain exceptions.

### return

The final step defines the Execution output. Its data must be an array/object. Placeholder paths are resolved recursively so values can come from input, context, state, and item where the validator allows item paths.

A return step is mandatory and no step may follow it.

## Validation layers

Behavior execution is protected by more than one validator:

1. BehaviorContractValidator checks the declarative logic structure and step contracts.
2. InputContractValidator validates the revision's contract definition and checks required top-level input keys when a required list is declared. Contracts without a required list remain backward compatible.
3. AssessmentResultsAnalyzerInputValidator applies additional product-specific rules to the built-in assessment results analyzer, including learner object shape, name length, finite non-negative scores, positive maximum scores, and score not exceeding maximum.

The teacher-facing Form Request also validates the expected input shape for the registered teacher-tool slugs before the controller executes them. These layers serve different purposes and should not be treated as interchangeable.

## Execution and failure handling

Before running logic, the engine verifies that the requested revision belongs to the Blueprint, is frozen, is current, and the Blueprint is active. It then validates input and calls the runner.

Successful logic returns an array/object output and marks the Execution completed. A thrown runtime exception is caught by the engine and recorded as a failed Execution with an error message. Error messages can contain implementation details; do not expose them to unrelated users or include sensitive input in error strings.

## Authoring guidance

- Keep logic declarative and deterministic; do not add arbitrary code evaluation.
- Keep contracts and output descriptions aligned with the actual logic.
- Prefer explicit state variable names and simple paths.
- Test edge cases for missing paths, invalid arrays, numeric boundaries, and nested map/aggregate data.
- Freeze only after the revision has passed focused tests; create a new revision for behavior changes.
- Treat behavior digests and frozen revisions as versioning evidence, not as authorization controls.
