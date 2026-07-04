<?php

namespace App\Domain\CRM\Services;

use App\Domain\CRM\Models\CustomField;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

class CustomFieldValidator
{
    /**
     * Validate an array of custom field values against configured fields in the database.
     *
     * @param string $modelType E.g. 'Lead', 'Contact', 'Company', 'Opportunity'
     * @param string $organizationId
     * @param array $values Key-value pairs where key is custom field name/slug, value is input
     * @return array Array of validation errors (empty if completely valid)
     */
    public function validate(string $modelType, string $organizationId, array $values): array
    {
        $fields = CustomField::where('model_type', $modelType)
            ->where('organization_id', $organizationId)
            ->get();

        $errors = [];

        foreach ($fields as $field) {
            $fieldName = $field->name;
            $fieldType = strtolower($field->field_type);
            $isRequired = $field->is_required;
            $options = $field->options ?? [];

            $hasValue = isset($values[$fieldName]) && $values[$fieldName] !== '' && $values[$fieldName] !== null;

            // 1. Check requirement constraint
            if ($isRequired && !$hasValue) {
                $errors[$fieldName][] = "The custom field [{$fieldName}] is required.";
                continue;
            }

            if (!$hasValue) {
                continue;
            }

            $value = $values[$fieldName];

            // 2. Format & type validation
            switch ($fieldType) {
                case 'boolean':
                    if (!is_bool($value) && !in_array($value, [0, 1, '0', '1', 'true', 'false'], true)) {
                        $errors[$fieldName][] = "The custom field [{$fieldName}] must be a boolean.";
                    }
                    break;

                case 'integer':
                    if (!filter_var($value, FILTER_VALIDATE_INT) && !is_int($value)) {
                        $errors[$fieldName][] = "The custom field [{$fieldName}] must be an integer.";
                    }
                    break;

                case 'decimal':
                case 'currency':
                    if (!is_numeric($value)) {
                        $errors[$fieldName][] = "The custom field [{$fieldName}] must be a numeric value.";
                    }
                    break;

                case 'date':
                    if (!strtotime($value)) {
                        $errors[$fieldName][] = "The custom field [{$fieldName}] must be a valid date.";
                    }
                    break;

                case 'datetime':
                    if (!strtotime($value)) {
                        $errors[$fieldName][] = "The custom field [{$fieldName}] must be a valid datetime.";
                    }
                    break;

                case 'email':
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $errors[$fieldName][] = "The custom field [{$fieldName}] must be a valid email address.";
                    }
                    break;

                case 'url':
                    if (!filter_var($value, FILTER_VALIDATE_URL)) {
                        $errors[$fieldName][] = "The custom field [{$fieldName}] must be a valid URL.";
                    }
                    break;

                case 'phone':
                    // Basic check for phone number characters
                    if (!preg_match('/^[+0-9\s\-()]*$/', $value)) {
                        $errors[$fieldName][] = "The custom field [{$fieldName}] contains invalid phone characters.";
                    }
                    break;

                case 'select':
                case 'dropdown':
                    if (!empty($options) && !in_array($value, $options, true)) {
                        $optionsStr = implode(', ', $options);
                        $errors[$fieldName][] = "The custom field [{$fieldName}] must be one of: {$optionsStr}.";
                    }
                    break;

                case 'multi-select':
                    if (!is_array($value)) {
                        $errors[$fieldName][] = "The custom field [{$fieldName}] must be an array of selected options.";
                        break;
                    }
                    foreach ($value as $item) {
                        if (!empty($options) && !in_array($item, $options, true)) {
                            $optionsStr = implode(', ', $options);
                            $errors[$fieldName][] = "The selected option '{$item}' in [{$fieldName}] is invalid. Must be one of: {$optionsStr}.";
                        }
                    }
                    break;

                case 'json':
                    if (!is_array($value)) {
                        json_decode($value);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            $errors[$fieldName][] = "The custom field [{$fieldName}] must be a valid JSON string or array.";
                        }
                    }
                    break;
            }
        }

        return $errors;
    }
}
