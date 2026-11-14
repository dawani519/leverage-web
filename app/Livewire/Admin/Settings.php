<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\RequiresAdmin;

use App\Models\AuditLog;
use App\Services\SettingsService;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Platform settings')]
class Settings extends Component
{
    use RequiresAdmin;

    public string $feePercent = '';

    public string $autoReleaseDays = '';

    public string $minAmount = '';

    public string $maxAmount = '';

    public string $minDeposit = '';

    public string $paystackPublicKey = '';

    /**
     * Write-only. Public properties are sent to the browser, so the stored secret is never loaded here:
     * the form only knows whether one is set.
     */
    public string $paystackSecret = '';

    public bool $secretIsSet = false;

    public function mount(SettingsService $settings): void
    {
        $this->feePercent = $this->plain($settings->int('escrow.fee_bps'));
        $this->autoReleaseDays = (string) $settings->int('escrow.auto_release_days');
        $this->minAmount = $this->plain($settings->int('escrow.min_amount_kobo'));
        $this->maxAmount = $this->plain($settings->int('escrow.max_amount_kobo'));
        $this->minDeposit = $this->plain($settings->int('wallet.min_deposit_kobo'));
        $this->paystackPublicKey = (string) $settings->get('paystack.public_key', '');
        $this->secretIsSet = filled($settings->get('paystack.secret_key'));
    }

    /** 200 -> "2", 250 -> "2.5": a value stored in hundredths, shown without trailing zeros. */
    private function plain(int $hundredths): string
    {
        return rtrim(rtrim(number_format($hundredths / 100, 2, '.', ''), '0'), '.');
    }

    protected function rules(): array
    {
        return [
            'feePercent' => ['required', 'regex:/^\d{1,2}(\.\d{1,2})?$/'],
            'autoReleaseDays' => ['required', 'integer', 'between:1,60'],
            'minAmount' => ['required', 'string'],
            'maxAmount' => ['required', 'string'],
            'minDeposit' => ['required', 'string'],
            'paystackPublicKey' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9_]*$/'],
            'paystackSecret' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9_]*$/'],
        ];
    }

    public function save(SettingsService $settings): void
    {
        $this->validate();

        // Money::parse turns "2.5" into 250 (hundredths), which is exactly basis points for a percentage.
        $bps = Money::parse($this->feePercent);
        $min = Money::parse($this->minAmount);
        $max = Money::parse($this->maxAmount);
        $minDeposit = Money::parse($this->minDeposit);

        if ($bps === null || $bps > 5000) {
            $this->addError('feePercent', __('Fee must be between 0% and 50%.'));

            return;
        }
        if ($min === null || $max === null || $min < 100 || $min >= $max) {
            $this->addError('minAmount', __('Minimum must be at least ₦1 and below the maximum.'));

            return;
        }
        if ($minDeposit === null || $minDeposit < 100) {
            $this->addError('minDeposit', __('Minimum deposit must be at least ₦1.'));

            return;
        }

        $changed = [];
        $apply = function (string $key, mixed $value) use ($settings, &$changed) {
            if ((string) $settings->get($key) !== (string) $value) {
                $settings->set($key, $value);
                $changed[] = $key;
            }
        };

        $apply('escrow.fee_bps', $bps);
        $apply('escrow.auto_release_days', (int) $this->autoReleaseDays);
        $apply('escrow.min_amount_kobo', $min);
        $apply('escrow.max_amount_kobo', $max);
        $apply('wallet.min_deposit_kobo', $minDeposit);
        $apply('paystack.public_key', $this->paystackPublicKey ?: null);

        if ($this->paystackSecret !== '') {
            $settings->set('paystack.secret_key', $this->paystackSecret);
            $changed[] = 'paystack.secret_key';
            $this->paystackSecret = '';
            $this->secretIsSet = true;
        }

        // Key names only: the audit log must never contain a secret.
        AuditLog::record('settings.updated', Auth::user(), null, ['keys' => $changed]);
        Flux::toast(variant: 'success', text: $changed ? __('Settings saved.') : __('Nothing changed.'));
    }

    public function clearSecret(SettingsService $settings): void
    {
        $settings->set('paystack.secret_key', null);
        $this->secretIsSet = false;
        AuditLog::record('settings.updated', Auth::user(), null, ['keys' => ['paystack.secret_key'], 'cleared' => true]);
        Flux::toast(variant: 'success', text: __('Paystack secret key removed.'));
    }

    public function render()
    {
        return view('livewire.admin.settings');
    }
}
