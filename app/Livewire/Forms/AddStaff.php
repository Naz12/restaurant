<?php

namespace App\Livewire\Forms;

use Log;
use App\Models\Role;
use App\Models\User;
use App\Models\Country;
use Livewire\Component;
use App\Notifications\StaffWelcomeEmail;
use Jantinnerezo\LivewireAlert\LivewireAlert;

class AddStaff extends Component
{

    use LivewireAlert;

    public $roles;
    public $memberName;
    public $memberEmail;
    public $memberRole;
    public $memberPassword;
    public $restaurantPhoneNumber;
    public $restaurantPhoneCode;
    public $phoneCodeSearch = '';
    public $phoneCodeIsOpen = false;
    public $allPhoneCodes;
    public $filteredPhoneCodes;

    public function mount()
    {
        $this->roles = Role::where('display_name', '<>', 'Super Admin')->get();
        $this->memberRole = $this->roles->first()->name;

        // Initialize phone codes
        $this->allPhoneCodes = collect(Country::pluck('phonecode')->unique()->filter()->values());
        $this->filteredPhoneCodes = $this->allPhoneCodes;
    }

    public function updatedPhoneCodeIsOpen($value)
    {
        if (!$value) {
            $this->reset(['phoneCodeSearch']);
            $this->updatedPhoneCodeSearch();
        }
    }

    public function updatedPhoneCodeSearch()
    {
        $this->filteredPhoneCodes = $this->allPhoneCodes->filter(function ($phonecode) {
            return str_contains($phonecode, $this->phoneCodeSearch);
        })->values();
    }

    public function selectPhoneCode($phonecode)
    {
        $this->restaurantPhoneCode = $phonecode;
        $this->phoneCodeIsOpen = false;
        $this->phoneCodeSearch = '';
        $this->updatedPhoneCodeSearch();
    }

    public function submitForm()
    {
        $this->validate([
            'memberName' => 'required',
            'restaurantPhoneNumber' => [
                'required',
                'regex:/^[0-9\s]{8,20}$/',
            ],
            'restaurantPhoneCode' => 'required',
            'memberPassword' => 'required',
            'memberEmail' => 'required|unique:users,email'
        ]);

        try {
            $currentUser = user();
            if (!$currentUser || !$currentUser->restaurant_id) {
                $this->alert('error', __('messages.restaurantNotFound'), [
                    'toast' => true,
                    'position' => 'top-end',
                ]);
                return;
            }

            $user = User::create([
                'name' => $this->memberName,
                'email' => $this->memberEmail,
                'phone_number' => $this->restaurantPhoneNumber,
                'phone_code' => $this->restaurantPhoneCode,
                'password' => bcrypt($this->memberPassword),
                'restaurant_id' => $currentUser->restaurant_id,
            ]);

            $user->assignRole($this->memberRole);

            // Refresh user to load restaurant relationship
            $user->refresh();

            try {
                if ($user->restaurant) {
                    $user->notify(new StaffWelcomeEmail($user->restaurant, $this->memberPassword));
                }
            } catch (\Exception $e) {
                Log::error('Error sending staff welcome email: ' . $e->getMessage());
            }

            // Reset the form values
            $this->memberName = '';
            $this->memberEmail = '';
            $this->memberRole = $this->roles->first()->name ?? '';
            $this->memberPassword = '';
            $this->restaurantPhoneNumber = '';
            $this->restaurantPhoneCode = '';

            // Dispatch event to close modal
            $this->dispatch('hideAddStaff');
            
            // Dispatch event to refresh staff list
            $this->dispatch('staffCreated');

            $this->alert('success', __('messages.memberAdded'), [
                'toast' => true,
                'position' => 'top-end',
                'showCancelButton' => false,
                'cancelButtonText' => __('app.close')
            ]);
        } catch (\Exception $e) {
            Log::error('Error creating staff member: ' . $e->getMessage());
            $this->alert('error', __('messages.errorOccurred'), [
                'toast' => true,
                'position' => 'top-end',
            ]);
        }
    }

    public function render()
    {
        return view('livewire.forms.add-staff', [
            'phonecodes' => $this->filteredPhoneCodes,
        ]);
    }

}
