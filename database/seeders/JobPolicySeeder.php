<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class JobPolicySeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rows = [
            [
                'id' => 'fulfill_item',
                'title' => 'Fulfill order item',
                'max_retry_count' => 3,
                'max_exceptions_count' => 3,
                'backoff_period' => '30,180',
                'queue' => 'order_fulfilment',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ];

        foreach ($rows as $row) {
            DB::table('job_policies')->insert($row);
        }
    }
}
