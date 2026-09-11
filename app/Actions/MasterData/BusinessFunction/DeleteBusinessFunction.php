<?php

namespace App\Actions\MasterData\BusinessFunction;

use App\Models\BusinessFunction;
use Illuminate\Validation\ValidationException;

class DeleteBusinessFunction
{
    /**
     * @throws ValidationException
     */
    public function handle(BusinessFunction $businessFunction): void
    {
        if ($businessFunction->documents()->exists()) {
            throw ValidationException::withMessages([
                'delete' => 'Proses/Fungsi tidak dapat dihapus karena sudah digunakan pada dokumen.',
            ]);
        }

        $businessFunction->delete();
    }
}
