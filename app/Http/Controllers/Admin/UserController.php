<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ClubStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(
        private AuditLogger $audit
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $users = User::query()
            ->when($request->filled('search'), fn ($q) => $q->where(function ($sub) use ($request) {
                $search = '%'.$request->input('search').'%';
                $sub->where('name', 'like', $search)
                    ->orWhere('surname', 'like', $search)
                    ->orWhere('email', 'like', $search);
            }))
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->input('role')))
            ->when($request->filled('status'), fn ($q) => $request->input('status') === 'blocked'
                ? $q->where('is_blocked', true)
                : $q->where('is_blocked', false))
            ->orderBy('surname')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('admin.users.create');
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', User::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'surname' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'in:guest,arbiter,admin'],
        ]);

        $validated['password'] = Hash::make(Str::random(32));
        $validated['version'] = 1;
        $validated['is_blocked'] = false;

        $user = User::create($validated);

        $this->audit->log('user.created', $request->user(), 'user', $user->id, [
            'email' => $user->email,
            'role' => $user->role->value,
        ]);

        $status = null;

        try {
            $status = Password::sendResetLink(['email' => $user->email]);
        } catch (\Throwable $e) {
            report($e);
        }

        if ($status !== Password::RESET_LINK_SENT) {
            return redirect()->route('admin.users.edit', $user)
                ->with('warning', __('admin.flash.created_mail_failed'));
        }

        return redirect()->route('admin.users.index')
            ->with('success', __('admin.flash.created'));
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'surname' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'role' => ['required', 'in:guest,arbiter,admin'],
            'version' => ['required', 'integer', 'min:1'],
        ]);

        $expectedVersion = (int) $validated['version'];
        $newRole = $validated['role'];

        // Prevent self demotion.
        if ($request->user()->id === $user->id && $newRole !== $request->user()->role->value) {
            return back()->with('error', __('admin.errors.self_role'));
        }

        // Prevent removing the last admin.
        if ($user->isAdmin() && $newRole !== 'admin' && User::query()->active()->where('role', 'admin')->count() <= 1) {
            return back()->with('error', __('admin.errors.last_admin'));
        }

        $roleChanged = $user->role->value !== $newRole;
        $previousRole = $user->role->value;

        $user->updateWithVersion([
            'name' => $validated['name'],
            'surname' => $validated['surname'],
            'email' => $validated['email'],
            'role' => $newRole,
        ], $expectedVersion);

        if ($roleChanged) {
            $this->audit->log('user.role_changed', $request->user(), 'user', $user->id, [
                'from' => $previousRole,
                'to' => $newRole,
            ]);
        }

        $redirect = redirect()->route('admin.users.index')->with('success', __('admin.flash.updated'));

        if ($newRole === UserRole::Guest->value) {
            $soleManagedClubs = $this->soleManagedActiveClubNames($user);
            if ($soleManagedClubs !== '') {
                $redirect->with('warning', __('clubs.warnings.sole_manager_affected', ['clubs' => $soleManagedClubs]));
            }
        }

        return $redirect;
    }

    public function block(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('block', $user);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
            'version' => ['required', 'integer', 'min:1'],
        ]);

        if ($user->isAdmin() && User::query()->active()->where('role', 'admin')->count() <= 1) {
            return back()->with('error', __('admin.errors.block_last_admin'));
        }

        $user->updateWithVersion([
            'is_blocked' => true,
            'blocked_at' => now(),
            'blocked_reason' => $validated['reason'] ?? null,
        ], (int) $validated['version']);

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->delete();
        }

        $this->audit->log('user.blocked', $request->user(), 'user', $user->id, [
            'reason' => $validated['reason'] ?? null,
        ]);

        $redirect = back()->with('success', __('admin.flash.blocked'));
        $soleManagedClubs = $this->soleManagedActiveClubNames($user);
        if ($soleManagedClubs !== '') {
            $redirect->with('warning', __('clubs.warnings.sole_manager_affected', ['clubs' => $soleManagedClubs]));
        }

        return $redirect;
    }

    public function unblock(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('unblock', $user);

        $validated = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
        ]);

        $user->updateWithVersion([
            'is_blocked' => false,
            'blocked_at' => null,
            'blocked_reason' => null,
        ], (int) $validated['version']);

        $this->audit->log('user.unblocked', $request->user(), 'user', $user->id);

        return back()->with('success', __('admin.flash.unblocked'));
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('resetPassword', $user);

        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status === Password::RESET_LINK_SENT) {
            $this->audit->log('user.password_reset_requested', $request->user(), 'user', $user->id);

            return back()->with('success', __('admin.flash.reset_sent'));
        }

        return back()->with('error', __('admin.flash.reset_failed'));
    }

    private function soleManagedActiveClubNames(User $user): string
    {
        return $user->managedClubs()
            ->where('clubs.status', ClubStatus::Active->value)
            ->get()
            ->filter(function ($club) use ($user) {
                return $club->managers()
                    ->where('users.id', '!=', $user->id)
                    ->where('users.is_blocked', false)
                    ->count() === 0;
            })
            ->pluck('name')
            ->implode(', ');
    }
}
