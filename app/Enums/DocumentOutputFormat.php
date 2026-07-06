<?php

namespace App\Enums;

enum DocumentOutputFormat: string
{
    case Preview = 'preview';
    case Pdf = 'pdf';
    case HtmlDownload = 'html_download';
}
