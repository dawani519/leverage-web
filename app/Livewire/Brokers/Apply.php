<?php

namespace App\Livewire\Brokers;

use App\Models\Broker;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Become a broker')]
class Apply extends Component
{
    public const STATES = [
        'Abia', 'Adamawa', 'Akwa Ibom', 'Anambra', 'Bauchi', 'Bayelsa', 'Benue', 'Borno', 'Cross River', 'Delta',
        'Ebonyi', 'Edo', 'Ekiti', 'Enugu', 'FCT', 'Gombe', 'Imo', 'Jigawa', 'Kaduna', 'Kano', 'Katsina', 'Kebbi',
        'Kogi', 'Kwara', 'Lagos', 'Nasarawa', 'Niger', 'Ogun', 'Ondo', 'Osun', 'Oyo', 'Plateau', 'Rivers', 'Sokoto',
        'Taraba', 'Yobe', 'Zamfara',
    ];

    public string $firstName = '';

    public string $middleName = '';

    public string $lastName = '';

    public string $dob = '';

    public string $state = '';

    public string $lga = '';

    public string $parkName = '';

    public string $phone = '';

    #[Computed]
    public function existing(): ?Broker
    {
        return Broker::where('user_id', Auth::id())->first();
    }

    protected function rules(): array
    {
        return [
            'firstName' => ['required', 'string', 'max:80'],
            'middleName' => ['nullable', 'string', 'max:80'],
            'lastName' => ['required', 'string', 'max:80'],
            'dob' => ['required', 'date', 'before_or_equal:'.now()->subYears(18)->toDateString()],
            'state' => ['required', 'in:'.implode(',', self::STATES)],
            'lga' => ['required', 'string', 'max:80'],
            'parkName' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'regex:/^\+?[0-9 ]{8,20}$/'],
        ];
    }

    protected function messages(): array
    {
        return ['dob.before_or_equal' => __('You must be at least 18 years old.')];
    }

    public function save()
    {
        abort_if($this->existing, 403);
        $this->validate();

        $broker = new Broker([
            'first_name' => trim($this->firstName),
            'middle_name' => trim($this->middleName) ?: null,
            'last_name' => trim($this->lastName),
            'dob' => $this->dob,
            'state' => $this->state,
            'lga' => trim($this->lga),
            'park_name' => trim($this->parkName),
            'phone' => trim($this->phone),
        ]);
        $broker->user_id = Auth::id();
        $broker->status = 'pending';
        $broker->save();

        unset($this->existing);
        Flux::toast(variant: 'success', text: __('Application received. We will review it shortly.'));
    }

    public function render()
    {
        return view('livewire.brokers.apply');
    }
}
