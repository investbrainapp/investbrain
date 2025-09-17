<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Support\Arr;

trait HasLocalizedMarkdown
{
    public function localizedMarkdownPath($name)
    {
        $localName = preg_replace('#(\.md)$#i', '.'.app()->getLocale().'$1', $name);

        return Arr::first([
            resource_path('markdown/'.$localName),
            resource_path('markdown/'.$name),
        ], function ($path) {
            return file_exists($path);
        });
    }
}
