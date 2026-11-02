<?php

declare(strict_types=1);

namespace App\Domain\Documents\Enums;

enum DocumentOutputFormat: string
{
    case Preview = 'preview';
    case Pdf = 'pdf';
    case HtmlDownload = 'html_download';
}
