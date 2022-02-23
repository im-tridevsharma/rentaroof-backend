<?php

namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class LandlordsExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return User::where("role", "landlord")->get();
    }

    public function map($row): array
    {
        return [
            $row->id, $row->system_userid,
            $row->first, $row->last, $row->username, $row->email, $row->mobile, $row->dob,
            $row->gender, $row->account_status, $row->deactivate_reason, $row->referral_code
        ];
    }

    public function headings(): array
    {
        return [
            'id', 'landlord_id',
            'first', 'last', 'username', 'email', 'mobile', 'dob',
            'gender', 'account_status', 'deactivate_reason', 'referral_code'
        ];
    }
}
