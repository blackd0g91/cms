<?php

namespace App\Http\Controllers\Cp;

use App\Cms\PasswordLinks;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Who can log in. New people are invited with a link to choose a password,
 * which is shown here once to copy and send. Admins can not change their own
 * role or remove themselves, so there is always an admin.
 */
class UserController extends Controller
{
    public function __construct(private PasswordLinks $links) {}

    public function index(Request $request): Response
    {
        $users = User::query()->withCount('posts')->orderBy('name')->get();
        $expiries = $this->links->expiries($users->map(fn (User $user) => $user->email)->all());

        return Inertia::render('cp/users/Index', [
            'users' => $users->map(fn (User $user) => [
                ...$user->only(['id', 'name', 'email', 'role', 'posts_count']),
                'invited' => $user->isInvited(),
                'link_expires_at' => $expiries[$user->email] ?? null,
                'is_me' => $user->is($request->user()),
            ]),
            'roles' => array_map(fn (UserRole $role) => ['value' => $role->value, 'label' => $role->label()], UserRole::cases()),
        ]);
    }

    /**
     * Add someone, and show the link that lets them choose a password.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = User::create($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'role' => ['required', Rule::enum(UserRole::class)],
        ]));

        return $this->showLink($user);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 403, 'You can not change your own role.');

        $user->update($request->validate([
            'role' => ['required', Rule::enum(UserRole::class)],
        ]));

        return back();
    }

    /**
     * Their posts stay, without an author.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 403, 'You can not remove yourself.');

        $user->delete();

        return back();
    }

    /**
     * A new link for choosing a password: a fresh invitation, or for someone
     * who forgot theirs. The previous link stops working.
     */
    public function link(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 403, 'Change your own password on the Account page.');

        return $this->showLink($user);
    }

    private function showLink(User $user): RedirectResponse
    {
        Inertia::flash('link', [
            ...$this->links->create($user),
            'name' => $user->name,
            'invited' => $user->isInvited(),
        ]);

        return to_route('cp.users.index');
    }
}
