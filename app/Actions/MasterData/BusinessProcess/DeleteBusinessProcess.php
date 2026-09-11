<?php

namespace App\Actions\MasterData\BusinessProcess;

use App\Models\BusinessProcess;
use Illuminate\Validation\ValidationException;

class DeleteBusinessProcess
{
    /**
     * @throws ValidationException
     */
    public function handle(BusinessProcess $businessProcess): void
    {
        if ($businessProcess->documents()->exists()) {
            throw ValidationException::withMessages([
                'delete' => 'Proses bisnis tidak bisa dihapus karena sudah digunakan pada dokumen.',
            ]);
        }

        $businessProcess->delete();
    }
}
