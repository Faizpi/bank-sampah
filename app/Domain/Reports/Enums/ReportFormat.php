<?php

declare(strict_types=1);

namespace App\Domain\Reports\Enums;

enum ReportFormat: string
{
    case Csv = 'csv';
    case Xlsx = 'xlsx';
    case Pdf = 'pdf';
}
