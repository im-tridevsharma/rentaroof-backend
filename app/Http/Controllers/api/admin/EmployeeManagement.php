<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Models\Employee;
use App\Models\Role;
use Illuminate\Support\Facades\Storage;

class EmployeeManagement extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $employees = Employee::all();
        foreach ($employees as $emp) {
            $role = Role::find($emp->role);
            $emp->role = isset($role->title) ? $role->title : '';
        }

        if ($employees) {
            return response([
                'status'    => true,
                'message'   => 'Employees fetched successfully.',
                'data'      => $employees
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Something went wrong!'
        ], 500);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|between:2,50',
            'email'     => 'required|email|unique:employees',
            'mobile'    => 'required|digits_between:10,12|unique:employees',
            'gender'    => 'required|in:male,female,other',
            'role'      => 'required|string',
            'password'  => 'required|min:6',
            'profile_pic' => 'mimes:png,jpg,jpeg'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some errors occured.',
                'error'     => $validator->errors()
            ], 400);
        }

        $profile_pic_url = '';
        if ($request->hasFile('profile_pic')) {
            $upload_dir = "/uploads/employees/profile_pic";
            $name = Storage::disk('digitalocean')->put($upload_dir, $request->file('profile_pic'), 'public');
            $profile_pic_url = Storage::disk('digitalocean')->url($name);
        }

        $employee = new Employee;
        $employee->role = $request->role;
        $employee->permissions = json_encode([]);
        $employee->name = $request->name;
        $employee->email = $request->email;
        $employee->mobile = $request->mobile;
        $employee->gender = $request->gender;
        $employee->password = bcrypt($request->password);
        $employee->profile_pic = $profile_pic_url;
        $employee->system_ip = $request->ip();

        if ($employee->save()) {
            $address = new Address;
            $address->employee_id = $employee->id;
            $address->landmark = isset($request->landmark) ? $request->landmark : '';
            $address->house_number = isset($request->houseno) ? $request->houseno : '';
            $address->country = $request->country;
            $address->state = $request->state;
            $address->city = $request->city;
            $address->pincode = isset($request->pincode) ? $request->pincode : '';
            $address->full_address = isset($request->fulladdress) ? $request->fulladdress : '';

            if ($address->save()) {
                $employee->address_id = $address->id;
                $employee->save();
            }

            return response([
                'status'    => true,
                'message'   => 'New Employee added successfully.',
                'employee'      => $employee
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Something went wrong!'
        ], 500);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $employee = Employee::find($id);

        if ($employee) {
            return response([
                'status'  => true,
                'message' => 'Employee fetched successfully.',
                'data'    => $employee->load('address')
            ], 200);
        }

        return response([
            'status'  => false,
            'message' => 'Employee not found.'
        ], 404);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name'      => 'required|string|between:2,50',
            'email'     => 'required|email',
            'mobile'    => 'required|digits_between:10,12',
            'gender'    => 'required|in:male,female,other',
            'role'      => 'required|string',
            'password'  => 'min:6',
            'profile_pic' => 'mimes:png,jpg,jpeg'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some errors occured.',
                'error'     => $validator->errors()
            ], 400);
        }

        $employee = Employee::find($id);

        $profile_pic_url = '';
        if ($request->hasFile('profile_pic')) {
            $upload_dir = "/uploads/employees/profile_pic";
            $name = Storage::disk('digitalocean')->put($upload_dir, $request->file('profile_pic'), 'public');
            $profile_pic_url = Storage::disk('digitalocean')->url($name);

            //remove old image
            $oldimg = $employee->profile_pic;
            if (Storage::disk('digitalocean')->exists($upload_dir . '/' . basename($oldimg))) {
                Storage::disk('digitalocean')->delete($upload_dir . '/' . basename($oldimg));
            }
        }

        if ($employee) {
            $employee->name = $request->name;
            $employee->email = $request->email;
            $employee->mobile = $request->mobile;
            $employee->gender = $request->gender;
            $employee->password = bcrypt($request->password);
            $employee->profile_pic = !empty($profile_pic_url) ? $profile_pic_url : $employee->profile_pic;
            $employee->system_ip = $request->ip();

            if ($employee->save()) {
                $address = Address::find($employee->address_id);
                if ($address) {
                    $address->employee_id = $employee->id;
                    $address->landmark = isset($request->landmark) ? $request->landmark : '';
                    $address->house_number = isset($request->houseno) ? $request->houseno : '';
                    $address->country = $request->country;
                    $address->state = $request->state;
                    $address->city = $request->city;
                    $address->pincode = isset($request->pincode) ? $request->pincode : '';
                    $address->full_address = isset($request->fulladdress) ? $request->fulladdress : '';

                    $address->save();

                    return response([
                        'status'    => true,
                        'message'   => 'Employee\'s information updated successfully.',
                        'user'      => $employee
                    ], 200);
                }
            }
        }

        return response([
            'status'    => false,
            'message'   => 'Employee not found!'
        ], 404);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $employee = Employee::find($id);
        if ($employee) {
            //remove files from the server
            $upload_dir = "/uploads/employees/profile_pic";
            $oldimg = $employee->profile_pic;
            if (Storage::disk('digitalocean')->exists($upload_dir . '/' . basename($oldimg))) {
                Storage::disk('digitalocean')->delete($upload_dir . '/' . basename($oldimg));
            }
            $employee->delete();

            //remove address if not removed automatically
            $address = Address::find($employee->address_id);
            if ($address) {
                $address->delete();
            }

            return response([
                'status'  => true,
                'message' => 'Employee deleted successfully.',
                'data'    => $employee
            ], 200);
        }

        return response([
            'status'  => false,
            'message' => 'Employee not found.'
        ], 404);
    }
}
