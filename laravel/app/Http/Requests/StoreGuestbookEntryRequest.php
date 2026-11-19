<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\GuestbookEntry;
use Illuminate\Foundation\Http\FormRequest;

class StoreGuestbookEntryRequest extends FormRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:'.GuestbookEntry::NAME_MAX_LENGTH],
            'message' => ['required', 'string', 'max:'.GuestbookEntry::MESSAGE_MAX_LENGTH],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'name.max' => 'Nama paling panjang :max karakter.',
            'message.required' => 'Pesan wajib diisi.',
            'message.max' => 'Pesan paling panjang :max karakter.',
        ];
    }
}
