<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Utilities\BaseUtil;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class OrderPlaceRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'billing' => 'required|array',
            'billing.first_name' => 'required|string|max:255',
            'billing.last_name' => 'required|string|max:255',
            'billing.address_1' => 'required|string|max:255',
            'billing.city' => 'required|string|max:255',
            'billing.country' => 'required|string|size:2',
            'billing.email' => 'required|email|max:255',
            'billing.phone' => 'required|string|max:20',

            'shipping' => 'required|array',
            'shipping.first_name' => 'required|string|max:255',
            'shipping.last_name' => 'required|string|max:255',
            'shipping.address_1' => 'required|string|max:255',
            'shipping.city' => 'required|string|max:255',
            'shipping.postcode' => 'required|string|max:10',
            'shipping.country' => 'required|string|size:2',

            'attempt_id' => 'required|uuid',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param \Illuminate\Contracts\Validation\Validator $validator
     * @return void
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => BaseUtil::humanReadableErrors($validator->errors()),
            'data' => $validator->errors()
        ]));
    }
}
