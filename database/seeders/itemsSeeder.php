<?php

namespace Database\Seeders;

use App\Models\tbl_items;
use DateTime;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class itemsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $csvData = fopen(base_path('database/data/data.csv'), 'r');
        while (($data = fgetcsv($csvData, 555, ',')) !== false) {

            $stk = new tbl_items();
            $stk->stk_serno = $data['0'] != null ? $data['0'] : 'N/A';
            $stk->stk_category =  $data['2'] != null ? $data['2'] : 'N/A';
            $stk->stk_description =  $data['3'] != null ? $data['3'] : 'N/A';
            $stk->stk_qty =  $data['1'] != null ? $data['1'] : 0;
            $stk->stk_supplier = 'N/A';

            $stk->save();
        }
        fclose($csvData);
    }
}
