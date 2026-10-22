<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\PublicRelations\Contracts\Fieldable;
use Modules\PublicRelations\Entities\PrField;
use Modules\PublicRelations\Entities\PrFieldValue;
use Modules\PublicRelations\Enums\PrFieldModule;
use Modules\PublicRelations\Enums\PrFieldType;
use Modules\PublicRelations\Repositories\Contracts\PrFieldRepositoryInterface;

class PrCustomFieldService
{
    public function __construct(private readonly PrFieldRepositoryInterface $fields)
    {
    }

    /**
     * Validate raw custom-field input (keyed by field `key`) against the field
     * definitions configured for the given module, and return it keyed by field id.
     *
     * @param array<string, mixed> $input
     * @return array<int, string>
     */
    public function validate(PrFieldModule $module, int $ownerId, array $input): array
    {
        $definitions = $this->fields->listForModule($module, $ownerId);
        $errors = [];
        $resolved = [];

        foreach ($definitions as $field) {
            /** @var PrField $field */
            $value = $input[$field->key] ?? null;

            if ($field->is_required && ($value === null || $value === '')) {
                $errors["custom_fields.{$field->key}"][] = "The {$field->label} field is required.";
                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            if ($field->type->requiresOptions() && ! $this->isValidChoice($value, $field->options ?? [])) {
                $errors["custom_fields.{$field->key}"][] = "The selected {$field->label} is invalid.";
                continue;
            }

            if ($field->type === PrFieldType::Number && ! is_numeric($value)) {
                $errors["custom_fields.{$field->key}"][] = "The {$field->label} field must be a number.";
                continue;
            }

            $resolved[$field->id] = is_array($value) ? json_encode(array_values($value)) : (string) $value;
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        return $resolved;
    }

    /**
     * Validate then persist custom-field values for a fieldable model instance.
     *
     * @param array<string, mixed> $input
     */
    public function sync(Fieldable&Model $fieldable, PrFieldModule $module, int $ownerId, array $input): void
    {
        foreach ($this->validate($module, $ownerId, $input) as $fieldId => $value) {
            PrFieldValue::query()->updateOrCreate(
                [
                    'pr_field_id' => $fieldId,
                    'fieldable_id' => $fieldable->getKey(),
                    'fieldable_type' => $fieldable->getMorphClass(),
                ],
                ['value' => $value]
            );
        }
    }

    /**
     * The custom-field values of a model instance, keyed by field `key`.
     * Checkbox values are decoded back into arrays; every other type stays a string.
     *
     * @return Collection<string, array{label: string, type: string, value: mixed}>
     */
    public function get(Fieldable&Model $fieldable): Collection
    {
        return $fieldable->fieldValues()
            ->with('field')
            ->get()
            ->filter(fn (PrFieldValue $value) => $value->field !== null)
            ->mapWithKeys(fn (PrFieldValue $value) => [
                $value->field->key => [
                    'label' => $value->field->label,
                    'type' => $value->field->type->value,
                    'value' => $value->field->type === PrFieldType::Checkbox && is_string($value->value)
                        ? (json_decode($value->value, true) ?? [])
                        : $value->value,
                ],
            ]);
    }

    private function isValidChoice(mixed $value, array $options): bool
    {
        foreach (is_array($value) ? $value : [$value] as $choice) {
            if (! in_array($choice, $options, true)) {
                return false;
            }
        }

        return true;
    }
}
