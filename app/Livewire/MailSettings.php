<?php

namespace App\Livewire;

use App\Support\MailConfig;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Layout('components.layouts.app')]
#[Title('Mail settings')]
class MailSettings extends Component
{
    public string $host = '';

    public int $port = 587;

    public string $username = '';

    public string $password = '';

    public string $encryption = 'tls';

    public string $from_address = '';

    public string $from_name = '';

    public string $testTo = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('settings.manage'), 403);

        $s = MailConfig::current();
        $this->host = $s['host'];
        $this->port = (int) $s['port'];
        $this->username = $s['username'];
        $this->password = $s['password'] === '********' ? '********' : '';
        $this->encryption = $s['encryption'];
        $this->from_address = $s['from_address'];
        $this->from_name = $s['from_name'] ?: config('app.name');
        $this->testTo = auth()->user()?->email ?? '';
    }

    protected function rules(): array
    {
        return [
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'encryption' => ['required', 'in:tls,ssl,none'],
            'from_address' => ['required', 'email'],
            'from_name' => ['required', 'string', 'max:255'],
        ];
    }

    public function save(): void
    {
        $data = $this->validate();
        MailConfig::save($data);
        MailConfig::apply();

        $this->password = MailConfig::current()['password'] === '********' ? '********' : '';
        session()->flash('status', 'Mail settings saved.');
    }

    public function sendTest(): void
    {
        $this->validate(['testTo' => ['required', 'email']]);

        // Persist current form first so the test uses what's on screen.
        $this->validate();
        MailConfig::save($this->only(['host', 'port', 'username', 'password', 'encryption', 'from_address', 'from_name']));
        MailConfig::apply();

        try {
            Mail::raw('This is a test email from DM System. Your SMTP settings are working.', function ($m) {
                $m->to($this->testTo)->subject('DM System — SMTP test');
            });
            session()->flash('status', "Test email sent to {$this->testTo}.");
        } catch (Throwable $e) {
            $this->addError('testTo', 'Send failed: '.$e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.mail-settings');
    }
}
