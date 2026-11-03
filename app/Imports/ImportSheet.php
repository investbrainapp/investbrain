<?php

declare(strict_types=1);

namespace App\Imports;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Row;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * One worksheet of an Investbrain workbook. Collects its rows (keyed by their spreadsheet row
 * number) so the whole workbook can be validated before anything is written to the database.
 */
abstract class ImportSheet implements OnEachRow, SkipsEmptyRows, WithCalculatedFormulas, WithHeadingRow
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

    abstract public function title(): string;

    /**
     * @return array<string, array{label: string, required: bool}>
     */
    abstract public static function columns(): array;

    /**
     * Tidy up a row's raw cell values before they are validated.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    abstract public function normalize(array $row): array;

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    abstract public function rowRules(array $row): array;

    /**
     * Collect each row as the package reads the sheet.
     */
    public function onRow(Row $row): void
    {
        // the same arguments the package read the row with, so this is a cache hit
        $cells = $row->toArray(calculateFormulas: true, formatData: false);

        if (empty($this->headings)) {
            $this->headings = array_map('strval', array_keys($cells));
        }

        $this->rows[$row->getIndex()] = $this->normalize($cells);
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
     * Accept real booleans plus the usual spreadsheet spellings (TRUE, Yes, 1...). Blank cells
     * are false, unrecognised values are returned untouched so validation can report them.
     */
    protected function boolean(mixed $value): mixed
    {
        $value = $this->text($value);

        if (is_null($value)) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $value;
    }

    /**
     * Accept Excel date cells (which are read as serial numbers) as well as date strings and
     * return them as Y-m-d. Unparseable values are returned untouched so validation can report them.
     */
    protected function date(mixed $value): mixed
    {
        $value = $this->text($value);

        if (is_null($value)) {
            return null;
        }

        try {
            if (is_numeric($value)) {

                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString();
            }

            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {

            return $value;
        }
    }

    /**
     * Strip currency symbols and spaces that sneak in when numbers are pasted as text. Commas
     * are left alone because they are decimal separators in many locales.
     */
    protected function number(mixed $value): mixed
    {
        $value = $this->text($value);

        if (is_string($value)) {
            $stripped = preg_replace('/[\s$€£¥]/u', '', $value);

            return is_numeric($stripped) ? (float) $stripped : $value;
        }

        return $value;
    }
}
