<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Utilities\BaseUtil;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class AttemptScrapeRequest extends FormRequest
{
    /**
     * Allowed domains for scraping.
     *
     * @var array
     */
    protected $allowedDomains = [
        'www.amazon.com',
        'www.amazon.ae',
        'www.aliexpress.com',
        'www.shein.com',
        'www.noon.com',
    ];

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'url' => [
                'required',
                'url',
                $this->allowedDomainRule(),
            ],
        ];
    }

    /**
     * Constructs a regex validation rule for allowed domains.
     *
     * @return string
     */
    protected function allowedDomainRule(): string
    {
        $pattern = implode('|', array_map(function ($domain) {
            return preg_quote($domain, '/');
        }, $this->allowedDomains));

        return 'regex:/^(https?:\\/\\/)?(' . $pattern . ')(\\/[^\\s]*)?$/i';
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => BaseUtil::humanReadableErrors($validator->errors()),
            'data' => $validator->errors()
        ], 422));
    }
}
