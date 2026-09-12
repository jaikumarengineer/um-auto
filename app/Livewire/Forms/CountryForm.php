<?php

namespace App\Livewire\Forms;

use App\Models\Country;
use Illuminate\Validation\Rule;
use Livewire\Form;

class CountryForm extends Form
{
    public ?int $countryId = null;

    public string $iso = '';

    public string $name = '';

    public string $nicename = '';

    public ?string $iso3 = null;

    public ?string $numcode = null;

    public string $phonecode = '';

    public ?string $region_id = null;

    public string $timezone = '';

    public string $utcname = '';

    public string $utc = '';

    public string $abbreviations = '';

    public bool $status = true;

    public function setCountry(Country $country): void
    {
        $this->countryId = $country->id;
        $this->iso = (string) $country->iso;
        $this->name = (string) $country->name;
        $this->nicename = (string) $country->nicename;
        $this->iso3 = $country->iso3 ? (string) $country->iso3 : null;
        $this->numcode = $country->numcode !== null ? (string) $country->numcode : null;
        $this->phonecode = (string) ($country->phonecode ?? '');
        $this->region_id = $country->region_id !== null ? (string) $country->region_id : null;
        $this->timezone = (string) ($country->timezone ?? '');
        $this->utcname = (string) ($country->utcname ?? '');
        $this->utc = (string) ($country->utc ?? '');
        $this->abbreviations = (string) ($country->abbreviations ?? '');
        $this->status = (bool) $country->status;
    }

    public function rules(): array
    {
        return [
            'iso' => [
                'required',
                'string',
                'size:2',
                Rule::unique(Country::class, 'iso')->ignore($this->countryId),
            ],
            'name' => ['required', 'string', 'max:191'],
            'nicename' => ['required', 'string', 'max:80'],
            'iso3' => [
                'nullable',
                'string',
                'size:3',
                Rule::unique(Country::class, 'iso3')->ignore($this->countryId),
            ],
            'numcode' => ['nullable', 'integer', 'min:0', 'max:999'],
            'phonecode' => ['nullable', 'string', 'max:10'],
            'region_id' => ['nullable', 'integer', 'min:0'],
            'timezone' => ['nullable', 'string', 'max:255'],
            'utcname' => ['nullable', 'string', 'max:255'],
            'utc' => ['nullable', 'string', 'max:32'],
            'abbreviations' => ['nullable', 'string', 'max:255'],
            'status' => ['boolean'],
        ];
    }

    public function update(): Country
    {
        $this->iso = strtoupper(trim($this->iso));
        $this->iso3 = filled($this->iso3) ? strtoupper(trim($this->iso3)) : null;
        $this->numcode = filled($this->numcode) ? $this->numcode : null;
        $this->region_id = filled($this->region_id) ? $this->region_id : null;

        $validated = $this->validate();

        $country = Country::query()->findOrFail($this->countryId);

        $country->update([
            'iso' => $validated['iso'],
            'name' => strtoupper($validated['name']),
            'nicename' => $validated['nicename'],
            'iso3' => filled($validated['iso3'] ?? null) ? $validated['iso3'] : null,
            'numcode' => isset($validated['numcode']) ? (int) $validated['numcode'] : null,
            'phonecode' => $validated['phonecode'] ?: null,
            'region_id' => isset($validated['region_id']) ? (int) $validated['region_id'] : null,
            'timezone' => $validated['timezone'] ?: null,
            'utcname' => $validated['utcname'] ?: null,
            'utc' => $validated['utc'] ?: null,
            'abbreviations' => $validated['abbreviations'] ?: null,
            'status' => (bool) $validated['status'],
        ]);

        return $country->refresh();
    }
}
