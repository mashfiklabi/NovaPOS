<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePurchasePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('purchase')) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $purchase = $this->route('purchase');
        $maxAmount = $purchase ? (float) $purchase->due_amount : 999999999;

        return [
            'amount' => ['required', 'numeric', 'gt:0', "lte:{$maxAmount}"],
        ];
    }
}
