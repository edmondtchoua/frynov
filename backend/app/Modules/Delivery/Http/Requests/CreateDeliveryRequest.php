<?php

namespace App\Modules\Delivery\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
return true; // La logique d'autorisation est dans les Policies
    }

    public function rules(): array
    {
return [
    // TODO: définir les règles de validation
];
    }
}