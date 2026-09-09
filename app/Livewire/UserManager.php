<?php

namespace App\Livewire;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
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

    public string $role = 'RD';

    /** @var list<string> RD codes this user may see (ASM / TSO / RD roles only). */
    public array $scopedRdCodes = [];

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('users.manage'), 403);
    }

    /** @return list<string> */
    private function scopedRoles(): array
    {
        return RolesAndPermissionsSeeder::SCOPED_ROLES;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($this->editingId)],
            'password' => [$this->editingId ? 'nullable' : 'required', 'min:8'],
            'role' => ['required', Rule::in(Role::pluck('name'))],
            'scopedRdCodes' => ['array'],
            'scopedRdCodes.*' => ['string'],
        ];
    }

    public function updatedRole(string $value): void
    {
        if (! in_array($value, $this->scopedRoles(), true)) {
            $this->scopedRdCodes = [];
        }
    }

    public function edit(int $id): void
    {
        $user = User::findOrFail($id);
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->role = $user->roles->first()?->name ?? 'RD';
        $this->scopedRdCodes = $user->scopedRdCodes();
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        $isScoped = in_array($data['role'], $this->scopedRoles(), true);
        $codes = $isScoped
            ? array_values(array_filter($data['scopedRdCodes'], fn ($v) => trim((string) $v) !== ''))
            : [];

        if ($isScoped && $codes === []) {
            $this->addError('scopedRdCodes', 'Pick at least one distributor (RD) for an ASM / TSO / RD user.');

            return;
        }

        $user = User::updateOrCreate(
            ['id' => $this->editingId],
            [
                'name' => $data['name'],
                'email' => $data['email'],
                'scoped_rd_codes' => $codes ?: null,
                ...($data['password'] ? ['password' => Hash::make($data['password'])] : []),
            ],
        );
        $user->syncRoles([$data['role']]);

        $this->reset('showForm', 'editingId', 'name', 'email', 'password', 'role', 'scopedRdCodes');
        session()->flash('status', 'User saved.');
    }

    public function render()
    {
        return view('livewire.user-manager', [
            'users' => User::with('roles')->orderBy('name')->get(),
            'roles' => Role::orderByRaw("FIELD(name, 'Super Admin','Admin','NSM','ASM','TSO','RD')")->pluck('name'),
            'scopedRoles' => $this->scopedRoles(),
            'rdOptions' => DB::table('retail_distributors')
                ->orderBy('code')
                ->get(['code', 'name'])
                ->map(fn ($r) => ['code' => $r->code, 'label' => trim($r->code.' — '.($r->name ?? ''), ' —')]),
        ]);
    }
}
