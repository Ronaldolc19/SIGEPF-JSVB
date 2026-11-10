<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUserPersonaRequest;
use App\Models\Persona;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserPersonaController extends Controller
{
    public function edit(Request $request, User $user): Response
    {
        $validated = $request->validate(['buscar' => ['nullable', 'string', 'max:100']]);
        $search = trim($validated['buscar'] ?? '');

        return Inertia::render('user-persona', [
            'account' => $user->only(['id', 'name', 'email', 'id_persona']),
            'status' => $request->session()->get('status'),
            'currentPerson' => $user->persona()->select('id_persona', 'nombre', 'ap', 'am', 'correo')->first(),
            'personas' => Persona::query()->select('id_persona', 'nombre', 'ap', 'am', 'correo')
                ->where(function (Builder $available) use ($user): void {
                    $available->whereDoesntHave('usuario')->orWhereHas('usuario', fn (Builder $accounts): Builder => $accounts->whereKey($user->id));
                })
                ->when($search !== '', function (Builder $query) use ($search): void {
                    $query->where(function (Builder $names) use ($search): void {
                        $names->where('nombre', 'like', '%'.$search.'%')->orWhere('ap', 'like', '%'.$search.'%')
                            ->orWhere('am', 'like', '%'.$search.'%')->orWhere('correo', 'like', '%'.$search.'%');
                    });
                })->withExists('asesor')->withCount('participaciones')->orderBy('ap')->orderBy('id_persona')->paginate(10)->withQueryString(),
            'buscar' => $search,
        ]);
    }

    public function update(UpdateUserPersonaRequest $request, User $user): RedirectResponse
    {
        DB::transaction(function () use ($request, $user): void {
            $account = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $id = $request->validated('id_persona');
            if ($id !== null) {
                $persona = Persona::whereKey($id)->lockForUpdate()->first();
                if (! $persona) {
                    throw ValidationException::withMessages(['id_persona' => 'La persona no existe o está eliminada.']);
                }
                if (User::where('id_persona', $id)->whereKeyNot($account->id)->exists()) {
                    throw ValidationException::withMessages(['id_persona' => 'Esta persona ya está vinculada a otra cuenta.']);
                }
            }
            $account->id_persona = $id;
            $account->save();
        });

        return to_route('usuarios.index')->with('status', 'Vínculo actualizado. Los roles y los datos de la persona no se modificaron.');
    }
}
