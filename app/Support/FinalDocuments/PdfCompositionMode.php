<?php

namespace App\Support\FinalDocuments;

enum PdfCompositionMode: string
{
    case PRESERVE = 'preserve';
    case FIT_TO_SAFE_AREA = 'fit_to_safe_area';
    case FIT_WIDTH_TO_SAFE_TOP = 'fit_width_to_safe_top';
}
