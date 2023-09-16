<?php

namespace App\Http\Controllers;


use App\Models\tbl_users;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Throwable;

class login extends Controller
{
    function login(Request $req)
    {
        try {

            sleep(0.5);
            $valid = Validator::make($req->all(), $this->insertrules(), $this->globalMessages());
            if ($valid->fails()) {
                // dd($valid);
                return response($valid->messages()->first(), 400);
            }



            $user = tbl_users::where('user_code', $req->code)->first();

            if ($user == null ||  $user->user_password != $req->password) {
                return response('Invalid Login', 401);
            } else {
                return response(['code' => $user->user_code, 'name' => $user->user_name], 200);
            }
        } catch (Throwable  | Exception $ex) {
            return response('An error has occured.' . $ex->getMessage(), 400);
        }
    }


    private function insertrules()
    {
        return [
            'code' => 'required',
            'password' => 'string|required',

        ];
    }

    private function logoutrules()
    {
        return [
            'code' => 'required',
            'token' => 'required'
        ];
    }
    private function globalMessages()
    { //used to validate or inputs by using attributes placeholders
        return [
            'required' => 'The :attribute field Is Required',
        ];
    }
}
