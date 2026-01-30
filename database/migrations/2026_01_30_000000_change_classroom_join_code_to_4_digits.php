<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $used = [];
        foreach (DB::table('classrooms')->get() as $row) {
            $len = strlen((string) ($row->join_code ?? ''));
            if ($len > 4) {
                do {
                    $code = (string) rand(1000, 9999);
                } while (in_array($code, $used, true));
                $used[] = $code;
                DB::table('classrooms')->where('id', $row->id)->update(['join_code' => $code]);
            } else {
                $used[] = (string) $row->join_code;
            }
        }

        // Only change column length; do not touch existing unique index (avoids 1061 duplicate key).
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE classrooms MODIFY join_code VARCHAR(4) NOT NULL');
        } else {
            DB::statement('ALTER TABLE classrooms ALTER COLUMN join_code TYPE VARCHAR(4)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE classrooms MODIFY join_code VARCHAR(10) NOT NULL');
        } else {
            DB::statement('ALTER TABLE classrooms ALTER COLUMN join_code TYPE VARCHAR(10)');
        }
    }
};
