<?php

namespace App\Http\Controllers;

use App\Models\tbl_invoicedetails;
use App\Models\tbl_invoiceheader;
use App\Models\tbl_items;
use DateTime;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use stdClass;
use Throwable;

class invoice extends Controller
{
    function upsert(Request $req)
    {
        try {
            // return $req->all();
            $valid = Validator::make($req->all(), $this->insertrules(), $this->globalMessages());
            if ($valid->fails()) {
                // dd($valid);
                return response($valid->messages()->first(), 400);
            }

            foreach ($req->items as $k => $v) {


                $valid = Validator::make($v, $this->itemdetailsrules(), $this->globalMessages());

                if ($valid->fails()) {
                    return response($valid->messages()->first(), 400);
                }
            }

            //Reduce the array to make sure that no item is repeated multiple times.
            //So reduce the array
            $summarizedQtys = collect($req->items)->reduce(function ($carry, $item) {
                // dd($item);
                $itemId = $item['itemid'];
                $quantity = $item['qty'];


                if (!array_key_exists($itemId, $carry)) {
                    $carry[$itemId] = 0;
                }

                $carry[$itemId] += $quantity;

                return $carry;
            }, []);
            // return json_encode($summarizedQtys);


            $summarizedQtys = collect($summarizedQtys)->map(function ($val, $key) {
                return ["itemid" => intval($key), "qty" => floatval($val)];
            })->values(); // or array_values($summarizedQtys);


            if ($req->type === 'S') {
                foreach ($summarizedQtys as $k => $v) {
                    //Sales Invoice Check If Quantity is available. In case of update to old invoice,
                    //extract previous quantity from computation.
                    $itemObj = tbl_items::where('stk_recid', $v['itemid'])->first();
                    $invObj = tbl_invoicedetails::where([['ind_hid', '=', $req->id ? $req->id : -1], ['ind_stkid', '=', $v['itemid']]])->first();

                    if ($itemObj->stk_qty + ($invObj ? $invObj->ind_qty : 0)  < $v['qty']) {
                        return response('Requested Quantity For Item ' . $itemObj->stk_description .  ' Exceeds Available Quantity', 400);
                    }
                }
            }


            $id = $req->id;
            $invH = null;
            $invDArr = [];
            $now = new DateTime();
            $pvsInvoice = null;
            if (!$id) {
                $invH = new tbl_invoiceheader();


                $invH->inh_client = $req->client;
                $invH->inh_type = $req->type;
                $invH->inh_date =  $req->date;
                $invH->inh_remarks = $req->remark;
                $invH->inh_dstmp = $now->format("Y-m-d H:i:s");
            } else {
                $invH = tbl_invoiceheader::where('inh_recid', $req->id)->first();
                $invH->inh_client = $req->client;
                $invH->inh_type = $req->type;
                $invH->inh_date =  $req->date;
                $invH->inh_remarks = $req->remark;
                $invH->inh_dstmp = $now->format("Y-m-d H:i:s");
                $pvsInvoice = tbl_invoicedetails::where('ind_hid', $req->id)->get();
                $pvsInvoice = collect($pvsInvoice);
            }
            foreach ($summarizedQtys as $k => $v) {
                $invD = new tbl_invoicedetails();
                $invD->ind_stkid = $v['itemid'];
                $invD->ind_qty = $v['qty'];
                $invD->ind_dstmp = $now->format("Y-m-d H:i:s");
                if ($pvsInvoice->count() > 0) {
                    $pvsItem = $pvsInvoice->filter(function ($row) use ($v) {
                        return $row['ind_stkid'] === $v['itemid'];
                    });

                    $invDArr[] = ['detail' => $invD, 'previousQty' => count($pvsItem) > 0 ? $pvsItem[0]['ind_qty'] : 0];
                } else {
                    $invDArr[] = ['detail' => $invD, 'previousQty' => 0];
                }
            }



            DB::transaction(
                function () use ($invH, $invDArr) {
                    $invH->save();
                    if ($invH->inh_recid) {
                        tbl_invoicedetails::where('ind_hid', $invH->inh_recid)->delete();
                    }

                    foreach ($invDArr as $k => $v) {
                        $detailObj = $v['detail'];
                        $pvsqty = $v['previousQty'];
                        $detailObj->ind_hid = $invH->inh_recid;

                        $itemObj = tbl_items::where('stk_recid', $detailObj->ind_stkid)->first();
                        if ($invH->inh_type === 'S') {
                            $itemObj->stk_qty =  $itemObj->stk_qty +  $pvsqty - $detailObj->ind_qty;
                        } else {
                            $itemObj->stk_qty =  $itemObj->stk_qty -  $pvsqty + $detailObj->ind_qty;
                        }
                        $itemObj->save();
                        $detailObj->save();
                    }
                }
            );

            return response("Saved Successfully", 200);

            // return response($item, 200);
        } catch (Throwable  | Exception $ex) {
            return response('An error has occured.' . $ex->getMessage(), 400);
        }
    }

    function getInvoices(Request $req)
    {
        try {
            // return $req;

            $startDate = $req->strD ? $req->strD : (new DateTime())->format("Y-m-d");
            $endDate = $req->endD ? $req->endD : (new DateTime())->format("Y-m-d");
            $type = $req->type;



            $startDate = Carbon::createFromFormat('Y-m-d', $startDate)->addYears(-1);
            $endDate = Carbon::createFromFormat('Y-m-d', $endDate);

            $data = tbl_invoiceheader::select("*")
                ->whereBetween('inh_date', [$startDate, $endDate])
                ->where('inh_type', DB::raw($type));

            // return  [$data->toSql(), $data->getBindings()];


            // return [$data->toSql(), $data->getBindings()];
            $data = $data->get();


            $coll = collect($data);
            // return $data;
            // var_dump($coll);

            $data = $coll->map(function (string $val) {
                $obj = json_decode($val);
                $obj1 = new stdClass();

                $invdt = Carbon::parse($obj->inh_date);

                $obj1->id = $obj->inh_recid;
                $obj1->date = $invdt->format('Y-m-d');;
                $obj1->name = $obj->inh_client;
                return $obj1;
            });
            sleep(0.5);
            return $data;

            // return $data->toSql();
        } catch (Throwable  | Exception $ex) {
            return response('An error has occured.' . $ex->getMessage(), 400);
        }
    }



    function getDetails(Request $req)
    {
        try {
            $valid = Validator::make($req->all(), ['invid' => ['required']], $this->globalMessages());
            if ($valid->fails()) {
                // dd($valid);
                return response($valid->messages()->first(), 400);
            }

            $invid = $req->invid;

            $invhead = tbl_invoiceheader::select("*")->where('inh_recid', $invid)->first();


            $data = tbl_invoiceheader::select("*")
                ->join('tbl_invoicedetails', 'inh_recid', 'ind_hid')
                ->join('tbl_items', 'stk_recid', 'ind_stkid')
                ->where('inh_recid', $invid);


            // return [$data->toSql(), $data->getBindings()];
            $data = $data->get();

            $coll = collect($data);
            // return $data;
            // var_dump($coll);


            $header = collect([$invhead])->map(function (string $val) {
                $obj = json_decode($val);
                $obj1 = new stdClass();
                $dt = DateTime::createFromFormat('Y-m-d H:i:s', $obj->inh_date);
                $obj1->client = $obj->inh_client;
                $obj1->date = $dt->format('Y-m-d');
                $obj1->remark = $obj->inh_remarks;
                return $obj1;
            });

            $details = $coll->map(function (string $val) {
                $obj = json_decode($val);
                $obj1 = new stdClass();
                $obj1->id = $obj->stk_recid;
                $obj1->serno = $obj->stk_serno;
                $obj1->cat = $obj->stk_category;
                $obj1->name = $obj->stk_description;
                $obj1->qty = intval($obj->ind_qty);
                $obj1->supp = $obj->stk_supplier;
                return $obj1;
            });

            sleep(0.5);



            return ['header' =>  $header[0], 'details' => $details];

            // return $data->toSql();
        } catch (Throwable  | Exception $ex) {
            return response('An error has occured.' . $ex->getMessage(), 400);
        }
    }

    //
    private function insertrules()
    {
        return [
            'id' => ['sometimes', 'integer', 'gt:0'],

            'client' => 'required|max:500',
            'date' =>  ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/'],
            'type' =>    ['required', Rule::in(['S', 'P'])],
            'items' => ['required', 'array']
        ];
    }

    private function itemdetailsrules()
    {
        return [
            'itemid' => ['required', 'integer', 'gt:0'],
            'qty' =>  ['required', 'decimal:0,2', 'gt:0'],

        ];
    }

    private function globalMessages()
    { //used to validate or inputs by using attributes placeholders
        return [

            'required' => 'The :attribute field Is Required',
            'max.string' => 'The :attribute field must not be longer than :max characters.',
            'id.integer' => ':attribute Value Must Be > 0',
            'qty.decimal' => ':attribute Value Must Be > 0',
            'regex.date' => 'The :attribute field must have the following format : "yyyy-mm-dd"',
            'type.in' => 'The :attribute field must be "P" or "S"',
        ];
    }
}
