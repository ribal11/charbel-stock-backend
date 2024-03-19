<?php

namespace App\Http\Controllers;

use App\Jobs\ImportExcelData;
use App\Models\tbl_items;
use FFI\Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use stdClass;
use Throwable;

class items extends Controller
{
    function insert(Request $req)
    {
        try {
            $valid = Validator::make($req->all(), $this->insertItemRules(), $this->globalMessages());
            if ($valid->fails()) {
                // dd($valid);
                return response($valid->messages()->first(), 400);
            }

            $item = new tbl_items();
            $item->stk_serno = $req->serialno;
            $item->stk_description = $req->description;
            $item->stk_qty = $req->quantity;
            $item->three_month_sale = $req->threeMonthSale;
            $item->six_month_sale = $req->sixMonthSale;
            $item->one_year_sale = $req->oneYearSale;
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

            // $item->stk_serno = $req->serialno;

            // $item->stk_description = $req->description;
            // $item->stk_qty = $req->quantity;
            // $item->three_month_sale = $req->threeMonthSale;
            // $item->six_month_sale = $req->sixMonthSale;
            // $item->one_year_sale = $req->yearSale;
            $item->minimum_stock_three_month = $req->minThreeMonth;
            $item->minimum_stock_six_month = $req->minSixMonth;
            $item->minimum_stock_year = $req->minYear;


            $item->save();



            return response($item, 200);
        } catch (Throwable  | Exception $ex) {
            return response('An error has occured.' . $ex->getMessage(), 400);
        }
    }

    function allowUpdate(Request $req)
    {
        try {
            $valid = Validator::make($req->all(), $this->updateRules(), $this->globalMessages());
            if ($valid->fails()) {
                return response($valid->messages()->first(), 400);
            }
            $item = tbl_items::where('stk_recid', $req->id)->first();
            $item->allow_edit = $req->allow;

            $item->minimum_stock_three_month = $req->val3;
            $item->minimum_stock_six_month = $req->val6;
            $item->minimum_stock_year = $req->valY;
            $item->save();
        } catch (Throwable | Exception $ex) {
            return response($ex->getMessage(), 400);
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
                $obj1->name = $obj->stk_description;
                $obj1->qty = $obj->stk_qty;
                $obj1->order = $obj->stk_ordered;
                $obj1->reserve = $obj->stock_reservation;
                $obj1->threeMonth = $obj->three_month_sale;
                $obj1->sixMonth = $obj->six_month_sale;
                $obj1->year = $obj->one_year_sale;
                $obj1->minThree = $obj->minimum_stock_three_month;
                $obj1->minSix = $obj->minimum_stock_six_month;
                $obj1->minYear = $obj->minimum_stock_year;
                $obj1->status = $obj->purchase_Status;
                $obj1->allowChange = $obj->allow_edit;
                $obj1->moq = $obj->moq;
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

    function updateAndAddDatabase(Request $req)
    {
        try {
            $valid = Validator::make($req->all(), [
                'file' => 'required|mimes:xls,xlsx'
            ])->messages();



            $file = $req->file('file');
            $filePath = $file->store('public'); // Store in 'temp' directory

            $data = Excel::toCollection([], $filePath);

            // Assuming the first row contains column headers, so we start from the second row

            $rows = json_decode($data[0]->slice(1), true);

            foreach ($rows as $row) {
                // Check if the row is an array and not empty
                if ($row[0] != null) {

                    $record = tbl_items::where('stk_serno', $row[0])->first();

                    if (!$record) {
                        $item = new tbl_items();
                        $item->stk_serno = $row[0] ?? null;
                        $item->stk_description = $row[1]; // Assuming 'item' is the first column
                        $item->stk_qty = $row[2] ?? null; // Assuming 'stock' is the second column
                        $item->three_month_sale = $row[3] ?? null; // Assuming 'sales 3 months' is the third column
                        $item->one_year_sale = $row[5] ?? null;
                        if ($row[4] == null) {
                            $item->six_month_sale = 0;
                        } else {
                            $item->six_month_sale = $row[4];
                        };
                        $item->save(); // Assuming 'Sales 1 Year' is the fourth column
                    } else {

                        $record->stk_qty = $row[2] ?? null; // Assuming 'stock' is the second column
                        $record->three_month_sale = $row[3] ?? null; // Assuming 'sales 3 months' is the third column
                        $record->one_year_sale = $row[5] ?? null;
                        if ($row[4] == null) {
                            $record->six_month_sale = 0;
                        } else {
                            $record->six_month_sale = $row[4];
                        };
                        // Assuming other fields exist in your Excel file and can be accessed similarly
                        // Add additional fields as needed

                        // Save the item
                        $record->save();
                    }
                }
            }
            sleep(0.5);

            if (Storage::exists($filePath)) {
                return response('ok');
                Storage::delete($filePath);
            } else {
                return response('does not exist');
            }
        } catch (Throwable | Exception $ex) {
            return response('An error has occurred: ' . $ex->getMessage(), 400);
        }
    }
    function updateDatabase(Request $req)
    {
        try {
            $valid = Validator::make($req->all(), [
                'file' => 'required|mimes:xls,xlsx'
            ])->messages();



            $file = $req->file('file');
            $filePath = $file->store('public'); // Store in 'temp' directory

            $data = Excel::toCollection([], $filePath);

            // Assuming the first row contains column headers, so we start from the second row
            $rows = json_decode($data[0]->slice(1), true);

            foreach ($rows as $row) {
                // Check if the row is an array and not empty
                if ($row[0] != null) {

                    $record = tbl_items::where('stk_serno', $row[0])->first();


                    if ($record) {
                        $record->stk_qty = $row[2] ?? null;
                        $record->three_month_sale = $row[3] ?? null;
                        $record->one_year_sale = $row[5] ?? null;
                        if ($row[4] == null) {
                            $record->six_month_sale = 0;
                        } else {
                            $record->six_month_sale = $row[4];
                        };
                    }

                    $record->save();
                }
            }
            sleep(0.5);

            if (Storage::exists($filePath)) {
                return response($rows);
                Storage::delete($filePath);
            } else {
                return response('does not exist');
            }
        } catch (Throwable | Exception $ex) {
            return response('An error has occurred: ' . $ex->getMessage(), 400);
        }
    }


    function updateQty(Request $req)
    {
        try {
            $valid = Validator::make($req->all(), ['id' => ['required'], $this->globalMessages()]);
            if ($valid->fails()) {
                return response($valid->messages()->first(), 400);
            }

            $item = tbl_items::where('stk_recid', $req->id)->first();
            if ($req->type == 'qty') {
                $item->stk_qty = $req->attr;
            } else if ($req->type == 'moq') {
                $item->moq = $req->attr;
            }
            $item->save();
        } catch (Throwable | Exception $ex) {
            return response('An error has occured' . $ex->getMessage(), 400);
        }
    }


    private function insertrules()
    {
        return [


            'minSixMonth' => ['string'],
            'minThreeMonth' => ['string'],
            'minYear' => ['string'],
        ];
    }

    private function updateRules()
    {
        return [
            'allow' => 'required',
            'id' => 'required'
        ];
    }

    private function globalMessages()
    { //used to validate or inputs by using attributes placeholders
        return [

            'required' => 'The :attribute field Is Required',
            'max.string' => 'The :attribute field must not be longer than :max characters.',
            'quantity.integer' => ':attribute Value Must Be > 0',
            'file' => '::attribute is not a excel',
            'allow' => '::attribute is required'
        ];
    }

    private function insertItemRules()
    {
        return [
            'serialno' => 'required|max:100',

            'description' => 'required|max:500',
            'quantity' =>   ['integer', 'gt:0'],
            'threeMonthSale' => ['integer'],
            'sixMonthSale' => ['integer'],
            'yearSale' => ['integer'],
        ];
    }
}
