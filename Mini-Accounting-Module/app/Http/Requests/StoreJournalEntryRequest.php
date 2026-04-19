<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreJournalEntryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'entry_date' => ['required', 'date'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'integer', 'exists:accounts,id'],
            'lines.*.type' => ['required', 'string', 'in:debit,credit'],
            'lines.*.amount' => ['required', 'numeric', 'gt:0'],
            'lines.*.line_description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $lines = collect($this->validated('lines', []));
            $debit = $lines
                ->where('type', 'debit')
                ->sum(fn (array $line): float => (float) $line['amount']);
            $credit = $lines
                ->where('type', 'credit')
                ->sum(fn (array $line): float => (float) $line['amount']);

            if (abs($debit - $credit) > 0.00001) {
                $validator->errors()->add(
                    'lines',
                    'Total debits must equal total credits.'
                );
            }
        });
    }
}

