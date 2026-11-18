<?php

namespace App\Livewire\Auth;

use App\Models\ConsentRecord;
use App\Models\User;
use App\Rules\YorubaText;
use App\Support\Audit;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.guest', ['title' => 'Completar cadastro', 'noindex' => true])]
class CompleteGoogleRegistration extends Component
{
    public string $name = '';

    public string $email = '';

    public string $mother_maiden_name = '';

    public string $birthdate = '';

    public string $country = 'BR';

    public string $cpf = '';

    public string $phone = '';

    public bool $consent = false;

    public bool $submitted = false;

    public function mount(): void
    {
        $pending = session('google_signup');

        abort_unless($pending, 404);

        $this->name = $pending['name'];
        $this->email = $pending['email'];
    }

    protected function rules(): array
    {
        return [
            'mother_maiden_name' => ['required', 'string', 'max:255', new YorubaText],
            'birthdate' => ['nullable', 'date', 'before:today'],
            'country' => ['required', 'string', 'size:2'],
            'cpf' => [Rule::requiredIf($this->country === 'BR'), 'nullable', 'string', 'max:14'],
            'phone' => ['nullable', 'string', 'max:30'],
            'consent' => ['accepted'],
        ];
    }

    public function complete()
    {
        if ($this->birthdate) {
            $this->birthdate = $this->parseDate($this->birthdate);
        }

        $validated = $this->validate();

        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'mother_maiden_name' => $validated['mother_maiden_name'],
            'birthdate' => $validated['birthdate'] ?: null,
            'country' => $validated['country'],
            'cpf' => $validated['cpf'] ?: null,
            'phone' => $validated['phone'] ?: null,
            'role' => 'consulente',
            'status' => 'pending',
            'locale' => app()->getLocale(),
            'password' => Hash::make(Str::random(40)),
            // Google already verified ownership of this email during OAuth.
            'email_verified_at' => now(),
        ]);

        ConsentRecord::create([
            'user_id' => $user->id,
            'type' => 'lgpd_cadastro',
            'accepted_at' => now(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        Audit::record('register.google', $user, userId: $user->id);

        session()->forget('google_signup');

        return redirect()->route('login')->with('status_message', 'Seu cadastro via Google foi realizado com sucesso! Ele está aguardando a aprovação do administrador.');
    }

    private function parseDate(string $value): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $matches)) {
            return "{$matches[3]}-{$matches[2]}-{$matches[1]}";
        }

        return $value;
    }

    public function render()
    {
        return view('livewire.auth.complete-google-registration', [
            'countries' => [
                'BR' => 'Brasil',
                'PT' => 'Portugal',
                'US' => 'Estados Unidos',
                'ES' => 'Espanha',
                'AR' => 'Argentina',
                'CU' => 'Cuba',
                'MX' => 'México',
                'NG' => 'Nigéria',
                'CO' => 'Colômbia',
                'UY' => 'Uruguai',
            ],
        ]);
    }
}
