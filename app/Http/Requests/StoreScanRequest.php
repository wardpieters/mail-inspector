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
        return ['hidden_from_overview' => ['sometimes', 'boolean'], 'domain' => ['required', 'string', 'max:253'], 'selectors' => ['sometimes', 'array', 'max:10'], 'selectors.*' => ['string', 'max:190']];
    }

    public function messages(): array
    {
        return [
            'domain.required' => __('The domain is required.'),
            'domain.string' => __('The domain must be a string.'),
            'domain.max' => __('The domain may not exceed :max characters.'),
            'selectors.array' => __('The selectors must be an array.'),
            'selectors.max' => __('Use at most :max DKIM selectors.'),
            'selectors.*.string' => __('Each DKIM selector must be a string.'),
            'selectors.*.max' => __('Each DKIM selector may not exceed :max characters.'),
        ];
    }
}
