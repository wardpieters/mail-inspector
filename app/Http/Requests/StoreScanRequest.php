<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('selectors') && $this->input('selectors') === null) {
            $this->merge(['selectors' => []]);
        }if (is_string($this->input('selectors'))) {
            $this->merge(['selectors' => preg_split('/[,\s]+/', trim($this->input('selectors')), -1, PREG_SPLIT_NO_EMPTY)]);
        }
    }

    public function rules(): array
    {
        return ['domain' => ['required', 'string', 'max:253'], 'selectors' => ['sometimes', 'array', 'max:10'], 'selectors.*' => ['string', 'max:190']];
    }
}
