<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        $this->flashDepartmentWarning($request);

        return $request->wantsJson()
            ? new JsonResponse('', 204)
            : redirect()->intended(Fortify::redirects('login'));
    }

    private function flashDepartmentWarning(Request $request): void
    {
        $user = $request->user();

        if ($user === null || $user->isDeveloper() || $user->m_department_id !== null) {
            return;
        }

        $request->session()->flash('department_warning', [
            'title' => 'Department Belum Terdaftar',
            'message' => 'Akun Anda belum terdaftar di department manapun. Silakan hubungi admin untuk melengkapi data department.',
        ]);
    }
}
