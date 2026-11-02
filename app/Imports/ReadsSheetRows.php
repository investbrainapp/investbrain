<?php

declare(strict_types=1);

namespace App\Imports;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Collects a worksheet's rows (keyed by their spreadsheet row number) so the whole
 * workbook can be validated before anything is written to the database.
 */
trait ReadsSheetRows
{
    /**
     * @var array<int, array<string, mixed>>
     */
    public array $rows = [];

    /**
     * Column keys found in the sheet's heading row.
     *
     * @var list<string>
     */
    public array $headings = [];

    /**
     * The package hands each row (with its spreadsheet row number) to this hook as it reads
     * the sheet, which makes it the one place rows can be collected with their row number.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function prepareForValidation(array $row, int $rowNumber): array
    {
        if (empty($this->headings)) {
            $this->headings = array_map('strval', array_keys($row));
        }

        $this->rows[$rowNumber] = $this->normalize($row);

        return $row;
    }

    /**
     * Rows were already collected in prepareForValidation().
     *
     * @param  array<int, array<string, mixed>>  $array
     */
    public function array(array $array): void
    {
        //
    }

    /**
     * Rows are validated together with the rest of the workbook in validationErrors() rather
     * than by the package (which stops at the first sheet with a problem).
     */
    public function rules(): array
    {
        return [];
    }

    public function onUnknownSheet(string|int $sheetName): void
    {
        // every sheet is optional
    }

    /**
     * Validate every row against the sheet's rules.
     *
     * @return list<array{sheet: string, row: int|null, column: string|null, value: string|null, message: string}>
     */
    public function validationErrors(): array
    {
        if (empty($this->rows)) {
            return [];
        }

        // a missing column would fail every row, so report it once instead
        $missingColumns = collect(static::columns())
            ->filter(fn ($column, $key) => $column['required'] && ! in_array($key, $this->headings))
            ->map(fn ($column) => $this->error(null, null, __('The :sheet sheet is missing the :column column. Check the heading row matches the import template.', [
                'sheet' => $this->title(),
                'column' => $column['label'],
            ])));

        if ($missingColumns->isNotEmpty()) {
            return $missingColumns->values()->all();
        }

        $errors = [];

        $attributes = collect(static::columns())->map(fn ($column) => $column['label'])->all();

        foreach ($this->rows as $rowNumber => $row) {

            $validator = Validator::make($row, $this->rowRules($row), $this->messages(), $attributes);

            foreach ($validator->errors()->messages() as $field => $messages) {

                $errors[] = $this->error($rowNumber, $field, $messages[0], $row[$field] ?? null);
            }
        }

        return $errors;
    }

    /**
     * @return array{sheet: string, row: int|null, column: string|null, value: string|null, message: string}
     */
    public function error(?int $rowNumber, ?string $field, string $message, mixed $value = null): array
    {
        return [
            'sheet' => $this->title(),
            'row' => $rowNumber,
            'column' => is_null($field) ? null : (static::columns()[$field]['label'] ?? $field),
            'value' => is_scalar($value) ? mb_strimwidth((string) $value, 0, 100, '...') : null,
            'message' => $message,
        ];
    }

    /**
     * Custom validation messages for the sheet.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * Trim strings and turn blank cells into nulls.
     */
    protected function text(mixed $value): mixed
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        return $value === '' ? null : $value;
    }

    /**
     * Like text(), but numeric cells are read as strings (e.g. a portfolio titled 2024).
     */
    protected function string(mixed $value): mixed
    {
        $value = $this->text($value);

        return is_int($value) || is_float($value) ? (string) $value : $value;
    }

    protected function upper(mixed $value): mixed
    {
        $value = $this->string($value);

        return is_string($value) ? strtoupper($value) : $value;
    }

    /**
     * Blank cells are false. Values the boolean rule accepts (TRUE, FALSE, 1, 0) become real
     * booleans, anything else is returned untouched so validation can report it.
     */
    protected function boolean(mixed $value): mixed
    {
        $value = $this->text($value);

        return match (true) {
            is_null($value), in_array($value, [false, 0, '0'], true) => false,
            in_array($value, [true, 1, '1'], true) => true,
            default => $value,
        };
    }

    /**
     * Return date strings as Y-m-d. Anything else (including numbers) is returned untouched
     * so validation can report it.
     */
    protected function date(mixed $value): mixed
    {
        $value = $this->text($value);

        if (! is_string($value)) {
            return $value;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {

            return $value;
        }
    }
}
