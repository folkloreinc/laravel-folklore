<?php

namespace App\Http\Controllers;

use Folklore\Contracts\Services\CustomerIo;

class ExportController
{
    public function export()
    {
        return response()->csv(fn ($page) => [], 'export.csv');
    }

    public function open()
    {
        return response()->pixel();
    }

    public function subscribe(CustomerIo $customerIo)
    {
        return $customerIo->identify('1', []);
    }
}
