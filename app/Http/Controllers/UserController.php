<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * Staff accounts (administrators only).
     */
    public function index(Request $request): View
    {
        $this->authorizeAdmin($request->user());

        return view('users.index', [
            'users' => User::query()->orderBy('name')->get(),
        ]);
    }

    /**
     * Show the add-account form.
     */
    public function create(Request $request): View
    {
        $this->authorizeAdmin($request->user());

        return view('users.create', [
            'suggestedUsername' => '',
            'roles' => User::ROLES,
        ]);
    }

    /**
     * Create a staff account.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request->user());

        $validated = $this->validateUser($request);

        $user = User::create($validated + ['is_active' => true]);

        return redirect()
            ->route('users.index')
            ->with('success', 'Account '.$user->username.' was created.');
    }

    /**
     * Show the edit form for an account.
     */
    public function edit(Request $request, User $user): View
    {
        $this->authorizeAdmin($request->user());

        return view('users.edit', [
            'account' => $user,
            'roles' => User::ROLES,
        ]);
    }

    /**
     * Update an account.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAdmin($request->user());

        $validated = $this->validateUser($request, $user);

        if ($user->isAdmin() && ($validated['role'] ?? $user->role) !== 'administrator') {
            $activeAdmins = User::where('role', 'administrator')->where('is_active', true)->where('id', '!=', $user->id)->count();
            abort_unless($activeAdmins > 0, 403, 'At least one active administrator account is required.');
        }

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $user->update($validated);

        return redirect()->route('users.index')->with('success', 'Account '.$user->username.' was updated.');
    }

    /**
     * Activate or deactivate an account.
     */
    public function toggle(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAdmin($request->user());

        if ($user->id === $request->user()->id) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        if ($user->isAdmin() && $user->is_active) {
            $activeAdmins = User::where('role', 'administrator')->where('is_active', true)->where('id', '!=', $user->id)->count();
            if ($activeAdmins === 0) {
                return back()->with('error', 'At least one active administrator account is required.');
            }
        }

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('success', 'Account '.$user->username.' was '.($user->is_active ? 'activated' : 'deactivated').'.');
    }

    /**
     * Save the signed-in user's color palette choice.
     */
    public function updateTheme(Request $request): JsonResponse
    {
        $data = $request->validate([
            'theme' => ['required', Rule::in(User::THEMES)],
        ]);

        $request->user()->update(['theme' => $data['theme']]);

        return response()->json(['ok' => true, 'theme' => $data['theme']]);
    }

    /**
     * The signed-in user's own profile.
     */
    public function profile(Request $request): View
    {
        return view('users.profile', ['account' => $request->user()]);
    }

    /**
     * Edit the signed-in user's own details.
     */
    public function editProfile(Request $request): View
    {
        return view('users.profile_edit', ['account' => $request->user()]);
    }

    /**
     * Update the signed-in user's own details.
     */
    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:100', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['nullable', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        // Only these fields — role and status stay administrator-managed
        $user->update($validated);

        return redirect()
            ->route('profile')
            ->with('success', 'Your profile was updated.');
    }

    /**
     * Change the signed-in user's own password.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($request->string('current_password'), $user->password)) {
            return back()->withErrors(['current_password' => 'Your current password is incorrect.']);
        }

        $user->update(['password' => $request->string('password')]);

        return back()->with('success', 'Your password was updated.');
    }

    /**
     * Only administrators may manage accounts.
     */
    private function authorizeAdmin(User $user): void
    {
        abort_unless($user->isAdmin(), 403, 'Only administrators can manage accounts.');
    }

    /**
     * Shared account validation rules.
     *
     * @return array<string, mixed>
     */
    private function validateUser(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:100', Rule::unique('users', 'username')->ignore($user?->id)],
            'email' => ['nullable', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user?->id)],
            'role' => ['required', Rule::in(User::ROLES)],
            'password' => [$user === null ? 'required' : 'nullable', Rule::when($user === null || $request->filled('password'), ['string', 'min:8'])],
        ]);
    }
}
