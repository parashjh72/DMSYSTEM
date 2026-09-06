<?php

namespace App\Livewire;

use App\Models\User;
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
        ];
    }

    public function edit(int $id): void
    {
        $user = User::findOrFail($id);
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->role = $user->roles->first()?->name ?? 'Report User';
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        $user = User::updateOrCreate(
            ['id' => $this->editingId],
            [
                'name' => $data['name'],
                'email' => $data['email'],
                ...($data['password'] ? ['password' => Hash::make($data['password'])] : []),
            ],
        );
        $user->syncRoles([$data['role']]);

        $this->reset('showForm', 'editingId', 'name', 'email', 'password');
        session()->flash('status', 'User saved.');
    }

    public function render()
    {
        return view('livewire.user-manager', [
            'users' => User::with('roles')->orderBy('name')->get(),
            'roles' => Role::pluck('name'),
        ]);
    }
}
