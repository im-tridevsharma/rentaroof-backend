<?php

namespace App\Imports;

use App\Models\Address;
use App\Models\Property;
use App\Models\PropertyEssential;
use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Tymon\JWTAuth\Facades\JWTAuth;

class PropertyImport implements ToCollection, WithHeadingRow
{
    /**
     * @param Collection $collection
     */
    public function collection(Collection $collection)
    {
        $rent_a_roof = User::where("role", "landlord")
            ->where("username", "rentaroof")->first();
        $rent_a_roof = $rent_a_roof->id ?? 0;

        foreach ($collection as $row) {
            $property = new Property;
            $property->property_code = 'RARP-0' . rand(11111, 99999) . '0';
            $property->name = $row['name'] ?? '';
            $property->short_description = $row['short_description'] ?? '';
            $property->description = $row['description'] ?? '';
            $property->for = 'rent';
            $property->type = $row['type'] ?? "";
            $property->posting_as = $row['posting_as'] ?? NULL;
            $property->bedrooms = $row['bedrooms'] ?? 1;
            $property->balconies = $row['balconies'] ?? 1;
            $property->floors = $row['floors'] ?? 0;
            $property->furnished_status = $row['furnished_status'] ?? NULL;
            $property->ownership_type = $row['ownership_type'] ?? NULL;
            $property->bathrooms = $row['bathrooms'] ?? 1;
            $property->carpet_area = $row['carpet_area'] ?? 0;
            $property->carpet_area_unit = $row['carpet_area_unit'] ?? 'sqft';
            $property->super_area = $row['super_area'] ?? 0;
            $property->super_area_unit = $row['super_area_unit'] ?? 'sqft';
            $property->available_from = $row['available_from'] ? date('Y-m-d', strtotime($row['available_from'])) : NULL;
            $property->available_immediately = $row['available_immediately'] ?? 0;
            $property->age_of_construction = $row['age_of_construction'] ?? '';
            $property->monthly_rent = $row['monthly_rent'] ?? 0;
            $property->security_amount = $row['security_amount'] ?? 0;
            $property->maintenence_charge = $row['maintenence_charge'] ?? 0;
            $property->maintenence_duration = $row['maintenence_duration'] ?? NULL;
            $property->offered_price = $row['offered_price'] ?? $row['monthly_rent'];
            $property->front_image = $row['front_image'] ?? '';
            $property->landlord = $row['landlord_id'] ?? $rent_a_roof;
            $property->posted_by = $row['landlord_id'] ?? $rent_a_roof;


            $property->save();

            //address save

            $address = new Address;
            $address->property_id = $property->id;
            $address->address_type = 'property';
            $address->landmark = $row['landmark'] ?? '';
            $address->house_number = $row['house_number'] ?? '';
            $address->full_address = $row['full_address'] ?? '';
            $address->state = $row['state'] ?? '';
            $address->city = $row['city'] ?? '';
            $address->pincode = $row['pincode'] ?? '';
            $address->country = 'India';
            $address->zone = $row['zone'] ?? '';
            $address->area = $row['area'] ?? '';
            $address->sub_area = $row['sub_area'] ?? '';

            $address->save();

            //essential
            $essential = new PropertyEssential;
            $essential->property_id = $property->id;
            $essential->school = $row['school'] ?? '';
            $essential->metro = $row['metro'] ?? '';
            $essential->hospital = $row['hospital'] ?? '';
            $essential->bus_stop = $row['bus_stop'] ?? '';
            $essential->airport = $row['airport'] ?? '';
            $essential->train = $row['train'] ?? '';
            $essential->market = $row['market'] ?? '';
            $essential->restaurent = $row['restaurent'] ?? '';

            $essential->save();

            //update address id
            $property->address_id = $address->id;
            $property->property_essential_id = $essential->id;
            $property->save();
        }
    }
}
