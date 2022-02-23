<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Imports\PropertyImport;
use Exception;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class BulkPropertyImport extends Controller
{
    //import properties from excel file
    public function importFromExcel(Request $request)
    {
        if ($request->has('import_file') && $request->file('import_file')) {
            try {
                Excel::import(new PropertyImport, $request->file('import_file'));

                return response([
                    'status'    => true,
                    'message'   => 'Properties imported successfully.'
                ], 200);
            } catch (Exception $e) {
                return response([
                    'status'    => false,
                    'message'   => $e->getMessage()
                ], 500);
            }
        } else {
            return response([
                'status'    => false,
                'message'   => 'File not found!'
            ], 400);
        }
    }
}
