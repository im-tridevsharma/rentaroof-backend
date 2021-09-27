<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Models\Role;

class RoleManagement extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $roles = Role::all();
        if ($roles) {
            return response([
                'status'    => true,
                'message'   => 'Roles fetched successfully.',
                'data'      => $roles
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
            'title'         => 'required|string|between:2,50',
            'permissions'   => 'required|array|min:1'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some errors occured.',
                'error'     => $validator->errors()
            ], 400);
        }

        $role = new Role;
        $role->title = $request->title;
        $role->description = isset($request->description) ? $request->description : '';
        $role->permissions = json_encode($request->permissions);

        if ($role->save()) {
            return response([
                'status'    => true,
                'message'   => 'New Role added successfully.',
                'data'      => $role
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Something went wrong.'
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
        $role = Role::find($id);

        if ($role) {
            $role->permissions = json_decode($role->permissions);
            return response([
                'status'    => true,
                'message'   => 'Role fetched successfully.',
                'data'      => $role
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Role not found.'
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
            'title'         => 'required|string|between:2,50',
            'permissions'   => 'required|array|min:1'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some errors occured.',
                'error'     => $validator->errors()
            ], 400);
        }

        $role = Role::find($id);
        if ($role) {
            $role->title = $request->title;
            $role->description = isset($request->description) ? $request->description : '';
            $role->permissions = json_encode($request->permissions);

            if ($role->save()) {
                return response([
                    'status'    => true,
                    'message'   => 'Role updated successfully.',
                    'data'      => $role
                ], 200);
            }

            return response([
                'status'    => false,
                'message'   => 'Something went wrong.'
            ], 500);
        }

        return response([
            'status'    => false,
            'message'   => 'Role not found.'
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
        $role = Role::find($id);

        if ($role) {

            $is_role_assigned = Employee::where("role", $id)->count();
            if ($is_role_assigned > 0) {
                return response([
                    'status'    => false,
                    'message'   => 'Role is assigned to an employee. Please remove that first!'
                ], 400);
            }

            if ($role->delete()) {
                return response([
                    'status'    => true,
                    'message'   => 'Role deleted successfully.',
                    'data'      => $role
                ], 200);
            }

            return response([
                'status'    => false,
                'message'   => 'Something went wrong.'
            ], 500);
        }

        return response([
            'status'    => false,
            'message'   => 'Role not found.'
        ], 404);
    }
}
