<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

        Schema::table('classrooms', function (Blueprint $table) {
            $table->string('join_code', 4)->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->string('join_code', 10)->unique()->change();
        });
    }
};
