<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('custom_field_values')
                ->whereIn('custom_field_id', function ($query): void {
                    $query->select('id')->from('custom_fields')->where('field_type', 2);
                })
                ->whereNotNull('value_integer')
                ->update([
                    'value_decimal' => DB::raw('CAST(value_integer AS REAL)'),
                    'value_integer' => null,
                ]);

            if (DB::getDriverName() === 'pgsql') {
                DB::statement('
                    UPDATE custom_field_values
                    SET value_integer = lv.id, value_string = NULL
                    FROM custom_fields cf, list_values lv
                    WHERE custom_field_values.custom_field_id = cf.id
                    AND lv.list_name_id = cf.list_name_id
                    AND lv.name = custom_field_values.value_string
                    AND cf.field_type = 9
                    AND custom_field_values.value_string IS NOT NULL
                ');

                return;
            }

            $rows = DB::table('custom_field_values as cfv')
                ->join('custom_fields as cf', 'cfv.custom_field_id', '=', 'cf.id')
                ->where('cf.field_type', 9)
                ->whereNotNull('cfv.value_string')
                ->select('cfv.id', 'cfv.value_string', 'cf.list_name_id')
                ->get();

            foreach ($rows as $row) {
                $listValueId = DB::table('list_values')
                    ->where('list_name_id', $row->list_name_id)
                    ->where('name', $row->value_string)
                    ->value('id');

                if ($listValueId !== null) {
                    DB::table('custom_field_values')->where('id', $row->id)->update([
                        'value_integer' => $listValueId,
                        'value_string' => null,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('
                    UPDATE custom_field_values
                    SET value_string = lv.name, value_integer = NULL
                    FROM custom_fields cf, list_values lv
                    WHERE custom_field_values.custom_field_id = cf.id
                    AND lv.id = custom_field_values.value_integer
                    AND cf.field_type = 9
                    AND custom_field_values.value_integer IS NOT NULL
                ');
            } else {
                $rows = DB::table('custom_field_values as cfv')
                    ->join('custom_fields as cf', 'cfv.custom_field_id', '=', 'cf.id')
                    ->where('cf.field_type', 9)
                    ->whereNotNull('cfv.value_integer')
                    ->select('cfv.id', 'cfv.value_integer')
                    ->get();

                foreach ($rows as $row) {
                    $name = DB::table('list_values')->where('id', $row->value_integer)->value('name');

                    if ($name !== null) {
                        DB::table('custom_field_values')->where('id', $row->id)->update([
                            'value_string' => $name,
                            'value_integer' => null,
                        ]);
                    }
                }
            }

            DB::table('custom_field_values')
                ->whereIn('custom_field_id', function ($query): void {
                    $query->select('id')->from('custom_fields')->where('field_type', 2);
                })
                ->whereNotNull('value_decimal')
                ->update([
                    'value_integer' => DB::raw('CAST(value_decimal AS INTEGER)'),
                    'value_decimal' => null,
                ]);
        });
    }
};
