<?php

namespace App\Actions\MasterData\BusinessFunction;

use App\Models\BusinessFunction;

class ToggleBusinessFunctionStatus
{
    public function handle(BusinessFunction $businessFunction): BusinessFunction
    {
        $businessFunction->update([
            'is_active' => ! $businessFunction->is_active,
        ]);

        return $businessFunction->refresh();
    }
}
