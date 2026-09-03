<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\SalePaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sales.refund') ?? false;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'refund_method' => ['required', 'string', Rule::enum(SalePaymentMethod::class)],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
