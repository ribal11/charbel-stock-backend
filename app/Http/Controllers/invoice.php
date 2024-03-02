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
use Symfony\Component\VarDumper\VarDumper;
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
            $deletedEntries = [];

            if (!$id) {
                $invH = new tbl_invoiceheader();


                $invH->inh_client = $req->client;

                $invH->inh_type = $req->type;
                $invH->inh_date =  $req->date;

                $invH->inh_dstmp = $now->format("Y-m-d H:i:s");
            } else {
                $invH = tbl_invoiceheader::where('inh_recid', $req->id)->first();
                $invH->inh_client = $req->client;
                $invH->inh_type = $req->type;
                $invH->inh_date =  $req->date;

                $invH->inh_dstmp = $now->format("Y-m-d H:i:s");

                $pvsInvoice = tbl_invoicedetails::where('ind_hid', $req->id)->get();
                $pvsInvoice = collect($pvsInvoice);
            }

            if ($pvsInvoice && $pvsInvoice->count() > 0) {
                foreach ($pvsInvoice as $k => $v) {
                    if ($summarizedQtys->filter(function ($row) use ($v) {
                        return $v['ind_stkid'] === $row['itemid'];
                    })->count() === 0) {
                        $deletedEntries[] = ["itemid" => $v['ind_stkid'], "qty" => $v['ind_qty']];
                    }
                }
            }



            foreach ($summarizedQtys as $k => $v) {
                $invD = new tbl_invoicedetails();
                $invD->ind_stkid = $v['itemid'];
                $invD->ind_qty = $v['qty'];
                $invD->ind_dstmp = $now->format("Y-m-d H:i:s");
                if ($pvsInvoice && $pvsInvoice->count() > 0) {
                    $pvsItem = $pvsInvoice->filter(function ($row) use ($v) {
                        return $row['ind_stkid'] === $v['itemid'];
                    });

                    $invDArr[] = ['detail' => $invD, 'previousQty' => count($pvsItem) > 0 ? $pvsItem[0]['ind_qty'] : 0];
                } else {
                    $invDArr[] = ['detail' => $invD, 'previousQty' => 0];
                }
            }



            DB::transaction(
                function () use ($invH, $invDArr, $deletedEntries) {
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
                    if (count($deletedEntries) > 0) {
                        foreach ($deletedEntries as $k => $v) {
                            $itemObj = tbl_items::where('stk_recid', $v['itemid'])->first();
                            if ($invH->inh_type === 'S') {
                                $itemObj->stk_qty = $itemObj->stk_qty + $v['qty'];
                            } else {
                                $itemObj->stk_qty = $itemObj->stk_qty - $v['qty'];
                            }
                            $itemObj->save();
                        }
                    }
                }
            );

            return response("Saved Successfully", 200);

            // return response($item, 200);
        } catch (Throwable | Exception $ex) {
            return response('An error has occurred in '  . $ex->getMessage(), 400);
        }
    }

    function upsertUpdate(Request $req)
    {
        try {
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
            // get the qty with the stock_id of the item
            $summarizedQtys = collect($req->items)->reduce(function ($carry, $item) {
                $itemId = $item['itemid'];
                $qty = $item['qty'];

                if (!array_key_exists($itemId, $carry)) {
                    $carry[$itemId] = 0;
                }
                $carry[$itemId] += $qty;
                return $carry;
            }, []);
            //transform it into an array that begin 0...
            $summarizedQtys = collect($summarizedQtys)->map(function ($val, $key) {
                return ["itemid" => intval($key), "qty" => floatval($val)];
            })->values();

            //get the invH
            $invH = tbl_invoiceheader::where('inh_recid', $req->id)->first();
            //here the invoice Detail
            $invDArr = [];
            $now = new DateTime();
            //the previous invoice since this is an already done invH
            $pvsInvoice = null;
            //the invoices that were in the previous but not now
            $deletedEntries = [];

            $invH->inh_client = $req->client;
            $invH->inh_type = $req->type;
            $invH->inh_date =  $req->date;

            $invH->inh_dstmp = $now->format("Y-m-d H:i:s");

            $pvsInvoice = tbl_invoicedetails::select('*')->where('ind_hid', (int)$req->id)->get();

            $pvsInvoice = collect($pvsInvoice);

            //here we put the data in the deleteEntries those that are no longer in the invoice
            foreach ($pvsInvoice as $key => $value) {
                if ($summarizedQtys->filter(function ($row) use ($value) {
                    return $row['itemid'] == $value['ind_stkid'];
                })->count() == 0) {
                    $deletedEntries[] = ['itemid' => $value['ind_stkid'], 'qty' => $value['ind_qty']];
                }
            };

            //here we are usining hte invDarr we see if this item was alreasy in the invoice if it was we put in the pvsQty inde its previous qty if nt we put 0
            //these are all the new data 
            foreach ($summarizedQtys as $key => $value) {
                $invD = new tbl_invoicedetails();
                $invD->ind_stkid = $value['itemid'];
                $invD->ind_qty = $value['qty'];
                $invD->ind_dstmp = $now->format("Y-m-d H:i:s");
                $invItem = [];
                $invItem = $pvsInvoice->filter(function ($item) use ($value) {
                    return $item['ind_stkid'] == $value['itemid'];
                });
                if ($invItem->count() > 0) {
                    $firstItem = $invItem->first();
                    $invDArr[] = ['detail' => $invD, 'pvsQty' => $firstItem['ind_qty']];
                } else {
                    $invDArr[] = ['detail' => $invD, 'pvsQty' => 0];
                }
            }
            //here is where will change in the database
            DB::transaction(function () use ($invH, $invDArr, $deletedEntries) {

                //here we save this invH with the new data we put int it ig hte client name the date of today ...
                $invH->save();

                //we get all the previous invoice getails that belonged to this one since we are going to put them again with the new data
                tbl_invoicedetails::where('ind_hid', $invH->inh_recid)->delete();

                //here we are putting the data of $invDarr in the database and adding to the $itemD the id of the header
                //we get the item in the tbl_items that have the same id of this itteration of $invDarr
                //we remove from this item the previous qty of the detail and than we add the new one
                foreach ($invDArr as $k => $v) {
                    $itemD = $v['detail'];
                    $itemD->ind_hid = $invH->inh_recid;
                    $pvsQty = $v['pvsQty'];
                    $itemObj = tbl_items::where('stk_recid', $itemD->ind_stkid)->first();
                    if ($itemD)
                        $itemObj->stk_qty = $itemObj->stk_qty - $pvsQty + $itemD->ind_qty;
                    $itemObj->save();
                    $itemD->save();
                }
                if (count($deletedEntries) > 0) {
                    foreach ($deletedEntries as $k => $v) {
                        $item = tbl_items::where('stk_recid', $v['itemid'])->first();
                        $item->stk_qty = $item->stk_qty - $v['qty'];
                        $item->save();
                    }
                }
            });
            return response('success', 200);
        } catch (Throwable | Exception $ex) {
            return response('An error has occurred in '  . $ex->getMessage(), 400);
        }
    }
    function getInvoices(Request $req)
    {
        try {
            // return $req;

            $startDate = $req->strD ? $req->strD : (new DateTime())->format("Y-m-d");
            $endDate = $req->endD ? $req->endD : (new DateTime())->format("Y-m-d");

            $type = $req->type;



            $startDate = Carbon::createFromFormat('Y-m-d', $startDate);
            $endDate = Carbon::createFromFormat('Y-m-d', $endDate);

            $data = tbl_invoiceheader::select("*")
                ->whereBetween('inh_date', [$startDate, $endDate])
                ->where('inh_type', DB::raw($type))
                ->where('finished', '=', '0');



            // return  [$data->toSql(), $data->getBindings()];


            // return [$data->toSql(), $data->getBindings()];
            $data = $data->get();


            $coll = collect($data);

            // return $data;


            $data = $coll->map(function (string $val) {
                $obj = json_decode($val);
                $obj1 = new stdClass();

                $invdt = Carbon::parse($obj->inh_date);

                $obj1->id = $obj->inh_recid;
                $obj1->date = $invdt->format('Y-m-d');;
                $obj1->name = $obj->inh_client;
                $obj1->state = $obj->finished;
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

    function deleteInvoice(Request $req)
    {
        try {
            $valid = Validator::make($req->all(), $this->deleteInvoicerules(), $this->globalMessages());
            if ($valid->fails()) {
                return response($valid->messages()->first(), 400);
            }

            $id = $req->id;
            $invDeletedDet = tbl_invoicedetails::where('ind_hid', $id)->get();
            $invDeletedDet = collect($invDeletedDet);
            DB::transaction(
                function () use ($id, $invDeletedDet, $req) {
                    tbl_invoicedetails::where('ind_hid', $id)->delete();
                    tbl_invoiceheader::where('inh_recid', $id)->delete();

                    if ($invDeletedDet->count() > 0) {
                        foreach ($invDeletedDet as $k => $v) {
                            $item = tbl_items::where('stk_recid', $v['ind_stkid'])->first();
                            $qty = $v['ind_qty'];

                            if ($req->type === 'S') {
                                $item->stk_qty += $qty;
                            } else {
                                $item->stk_qty -= $qty;
                            }
                            $item->save();
                        }
                    }
                }
            );
            return response('Invoice deleted successfully', 200);
        } catch (Throwable | Exception $ex) {
            return response('An error has occured' . $ex->getMessage(), 400);
        }
    }

    function updateHeader(Request $req)
    {
        try {


            $id = $req->Hid;
            $state = $req->Hstate;

            $header = tbl_invoiceheader::where('inh_recid', $id)->first();

            DB::transaction(function () use ($state, $header) {
                $header->finished = $state;
                $header->save();
            });
            return response('Invoice updated successfully', 200);
        } catch (Throwable | Exception $ex) {
            return response('An error has occured' . $ex->getMessage(), 400);
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

    private function deleteInvoicerules()
    {
        return [
            'type' => ['required', Rule::in(['S', 'P'])],
            'id' => ['required', 'integer', 'gt:0']
        ];
    }

    private function updateHeaderRules()
    {
        return [
            'id' => ['required', 'integer', 'gt:0'],
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
