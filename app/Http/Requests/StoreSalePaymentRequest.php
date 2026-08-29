<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSalePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('pay', $this->route('sale')) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $sale = $this->route('sale');
        $maxAmount = $sale ? (float) $sale->due_amount : 999999999;

        return [
            'amount' => ['required', 'numeric', 'gt:0', "lte:{$maxAmount}"],
            'payment_method' => ['required', 'string', 'in:cash,card,bank_transfer,other'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
