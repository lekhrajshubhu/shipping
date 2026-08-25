<?php

uses(Tests\TestCase::class);

use Illuminate\Support\Facades\Validator;
use Systha\Shipping\Domains\EasyPost\Requests\VerifyAddressRequest;

function normalized_verify_address_data(array $data): array
{
    $request = new class extends VerifyAddressRequest {
        public function normalize(): void
        {
            $this->prepareForValidation();
        }
    };

    $request->replace($data);
    $request->normalize();

    return $request->all();
}

it('accepts valid required fields and keeps postal codes as strings', function (): void {
    $data = normalized_verify_address_data([
        'street1' => '417 Montgomery St',
        'street2' => 'Floor 5',
        'city' => 'San Francisco',
        'state' => 'CA',
        'zip' => '02108',
        'country' => 'us',
        'name' => 'John Doe',
        'company' => null,
        'phone' => '4151234567',
        'email' => 'john@example.com',
        'residential' => false,
    ]);

    $validator = Validator::make($data, (new VerifyAddressRequest())->rules());

    expect($validator->passes())->toBeTrue();
    expect($data)
        ->toMatchArray([
            'street1' => '417 Montgomery St',
            'street2' => 'Floor 5',
            'city' => 'San Francisco',
            'state' => 'CA',
            'zip' => '02108',
            'country' => 'US',
            'name' => 'John Doe',
            'company' => null,
            'phone' => '4151234567',
            'email' => 'john@example.com',
            'residential' => false,
        ]);
});

it('rejects missing required address fields', function (string $field): void {
    $data = [
        'street1' => '417 Montgomery St',
        'city' => 'San Francisco',
        'state' => 'CA',
        'zip' => '94104',
        'country' => 'US',
    ];

    unset($data[$field]);

    $validator = Validator::make($data, (new VerifyAddressRequest())->rules());

    expect($validator->fails())->toBeTrue();
})->with(['street1', 'city', 'state', 'zip', 'country']);

it('rejects country codes that are not exactly two characters', function (): void {
    $validator = Validator::make(normalized_verify_address_data([
        'street1' => '417 Montgomery St',
        'city' => 'San Francisco',
        'state' => 'CA',
        'zip' => '94104',
        'country' => 'USA',
    ]), (new VerifyAddressRequest())->rules());

    expect($validator->fails())->toBeTrue();
});

it('normalizes country codes to uppercase and trims string inputs', function (): void {
    $data = normalized_verify_address_data([
        'street1' => ' 417 Montgomery St ',
        'street2' => ' Floor 5 ',
        'city' => ' San Francisco ',
        'state' => ' CA ',
        'zip' => ' 94104-1234 ',
        'country' => ' us ',
        'name' => ' John Doe ',
        'company' => ' Acme ',
        'phone' => ' 4151234567 ',
        'email' => ' john@example.com ',
        'residential' => true,
    ]);

    $validator = Validator::make($data, (new VerifyAddressRequest())->rules());

    expect($validator->passes())->toBeTrue();
    expect($data)
        ->toMatchArray([
            'street1' => '417 Montgomery St',
            'street2' => 'Floor 5',
            'city' => 'San Francisco',
            'state' => 'CA',
            'zip' => '94104-1234',
            'country' => 'US',
            'name' => 'John Doe',
            'company' => 'Acme',
            'phone' => '4151234567',
            'email' => 'john@example.com',
            'residential' => true,
        ]);
});

it('allows optional fields to be omitted', function (): void {
    $data = normalized_verify_address_data([
        'street1' => '417 Montgomery St',
        'city' => 'San Francisco',
        'state' => 'CA',
        'zip' => '94104',
        'country' => 'US',
    ]);

    $validator = Validator::make($data, (new VerifyAddressRequest())->rules());

    expect($validator->passes())->toBeTrue();
    expect($data)
        ->toMatchArray([
            'street1' => '417 Montgomery St',
            'city' => 'San Francisco',
            'state' => 'CA',
            'zip' => '94104',
            'country' => 'US',
        ]);
});
