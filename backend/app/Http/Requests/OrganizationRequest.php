<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Проверка данных организации при создании и обновлении.
 * Один класс на оба случая: набор полей одинаковый.
 */
class OrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Авторизация в этом сервисе не предусмотрена
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'full_name' => ['nullable', 'string', 'max:500'],

            // ИНН: 10 цифр у юрлица, 12 у ИП
            'inn' => ['nullable', 'string', 'regex:/^(\d{10}|\d{12})$/'],
            'kpp' => ['nullable', 'string', 'regex:/^\d{9}$/'],
            'ogrn' => ['nullable', 'string', 'regex:/^(\d{13}|\d{15})$/'],

            'legal_address' => ['nullable', 'string', 'max:500'],
            'actual_address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],

            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account' => ['nullable', 'string', 'regex:/^\d{20}$/'],
            'bank_corr_account' => ['nullable', 'string', 'regex:/^\d{20}$/'],
            'bank_bik' => ['nullable', 'string', 'regex:/^\d{9}$/'],

            'director_name' => ['nullable', 'string', 'max:255'],
            'director_position' => ['nullable', 'string', 'max:255'],

            // Логотип попадает в документ вместо метки ${org.logo}
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:2048'],
        ];
    }

    /** Понятные сообщения об ошибках вместо стандартных английских. */
    public function messages(): array
    {
        return [
            'name.required' => 'Укажите название организации.',
            'inn.regex' => 'ИНН должен содержать 10 цифр (юрлицо) или 12 (ИП).',
            'kpp.regex' => 'КПП должен содержать 9 цифр.',
            'ogrn.regex' => 'ОГРН должен содержать 13 цифр (или 15 для ОГРНИП).',
            'bank_account.regex' => 'Расчётный счёт должен содержать 20 цифр.',
            'bank_corr_account.regex' => 'Корреспондентский счёт должен содержать 20 цифр.',
            'bank_bik.regex' => 'БИК должен содержать 9 цифр.',
            'logo.image' => 'Логотип должен быть изображением PNG или JPG.',
            'logo.max' => 'Логотип не должен быть больше 2 МБ.',
        ];
    }
}
