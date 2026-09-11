<?php

return [
    'qpdf_binary' => env('FINAL_PDF_QPDF_BINARY', 'qpdf'),
    'qpdf_timeout' => (int) env('FINAL_PDF_QPDF_TIMEOUT', 30),
];
