<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Support\Auth\ResolvesDepartmentFromIdentity;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'm_department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'department_code' => ['nullable', 'string'],
            'kode_department' => ['nullable', 'string'],
            'department_name' => ['nullable', 'string'],
            'nama_department' => ['nullable', 'string'],
            'password' => $this->passwordRules(),
        ])->validate();

        return User::create([
            'm_department_id' => app(ResolvesDepartmentFromIdentity::class)->resolveId($input),
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);
    }
}
