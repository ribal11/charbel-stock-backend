<?php

namespace App\Jobs;

use App\Models\tbl_items;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;

class ImportExcelData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */

    protected $filePath;
    public function __construct($filePath)
    {
        $this->filePath = $filePath;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $data = Excel::toCollection([], $this->filePath);

        // Skip the first two rows and start iterating from the third row
        $rows = $data[0]->slice(2);

        foreach ($rows as $row) {
            // Check if 'qty' field is not empty
            if (!empty($row['item'])) {

                $record = tbl_items::where('stk_serno', $row['item'])->first();

                if (!$record) {
                    $item = new tbl_items();
                    $item->stk_serno = $row['item'];
                    $item->qty = $row['stock'];
                    $item->three_month_sale = $row['sales 3 months'];
                    $item->one_year_sale = $row['Sales 1 Year'];

                    $item->stk_description = $row['Description'];

                    if (!empty($row['6 Months Sales'])) {
                        $item->six_month_sale = $row['6 Months Sales'];
                    } else {
                        $item->six_month_sale = 0;
                    }
                    $item->save();
                }

                if ($record) {
                    $record->qty = $row['stock'];
                    $record->three_month_sale = $row['sales 3 months'];
                    $record->one_year_sale = $row['Sales 1 Year'];

                    if (!empty($row['6 Months Sales'])) {
                        $record->six_month_sale = $row['6 Months Sales'];
                    } else {
                        $record->six_month_sale = 0;
                    }
                    $record->save();
                }
            }
        }
    }
}
