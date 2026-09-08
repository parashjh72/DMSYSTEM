<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;

#[Layout('components.layouts.app')]
#[Title('Users')]
class UserManager extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $role = 'Report User';

    /** @var list<string> TSO names this user may see (role = TSO only). */
    public array $scopedTsos = [];

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('users.manage'), 403);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($this->editingId)],
            'password' => [$this->editingId ? 'nullable' : 'required', 'min:8'],
            'role' => ['required', Rule::in(Role::pluck('name'))],
            'scopedTsos' => ['array'],
            'scopedTsos.*' => ['string'],
        ];
    }

    public function updatedRole(string $value): void
    {
        if ($value !== 'TSO') {
            $this->scopedTsos = [];
        }
    }

    public function edit(int $id): void
    {
        $user = User::findOrFail($id);
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->role = $user->roles->first()?->name ?? 'Report User';
        $this->scopedTsos = $user->scopedTsos();
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        $isTso = $data['role'] === 'TSO';
        $tsos = $isTso ? array_values(array_filter($data['scopedTsos'], fn ($v) => trim((string) $v) !== '')) : [];

        if ($isTso && $tsos === []) {
            $this->addError('scopedTsos', 'Pick at least one TSO for a TSO user.');

            return;
        }

        $user = User::updateOrCreate(
            ['id' => $this->editingId],
            [
                'name' => $data['name'],
                'email' => $data['email'],
                'scoped_tsos' => $tsos ?: null,
                ...($data['password'] ? ['password' => Hash::make($data['password'])] : []),
            ],
        );
        $user->syncRoles([$data['role']]);

        $this->reset('showForm', 'editingId', 'name', 'email', 'password', 'role', 'scopedTsos');
        session()->flash('status', 'User saved.');
    }

    public function render()
    {
        return view('livewire.user-manager', [
            'users' => User::with('roles')->orderBy('name')->get(),
            'roles' => Role::orderBy('name')->pluck('name'),
            'tsoOptions' => DB::table('territory_officers')->orderBy('name')->pluck('name'),
        ]);
    }
}
