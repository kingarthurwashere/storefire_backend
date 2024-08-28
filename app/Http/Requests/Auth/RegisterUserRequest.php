<?php

namespace App\Http\Requests\Auth;

use App\Enums\DB\Sex;
use App\Utilities\BaseUtil;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class RegisterUserRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'username' => 'required|unique:users|regex:/^[\w].*$/',
            'phone' => 'required|unique:users|regex:/^[\w].*$/',
            'email' => 'required|email|unique:users,email',
            'name' => 'required|string|min:3|regex:/^[\w].*$/',
            'password' => 'required|min:8|confirmed',
        ];
    }

    public function failedValidation(Validator $validator)
    {

        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => BaseUtil::humanReadableErrors($validator->errors()),
            'data' => $validator->errors()
        ]));
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            
        ];
    }
}
