<?php

namespace App\Http\Controllers;

use App\Models\tbl_items;
use FFI\Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use stdClass;
use Throwable;

class items extends Controller
{
    function insert(Request $req)
    {
        try {
            $valid = Validator::make($req->all(), $this->insertrules(), $this->globalMessages());
            if ($valid->fails()) {
                // dd($valid);
                return response($valid->messages()->first(), 400);
            }

            $item = new tbl_items();
            $item->stk_serno = $req->serialno;
            $item->stk_category = $req->category;
            $item->stk_description = $req->description;
            $item->stk_qty = $req->quantity;
            $item->stk_supplier = $req->supplier;

            $item->save();



            return response($item, 200);
        } catch (Throwable  | Exception $ex) {
            return response('An error has occured.' . $ex->getMessage(), 400);
        }
    }

    function update(Request $req)
    {
        try {
            $valid = Validator::make($req->all(), $this->insertrules() + ['id' => ['required']], $this->globalMessages());
            if ($valid->fails()) {
                // dd($valid);
                return response($valid->messages()->first(), 400);
            }

            $item = tbl_items::where('stk_recid', $req->id)->first();

            $item->stk_serno = $req->serialno;
            $item->stk_category = $req->category;
            $item->stk_description = $req->description;
            $item->stk_qty = $req->quantity;
            $item->stk_supplier = $req->supplier;

            $item->save();



            return response($item, 200);
        } catch (Throwable  | Exception $ex) {
            return response('An error has occured.' . $ex->getMessage(), 400);
        }
    }

    function get(Request $req)
    {
        try {

            $item = $req->itemid;
            $minqty = $req->minqty;

            $data = tbl_items::select('*');

            if ($item != null && $item != '') {
                $data = $data->where('tbl_items.stk_recid', '=', $item);
            }
            if ($minqty !== null) {
                $data = $data->where('tbl_items.stk_qty', '<', $minqty);
            }

            // return $data->toSql();
            $data = $data->get();

            $coll = collect($data);

            $data = $coll->map(function (string $val) {
                $obj = json_decode($val);
                $obj1 = new stdClass();
                $obj1->id = $obj->stk_recid;
                $obj1->serno = $obj->stk_serno;
                $obj1->cat = $obj->stk_category;
                $obj1->name = $obj->stk_description;
                $obj1->qty = $obj->stk_qty;
                $obj1->supp = $obj->stk_supplier;
                return $obj1;
            });
            return $data;
        } catch (Throwable  | Exception $ex) {
            return response('An error has occured.' . $ex->getMessage(), 400);
        }
    }

    function delete(Request $req)
    {
        try {
            $valid = Validator::make($req->all(),  ['id' => ['required']], $this->globalMessages());
            if ($valid->fails()) {
                // dd($valid);
                return response($valid->messages()->first(), 400);
            }

            tbl_items::where('stk_recid', $req->id)->first()->delete();


            return response('Deleted Successfully', 200);
        } catch (Throwable  | Exception $ex) {
            return response('An error has occured.' . $ex->getMessage(), 400);
        }
    }


    private function insertrules()
    {
        return [

            'serialno' => 'required|max:100',
            'category' => 'required|max:100',
            'description' => 'required|max:500',
            'quantity' =>   ['integer', 'gt:0'],
            'supplier' => 'required|max:200',
        ];
    }

    private function globalMessages()
    { //used to validate or inputs by using attributes placeholders
        return [

            'required' => 'The :attribute field Is Required',
            'max.string' => 'The :attribute field must not be longer than :max characters.',
            'quantity.integer' => ':attribute Value Must Be > 0',
        ];
    }
}
